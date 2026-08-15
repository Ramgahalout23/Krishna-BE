<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductVariant extends Model
{
    use HasUuids;
    protected $fillable = ['product_id', 'name', 'sku', 'attributes', 'price', 'quantity', 'images'];
    protected $casts = ['attributes' => 'json', 'images' => 'json', 'price' => 'decimal:2'];
    public function product(): BelongsTo { return $this->belongsTo(Product::class); }
    public function stockMovements(): HasMany { return $this->hasMany(VariantStockMovement::class, 'variant_id'); }
}
