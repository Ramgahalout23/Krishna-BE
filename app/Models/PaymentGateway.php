<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class PaymentGateway extends Model
{
    use HasUuids;
    protected $fillable = ['name', 'provider', 'api_key', 'api_secret', 'mode', 'is_active', 'metadata'];
    protected $casts = ['is_active' => 'boolean'];
}
