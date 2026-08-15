<?php

namespace App\Services;

use App\Repositories\AuthRepository;
use App\Services\SMSService;
use App\Services\EmailService;
use App\Services\WebhookService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use App\Exceptions\AppError;
use App\Models\User;

class AuthService
{
    public function __construct(
        protected AuthRepository $authRepository,
        protected EmailService $emailService,
        protected SMSService $smsService,
        protected WebhookService $webhookService
    ) {}

    private function validatePassword(string $password): void
    {
        $errors = [];
        if (strlen($password) < 8) $errors[] = 'Password must be at least 8 characters';
        if (!preg_match('/[A-Z]/', $password)) $errors[] = 'Password must contain an uppercase letter';
        if (!preg_match('/[a-z]/', $password)) $errors[] = 'Password must contain a lowercase letter';
        if (!preg_match('/[0-9]/', $password)) $errors[] = 'Password must contain a number';
        if (!preg_match('/[^a-zA-Z0-9]/', $password)) $errors[] = 'Password must contain a special character';
        if (!empty($errors)) {
            throw AppError::validation(implode(', ', $errors));
        }
    }

    public function register(array $data): array
    {
        $existing = $this->authRepository->findByEmail($data['email']);
        if ($existing) {
            throw AppError::conflict('Email already registered');
        }

        // TS-style password validation: uppercase, lowercase, number, special char, length
        $this->validatePassword($data['password']);

        $user = $this->authRepository->createUser(array_merge($data, [
            'password' => $data['password'],
        ]));
        $this->authRepository->createWallet($user->id);
        $this->authRepository->createLoyaltyPoints($user->id);

        $token = $user->createToken('auth-token')->plainTextToken;

        // Send welcome email (fire-and-forget, matching TS behavior)
        try {
            $this->emailService->sendWelcomeEmail(
                $user->email,
                $user->first_name . ' ' . $user->last_name,
                'THREVOLT'
            );
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Failed to send welcome email', ['error' => $e->getMessage()]);
        }

        // ── Webhook: user.registered ──
        try {
            $this->webhookService->dispatch('user.registered', [
                'user_id' => $user->id,
                'email' => $user->email,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'registered_at' => now()->toIso8601String(),
            ]);
        } catch (\Exception $e) {
            Log::error('[Webhook] Failed to dispatch user.registered', ['error' => $e->getMessage()]);
        }

        return [
            'user' => $user->only(['id', 'first_name', 'last_name', 'email', 'role']),
            'token' => $token,
            'message' => 'User registered successfully. Please verify your email.',
        ];
    }

    public function login(string $email, string $password): array
    {
        $user = $this->authRepository->findByEmail(strtolower($email));
        if (!$user || !Hash::check($password, $user->password)) {
            throw AppError::authentication('Invalid email or password');
        }

        if (!$user->is_active) {
            throw AppError::authentication('Your account is inactive');
        }

        if ($user->is_blocked) {
            throw AppError::authentication('Your account is blocked');
        }

        $this->authRepository->updateLastLogin($user->id);

        // Revoke old tokens
        $user->tokens()->delete();
        $token = $user->createToken('auth-token')->plainTextToken;

        return [
            'user' => $user->only(['id', 'first_name', 'last_name', 'email', 'role', 'is_email_verified', 'avatar']),
            'token' => $token,
            'message' => 'Login successful',
        ];
    }

    /**
     * Refresh authentication token.
     * Revokes old tokens and issues a new one.
     */
    public function refreshToken(string $userId): array
    {
        $user = $this->authRepository->findById($userId);
        if (!$user || !$user->is_active) {
            throw AppError::authentication('Invalid token or user not found');
        }

        if ($user->is_blocked) {
            throw AppError::authentication('Your account is blocked');
        }

        // Revoke old tokens and create new one
        $user->tokens()->delete();
        $token = $user->createToken('auth-token')->plainTextToken;

        return [
            'token' => $token,
            'message' => 'Token refreshed successfully',
        ];
    }

    /**
     * Generate tokens for OAuth-authenticated user.
     */
    public function oauthLogin(string $userId): array
    {
        $user = $this->authRepository->findById($userId);
        if (!$user) {
            throw AppError::notFound('User not found');
        }

        $this->authRepository->updateLastLogin($user->id);

        // Revoke old tokens
        $user->tokens()->delete();
        $token = $user->createToken('auth-token')->plainTextToken;

        return [
            'user' => $user->only(['id', 'first_name', 'last_name', 'email', 'role', 'avatar', 'is_email_verified']),
            'token' => $token,
            'message' => 'Login successful',
        ];
    }

