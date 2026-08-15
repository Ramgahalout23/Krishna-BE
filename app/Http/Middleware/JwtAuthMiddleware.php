<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;

/**
 * JwtAuthMiddleware
 * 
 * Validates JWT tokens issued by the Node.js authentication system.
 * This allows the React frontend (which authenticates via Node.js)
 * to make authorized requests to Laravel's admin API endpoints.
 * 
 * Token format: standard JWT (HMAC-SHA256) with payload containing user id.
 * The shared secret must be set in JWT_SECRET env variable (same as Node.js config.jwt.secret).
 */
class JwtAuthMiddleware
{
    /**
     * Handle an incoming request - validate JWT and authenticate user.
     */
    public function handle(Request $request, Closure $next)
    {
        $token = $request->bearerToken();

        if (!$token) {
            return response()->json([
                'success' => false,
                'message' => 'No authentication token provided',
            ], 401);
        }

        $payload = $this->validateJwt($token);

        if (!$payload) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired authentication token',
            ], 401);
        }

        $userId = $payload->sub ?? $payload->id ?? $payload->userId ?? null;

        if (!$userId) {
            return response()->json([
                'success' => false,
                'message' => 'Token payload missing user identifier',
            ], 401);
        }

        $user = User::find($userId);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found',
            ], 401);
        }

        if (!$user->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Your account is inactive',
            ], 401);
        }

        if ($user->is_blocked) {
            return response()->json([
                'success' => false,
                'message' => 'Your account is blocked',
            ], 401);
        }

        // Set the authenticated user for this request (API routes are stateless)
        Auth::setUser($user);

        return $next($request);
    }

    /**
     * Validate a JWT token and return the payload, or null on failure.
     * 
     * Supports both HS256 (default) and HS384/HS512 algorithms.
     * Uses PHP's built-in hash_hmac and base64 encoding — no external packages needed.
     */
    private function validateJwt(string $token): ?object
    {
        $secret = config('jwt.secret');

        if (empty($secret)) {
            // JWT_SECRET not configured — log a warning
            logger()->warning('JWT_SECRET is not configured in .env');
            return null;
        }

        $parts = explode('.', $token);

        if (count($parts) !== 3) {
            return null; // Invalid token format
        }

        [$headB64, $payloadB64, $signatureB64] = $parts;

        // Decode header to check algorithm
        $headerJson = $this->base64UrlDecode($headB64);
        if (!$headerJson) {
            return null;
        }

        $header = json_decode($headerJson);
        if (!$header || !isset($header->alg)) {
            return null;
        }

        // Determine hash algorithm from JWT header
        $algorithm = $this->getHashAlgorithm($header->alg);
        if (!$algorithm) {
            return null; // Unsupported algorithm
        }

        // Verify signature
        $signature = $this->base64UrlDecode($signatureB64);
        $expectedSignature = hash_hmac($algorithm, "{$headB64}.{$payloadB64}", $secret, true);

        if (!hash_equals($expectedSignature, $signature)) {
            return null; // Invalid signature
        }

        // Decode payload
        $payloadJson = $this->base64UrlDecode($payloadB64);
        if (!$payloadJson) {
            return null;
        }

        $payload = json_decode($payloadJson);
        if (!$payload) {
            return null;
        }

        // Check expiration
        if (isset($payload->exp) && $payload->exp < time()) {
            return null; // Token expired
        }

        return $payload;
    }

    /**
     * Decode a base64url-encoded string (standard JWT encoding).
     */
    private function base64UrlDecode(string $data): ?string
    {
        $remainder = strlen($data) % 4;
        if ($remainder) {
            $data .= str_repeat('=', 4 - $remainder);
        }

        $decoded = base64_decode(strtr($data, '-_', '+/'), true);

        return $decoded !== false ? $decoded : null;
    }

    /**
     * Map JWT algorithm names to PHP hash_hmac algorithms.
     */
    private function getHashAlgorithm(string $jwtAlg): ?string
    {
        $map = [
            'HS256' => 'sha256',
            'HS384' => 'sha384',
            'HS512' => 'sha512',
        ];

        return $map[$jwtAlg] ?? null;
    }
}
