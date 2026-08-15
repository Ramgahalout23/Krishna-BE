<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CartItem extends Model
{
    use HasUuids;
    protected $fillable = ['user_id', 'session_id', 'product_id', 'variant_id', 'quantity', 'price', 'guest_cart_id', 'color', 'size', 'saved_for_later'];
    protected $casts = ['price' => 'decimal:2', 'quantity' => 'integer'];

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function product(): BelongsTo { return $this->belongsTo(Product::class); }
    public function guestCart(): BelongsTo { return $this->belongsTo(GuestCart::class); }
    public function variant(): BelongsTo { return $this->belongsTo(ProductVariant::class); }
}
