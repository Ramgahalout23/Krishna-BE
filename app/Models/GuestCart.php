<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GuestCart extends Model
{
    use HasUuids;
    protected $fillable = ['session_id'];
    public function cartItems(): HasMany { return $this->hasMany(CartItem::class); }
}
