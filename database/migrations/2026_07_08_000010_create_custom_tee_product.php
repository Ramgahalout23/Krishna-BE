<?php

use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Inventory;
use App\Models\Category;
use App\Models\Brand;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * UUID for the Custom T-Shirt product — shared with the frontend
     * so custom design items reference a real product in the database.
     */
    const CUSTOM_TEE_PRODUCT_ID = 'c5b8e3f0-3a1c-4b7e-9d6f-1a2b3c4d5e6f';

    public function up(): void
    {
        // Skip if the product already exists (e.g. re-running after rollback)
        if (Product::where('id', self::CUSTOM_TEE_PRODUCT_ID)->exists()) {
            return;
        }

        // Find or create the "Men's Tees" category
        $category = Category::firstOrCreate(
            ['slug' => 'mens-tees'],
            ['name' => "Men's Tees", 'description' => 'Premium everyday t-shirts', 'is_active' => true]
        );

        // Find any brand (first one, or create a placeholder)
        $brand = Brand::first();
        if (!$brand) {
            $brand = Brand::create([
                'name' => 'THREVOLT',
                'slug' => 'threvolt',
                'description' => 'In-house premium brand',
            ]);
        }

        // Price (₹699 = BASE_PRICE 499 + CUSTOM_DESIGN_FEE 200) is also
        // computed client-side. During checkout, the submitted price
        // is trusted for this product since it uses a dedicated product ID.
        $product = Product::create([
            'id'                => self::CUSTOM_TEE_PRODUCT_ID,
            'name'              => 'Custom T-Shirt Design',
            'slug'              => 'custom-t-shirt-design',
            'description'       => 'Upload your own artwork, pick colors and size — we\'ll print your custom design on a premium quality t-shirt.',
            'short_description' => 'Design your own custom printed t-shirt.',
            'price'             => 699, // BASE_PRICE (499) + CUSTOM_DESIGN_FEE (200)
            'old_price'         => null,
            'cost'              => 280,
            'badge'             => 'Custom',
            'quantity'          => 9999, // effectively unlimited
            'sku'               => 'CUSTOM-TEE-001',
            'category_id'       => $category->id,
            'brand_id'          => $brand->id,
            'status'            => 'PUBLISHED',
            'is_featured'       => false,
            'is_new'            => false,
            'is_digital'        => false,
            'seo_title'         => 'Custom T-Shirt Design | Design Your Own Tee',
            'seo_description'   => 'Upload your artwork and design your own custom printed t-shirt. Premium quality with free shipping.',
            'rating'            => 4.5,
            'review_count'      => 0,
        ]);

        // Add a placeholder image
        ProductImage::create([
            'product_id' => $product->id,
            'url'        => 'https://images.unsplash.com/photo-1576566588028-4147f3842f27?w=800&q=80',
            'alt'        => 'Custom T-Shirt Design',
            'display_order' => 1,
        ]);

        // Create inventory entry
        Inventory::create([
            'product_id'         => $product->id,
            'total_quantity'     => 9999,
            'available_quantity' => 9999,
            'reserved_quantity'  => 0,
            'damaged_quantity'   => 0,
        ]);
    }

    public function down(): void
    {
        Product::where('id', self::CUSTOM_TEE_PRODUCT_ID)->delete();
    }
};
