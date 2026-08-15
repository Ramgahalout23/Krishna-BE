<?php

use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Backfill products.hover_image_url from the second product image
     * (display_order = 2) for all products that have at least 2 images.
     *
     * This migration is idempotent — it only updates products where
     * hover_image_url is currently NULL and a second image exists.
     */
    public function up(): void
    {
        // Find products that have at least 2 images but no hover_image_url set
        $products = Product::whereNull('hover_image_url')
            ->whereHas('images', function ($q) {
                $q->where('display_order', 2);
            })
            ->with(['images' => function ($q) {
                $q->where('display_order', 2)->orderBy('display_order');
            }])
            ->get();

        $updated = 0;

        foreach ($products as $product) {
            $secondImage = $product->images->first();
            if ($secondImage && !empty($secondImage->url)) {
                $product->update(['hover_image_url' => $secondImage->url]);
                $updated++;
            }
        }

        // Fallback: products with 2+ images but no display_order=2 set
        // Query distinct product_ids from product_images that have at least 2 images per product
        $productIdsWithMultipleImages = \Illuminate\Support\Facades\DB::table('product_images')
            ->select('product_id')
            ->groupBy('product_id')
            ->havingRaw('COUNT(*) >= 2')
            ->pluck('product_id');

        $fallbackProducts = Product::whereNull('hover_image_url')
            ->whereIn('id', $productIdsWithMultipleImages)
            ->get();

        foreach ($fallbackProducts as $product) {
            $images = ProductImage::where('product_id', $product->id)
                ->orderBy('display_order')
                ->orderBy('created_at')
                ->get();

            if ($images->count() >= 2 && !empty($images[1]->url)) {
                $product->update(['hover_image_url' => $images[1]->url]);
                $updated++;
            }
        }

        if ($updated > 0) {
            echo "✅ Backfilled hover_image_url for {$updated} products.\n";
        } else {
            echo "ℹ️  No products needed hover_image_url backfill.\n";
        }
    }

    /**
     * Reverse: clear the hover_image_url for products that were backfilled.
     */
    public function down(): void
    {
        $count = Product::whereNotNull('hover_image_url')->count();
        // We don't blindly clear all since some may have been set manually.
        // Only clear records where hover_image_url matches the second product image URL.
        $cleared = 0;
        $products = Product::whereNotNull('hover_image_url')->get();

        foreach ($products as $product) {
            $secondImage = ProductImage::where('product_id', $product->id)
                ->where('display_order', 2)
                ->first();

            if ($secondImage && $product->hover_image_url === $secondImage->url) {
                $product->update(['hover_image_url' => null]);
                $cleared++;
            }
        }

        if ($cleared > 0) {
            echo "↩️  Reverted hover_image_url for {$cleared} products.\n";
        } else {
            echo "ℹ️  No hover_image_url records to revert.\n";
        }
    }
};
