<?php

namespace App\Services;

use App\Models\LoyaltyPoint;
use App\Models\LoyaltyTransaction;
use App\Exceptions\AppError;
use Illuminate\Support\Facades\DB;

class LoyaltyService
{
    /**
     * Get user's loyalty points with transactions.
     */
    public function getPoints(string $userId): array
    {
        $loyalty = LoyaltyPoint::where('user_id', $userId)->first();
        if (!$loyalty) {
            return ['points' => 0, 'tier' => 'BRONZE'];
        }
        $loyalty->load('transactions');
        return $loyalty->toArray();
    }

    /**
     * Get current point balance for a user.
     */
    public function getBalance(string $userId): int
    {
        $loyalty = LoyaltyPoint::where('user_id', $userId)->first();
        return $loyalty ? (int) $loyalty->points : 0;
    }

    /**
     * Earn loyalty points.
     */
    public function earnPoints(string $userId, int $points, string $reason = 'Purchase', ?string $referenceId = null): LoyaltyPoint
    {
        if ($points <= 0) {
            throw AppError::validation('Points must be positive');
        }

        return DB::transaction(function () use ($userId, $points, $reason, $referenceId) {
            $loyalty = LoyaltyPoint::firstOrCreate(
                ['user_id' => $userId],
                ['points' => 0]
            );
            $loyalty->increment('points', $points);

            LoyaltyTransaction::create([
                'loyalty_id' => $loyalty->id,
                'type' => 'EARNED',
                'points' => $points,
                'reason' => $reason,
                'reference_id' => $referenceId,
            ]);

            return $loyalty->fresh();
        });
    }

    /**
     * Redeem (spend) loyalty points.
     */
    public function redeemPoints(string $userId, int $points, string $reason = 'Points redeemed', ?string $referenceId = null): LoyaltyPoint
    {
        if ($points <= 0) {
            throw AppError::validation('Points must be positive');
        }

        return DB::transaction(function () use ($userId, $points, $reason, $referenceId) {
            $loyalty = LoyaltyPoint::where('user_id', $userId)->firstOrFail();

            if ((int) $loyalty->points < $points) {
                throw AppError::validation('Insufficient loyalty points');
            }

            $loyalty->decrement('points', $points);

            LoyaltyTransaction::create([
                'loyalty_id' => $loyalty->id,
                'type' => 'REDEEMED',
                'points' => $points,
                'reason' => $reason,
                'reference_id' => $referenceId,
            ]);

            return $loyalty->fresh();
        });
    }

    /**
     * Get loyalty transaction history for a user.
     */
    public function getTransactionHistory(string $userId, int $limit = 20): array
    {
        $loyalty = LoyaltyPoint::where('user_id', $userId)->firstOrFail();
        return LoyaltyTransaction::where('loyalty_id', $loyalty->id)
            ->latest()
            ->take($limit)
            ->get()
            ->toArray();
    }

    /**
     * Determine loyalty tier based on points.
     */
    public function getTier(int $points): string
    {
        return match (true) {
            $points >= 10000 => 'DIAMOND',
            $points >= 5000 => 'PLATINUM',
            $points >= 2000 => 'GOLD',
            $points >= 500 => 'SILVER',
            default => 'BRONZE',
        };
    }

    /**
     * Get conversion rate for points to currency.
     */
    public function getConversionRate(): float
    {
        return 0.10; // 10 points = ₹1
    }

    /**
     * Convert points to monetary value.
     */
    public function pointsToMoney(int $points): float
    {
        return $points * $this->getConversionRate();
    }

    /**
     * Admin: adjust points for any user.
     */
    public function adjustPoints(string $userId, int $points, string $reason, string $adminId): LoyaltyPoint
    {
        return DB::transaction(function () use ($userId, $points, $reason, $adminId) {
            $loyalty = LoyaltyPoint::where('user_id', $userId)->firstOrFail();

            if ($points < 0 && (int) $loyalty->points < abs($points)) {
                throw AppError::validation('Cannot deduct more points than available');
            }

            if ($points > 0) {
                $loyalty->increment('points', $points);
            } else {
                $loyalty->decrement('points', abs($points));
            }

            LoyaltyTransaction::create([
                'loyalty_id' => $loyalty->id,
                'type' => $points > 0 ? 'ADMIN_CREDIT' : 'ADMIN_DEBIT',
                'points' => abs($points),
                'reason' => "Admin ({$adminId}): {$reason}",
            ]);

            return $loyalty->fresh();
        });
    }

    /**
     * Get all loyalty records (admin).
     */
    public function getAll(array $filters = []): array
    {
        $query = LoyaltyPoint::with('user');
        if (!empty($filters['min_points'])) {
            $query->where('points', '>=', $filters['min_points']);
        }
        return $query->latest()->paginate($filters['per_page'] ?? 20)->toArray();
    }
}
