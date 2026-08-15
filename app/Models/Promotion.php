<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Promotion extends Model
{
    use HasUuids;
    protected $fillable = [
        'title', 'description', 'type', 'image_url', 'link_url', 'discount', 'status',
        'start_date', 'end_date', 'priority', 'is_active', 'show_on_mobile', 'show_on_desktop',
        'min_purchase', 'max_discount', 'coupon_code', 'created_by',
        'offer_badge', 'offer_highlight', 'offer_tagline', 'offer_theme', 'auto_apply'
    ];
    protected $casts = [
        'discount' => 'decimal:2', 'min_purchase' => 'decimal:2', 'max_discount' => 'decimal:2',
        'is_active' => 'boolean', 'show_on_mobile' => 'boolean', 'show_on_desktop' => 'boolean',
        'auto_apply' => 'boolean',
        'start_date' => 'datetime', 'end_date' => 'datetime'
    ];

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'promotion_product')
            ->withTimestamps();
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'promotion_category')
            ->withTimestamps();
    }
}
