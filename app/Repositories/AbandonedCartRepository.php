<?php

namespace App\Repositories;

use App\Models\AbandonedCart;
use Illuminate\Database\Eloquent\Collection;

class AbandonedCartRepository extends BaseRepository
{
    protected function modelClass(): string
    {
        return AbandonedCart::class;
    }

    public function getUserAbandonedCarts(string $userId): Collection
    {
        return AbandonedCart::where('user_id', $userId)->latest()->get();
    }

    /**
     * Get active abandoned carts that haven't been reminded yet and are older than 2 hours.
     */
    public function getActiveCarts(): Collection
    {
        return AbandonedCart::with('user')
            ->where('reminder_sent', false)
            ->where('recovered', false)
            ->where('created_at', '<', now()->subHours(2))
            ->get();
    }

    /**
     * Mark a cart as reminded.
     */
    public function markReminded(string $id): void
    {
        AbandonedCart::where('id', $id)->update([
            'reminder_sent' => true,
            'reminded_at' => now(),
        ]);
    }

    /**
     * Get recovery stats.
     */
    public function getRecoveryStats(): array
    {
        $total = AbandonedCart::count();
        $recovered = AbandonedCart::where('recovered', true)->count();
        $reminded = AbandonedCart::where('reminder_sent', true)->count();
        return [
            'total' => $total,
            'reminded' => $reminded,
            'recovered' => $recovered,
            'rate' => $total > 0 ? round(($recovered / $total) * 100, 2) : 0,
        ];
    }
}
