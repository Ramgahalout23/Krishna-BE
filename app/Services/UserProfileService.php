<?php

namespace App\Services;

use App\Models\User;
use App\Models\Address;
use App\Exceptions\AppError;

class UserProfileService
{
    public function __construct(
        protected WalletService $walletService,
        protected LoyaltyService $loyaltyService
    ) {}

    /**
     * Get full user profile with wallet, loyalty, addresses.
     */
    public function getProfile(string $userId): array
    {
        $user = User::with(['addresses', 'wallet', 'loyaltyPoints', 'vipTier'])->find($userId);
        if (!$user) {
            throw AppError::notFound('User not found');
        }
        return $user->toArray();
    }

    /**
     * Get user orders.
     */
    public function getOrders(string $userId): array
    {
        $user = User::with(['orders' => function ($q) {
            $q->latest()->with(['items.product', 'payment']);
        }])->findOrFail($userId);

        return $user->orders->toArray();
    }

    /**
     * Get wallet with transactions via WalletService.
     */
    public function getWallet(string $userId): array
    {
        return $this->walletService->getWallet($userId);
    }

    /**
     * Get loyalty points with transactions via LoyaltyService.
     */
    public function getLoyalty(string $userId): array
    {
        return $this->loyaltyService->getPoints($userId);
    }

    /**
     * Get user profile stats.
     */
    public function getStats(string $userId): array
    {
        $user = User::withCount(['orders', 'reviews'])->findOrFail($userId);
        return [
            'total_orders' => $user->orders_count,
            'total_reviews' => $user->reviews_count,
            'wallet_balance' => $user->wallet?->balance ?? 0,
            'loyalty_points' => $user->loyaltyPoints?->points ?? 0,
        ];
    }

    // ── Address Management ──

    public function getAddresses(string $userId): array
    {
        return Address::where('user_id', $userId)->get()->toArray();
    }

    public function getAddress(string $userId, string $addressId): array
    {
        $address = Address::where('user_id', $userId)->find($addressId);
        if (!$address) throw AppError::notFound('Address not found');
        return $address->toArray();
    }

    public function getDefaultAddress(string $userId): array
    {
        $address = Address::where('user_id', $userId)->where('is_default', true)->first();
        if (!$address) throw AppError::notFound('No default address found');
        return $address->toArray();
    }

    public function addAddress(string $userId, array $data): array
    {
        $data['user_id'] = $userId;
        if (!empty($data['is_default'])) {
            Address::where('user_id', $userId)->update(['is_default' => false]);
        }
        $address = Address::create($data);
        return $address->fresh()->toArray();
    }

    public function updateAddress(string $userId, string $addressId, array $data): array
    {
        $address = Address::where('user_id', $userId)->find($addressId);
        if (!$address) throw AppError::notFound('Address not found');
        if (!empty($data['is_default'])) {
            Address::where('user_id', $userId)->where('id', '!=', $addressId)->update(['is_default' => false]);
        }
        $address->update($data);
        return $address->fresh()->toArray();
    }

    public function deleteAddress(string $userId, string $addressId): void
    {
        $address = Address::where('user_id', $userId)->find($addressId);
        if (!$address) throw AppError::notFound('Address not found');
        $address->delete();
    }

    public function setDefaultAddress(string $userId, string $addressId): array
    {
        $address = Address::where('user_id', $userId)->find($addressId);
        if (!$address) throw AppError::notFound('Address not found');
        Address::where('user_id', $userId)->update(['is_default' => false]);
        $address->update(['is_default' => true]);
        return $address->fresh()->toArray();
    }
}
