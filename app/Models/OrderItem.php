<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    use HasUuids;
    protected $fillable = ['order_id', 'user_id', 'product_id', 'variant_id', 'quantity', 'price', 'discount', 'total'];
    protected $casts = ['price' => 'decimal:2', 'discount' => 'decimal:2', 'total' => 'decimal:2'];

    public function order(): BelongsTo { return $this->belongsTo(Order::class); }
    public function product(): BelongsTo { return $this->belongsTo(Product::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function variant(): BelongsTo { return $this->belongsTo(ProductVariant::class); }
}
