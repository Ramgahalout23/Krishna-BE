<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Coupon extends Model
{
    use HasUuids;

    public const NON_MONETARY_TYPES = ['FREE_SHIPPING', 'BOGO', 'BUY_X_GET_Y', 'CASHBACK', 'REFERRAL', 'WALLET_CASHBACK', 'AUTO_CART_DISCOUNT'];
    protected $fillable = [
        'code', 'type', 'discount_type', 'discount_value', 'min_order_value', 'max_discount',
        'usage_limit', 'usage_per_user', 'usage_count', 'is_active', 'start_date', 'expiry_date',
        'applicable_categories', 'applicable_brands', 'applicable_products', 'applicable_roles',
        'applicable_payment_methods', 'is_new_user_only', 'is_auto_apply', 'is_stackable',
        'is_single_use', 'is_bulk', 'schedule_start', 'schedule_end', 'campaign_id',
        'fraud_protection_level', 'created_by', 'description'
    ];
    protected $casts = [
        'discount_value' => 'decimal:2', 'min_order_value' => 'decimal:2', 'max_discount' => 'decimal:2',
        'is_active' => 'boolean', 'is_new_user_only' => 'boolean', 'is_auto_apply' => 'boolean',
        'is_stackable' => 'boolean', 'is_single_use' => 'boolean', 'is_bulk' => 'boolean',
        'start_date' => 'datetime', 'expiry_date' => 'datetime',
        'schedule_start' => 'datetime', 'schedule_end' => 'datetime',
    ];

    public function campaign(): BelongsTo { return $this->belongsTo(CouponCampaign::class, 'campaign_id'); }
    public function createdBy(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function analytics(): HasOne { return $this->hasOne(CouponAnalytics::class); }
    public function usages(): HasMany { return $this->hasMany(CouponUsage::class); }
    public function orders(): HasMany { return $this->hasMany(Order::class); }
    public function checkoutSessions(): HasMany { return $this->hasMany(CheckoutSession::class); }
    public function referrals(): HasMany { return $this->hasMany(Referral::class); }
}
