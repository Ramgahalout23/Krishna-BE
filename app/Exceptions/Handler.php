<?php

namespace App\Exceptions;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Throwable;

class Handler extends ExceptionHandler
{
    protected $levels = [];
    protected $dontReport = [];
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    public function register()
    {
        $this->reportable(function (Throwable $e) {});
    }

    public function render($request, Throwable $e)
    {
        // ── Database connection errors ──
        if ($e instanceof QueryException) {
            $code = $e->getCode();
            $isConnectionError = (
                str_contains($e->getMessage(), 'Connection refused') ||
                str_contains($e->getMessage(), 'could not find driver') ||
                str_contains($e->getMessage(), 'could not connect') ||
                str_contains($e->getMessage(), 'Unknown database') ||
                str_contains($e->getMessage(), 'Access denied')
            );

            if ($isConnectionError && $request->expectsJson()) {
                $dbConfig = config('database.connections.' . config('database.default'));
                return response()->json([
                    'success' => false,
                    'error' => 'database_connection_failed',
                    'message' => 'Database connection failed. Check your .env database settings.',
                    'debug' => app()->environment('local') ? [
                        'driver' => config('database.default'),
                        'host' => $dbConfig['host'] ?? 'unknown',
                        'port' => $dbConfig['port'] ?? 'unknown',
                        'database' => $dbConfig['database'] ?? 'unknown',
                        'error_message' => $e->getMessage(),
                    ] : null,
                ], 500);
            }
        }

        if ($e instanceof AppError) {
            if ($request->expectsJson()) {
                return $e->render();
            }
            // For web requests, use appropriate HTTP error pages
            abort($e->getStatusCode(), $e->getMessage());
        }
        return parent::render($request, $e);
    }

    /**
     * Convert an authentication exception into a response.
     *
     * This overrides the default behaviour which tries to redirect to a named
     * "login" route (which doesn't exist in this API-only app) and instead
     * returns a consistent JSON 401 response for all unauthenticated requests.
     */
    protected function unauthenticated($request, AuthenticationException $exception)
    {
        return response()->json([
            'success' => false,
            'message' => 'Unauthenticated. Please provide a valid Bearer token.',
        ], 401);
    }
}
