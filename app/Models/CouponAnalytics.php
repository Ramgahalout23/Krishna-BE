<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CouponAnalytics extends Model
{
    use HasUuids;
    protected $fillable = ['coupon_id', 'usage_count', 'total_discount_given', 'fraud_attempts'];
    protected $casts = ['usage_count' => 'integer', 'total_discount_given' => 'decimal:2', 'fraud_attempts' => 'integer'];
    public function coupon(): BelongsTo { return $this->belongsTo(Coupon::class); }
}
