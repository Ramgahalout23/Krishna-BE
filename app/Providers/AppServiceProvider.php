<?php

namespace App\Providers;

use App\Models\Review;
use App\Observers\ReviewObserver;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Slow query threshold in milliseconds.
     * Queries exceeding this will be logged as warnings.
     */
    private const SLOW_QUERY_THRESHOLD_MS = 100;

    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // ── Auto-sync review_count and rating on review changes ──
        Review::observe(ReviewObserver::class);

        // ── Database Query Logging ──
        // Only log queries in local/development environments to avoid overhead in production.
        if ($this->app->environment('local', 'development', 'staging')) {
            $this->registerQueryLogger();
        }

        // ── Date Formatting Directives ──
        // Usage: @formatDate($date) — formats using config('app.date_format') (default: "F d, Y")
        // Usage: @formatTime($date) — formats using config('app.time_format') (default: "h:i A")
        // Both work with Carbon instances, date strings, or null.

        Blade::directive('formatDate', function (string $expression): string {
            return "<?php echo ((\$__d = $expression) ? \Carbon\Carbon::parse(\$__d)->format(config('app.date_format')) : '—'); ?>";
        });

        Blade::directive('formatTime', function (string $expression): string {
            return "<?php echo ((\$__t = $expression) ? \Carbon\Carbon::parse(\$__t)->format(config('app.time_format')) : '—'); ?>";
        });
    }

    /**
     * Register the DB::listen query logger.
     * Logs all queries in debug mode, or only slow queries otherwise.
     * Includes the request URL and caller stack trace for easy identification.
     */
    private function registerQueryLogger(): void
    {
        $isDebug = config('app.debug', false);

        DB::listen(function ($query) use ($isDebug) {
            $sql = $query->sql;
            $bindings = $query->bindings;
            $time = $query->time; // milliseconds
            $isSlow = $time >= self::SLOW_QUERY_THRESHOLD_MS;

            // In debug mode, log every query. Otherwise, log only slow queries.
            if (!$isDebug && !$isSlow) {
                return;
            }

            // Format SQL with bindings replaced inline for readability
            $fullSql = $sql;
            if (!empty($bindings)) {
                $indexedBindings = [];
                foreach ($bindings as $binding) {
                    if (is_numeric($binding)) {
                        $indexedBindings[] = $binding;
                    } elseif (is_bool($binding)) {
                        $indexedBindings[] = $binding ? 'true' : 'false';
                    } elseif (is_null($binding)) {
                        $indexedBindings[] = 'NULL';
                    } else {
                        $indexedBindings[] = "'" . addslashes($binding) . "'";
                    }
                }
                // Replace ? placeholders with actual bindings
                $parts = explode('?', $fullSql);
                $fullSql = '';
                foreach ($parts as $i => $part) {
                    $fullSql .= $part;
                    if (isset($indexedBindings[$i])) {
                        $fullSql .= $indexedBindings[$i];
                    }
                }
            }

            // Trim excessively long SQL strings
            $maxLen = 2000;
            if (strlen($fullSql) > $maxLen) {
                $fullSql = substr($fullSql, 0, $maxLen) . '... [truncated]';
            }

            // Gather context: connection name + request URL
            $context = [
                'connection' => $query->connectionName ?? 'default',
                'time_ms' => round($time, 2),
            ];

            // Add request URL if available (not in CLI context)
            if (app()->runningInConsole()) {
                $args = isset($_SERVER['argv']) ? implode(' ', $_SERVER['argv']) : 'unknown';
                $context['command'] = 'artisan ' . $args;
            } else {
                $context['url'] = request()->fullUrl();
                $context['method'] = request()->method();
            }

            // Log level: warning for slow queries, debug for normal
            if ($isSlow) {
                Log::warning("[SLOW QUERY] {$time}ms — {$fullSql}", $context);
            } else {
                Log::debug("[QUERY] {$time}ms — {$fullSql}", $context);
            }
        });
    }
}
