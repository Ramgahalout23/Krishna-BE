<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Shipping extends Model
{
    use HasUuids;
    protected $fillable = ['order_id', 'carrier', 'tracking_number', 'cost', 'estimated_delivery', 'actual_delivery', 'status', 'notes'];
    protected $casts = ['cost' => 'decimal:2', 'estimated_delivery' => 'datetime', 'actual_delivery' => 'datetime'];
    public function order(): BelongsTo { return $this->belongsTo(Order::class); }
}
