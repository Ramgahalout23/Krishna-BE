<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Discount extends Model
{
    use HasUuids;
    protected $fillable = ['name', 'discount_type', 'discount_value', 'applicable_type', 'category_id', 'is_active', 'start_date', 'expiry_date'];
    protected $casts = ['discount_value' => 'decimal:2', 'is_active' => 'boolean', 'start_date' => 'datetime', 'expiry_date' => 'datetime'];
    public function category(): BelongsTo { return $this->belongsTo(Category::class); }
}
