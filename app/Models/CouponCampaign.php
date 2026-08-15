<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CouponCampaign extends Model
{
    use HasUuids;
    protected $fillable = ['name', 'description', 'start_date', 'end_date'];
    protected $casts = ['start_date' => 'datetime', 'end_date' => 'datetime'];
    public function coupons(): HasMany { return $this->hasMany(Coupon::class, 'campaign_id'); }
}
