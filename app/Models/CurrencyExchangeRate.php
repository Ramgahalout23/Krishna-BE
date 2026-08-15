<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class CurrencyExchangeRate extends Model
{
    use HasUuids;
    protected $fillable = ['code', 'name', 'symbol', 'exchange_rate', 'is_default', 'is_active', 'last_synced_at'];
    protected $casts = ['exchange_rate' => 'decimal:6', 'is_default' => 'boolean', 'is_active' => 'boolean', 'last_synced_at' => 'datetime'];
}
