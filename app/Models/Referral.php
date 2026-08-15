<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Referral extends Model
{
    use HasUuids;
    protected $fillable = ['referrer_id', 'referee_id', 'coupon_id', 'reward_points'];
    protected $casts = ['reward_points' => 'integer'];
    public function referrer(): BelongsTo { return $this->belongsTo(User::class, 'referrer_id'); }
    public function referee(): BelongsTo { return $this->belongsTo(User::class, 'referee_id'); }
    public function coupon(): BelongsTo { return $this->belongsTo(Coupon::class); }
}
