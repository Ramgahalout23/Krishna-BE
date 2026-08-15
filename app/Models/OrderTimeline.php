<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderTimeline extends Model
{
    use HasUuids;
    protected $fillable = ['order_id', 'status', 'description'];
    public function order(): BelongsTo { return $this->belongsTo(Order::class); }
}
