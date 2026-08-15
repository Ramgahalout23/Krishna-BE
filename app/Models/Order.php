<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Order extends Model
{
    use HasUuids;
    protected $fillable = [
        'order_number', 'user_id', 'shipping_address_id', 'billing_address_id',
        'subtotal', 'tax', 'shipping_cost', 'discount', 'total', 'coupon_id',
        'status', 'notes', 'admin_notes', 'label_url',
        'confirmed_at', 'processing_at', 'shipped_at', 'delivered_at', 'cancelled_at'
    ];
    protected $casts = [
        'subtotal' => 'decimal:2', 'tax' => 'decimal:2', 'shipping_cost' => 'decimal:2',
        'discount' => 'decimal:2', 'total' => 'decimal:2',
        'confirmed_at' => 'datetime', 'processing_at' => 'datetime',
        'shipped_at' => 'datetime', 'delivered_at' => 'datetime', 'cancelled_at' => 'datetime',
    ];

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function shippingAddress(): BelongsTo { return $this->belongsTo(Address::class, 'shipping_address_id'); }
    public function billingAddress(): BelongsTo { return $this->belongsTo(Address::class, 'billing_address_id'); }
    public function items(): HasMany { return $this->hasMany(OrderItem::class); }
    public function timeline(): HasMany { return $this->hasMany(OrderTimeline::class); }
    public function payment(): HasOne { return $this->hasOne(Payment::class); }
    public function shipping(): HasOne { return $this->hasOne(Shipping::class); }
    public function coupon(): BelongsTo { return $this->belongsTo(Coupon::class); }
    public function couponUsages(): HasMany { return $this->hasMany(CouponUsage::class); }
    public function refundRequests(): HasMany { return $this->hasMany(RefundRequest::class); }
    public function returnRequests(): HasMany { return $this->hasMany(ReturnRequest::class); }
    public function supportTickets(): HasMany { return $this->hasMany(SupportTicket::class); }
    public function customDesigns(): HasMany { return $this->hasMany(CustomDesign::class); }
}