    public function getUserById(string $userId): array
    {
        $user = $this->authRepository->findById($userId);
        if (!$user) {
            throw AppError::notFound('User not found');
        }
        return $user->toArray();
    }

    public function updateProfile(string $userId, array $data): array
    {
        $user = $this->authRepository->update($userId, $data);
        return $user->toArray();
    }

    public function changePassword(string $userId, string $currentPassword, string $newPassword): array
    {
        $user = $this->authRepository->findByIdOrFail($userId);

        if (!Hash::check($currentPassword, $user->password)) {
            throw AppError::authentication('Current password is incorrect');
        }

        $this->validatePassword($newPassword);

        $this->authRepository->updatePassword($userId, Hash::make($newPassword));
        return ['message' => 'Password changed successfully'];
    }

    public function forgotPassword(string $email): array
    {
        $user = $this->authRepository->findByEmail(strtolower($email));
        if (!$user) {
            return ['message' => 'If email exists, password reset link will be sent'];
        }

        $token = Str::random(64);
        $expiresAt = now()->addHour();
        $this->authRepository->setPasswordResetToken($user->email, $token, $expiresAt);

        $resetLink = url("/reset-password?token={$token}&email={$user->email}");
        $this->emailService->sendPasswordResetEmail($user->email, $user->first_name, $resetLink);

        return ['message' => 'Password reset link sent to your email'];
    }

    public function resetPassword(string $email, string $token, string $newPassword): array
    {
        $user = $this->authRepository->verifyPasswordResetToken(strtolower($email), $token);
        if (!$user) {
            throw AppError::validation('Invalid or expired reset token');
        }

        $this->validatePassword($newPassword);

        $this->authRepository->updatePassword($user->id, Hash::make($newPassword));
        return ['message' => 'Password reset successfully'];
    }

    public function sendOtp(string $email): array
    {
        $user = $this->authRepository->findByEmail(strtolower($email));
        if (!$user) {
            throw AppError::notFound('User not found');
        }

        if ($this->authRepository->checkOtpAttempts($email)) {
            throw AppError::validation('Too many OTP attempts. Please try again later.');
        }

        $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $expiresAt = now()->addMinutes(5);
        $this->authRepository->setOtpCode($user->email, $otp, $expiresAt);

        if ($user->phone_number) {
            // Fire-and-forget SMS (matching TS behavior)
            try {
                $this->smsService->sendOTP($user->phone_number, $otp);
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error('Failed to send OTP SMS', ['error' => $e->getMessage()]);
            }
        }

        return ['message' => 'OTP sent to your phone'];
    }

    public function verifyOtp(string $email, string $otp): array
    {
        $user = $this->authRepository->verifyOtpCode(strtolower($email), $otp);
        if (!$user) {
            $this->authRepository->incrementOtpAttempts($email);
            throw AppError::validation('Invalid or expired OTP');
        }

        $token = $user->createToken('auth-token')->plainTextToken;
        return ['token' => $token, 'message' => 'OTP verified successfully'];
    }

    public function sendEmailVerification(string $email): array
    {
        $user = $this->authRepository->findByEmail(strtolower($email));
        if (!$user) {
            throw AppError::notFound('User not found');
        }

        if ($user->is_email_verified) {
            throw AppError::validation('Email is already verified');
        }

        $token = Str::random(64);
        $expiresAt = now()->addDay();
        $this->authRepository->setEmailVerificationToken($user->email, $token, $expiresAt);

        $verificationLink = url("/verify-email?token={$token}&email={$user->email}");
        $this->emailService->sendVerificationEmail($user->email, $user->first_name, $verificationLink);

        return ['message' => 'Verification email sent'];
    }

    public function verifyEmail(string $email, string $token): array
    {
        $user = $this->authRepository->verifyEmail(strtolower($email), $token);
        if (!$user) {
            throw AppError::validation('Invalid or expired verification token');
        }
        return ['message' => 'Email verified successfully'];
    }

    public function findOrCreateOAuthUser(array $data): User
    {
        $user = $this->authRepository->findOrCreateOAuthUser($data);

        // Ensure wallet and loyalty points exist (safe to call)
        try {
            $this->authRepository->createWallet($user->id);
        } catch (\Exception $e) {
            // Wallet may already exist
        }
        try {
            $this->authRepository->createLoyaltyPoints($user->id);
        } catch (\Exception $e) {
            // Loyalty points may already exist
        }

        return $user;
    }
}
