<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class TaxRate extends Model
{
    use HasUuids;
    protected $fillable = ['name', 'rate', 'type', 'country', 'state', 'city', 'zip_pattern', 'is_active', 'priority', 'description'];
    protected $casts = ['rate' => 'decimal:5', 'is_active' => 'boolean'];
}
