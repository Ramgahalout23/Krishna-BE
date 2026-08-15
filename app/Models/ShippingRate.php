<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShippingRate extends Model
{
    use HasUuids;
    protected $fillable = ['zone_id', 'min_weight', 'max_weight', 'min_price', 'max_price', 'cost', 'free_shipping_above'];
    protected $casts = ['cost' => 'decimal:2', 'free_shipping_above' => 'decimal:2', 'min_weight' => 'decimal:3', 'max_weight' => 'decimal:3', 'min_price' => 'decimal:2', 'max_price' => 'decimal:2'];
    public function zone(): BelongsTo { return $this->belongsTo(ShippingZone::class, 'zone_id'); }
}
