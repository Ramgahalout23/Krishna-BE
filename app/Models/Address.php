<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Address extends Model
{
    use HasUuids;
    protected $fillable = ['user_id', 'type', 'first_name', 'last_name', 'phone_number', 'address_line1', 'address_line2', 'city', 'state', 'zip_code', 'country', 'is_default'];
    protected $casts = ['is_default' => 'boolean'];

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function shippingOrders(): HasMany { return $this->hasMany(Order::class, 'shipping_address_id'); }
    public function billingOrders(): HasMany { return $this->hasMany(Order::class, 'billing_address_id'); }
}
