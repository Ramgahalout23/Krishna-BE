<?php

namespace App\Repositories;

use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Models\WalletAdjustment;
use App\Exceptions\AppError;
use Illuminate\Support\Facades\DB;

class WalletRepository
{
    /**
     * Get or create a wallet for a user.
     */
    public function getOrCreate(string $userId): Wallet
    {
        return Wallet::firstOrCreate(
            ['user_id' => $userId],
            ['balance' => 0]
        );
    }

    /**
     * Get wallet by user ID with recent transactions.
     */
    public function findByUser(string $userId): ?Wallet
    {
        return Wallet::where('user_id', $userId)
            ->with(['transactions' => function ($q) {
                $q->latest()->limit(10);
            }])
            ->first();
    }

    /**
     * Find wallet by ID or fail.
     */
    public function findByIdOrFail(int $id): Wallet
    {
        $wallet = Wallet::find($id);
        if (!$wallet) {
            throw AppError::notFound('Wallet not found');
        }
        return $wallet;
    }

    /**
     * Find wallet by user ID or fail.
     */
    public function findByUserOrFail(string $userId): Wallet
    {
        $wallet = $this->findByUser($userId);
        if (!$wallet) {
            throw AppError::notFound('Wallet not found for this user');
        }
        return $wallet;
    }

    /**
     * Add credit to wallet and create transaction record.
     */
    public function addBalance(string $userId, float $amount, string $reason, ?string $referenceId = null): WalletTransaction
    {
        return DB::transaction(function () use ($userId, $amount, $reason, $referenceId) {
            $wallet = $this->getOrCreate($userId);
            $wallet->increment('balance', $amount);

            return WalletTransaction::create([
                'wallet_id' => $wallet->id,
                'type' => 'CREDIT',
                'amount' => $amount,
                'reason' => $reason,
                'reference_id' => $referenceId,
            ]);
        });
    }

    /**
     * Deduct from wallet balance and create transaction record.
     */
    public function deductBalance(string $userId, float $amount, string $reason, ?string $referenceId = null): WalletTransaction
    {
        return DB::transaction(function () use ($userId, $amount, $reason, $referenceId) {
            $wallet = $this->getOrCreate($userId);

            if ((float) $wallet->balance < $amount) {
                throw AppError::validation('Insufficient wallet balance');
            }

            $wallet->decrement('balance', $amount);

            return WalletTransaction::create([
                'wallet_id' => $wallet->id,
                'type' => 'DEBIT',
                'amount' => $amount,
                'reason' => $reason,
                'reference_id' => $referenceId,
            ]);
        });
    }

    /**
     * Admin wallet adjustment with audit trail.
     */
    public function adjustBalance(string $userId, float $amount, string $reason, string $adminId): WalletAdjustment
    {
        return DB::transaction(function () use ($userId, $amount, $reason, $adminId) {
            $wallet = $this->getOrCreate($userId);
            $currentBalance = (float) $wallet->balance;

            if ($amount < 0 && $currentBalance < abs($amount)) {
                throw AppError::validation('Cannot deduct more than current balance');
            }

            if ($amount > 0) {
                $wallet->increment('balance', $amount);
            } else {
                $wallet->decrement('balance', abs($amount));
            }

            WalletTransaction::create([
                'wallet_id' => $wallet->id,
                'type' => $amount > 0 ? 'CREDIT' : 'DEBIT',
                'amount' => abs($amount),
                'reason' => "Admin adjustment: {$reason}",
            ]);

            return WalletAdjustment::create([
                'wallet_id' => $wallet->id,
                'admin_id' => $adminId,
                'amount' => $amount,
                'reason' => $reason,
            ]);
        });
    }

    /**
     * Get transactions for a user's wallet with pagination.
     */
    public function getTransactions(string $userId, int $perPage = 20): array
    {
        $wallet = $this->getOrCreate($userId);

        return WalletTransaction::where('wallet_id', $wallet->id)
            ->latest()
            ->paginate($perPage)
            ->toArray();
    }

    /**
     * Get all wallets with optional filters (admin).
     */
    public function getAll(array $filters = []): array
    {
        $query = Wallet::with('user');

        if (!empty($filters['min_balance'])) {
            $query->where('balance', '>=', $filters['min_balance']);
        }
        if (!empty($filters['max_balance'])) {
            $query->where('balance', '<=', $filters['max_balance']);
        }
        if (!empty($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }

        return $query->latest()->paginate($filters['per_page'] ?? 20)->toArray();
    }

    /**
     * Get total wallet stats (admin).
     */
    public function getStats(): array
    {
        return [
            'total_wallets' => Wallet::count(),
            'total_balance' => (float) Wallet::sum('balance'),
            'average_balance' => (float) Wallet::avg('balance'),
            'total_transactions' => WalletTransaction::count(),
            'total_credits' => (float) WalletTransaction::where('type', 'CREDIT')->sum('amount'),
            'total_debits' => (float) WalletTransaction::where('type', 'DEBIT')->sum('amount'),
        ];
    }
}
