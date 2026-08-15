<?php

namespace App\Repositories;

use App\Models\User;
use App\Models\Wallet;
use App\Models\LoyaltyPoint;
use Illuminate\Support\Facades\Hash;

class AuthRepository extends BaseRepository
{
    protected function modelClass(): string
    {
        return User::class;
    }

    public function findByEmail(string $email): ?User
    {
        return User::where('email', $email)->first();
    }

    public function createUser(array $data): User
    {
        return User::create([
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'email' => strtolower($data['email']),
            'password' => Hash::make($data['password']),
            'phone_number' => $data['phone_number'] ?? null,
            'role' => $data['role'] ?? 'CUSTOMER',
            'is_active' => true,
        ]);
    }

    public function createWallet(string $userId): Wallet
    {
        return Wallet::create(['user_id' => $userId, 'balance' => 0, 'points' => 0]);
    }

    public function createLoyaltyPoints(string $userId): LoyaltyPoint
    {
        return LoyaltyPoint::create(['user_id' => $userId, 'points' => 0, 'tier' => 'BRONZE']);
    }

    public function updateLastLogin(string $userId): void
    {
        User::where('id', $userId)->update(['last_login_at' => now()]);
    }

    public function setPasswordResetToken(string $email, string $token, \DateTime $expiresAt): void
    {
        User::where('email', $email)->update([
            'password_reset_token' => $token,
            'password_reset_expiry' => $expiresAt,
        ]);
    }

    public function verifyPasswordResetToken(string $email, string $token): ?User
    {
        return User::where('email', $email)
            ->where('password_reset_token', $token)
            ->where('password_reset_expiry', '>', now())
            ->first();
    }

    public function updatePassword(string $userId, string $hashedPassword): void
    {
        User::where('id', $userId)->update([
            'password' => $hashedPassword,
            'password_reset_token' => null,
            'password_reset_expiry' => null,
        ]);
    }

    public function setEmailVerificationToken(string $email, string $token, \DateTime $expiresAt): void
    {
        User::where('email', $email)->update([
            'email_verification_token' => $token,
            'email_verification_expiry' => $expiresAt,
        ]);
    }

    public function verifyEmail(string $email, string $token): ?User
    {
        $user = User::where('email', $email)
            ->where('email_verification_token', $token)
            ->where('email_verification_expiry', '>', now())
            ->first();
        if ($user) {
            $user->update([
                'is_email_verified' => true,
                'email_verification_token' => null,
                'email_verification_expiry' => null,
            ]);
        }
        return $user;
    }

    public function setOtpCode(string $email, string $otp, \DateTime $expiresAt): void
    {
        User::where('email', $email)->update([
            'otp_code' => $otp,
            'otp_expiry' => $expiresAt,
            'otp_attempts' => 0,
        ]);
    }

    public function verifyOtpCode(string $email, string $otp): ?User
    {
        $user = User::where('email', $email)
            ->where('otp_code', $otp)
            ->where('otp_expiry', '>', now())
            ->first();
        if ($user) {
            $user->update(['otp_code' => null, 'otp_expiry' => null]);
        }
        return $user;
    }

    public function checkOtpAttempts(string $email): bool
    {
        $user = User::where('email', $email)->first();
        if (!$user) return false;
        return ($user->otp_attempts ?? 0) >= 5;
    }

    public function incrementOtpAttempts(string $email): void
    {
        User::where('email', $email)->increment('otp_attempts');
    }

    public function findOrCreateOAuthUser(array $data): User
    {
        $user = User::where('email', $data['email'])->first();
        if (!$user) {
            $user = User::create([
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'email' => $data['email'],
                'password' => Hash::make(\Illuminate\Support\Str::random(32)),
                'avatar' => $data['avatar'] ?? null,
                'role' => 'CUSTOMER',
                'is_email_verified' => true,
                'is_active' => true,
            ]);
        }
        return $user;
    }

    public function findById(string $id): ?User
    {
        return User::find($id);
    }

    public function findByIdWithDetails(string $id): ?User
    {
        return User::with(['address', 'wallet', 'loyaltyPoints'])->find($id);
    }

    public function emailExists(string $email): bool
    {
        return User::where('email', strtolower($email))->exists();
    }
}
