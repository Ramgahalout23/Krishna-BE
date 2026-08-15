<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductRelation extends Model
{
    use HasUuids;
    protected $fillable = ['product_id', 'related_product_id', 'relation_type'];
    public function product(): BelongsTo { return $this->belongsTo(Product::class, 'product_id'); }
    public function relatedProduct(): BelongsTo { return $this->belongsTo(Product::class, 'related_product_id'); }
}
