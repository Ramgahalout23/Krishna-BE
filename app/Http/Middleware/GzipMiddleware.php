<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gzip Middleware
 *
 * Compresses JSON API responses on the fly when the client advertises
 * Accept-Encoding: gzip. Typical JSON payloads (homepage ≈143 KB) shrink
 * to roughly 10–20% of their original size — the single biggest win for
 * API transfer size, without requiring the web server (nginx/apache) to
 * have compression enabled.
 *
 * - Only compresses application/json responses (never images/video/streams)
 * - Skips small responses (< 1 KB) where compression overhead isn't worth it
 * - Sets Vary: Accept-Encoding so caches store per-encoding variants
 * - Bails out if the response is already encoded
 */
class GzipMiddleware
{
    /** Don't bother compressing payloads smaller than this. */
    private const MIN_COMPRESS_BYTES = 1024;

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Only act when the client accepts gzip.
        if (!str_contains($request->header('Accept-Encoding', ''), 'gzip')) {
            return $response;
        }

        // Never touch an already-encoded response (e.g. a CDN/web server already did it).
        if ($response->headers->has('Content-Encoding')) {
            return $response;
        }

        // Only compress JSON payloads that are worth it.
        $contentType = $response->headers->get('Content-Type', '');
        if (!str_contains($contentType, 'application/json')) {
            return $response;
        }

        $content = $response->getContent();
        if ($content === false || strlen($content) < self::MIN_COMPRESS_BYTES) {
            return $response;
        }

        $compressed = gzencode($content, 6);
        if ($compressed === false) {
            return $response;
        }

        $response->setContent($compressed);
        $response->headers->set('Content-Encoding', 'gzip');
        // Append (don't replace) so an existing Vary (e.g. Origin from CORS) is preserved.
        $response->headers->set('Vary', 'Accept-Encoding', false);

        return $response;
    }
}
