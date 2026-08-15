<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoyaltyTransaction extends Model
{
    use HasUuids;
    protected $fillable = ['loyalty_id', 'type', 'points', 'reason', 'reference_id'];
    protected $casts = ['points' => 'integer'];
    public function loyaltyPoint(): BelongsTo { return $this->belongsTo(LoyaltyPoint::class, 'loyalty_id'); }
}
