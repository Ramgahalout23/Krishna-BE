<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Performance Logger Middleware
 *
 * Tracks total query count and query time using DB::listen() (lightweight —
 * no full query storage in memory, unlike DB::getQueryLog()).
 *
 * This makes N+1 issues trivially detectable — if a simple list endpoint
 * runs 50+ queries, that's an N+1 problem.
 *
 * Only active in non-production environments to avoid overhead.
 */
class PerformanceLogger
{
    /** Thresholds for different warning levels */
    private const QUERY_COUNT_WARN = 20;     // Warning if >20 queries per request
    private const QUERY_COUNT_CRITICAL = 50; // Critical if >50 queries per request
    private const TOTAL_TIME_WARN_MS = 2000; // Warning if >2 seconds total

    /** Paths to exclude from logging (reduces noise) */
    private const EXCLUDED_PATHS = ['/health', '/_debugbar'];

    public function handle(Request $request, Closure $next)
    {
        if (app()->environment('production')) {
            return $next($request);
        }

        // Track queries via DB::listen() — lighter than enableQueryLog()
        // because it doesn't store full query + bindings in memory
        $queryCount = 0;
        $totalQueryTime = 0;

        DB::listen(function ($query) use (&$queryCount, &$totalQueryTime) {
            $queryCount++;
            $totalQueryTime += $query->time;
        });

        $startTime = microtime(true);
        $startMemory = memory_get_usage(true);

        $response = $next($request);

        $duration = (microtime(true) - $startTime) * 1000; // ms
        $memoryUsed = (memory_get_usage(true) - $startMemory) / 1024; // KB

        // Skip logging for excluded paths to reduce noise
        $path = $request->path();
        foreach (self::EXCLUDED_PATHS as $excluded) {
            if (str_contains($path, $excluded)) {
                return $response;
            }
        }

        $logData = [
            'method' => $request->method(),
            'path' => $path,
            'duration_ms' => round($duration, 2),
            'memory_kb' => round($memoryUsed),
            'query_count' => $queryCount,
            'total_query_time_ms' => round($totalQueryTime, 2),
            'avg_query_time_ms' => $queryCount > 0 ? round($totalQueryTime / $queryCount, 2) : 0,
        ];

        $message = sprintf(
            '[PERF] %s %s — %d queries (%.0fms total, avg %.1fms) — %.0fms request — %dKB',
            $logData['method'],
            $logData['path'],
            $logData['query_count'],
            $logData['total_query_time_ms'],
            $logData['avg_query_time_ms'],
            $logData['duration_ms'],
            $logData['memory_kb']
        );

        // Determine log level based on thresholds
        if ($queryCount >= self::QUERY_COUNT_CRITICAL) {
            Log::critical("[N+1 DETECTED] {$message} — Likely N+1 issue! Check relationships.", $logData);
        } elseif ($queryCount >= self::QUERY_COUNT_WARN) {
            Log::warning("[N+1 WARNING] {$message} — High query count, possible N+1.", $logData);
        } elseif ($duration >= self::TOTAL_TIME_WARN_MS) {
            Log::warning("[SLOW REQUEST] {$message}", $logData);
        } else {
            Log::debug($message, $logData);
        }

        // Attach performance headers to response for frontend debugging
        if (app()->environment('local', 'development')) {
            $response->headers->set('X-Debug-Query-Count', $queryCount);
            $response->headers->set('X-Debug-Query-Time', round($totalQueryTime, 2));
            $response->headers->set('X-Debug-Duration-Ms', round($duration, 2));
        }

        return $response;
    }
}
