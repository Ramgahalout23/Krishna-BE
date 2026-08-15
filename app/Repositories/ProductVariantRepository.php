<?php

namespace App\Repositories;

use App\Models\ProductVariant;

class ProductVariantRepository extends BaseRepository
{
    protected function modelClass(): string
    {
        return ProductVariant::class;
    }

    public function findByProduct(string $productId): \Illuminate\Database\Eloquent\Collection
    {
        return ProductVariant::where('product_id', $productId)
            ->select('id', 'product_id', 'name', 'sku', 'attributes', 'price', 'quantity', 'images')
            ->get();
    }

    public function findBySku(string $sku): ?ProductVariant
    {
        return ProductVariant::where('sku', $sku)->first();
    }

    public function getLowStock(int $threshold = 5): \Illuminate\Database\Eloquent\Collection
    {
        return ProductVariant::with('product')
            ->where('quantity', '<=', $threshold)
            ->where('quantity', '>', 0)
            ->get();
    }
}
