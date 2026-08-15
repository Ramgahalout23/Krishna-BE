<?php

namespace App\Console\Commands;

use App\OpenApi\SpecBuilder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Route;
use Illuminate\Routing\Route as RouteItem;

class ValidateOpenApiSpec extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'openapi:validate
                            {--json : Output results as JSON}
                            {--detailed : Show detailed per-route breakdown}
                            {--fail-on-missing : Exit with code 1 if any issues found}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Validate that all API v1 routes have proper request/response schemas in the OpenAPI spec';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        // ── Step 1: Build the OpenAPI spec ──
        try {
            $spec = SpecBuilder::build();
        } catch (\Throwable $e) {
            $this->components->error('Failed to build OpenAPI spec: ' . $e->getMessage());
            return 1;
        }

        $specPaths = $spec['paths'] ?? [];

        // ── Step 2: Collect all registered API v1 routes ──
        $apiRoutes = $this->collectApiRoutes();

        if (empty($apiRoutes)) {
            $this->warn('No API v1 routes found.');
            return 0;
        }

        // ── Step 3: Normalise spec paths into matcher patterns ──
        $specMatchers = [];
        foreach ($specPaths as $specPath => $methods) {
            $regex = preg_replace('/\{([^}]+)\}/', '(?P<$1>[^/]+)', $specPath);
            $specMatchers[] = [
                'path' => $specPath,
                'regex' => '#^' . $regex . '$#',
                'methods' => $methods,
            ];
        }

        // ── Step 4: Match each route against the spec ──
        $results = [
            'total_api_routes' => count($apiRoutes),
            'routes_in_spec' => 0,
            'routes_missing_from_spec' => [],
            'routes_without_response_schema' => [],
            'routes_without_request_body' => [],
            'routes_with_bare_description_response' => [],
        ];

        $matchedSpecPaths = [];

        foreach ($apiRoutes as $key => $route) {
            $found = false;

            foreach ($specMatchers as $matcher) {
                if (preg_match($matcher['regex'], $route['path'])) {
                    $method = $route['method'];

                    if (isset($matcher['methods'][$method])) {
                        $found = true;
                        $results['routes_in_spec']++;
                        $matchedSpecPaths[$matcher['path']] = true;

                        $specEntry = $matcher['methods'][$method];

                        // ── Check response schemas ──
                        $responses = $specEntry['responses'] ?? [];
                        $successCodes = array_filter(
                            array_keys($responses),
                            fn($c) => is_numeric($c) && $c >= 200 && $c < 300
                        );

                        if (empty($successCodes)) {
                            // No success response at all
                            $results['routes_without_response_schema'][] = $key;
                        } else {
                            $hasContent = false;
                            $hasBareDescription = false;

                            foreach ($successCodes as $code) {
                                if (isset($responses[$code]['content'])) {
                                    $hasContent = true;
                                } elseif (isset($responses[$code]['description'])) {
                                    $hasBareDescription = true;
                                }
                            }

                            if (!$hasContent && !$hasBareDescription) {
                                // Response exists but has neither content nor description
                                $results['routes_without_response_schema'][] = $key;
                            } elseif ($hasBareDescription && !$hasContent) {
                                // Only bare descriptions, no actual content schemas
                                $results['routes_with_bare_description_response'][] = $key;
                            } elseif (!$hasContent && $hasBareDescription) {
                                $results['routes_with_bare_description_response'][] = $key;
                            }
                        }

                        // ── Check requestBody for POST/PUT/PATCH ──
                        if (in_array($method, ['post', 'put', 'patch'])) {
                            if (!isset($specEntry['requestBody'])) {
                                $results['routes_without_request_body'][] = $key;
                            }
                        }

                        break;
                    }
                }
            }

            if (!$found) {
                $results['routes_missing_from_spec'][] = $key;
            }
        }

        // ── Step 5: Find spec paths with no matching route ──
        $results['spec_paths_without_route'] = [];
        foreach ($specPaths as $specPath => $methods) {
            if (!isset($matchedSpecPaths[$specPath])) {
                $results['spec_paths_without_route'][] = $specPath;
            }
        }

        // ── Step 6: Output ──
        if ($this->option('json')) {
            $this->line(json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $this->displayReport($results, $apiRoutes);
        }

        // ── Step 7: Determine exit code ──
        $hasIssues = !empty($results['routes_missing_from_spec'])
            || !empty($results['routes_without_response_schema'])
            || !empty($results['routes_without_request_body'])
            || !empty($results['routes_with_bare_description_response']);

        if ($hasIssues && ($this->option('fail-on-missing') || $this->option('json'))) {
            return 1;
        }

        return 0;
    }

    /**
     * Collect all registered API v1 routes with their methods and paths.
     */
    private function collectApiRoutes(): array
    {
        $routes = Route::getRoutes();
        $apiRoutes = [];

        foreach ($routes as $route) {
            $uri = $route->uri();

            if (!preg_match('#^api/v1(/.*)?$#', $uri, $matches)) {
                continue;
            }

            $path = rtrim($matches[1] ?: '/', '/') ?: '/';

            // Skip HEAD (auto-generated from GET)
            $methods = array_values(array_filter(
                $route->methods(),
                fn($m) => strtolower($m) !== 'head'
            ));

            foreach ($methods as $method) {
                $method = strtolower($method);
                $key = strtoupper($method) . ' ' . $path;

                $apiRoutes[$key] = [
                    'method' => $method,
                    'path' => $path,
                    'action' => $this->getRouteAction($route),
                    'middleware' => $route->gatherMiddleware(),
                ];
            }
        }

        ksort($apiRoutes);

        return $apiRoutes;
    }

    /**
     * Get a human-readable action name for a route.
     */
    private function getRouteAction(RouteItem $route): string
    {
        $action = $route->getAction('uses');
        if ($action instanceof \Closure) {
            return 'Closure';
        }
        if (is_string($action)) {
            return $action;
        }
        return 'Unknown';
    }

    /**
     * Display a human-readable validation report.
     */
    private function displayReport(array $results, array $apiRoutes): void
    {
        $this->newLine();
        $this->components->twoColumnDetail('<fg=cyan>OpenAPI Spec Validation Report</>', '');
        $this->components->twoColumnDetail('Total API v1 routes', (string) $results['total_api_routes']);
        $this->components->twoColumnDetail('Routes documented in spec', (string) $results['routes_in_spec']);

        $this->newLine();

        // ── Missing routes ──
        if (!empty($results['routes_missing_from_spec'])) {
            $this->components->error(sprintf(
                '%d route(s) missing from OpenAPI spec:',
                count($results['routes_missing_from_spec'])
            ));
            $rows = array_map(
                fn($key) => array_merge(explode(' ', $key, 2), [$apiRoutes[$key]['action'] ?? '']),
                array_slice($results['routes_missing_from_spec'], 0, 30)
            );
            $this->table(['Method', 'Path', 'Controller'], $rows);
            $remaining = count($results['routes_missing_from_spec']) - 30;
            if ($remaining > 0) {
                $this->warn(sprintf('... and %d more', $remaining));
            }
            $this->newLine();
        } else {
            $this->components->info('✓ All API routes are documented in the OpenAPI spec');
            $this->newLine();
        }

        // ── Missing response schemas ──
        if (!empty($results['routes_without_response_schema'])) {
            $this->components->warn(sprintf(
                '%d route(s) have no success response schema defined:',
                count($results['routes_without_response_schema'])
            ));
            if ($this->option('detailed')) {
                $rows = array_map(
                    fn($key) => array_merge(explode(' ', $key, 2), [$apiRoutes[$key]['action'] ?? '']),
                    array_slice($results['routes_without_response_schema'], 0, 30)
                );
                $this->table(['Method', 'Path', 'Controller'], $rows);
            } else {
                foreach (array_slice($results['routes_without_response_schema'], 0, 10) as $r) {
                    $this->line("  • {$r}");
                }
                $remaining = count($results['routes_without_response_schema']) - 10;
                if ($remaining > 0) {
                    $this->line(sprintf('  ... and %d more', $remaining));
                }
            }
            $this->newLine();
        }

        // ── Bare descriptions ──
        if (!empty($results['routes_with_bare_description_response'])) {
            $this->components->warn(sprintf(
                '%d route(s) have only description text (no content schema) in success responses:',
                count($results['routes_with_bare_description_response'])
            ));
            foreach (array_slice($results['routes_with_bare_description_response'], 0, 10) as $r) {
                $this->line("  • {$r}");
            }
            $remaining = count($results['routes_with_bare_description_response']) - 10;
            if ($remaining > 0) {
                $this->line(sprintf('  ... and %d more', $remaining));
            }
            $this->newLine();
        }

        // ── Missing request bodies ──
        if (!empty($results['routes_without_request_body'])) {
            $this->components->warn(sprintf(
                '%d POST/PUT/PATCH route(s) missing requestBody definition:',
                count($results['routes_without_request_body'])
            ));
            foreach (array_slice($results['routes_without_request_body'], 0, 15) as $r) {
                $this->line("  • {$r}");
            }
            $remaining = count($results['routes_without_request_body']) - 15;
            if ($remaining > 0) {
                $this->line(sprintf('  ... and %d more', $remaining));
            }
            $this->newLine();
        }

        // ── Orphaned spec paths ──
        if (!empty($results['spec_paths_without_route'])) {
            $this->components->warn(sprintf(
                '%d spec path(s) with no matching registered route:',
                count($results['spec_paths_without_route'])
            ));
            foreach (array_slice($results['spec_paths_without_route'], 0, 10) as $p) {
                $this->line("  • {$p}");
            }
            $remaining = count($results['spec_paths_without_route']) - 10;
            if ($remaining > 0) {
                $this->line(sprintf('  ... and %d more', $remaining));
            }
            $this->newLine();
        }

        // ── Summary ──
        $hasIssues = !empty($results['routes_missing_from_spec'])
            || !empty($results['routes_without_response_schema'])
            || !empty($results['routes_without_request_body'])
            || !empty($results['routes_with_bare_description_response']);

        if (!$hasIssues && empty($results['spec_paths_without_route'])) {
            $this->components->success('All routes have proper OpenAPI documentation ✓');
        } elseif (!$hasIssues && !empty($results['spec_paths_without_route'])) {
            $this->components->success('All routes documented, but some spec paths have no matching route (may be intentional)');
        } else {
            $this->components->error('Some issues found — see above for details');
        }

        $this->newLine();
    }
}
