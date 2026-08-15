<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CheckoutSession extends Model
{
    use HasUuids;
    protected $fillable = [
        'user_id', 'session_id', 'cart_data', 'shipping_address_id', 'billing_address_id',
        'shipping_method', 'payment_method', 'coupon_id', 'wallet_used', 'partial_payment',
        'order_notes', 'invoice_id', 'status'
    ];
    protected $casts = ['wallet_used' => 'decimal:2', 'partial_payment' => 'boolean'];
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function coupon(): BelongsTo { return $this->belongsTo(Coupon::class); }
    public function shippingAddress(): BelongsTo { return $this->belongsTo(Address::class, 'shipping_address_id'); }
    public function billingAddress(): BelongsTo { return $this->belongsTo(Address::class, 'billing_address_id'); }
}
