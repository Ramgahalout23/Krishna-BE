<?php

namespace App\Repositories;

use App\Models\Coupon;
use Illuminate\Pagination\LengthAwarePaginator;

class CouponRepository extends BaseRepository
{
    protected function modelClass(): string
    {
        return Coupon::class;
    }

    public function findByCode(string $code): ?Coupon
    {
        return Coupon::where('code', $code)->first();
    }

    public function findActiveByCode(string $code): ?Coupon
    {
        return Coupon::where('code', $code)
            ->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('expiry_date')->orWhere('expiry_date', '>', now());
            })
            ->first();
    }

    public function getAll(array $filters = []): LengthAwarePaginator
    {
        $query = Coupon::query();
        if (!empty($filters['is_active'])) {
            $query->where('is_active', $filters['is_active']);
        }
        return $query->latest()->paginate($filters['per_page'] ?? 15);
    }

    public function getUsageHistory(string $couponId): \Illuminate\Database\Eloquent\Collection
    {
        return \App\Models\CouponUsage::with([
            'user:id,first_name,last_name,email',
            'order:id,order_number,total,status,created_at',
        ])
            ->where('coupon_id', $couponId)
            ->latest()
            ->get();
    }

    public function getAnalytics(string $couponId): array
    {
        $coupon = $this->findByIdOrFail($couponId);
        return [
            'total_usage' => $coupon->usage_count ?? 0,
            'total_revenue' => \App\Models\Order::where('coupon_id', $couponId)->sum('total'),
            'usage_history' => $this->getUsageHistory($couponId),
        ];
    }
}
