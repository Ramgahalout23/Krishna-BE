<?php

namespace App\Services;

use App\Repositories\CouponRepository;
use App\Exceptions\AppError;
use App\Models\Coupon;
use App\Models\CouponUsage;
use App\Models\CouponAnalytics;
use App\Traits\CacheKeyRegistry;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class CouponService
{
    use CacheKeyRegistry;

    public function __construct(
        protected CouponRepository $couponRepository
    ) {}

    /**
     * Create a new coupon.
     */
    public function create(array $data): array
    {
        if (empty($data['code'])) throw AppError::validation('Coupon code is required');
        if (empty($data['discount_type'])) throw AppError::validation('Discount type is required');
        if (($data['discount_value'] ?? 0) <= 0) throw AppError::validation('Discount value must be positive');

        if (!empty($data['start_date']) && !empty($data['expiry_date'])) {
            if (strtotime($data['start_date']) >= strtotime($data['expiry_date'])) {
                throw AppError::validation('Expiry date must be after start date');
            }
        }

        $result = $this->couponRepository->create($data)->toArray();
        $this->clearTrackedCache();
        return $result;
    }

    public function getAll(array $filters = [], int $page = 1, int $limit = 10): array
    {
        $query = Coupon::query();

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('code', 'like', "%{$search}%");
            });
        }

        if (!empty($filters['is_active']) || isset($filters['isActive'])) {
            $isActive = $filters['is_active'] ?? $filters['isActive'];
            if (filter_var($isActive, FILTER_VALIDATE_BOOLEAN) !== false) {
                $query->where('is_active', true);
            } else {
                $query->where('is_active', false);
            }
        }

        $paginator = $query->latest()->paginate($limit, ['*'], 'page', $page);

        return [
            'data' => $paginator->items(),
            'pagination' => [
                'total' => $paginator->total(),
                'page' => $paginator->currentPage(),
                'limit' => $paginator->perPage(),
                'pages' => $paginator->lastPage(),
            ],
        ];
    }

    public function getById(string $id): array
    {
        $coupon = $this->couponRepository->findById($id);
        if (!$coupon) throw AppError::notFound('Coupon not found');
        return $coupon->toArray();
    }

    /**
     * Get coupon by code.
     */
    public function getByCode(string $code): array
    {
        $coupon = $this->couponRepository->findByCode($code);
        if (!$coupon) throw AppError::notFound('Coupon not found');
        return $coupon->toArray();
    }

    /**
     * Get public-facing coupons (active, not expired, non-auto-apply, monetary).
     */
    public function getPublicCoupons(): array
    {
        return $this->cacheWithTracking('coupons_public', 3600, function () {
            $nonMonetaryTypes = Coupon::NON_MONETARY_TYPES;

            return Coupon::select('id', 'code', 'type', 'discount_type', 'discount_value', 'min_order_value', 'max_discount', 'description', 'is_auto_apply', 'start_date', 'expiry_date', 'usage_count', 'usage_limit', 'usage_per_user', 'is_new_user_only', 'is_stackable')
                ->where('is_active', true)
                ->where(function ($q) {
                    $q->whereNull('start_date')->orWhere('start_date', '<=', now());
                })
                ->where(function ($q) {
                    $q->whereNull('expiry_date')->orWhere('expiry_date', '>', now());
                })
                ->where('is_auto_apply', false)
                ->where('discount_value', '>', 0)
                ->get()
                ->filter(fn($c) => !in_array($c->type ?? '', $nonMonetaryTypes))
                ->values()
                ->toArray();
        });
    }

    public function validateCoupon(string $code): array
    {
        $coupon = $this->couponRepository->findActiveByCode($code);
        if (!$coupon) throw AppError::validation('Invalid or expired coupon code');
        return $coupon->toArray();
    }

    public function update(string $id, array $data): array
    {
        $this->couponRepository->findByIdOrFail($id);

        if (!empty($data['start_date']) && !empty($data['expiry_date'])) {
            if (strtotime($data['start_date']) >= strtotime($data['expiry_date'])) {
                throw AppError::validation('Expiry date must be after start date');
            }
        }

        $result = $this->couponRepository->update($id, $data)->toArray();
        $this->clearTrackedCache();
        return $result;
    }

    public function delete(string $id): void
    {
        $this->couponRepository->findByIdOrFail($id);
        $this->couponRepository->delete($id);
        $this->clearTrackedCache();
    }

    /**
     * Get coupon analytics with full details (matching TS behavior: returns coupon + analytics + usageHistory).
     */
    public function getAnalytics(string $id): array
    {
        $coupon = $this->getById($id);
        $analytics = $this->couponRepository->getAnalytics($id);
        $usageHistory = $this->couponRepository->getUsageHistory($id);

        $totalUsage   = $analytics['total_usage'] ?? 0;
        $totalRevenue = $analytics['total_revenue'] ?? 0;

        // Get total discount from CouponAnalytics table (records discount given per usage)
        $couponAnalytics = \App\Models\CouponAnalytics::where('coupon_id', $id)->first();
        $totalDiscount  = $couponAnalytics ? (float) ($couponAnalytics->total_discount_given ?? 0) : 0;

        return [
            'coupon' => $coupon,
            'analytics' => $analytics,
            'usage_history' => $usageHistory->toArray(),
            // Flat camelCase fields expected by frontend analytics panel
            'usedCount'       => $totalUsage,
            'totalUses'       => $totalUsage,
            'usageCount'      => $totalUsage,
            'revenueGenerated' => $totalRevenue,
            'totalDiscount'   => $totalDiscount,
            'avgOrderValue'   => $totalUsage > 0 ? round($totalRevenue / $totalUsage, 2) : 0,
        ];
    }

    public function getUsageHistory(string $id): array
    {
        return $this->couponRepository->getUsageHistory($id)->toArray();
    }

    /**
     * Validate coupon with detailed result (for apply flow).
     */
    public function validate(string $couponCode, array $cartData, ?string $userId = null): array
    {
        $coupon = $this->couponRepository->findByCode($couponCode);

        if (!$coupon) {
            return ['is_valid' => false, 'discount_amount' => 0, 'reason' => 'Coupon not found'];
        }

        if (!$coupon->is_active) {
            return ['is_valid' => false, 'discount_amount' => 0, 'reason' => 'Coupon is inactive', 'code' => $coupon->code];
        }

        $now = now();
        if (($coupon->start_date && $coupon->start_date > $now) || ($coupon->expiry_date && $coupon->expiry_date < $now)) {
            return ['is_valid' => false, 'discount_amount' => 0, 'reason' => 'Coupon has expired or is not yet active', 'code' => $coupon->code];
        }

        if ($coupon->usage_limit && $coupon->usage_count >= $coupon->usage_limit) {
            return ['is_valid' => false, 'discount_amount' => 0, 'reason' => 'Coupon usage limit reached', 'code' => $coupon->code];
        }

        $subtotal = $cartData['subtotal'] ?? 0;
        if ($coupon->min_order_value && $subtotal < (float) $coupon->min_order_value) {
            return ['is_valid' => false, 'discount_amount' => 0, 'reason' => "Minimum cart value should be " . $coupon->min_order_value, 'code' => $coupon->code];
        }

        // Check per-user usage limit
        if ($coupon->usage_per_user && $userId) {
            $userUsageCount = CouponUsage::where('coupon_id', $coupon->id)
                ->where('user_id', $userId)
                ->count();
            if ($userUsageCount >= (int) $coupon->usage_per_user) {
                return ['is_valid' => false, 'discount_amount' => 0, 'reason' => 'You have already used this coupon', 'code' => $coupon->code];
            }
        }

        // Calculate discount
        $discountAmount = 0;
        if ($coupon->discount_type === 'PERCENTAGE') {
            $discountAmount = ($subtotal * (float) $coupon->discount_value) / 100;
        } else {
            $discountAmount = (float) $coupon->discount_value;
        }

        if ($coupon->max_discount && $discountAmount > (float) $coupon->max_discount) {
            $discountAmount = (float) $coupon->max_discount;
        }

        return [
            'is_valid' => true,
            'code' => $coupon->code,
            'discount_amount' => min($discountAmount, $subtotal),
            'coupon_id' => $coupon->id,
            'type' => $coupon->type,
        ];
    }

    /**
     * Apply coupon to cart/order.
     */
    public function apply(string $couponCode, array $cartData, ?string $userId = null, ?string $orderId = null): array
    {
        $validation = $this->validate($couponCode, $cartData, $userId);

        if (!$validation['is_valid']) {
            throw AppError::validation($validation['reason']);
        }

        $coupon = $this->couponRepository->findByCode($couponCode);
        if (!$coupon) throw AppError::notFound('Coupon not found');

        // Record usage
        if ($orderId || $userId) {
            CouponUsage::create([
                'coupon_id' => $coupon->id,
                'user_id' => $userId,
                'order_id' => $orderId,
                'discount_amount' => $validation['discount_amount'],
            ]);

            $coupon->increment('usage_count');
        }

        // Update analytics
        $analytics = CouponAnalytics::firstOrCreate(
            ['coupon_id' => $coupon->id],
            ['usage_count' => 0, 'total_discount_given' => 0]
        );
        $analytics->increment('usage_count');
        $analytics->increment('total_discount_given', $validation['discount_amount']);

        // Clear cached coupon lists so updated usage counts are reflected immediately
        $this->clearTrackedCache();

        return [
            'coupon_id' => $coupon->id,
            'code' => $coupon->code,
            'discount_amount' => $validation['discount_amount'],
            'discount_percentage' => $coupon->discount_type === 'PERCENTAGE' ? $coupon->discount_value : null,
            'type' => $coupon->type,
        ];
    }

    /**
     * Generate bulk coupons.
     */
    public function generateBulk(array $data): array
    {
        $quantity = $data['quantity'] ?? 1;
        if ($quantity > 10000) {
            throw AppError::validation('Cannot generate more than 10,000 coupons at once');
        }

        if (isset($data['start_date'], $data['expiry_date']) && strtotime($data['start_date']) >= strtotime($data['expiry_date'])) {
            throw AppError::validation('Expiry date must be after start date');
        }

        $coupons = [];
        for ($i = 0; $i < $quantity; $i++) {
            $code = strtoupper(Str::random(8));
            $coupons[] = Coupon::create(array_merge($data, [
                'code' => $code,
                'is_active' => true,
            ]));
        }

        return array_map(fn($c) => $c->toArray(), $coupons);
    }

    /**
     * Get auto-applicable coupons (for cart page).
     */
    public function getAutoApplyCoupons(): array
    {
        return $this->cacheWithTracking('coupons_auto_apply', 3600, function () {
            return Coupon::select('id', 'code', 'type', 'discount_type', 'discount_value', 'min_order_value', 'max_discount', 'description', 'start_date', 'expiry_date', 'usage_count', 'usage_limit', 'usage_per_user', 'is_new_user_only', 'is_stackable')
                ->where('is_active', true)
                ->where('is_auto_apply', true)
                ->where(function ($q) {
                    $q->whereNull('start_date')->orWhere('start_date', '<=', now());
                })
                ->where(function ($q) {
                    $q->whereNull('expiry_date')->orWhere('expiry_date', '>', now());
                })
                ->get()
                ->toArray();
        });
    }

    /**
     * Get the best coupon for a cart.
     */
    public function getBestCoupon(array $cartData, ?string $userId = null): ?array
    {
        $coupons = Coupon::where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('start_date')->orWhere('start_date', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('expiry_date')->orWhere('expiry_date', '>', now());
            })
            ->get();

        $bestDiscount = 0;
        $bestCoupon = null;

        foreach ($coupons as $coupon) {
            $result = $this->validate($coupon->code, $cartData, $userId);
            if ($result['is_valid'] && $result['discount_amount'] > $bestDiscount) {
                $bestDiscount = $result['discount_amount'];
                $bestCoupon = $coupon;
            }
        }

        return $bestCoupon ? $bestCoupon->toArray() : null;
    }
}
