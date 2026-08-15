<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductImage extends Model
{
    use HasUuids;
    protected $fillable = ['product_id', 'url', 'alt', 'display_order'];
    public function product(): BelongsTo { return $this->belongsTo(Product::class); }
}
