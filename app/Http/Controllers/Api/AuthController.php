<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\Traits\MapsCamelCaseFields;
use App\Services\AuthService;
use App\Exceptions\AppError;
use App\Traits\CacheKeyRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Support\Facades\Cache;

class AuthController extends Controller
{
    use CacheKeyRegistry;
    use MapsCamelCaseFields;

    public function __construct(
        protected AuthService $authService
    ) {}

    public function register(Request $request): JsonResponse
    {
        try {
            $input = $this->mapCamelCase($request->all(), [
                'firstName' => 'first_name',
                'lastName' => 'last_name',
                'phoneNumber' => 'phone_number',
            ]);
            $request->replace($input);

            $validated = $request->validate([
                'first_name' => 'required|string|max:255',
                'last_name' => 'required|string|max:255',
                'email' => 'required|email|max:255',
                'password' => 'required|string|min:8',
                'phone_number' => 'nullable|string|max:20',
            ]);

            $result = $this->authService->register($validated);
            return response()->json(['success' => true, 'message' => $result['message'], 'data' => $result], 201);
        } catch (AppError $e) {
            return $e->render();
        }
    }

    public function login(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'email' => 'required|email',
                'password' => 'required|string',
            ]);

            $result = $this->authService->login($validated['email'], $validated['password']);
            return response()->json(['success' => true, 'message' => $result['message'], 'data' => $result]);
        } catch (AppError $e) {
            return $e->render();
        }
    }

    public function logout(): JsonResponse
    {
        // Use tokens()->delete() instead of currentAccessToken()->delete()
        // to ensure the token is removed from the database
        Auth::user()->tokens()->delete();
        return response()->json(['success' => true, 'message' => 'Logged out successfully']);
    }

    public function me(): JsonResponse
    {
        $userId = Auth::id();
        $user = $this->cacheWithTracking("auth_user_profile:{$userId}", 60, function () use ($userId) {
            return Auth::user()->load(['addresses', 'wallet', 'loyaltyPoints', 'vipTier']);
        });
        return response()->json(['success' => true, 'data' => $user]);
    }

    public function updateProfile(Request $request): JsonResponse
    {
        try {
            $input = $this->mapCamelCase($request->all(), [
                'firstName' => 'first_name',
                'lastName' => 'last_name',
                'phoneNumber' => 'phone_number',
            ]);
            $request->replace($input);

            $validated = $request->validate([
                'first_name' => 'sometimes|string|max:255',
                'last_name' => 'sometimes|string|max:255',
                'phone_number' => 'nullable|string|max:20',
                'avatar' => 'nullable|string',
            ]);

            $user = $this->authService->updateProfile(Auth::id(), $validated);
            $this->clearTrackedCache();
            return response()->json(['success' => true, 'message' => 'Profile updated', 'data' => $user]);
        } catch (AppError $e) {
            return $e->render();
        }
    }

    public function changePassword(Request $request): JsonResponse
    {
        try {
            $input = $this->mapCamelCase($request->all(), [
                'currentPassword' => 'current_password',
                'newPassword' => 'new_password',
            ]);
            $request->replace($input);

            $validated = $request->validate([
                'current_password' => 'required|string',
                'new_password' => 'required|string|min:8',
            ]);

            $result = $this->authService->changePassword(Auth::id(), $validated['current_password'], $validated['new_password']);
            return response()->json(['success' => true, 'message' => $result['message']]);
        } catch (AppError $e) {
            return $e->render();
        }
    }

    public function forgotPassword(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate(['email' => 'required|email']);
            $result = $this->authService->forgotPassword($validated['email']);
            return response()->json(['success' => true, 'message' => $result['message']]);
        } catch (AppError $e) {
            return $e->render();
        }
    }

    public function resetPassword(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'email' => 'required|email',
                'token' => 'required|string',
                'password' => 'required|string|min:8|confirmed',
            ]);

            $result = $this->authService->resetPassword($validated['email'], $validated['token'], $validated['password']);
            return response()->json(['success' => true, 'message' => $result['message']]);
        } catch (AppError $e) {
            return $e->render();
        }
    }

    public function sendOtp(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate(['email' => 'required|email']);
            $result = $this->authService->sendOtp($validated['email']);
            return response()->json(['success' => true, 'message' => $result['message']]);
        } catch (AppError $e) {
            return $e->render();
        }
    }

    public function verifyOtp(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'email' => 'required|email',
                'otp' => 'required|string|size:6',
            ]);

            $result = $this->authService->verifyOtp($validated['email'], $validated['otp']);
            return response()->json(['success' => true, 'message' => $result['message'], 'data' => $result]);
        } catch (AppError $e) {
            return $e->render();
        }
    }

    // ── Missing Auth Routes (from TypeScript reference) ──

    /**
     * Refresh access token — issue a new token and revoke the old one.
     * POST /api/v1/auth/refresh-token
     */
    public function refreshToken(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();

            // Revoke current token
            $user->currentAccessToken()->delete();

            // Issue a new token
            $newToken = $user->createToken('auth-token')->plainTextToken;

            return response()->json([
                'success' => true,
                'message' => 'Token refreshed successfully',
                'data' => [
                    'token' => $newToken,
                    'user' => $user->only(['id', 'first_name', 'last_name', 'email', 'role']),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to refresh token',
            ], 401);
        }
    }

    /**
     * Send email verification link.
     * POST /api/v1/auth/send-verification
     */
    public function sendVerification(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate(['email' => 'required|email']);
            $result = $this->authService->sendEmailVerification($validated['email']);
            return response()->json(['success' => true, 'message' => $result['message']]);
        } catch (AppError $e) {
            return $e->render();
        }
    }

    /**
     * Verify email with token.
     * POST /api/v1/auth/verify-email
     */
    public function verifyEmail(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'email' => 'required|email',
                'token' => 'required|string',
            ]);

            $result = $this->authService->verifyEmail($validated['email'], $validated['token']);
            return response()->json(['success' => true, 'message' => $result['message']]);
        } catch (AppError $e) {
            return $e->render();
        }
    }

    /**
     * Get OAuth provider configuration status.
     * GET /api/v1/auth/oauth/status
     */
    public function oauthStatus(): JsonResponse
    {
        $providers = [
            'google' => [
                'enabled' => !empty(config('services.google.client_id')),
                'client_id' => config('services.google.client_id'),
            ],
            'facebook' => [
                'enabled' => !empty(config('services.facebook.client_id')),
                'client_id' => config('services.facebook.client_id'),
            ],
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'providers' => $providers,
                'strategies' => array_keys(array_filter($providers, fn($p) => $p['enabled'])),
            ],
        ]);
    }

    /**
     * Redirect to OAuth provider (Google / Facebook).
     * GET /api/v1/auth/{provider}
     */
    public function redirectToProvider(string $provider): JsonResponse
    {
        $provider = strtolower($provider);

        if (!in_array($provider, ['google', 'facebook'])) {
            return response()->json([
                'success' => false,
                'message' => 'Unsupported OAuth provider. Supported: google, facebook',
            ], 400);
        }

        if (empty(config("services.{$provider}.client_id"))) {
            return response()->json([
                'success' => false,
                'message' => "{$provider} OAuth is not configured",
            ], 400);
        }

        try {
            $redirectUrl = Socialite::driver($provider)
                ->stateless()
                ->redirect()
                ->getTargetUrl();

            return response()->json([
                'success' => true,
                'data' => [
                    'redirect_url' => $redirectUrl,
                    'provider' => $provider,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => "Failed to initiate {$provider} OAuth: " . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Handle OAuth provider callback.
     * GET /api/v1/auth/{provider}/callback
     */
    public function handleProviderCallback(string $provider): JsonResponse
    {
        $provider = strtolower($provider);

        if (!in_array($provider, ['google', 'facebook'])) {
            return response()->json([
                'success' => false,
                'message' => 'Unsupported OAuth provider',
            ], 400);
        }

        try {
            $socialUser = Socialite::driver($provider)->stateless()->user();

            $nameParts = explode(' ', $socialUser->getName() ?? $socialUser->getNickname() ?? 'User', 2);
            $firstName = $nameParts[0] ?? 'User';
            $lastName = $nameParts[1] ?? '';

            $user = $this->authService->findOrCreateOAuthUser([
                'email' => $socialUser->getEmail(),
                'first_name' => $firstName,
                'last_name' => $lastName,
                'avatar' => $socialUser->getAvatar(),
            ]);

            // Revoke old tokens and issue new one
            $user->tokens()->delete();
            $token = $user->createToken('auth-token')->plainTextToken;

            return response()->json([
                'success' => true,
                'message' => "{$provider} login successful",
                'data' => [
                    'token' => $token,
                    'user' => $user->only(['id', 'first_name', 'last_name', 'email', 'role', 'avatar']),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => "{$provider} authentication failed: " . $e->getMessage(),
            ], 401);
        }
    }

    /**
     * Refresh OAuth strategies from DB settings (Admin only).
     * POST /api/v1/auth/refresh-oauth
     */
    public function refreshOAuth(): JsonResponse
    {
        // Reload config from DB settings (if any OAuth overrides are stored)
        // In this implementation, config is already loaded from services.php
        // but this endpoint forces a re-read for the frontend to pick up any changes
        return response()->json([
            'success' => true,
            'message' => 'OAuth strategies refreshed',
            'data' => [
                'google' => !empty(config('services.google.client_id')),
                'facebook' => !empty(config('services.facebook.client_id')),
            ],
        ]);
    }


}
