<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Product extends Model
{
    /**
     * Scope to only include published products.
     */
    public function scopePublished($query)
    {
        return $query->where('status', 'PUBLISHED');
    }

    use HasUuids;

    protected $fillable = [
        'name', 'slug', 'description', 'short_description', 'price', 'old_price', 'cost',
        'badge', 'quantity', 'sku', 'barcode', 'weight', 'category_id', 'brand_id',
        'status', 'is_featured', 'is_new', 'is_sale', 'is_digital', 'seo_title',
        'seo_description', 'seo_keywords', 'tags', 'view_count', 'rating', 'review_count', 'sold_count',
        'hover_image_url'
    ];

    protected $casts = [
        'is_featured' => 'boolean', 'is_new' => 'boolean', 'is_sale' => 'boolean',
        'is_digital' => 'boolean', 'price' => 'decimal:2', 'old_price' => 'decimal:2',
        'cost' => 'decimal:2', 'weight' => 'decimal:3', 'rating' => 'decimal:2',
        'sold_count' => 'integer',
    ];

    public function category(): BelongsTo { return $this->belongsTo(Category::class); }
    public function brand(): BelongsTo { return $this->belongsTo(Brand::class); }
    public function images(): HasMany { return $this->hasMany(ProductImage::class); }
    public function variants(): HasMany { return $this->hasMany(ProductVariant::class); }
    public function attributes(): HasMany { return $this->hasMany(ProductAttribute::class); }
    public function inventory(): HasOne { return $this->hasOne(Inventory::class); }
    public function reviews(): HasMany { return $this->hasMany(Review::class); }
    public function cartItems(): HasMany { return $this->hasMany(CartItem::class); }
    public function wishlistItems(): HasMany { return $this->hasMany(WishlistItem::class); }
    public function orderItems(): HasMany { return $this->hasMany(OrderItem::class); }
    public function relatedProducts(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'product_relations', 'product_id', 'related_product_id');
    }

    public function curatedLooks(): BelongsToMany
    {
        return $this->belongsToMany(CuratedLook::class, 'curated_look_product')
                    ->withPivot('display_order')
                    ->withTimestamps();
    }
}
