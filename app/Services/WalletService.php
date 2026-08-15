<?php

namespace App\Services;

use App\Repositories\WalletRepository;
use App\Exceptions\AppError;

class WalletService
{
    public function __construct(
        protected WalletRepository $walletRepository
    ) {}

    /**
     * Get user's wallet with transactions.
     */
    public function getWallet(string $userId): array
    {
        $wallet = $this->walletRepository->findByUserOrFail($userId);
        return $wallet->toArray();
    }

    /**
     * Get wallet balance for a user.
     */
    public function getBalance(string $userId): float
    {
        $wallet = $this->walletRepository->findByUser($userId);
        return $wallet ? (float) $wallet->balance : 0.0;
    }

    /**
     * Recharge (add money to) wallet.
     */
    public function recharge(string $userId, float $amount, string $reason = 'Manual recharge', ?string $referenceId = null): array
    {
        if ($amount <= 0) {
            throw AppError::validation('Recharge amount must be positive');
        }

        $this->walletRepository->addBalance($userId, $amount, $reason, $referenceId);

        return $this->getWallet($userId);
    }

    /**
     * Deduct (remove money from) wallet.
     */
    public function deduct(string $userId, float $amount, string $reason = 'Purchase', ?string $referenceId = null): array
    {
        if ($amount <= 0) {
            throw AppError::validation('Deduction amount must be positive');
        }

        $this->walletRepository->deductBalance($userId, $amount, $reason, $referenceId);

        return $this->getWallet($userId);
    }

    /**
     * Admin adjustment — can add or remove money.
     */
    public function adjustWallet(string $userId, float $amount, string $reason, string $adminId): array
    {
        $this->walletRepository->adjustBalance($userId, $amount, $reason, $adminId);

        return $this->getWallet($userId);
    }

    /**
     * Get all transactions for a user's wallet.
     */
    public function getTransactions(string $userId, int $perPage = 20): array
    {
        return $this->walletRepository->getTransactions($userId, $perPage);
    }

    /**
     * Get all wallets (admin).
     */
    public function getAllWallets(array $filters = []): array
    {
        return $this->walletRepository->getAll($filters);
    }

    /**
     * Get wallet stats (admin).
     */
    public function getStats(): array
    {
        return $this->walletRepository->getStats();
    }
}
