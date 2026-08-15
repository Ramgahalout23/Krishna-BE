<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class CuratedLook extends Model
{
    use HasUuids;

    protected $fillable = [
        'name',
        'slug',
        'image_url',
        'description',
        'display_order',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'display_order' => 'integer',
    ];

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'curated_look_product')
                    ->withPivot('display_order')
                    ->withTimestamps()
                    ->orderBy('curated_look_product.display_order');
    }
}
