<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\Category;
use App\Models\Brand;
use App\Models\Inventory;
use App\Models\Setting;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProductSeeder extends Seeder
{
    /**
     * Run the ProductSeeder.
     *
     * Seeds categories, brands, and 15 products with variants.
     * Safe to run on both fresh and existing databases:
     * - Creates categories/brands only if they don't exist
     * - Truncates only product-related tables (not categories/brands)
     *
     * Usage: php artisan db:seed --class=ProductSeeder
     */
    public function run(): void
    {
        $this->command->info('🚀 ProductSeeder starting...');

        // ─────────────────────────────────────────────
        // 1. ENSURE CATEGORIES EXIST
        // ─────────────────────────────────────────────
        $this->command->info('📂 Ensuring categories...');
        $catDefs = [
            ['key' => 'tees',   'name' => "Men's Tees",       'slug' => 'mens-tees',       'desc' => 'Premium everyday t-shirts'],
            ['key' => 'polo',   'name' => 'Polo Shirts',       'slug' => 'polo-shirts',     'desc' => 'Classic polo shirts'],
            ['key' => 'hoodie', 'name' => 'Hoodies & Sweats',  'slug' => 'hoodies-sweats',  'desc' => 'Oversized hoodies and sweatshirts'],
            ['key' => 'outer',  'name' => 'Outerwear',          'slug' => 'outerwear',       'desc' => 'Jackets and layers'],
            ['key' => 'acc',    'name' => 'Accessories',        'slug' => 'accessories',     'desc' => 'Caps, bags & more'],
        ];
        $cats = [];
        foreach ($catDefs as $c) {
            $cat = Category::firstOrCreate(
                ['slug' => $c['slug']],
                ['name' => $c['name'], 'description' => $c['desc'], 'is_active' => true]
            );
            $cats[$c['key']] = $cat->id;
        }
        $this->command->info('   ✓ '.count($catDefs).' categories ready');

        // ─────────────────────────────────────────────
        // 2. ENSURE BRAND EXISTS
        // ─────────────────────────────────────────────
        $this->command->info('🏷️  Ensuring brand...');
        $storeName = 'THREVOLT';
        try {
            $val = Setting::where('module', 'SITE')->where('key', 'storeName')->value('value');
            if ($val) $storeName = $val;
        } catch (\Exception $e) {}
        $brand = Brand::firstOrCreate(
            ['slug' => 'threvolt'],
            ['name' => $storeName, 'description' => 'In-house premium brand',
             'logo' => 'https://images.unsplash.com/photo-1583743814966-8936f5b7be1a?q=80&w=200']
        );
        $this->command->info('   ✓ Brand ready');

        // ─────────────────────────────────────────────
        // 3. CLEAR OLD PRODUCT DATA
        // ─────────────────────────────────────────────
        $this->command->info('🗑️  Clearing old product data...');
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        ProductVariant::truncate();
        ProductImage::truncate();
        Inventory::truncate();
        Product::truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
        $this->command->info('   ✓ Cleared');

        // ─────────────────────────────────────────────
        // 4. PRODUCT DEFINITIONS
        // ─────────────────────────────────────────────
        $this->command->info('📦 Creating products...');

        $sizes = ['S', 'M', 'L', 'XL', 'XXL'];

        $productDefs = [
            // ── Men's Tees ──
            [
                'name' => 'Urban Oversized Tee — Black',
                'slug' => 'urban-oversized-tee-black',
                'cat' => 'tees',
                'price' => 599, 'old_price' => 999,
                'img' => 'https://images.unsplash.com/photo-1583743814966-8936f5b7be1a?q=80&w=800',
                'img2' => 'https://images.unsplash.com/photo-1572495641004-28421ae7c9d2?q=80&w=800',
                'badge' => 'Bestseller',
                'rating' => 4.8, 'reviews' => 0,
                'featured' => true, 'qty' => 120,
                'colors' => [
                    ['color' => 'Black', 'price' => 599],
                    ['color' => 'White', 'price' => 599],
                    ['color' => 'Navy', 'price' => 599],
                    ['color' => 'Grey', 'price' => 649],
                    ['color' => 'Olive', 'price' => 649],
                ],
            ],
            [
                'name' => 'Abstract Art Graphic Tee',
                'slug' => 'abstract-art-graphic-tee',
                'cat' => 'tees',
                'price' => 499, 'old_price' => 799,
                'img' => 'https://images.unsplash.com/photo-1503342217505-b0a15ec3261c?q=80&w=800',
                'img2' => 'https://images.unsplash.com/photo-1583743814966-8936f5b7be1a?q=80&w=800',
                'badge' => 'Trending',
                'rating' => 4.7, 'reviews' => 0,
                'featured' => true, 'qty' => 85,
                'colors' => [
                    ['color' => 'White', 'price' => 499],
                    ['color' => 'Black', 'price' => 499],
                    ['color' => 'Grey', 'price' => 549],
                ],
            ],
            [
                'name' => 'Essential Plain Tee — White',
                'slug' => 'essential-plain-tee-white',
                'cat' => 'tees',
                'price' => 349, 'old_price' => 599,
                'img' => 'https://images.unsplash.com/photo-1521572163474-6864f9cf17ab?q=80&w=800',
                'img2' => 'https://images.unsplash.com/photo-1618354691373-d851c5c3a990?q=80&w=800',
                'badge' => 'Value',
                'rating' => 4.5, 'reviews' => 0,
                'featured' => true, 'qty' => 200,
                'colors' => [
                    ['color' => 'White', 'price' => 349],
                    ['color' => 'Black', 'price' => 349],
                    ['color' => 'Grey', 'price' => 349],
                    ['color' => 'Navy', 'price' => 399],
                ],
            ],
            [
                'name' => 'Tokyo Streetwear Graphic Tee',
                'slug' => 'tokyo-streetwear-graphic-tee',
                'cat' => 'tees',
                'price' => 549, 'old_price' => 899,
                'img' => 'https://images.unsplash.com/photo-1576566588028-4147f3842f27?q=80&w=800',
                'img2' => 'https://images.unsplash.com/photo-1612714304529-e225036e6c4c?q=80&w=800',
                'badge' => 'New',
                'rating' => 4.9, 'reviews' => 0,
                'featured' => true, 'qty' => 65,
                'colors' => [
                    ['color' => 'Black', 'price' => 549],
                    ['color' => 'White', 'price' => 549],
                ],
            ],
            [
                'name' => 'Minimal Logo Tee — Navy',
                'slug' => 'minimal-logo-tee-navy',
                'cat' => 'tees',
                'price' => 449, 'old_price' => 749,
                'img' => 'https://images.unsplash.com/photo-1612714304529-e225036e6c4c?q=80&w=800',
                'img2' => 'https://images.unsplash.com/photo-1552374196-1ab2a1c593e8?q=80&w=800',
                'badge' => 'New',
                'rating' => 4.6, 'reviews' => 0,
                'featured' => false, 'qty' => 90,
                'colors' => [
                    ['color' => 'Navy', 'price' => 449],
                    ['color' => 'Black', 'price' => 449],
                    ['color' => 'Burgundy', 'price' => 479],
                ],
            ],

            // ── Polo Shirts ──
            [
                'name' => 'Classic Polo — Forest Green',
                'slug' => 'classic-polo-forest-green',
                'cat' => 'polo',
                'price' => 699, 'old_price' => 1199,
                'img' => 'https://images.unsplash.com/photo-1581655353564-df123a1eb820?q=80&w=800',
                'img2' => 'https://images.unsplash.com/photo-1529374255404-311a2a4f1fd9?q=80&w=800',
                'badge' => 'New',
                'rating' => 4.6, 'reviews' => 0,
                'featured' => false, 'qty' => 70,
                'colors' => [
                    ['color' => 'Green', 'price' => 699],
                    ['color' => 'Navy', 'price' => 699],
                    ['color' => 'White', 'price' => 699],
                ],
            ],

            // ── Hoodies & Sweats ──
            [
                'name' => 'Oversized Hoodie — Black',
                'slug' => 'oversized-hoodie-black',
                'cat' => 'hoodie',
                'price' => 1299, 'old_price' => 1999,
                'img' => 'https://images.unsplash.com/photo-1556821840-3a63f95609a7?q=80&w=800',
                'img2' => 'https://images.unsplash.com/photo-1578768079052-aa76e52ff62e?q=80&w=800',
                'badge' => 'Bestseller',
                'rating' => 4.7, 'reviews' => 0,
                'featured' => true, 'qty' => 50,
                'colors' => [
                    ['color' => 'Black', 'price' => 1299],
                    ['color' => 'Grey', 'price' => 1299],
                    ['color' => 'Navy', 'price' => 1349],
                ],
            ],
            [
                'name' => 'Full-Zip Sweatshirt — Cream',
                'slug' => 'full-zip-sweatshirt-cream',
                'cat' => 'hoodie',
                'price' => 999, 'old_price' => 1599,
                'img' => 'https://images.unsplash.com/photo-1556909114-f6e7ad7d3136?q=80&w=800',
                'img2' => 'https://images.unsplash.com/photo-1578768079052-aa76e52ff62e?q=80&w=800',
                'badge' => 'Trending',
                'rating' => 4.5, 'reviews' => 0,
                'featured' => true, 'qty' => 40,
                'colors' => [
                    ['color' => 'Cream', 'price' => 999],
                    ['color' => 'Black', 'price' => 999],
                ],
            ],

            // ── Outerwear ──
            [
                'name' => 'Bomber Jacket — Olive',
                'slug' => 'bomber-jacket-olive',
                'cat' => 'outer',
                'price' => 1899, 'old_price' => 2999,
                'img' => 'https://images.unsplash.com/photo-1591047139829-d91aecb6caea?q=80&w=800',
                'img2' => 'https://images.unsplash.com/photo-1551028719-00167b16eac5?q=80&w=800',
                'badge' => 'Limited',
                'rating' => 4.8, 'reviews' => 0,
                'featured' => true, 'qty' => 25,
                'colors' => [
                    ['color' => 'Olive', 'price' => 1899],
                    ['color' => 'Black', 'price' => 1899],
                ],
            ],
            [
                'name' => 'Denim Jacket — Light Wash',
                'slug' => 'denim-jacket-light-wash',
                'cat' => 'outer',
                'price' => 1599, 'old_price' => 2499,
                'img' => 'https://images.unsplash.com/photo-1576995853123-5a10305d93c0?q=80&w=800',
                'img2' => 'https://images.unsplash.com/photo-1544022613-e87ca75a784a?q=80&w=800',
                'badge' => null,
                'rating' => 4.4, 'reviews' => 0,
                'featured' => false, 'qty' => 35,
                'colors' => [
                    ['color' => 'Light Wash', 'price' => 1599],
                    ['color' => 'Medium Wash', 'price' => 1599],
                    ['color' => 'Dark Wash', 'price' => 1699],
                ],
            ],

            // ── Accessories ──
            [
                'name' => 'Premium Cap — Classic Black',
                'slug' => 'premium-cap-classic-black',
                'cat' => 'acc',
                'price' => 399, 'old_price' => 699,
                'img' => 'https://images.unsplash.com/photo-1523381210434-271e8be1f52b?q=80&w=800',
                'img2' => 'https://images.unsplash.com/photo-1556306535-0f09a537bee0?q=80&w=800',
                'badge' => 'Bestseller',
                'rating' => 4.5, 'reviews' => 0,
                'featured' => true, 'qty' => 150,
                'colors' => [
                    ['color' => 'Black', 'price' => 399],
                    ['color' => 'Navy', 'price' => 399],
                    ['color' => 'White', 'price' => 399],
                ],
            ],
            [
                'name' => 'Canvas Tote Bag — Natural',
                'slug' => 'canvas-tote-bag-natural',
                'cat' => 'acc',
                'price' => 349, 'old_price' => 599,
                'img' => 'https://images.unsplash.com/photo-1544816155-12df9643f363?q=80&w=800',
                'img2' => 'https://images.unsplash.com/photo-1523381210434-271e8be1f52b?q=80&w=800',
                'badge' => 'Eco',
                'rating' => 4.3, 'reviews' => 0,
                'featured' => false, 'qty' => 200,
                'colors' => [], // no size/color variants — simple product
            ],

            // ── SPECIAL TEST PRODUCTS ──

            // Out of stock (OOS badge test)
            [
                'name' => 'Classic Crew Neck Tee — OOS Test',
                'slug' => 'classic-crew-neck-oos-test',
                'cat' => 'tees',
                'price' => 399, 'old_price' => 699,
                'img' => 'https://images.unsplash.com/photo-1521572163474-6864f9cf17ab?q=80&w=800',
                'img2' => 'https://images.unsplash.com/photo-1618354691373-d851c5c3a990?q=80&w=800',
                'badge' => null,
                'rating' => 4.0, 'reviews' => 0,
                'featured' => false, 'qty' => 0,
                'colors' => [
                    ['color' => 'White', 'price' => 399],
                    ['color' => 'Black', 'price' => 399],
                ],
            ],

            // Low stock (Low stock warning test)
            [
                'name' => 'Striped Campus Tee — Blue — Low Stock',
                'slug' => 'striped-campus-tee-blue-low-stock',
                'cat' => 'tees',
                'price' => 499, 'old_price' => 849,
                'img' => 'https://images.unsplash.com/photo-1596755094514-f87e34085b2c?q=80&w=800',
                'img2' => 'https://images.unsplash.com/photo-1503342217505-b0a15ec3261c?q=80&w=800',
                'badge' => null,
                'rating' => 4.4, 'reviews' => 0,
                'featured' => false, 'qty' => 3,
                'colors' => [
                    ['color' => 'Blue', 'price' => 499],
                    ['color' => 'Navy', 'price' => 499],
                ],
            ],
        ];

        $created = [];

        foreach ($productDefs as $i => $def) {
            $prod = Product::create([
                'name'              => $def['name'],
                'slug'              => $def['slug'],
                'description'       => "Premium quality {$def['name']}. Crafted from high-grade materials for lasting comfort and style.",
                'short_description' => substr("Premium {$def['name']} — quality crafted.", 0, 100),
                'price'             => $def['price'],
                'old_price'         => $def['old_price'],
                'cost'              => round($def['price'] * 0.4, 2),
                'quantity'          => $def['qty'],
                'sku'               => 'SKU-' . str_pad($i + 1, 4, '0', STR_PAD_LEFT),
                'category_id'       => $cats[$def['cat']],
                'brand_id'          => $brand->id,
                'status'            => 'PUBLISHED',
                'is_featured'       => $def['featured'],
                'badge'             => $def['badge'],
                'rating'            => $def['rating'],
                'review_count'      => $def['reviews'],
                'seo_title'         => $def['name'] . ' | ' . $storeName,
                'seo_description'   => "Shop {$def['name']} at {$storeName}. Premium quality with free shipping.",
            ]);

            // Images
            ProductImage::create(['product_id' => $prod->id, 'url' => $def['img'],  'alt' => $def['name'],                  'display_order' => 1]);
            ProductImage::create(['product_id' => $prod->id, 'url' => $def['img2'], 'alt' => $def['name'] . ' — Alternate', 'display_order' => 2]);

            // Inventory
            Inventory::create([
                'product_id'         => $prod->id,
                'total_quantity'     => $def['qty'],
                'available_quantity' => $def['qty'],
                'reserved_quantity'  => 0,
                'damaged_quantity'   => 0,
            ]);

            // ── Variants ──
            if (!empty($def['colors'])) {
                foreach ($def['colors'] as $config) {
                    foreach ($sizes as $size) {
                        // Randomize variant stock within a range
                        if ($def['qty'] === 0) {
                            $vqty = 0;
                        } elseif ($def['qty'] <= 5) {
                            // Low stock product: spread thin
                            $vqty = max(0, min($def['qty'], rand(0, 2)));
                        } else {
                            $vqty = rand(3, max(4, (int)($def['qty'] / count($def['colors']) / count($sizes))));
                        }

                        ProductVariant::create([
                            'product_id' => $prod->id,
                            'name'       => $def['name'] . ' - ' . $config['color'] . ' - ' . $size,
                            'sku'        => $prod->sku . '-' . strtoupper(substr($config['color'], 0, 3)) . '-' . $size,
                            'attributes' => json_encode(['size' => $size, 'color' => $config['color']]),
                            'price'      => $config['price'],
                            'quantity'   => $vqty,
                        ]);
                    }
                }
            }

            $created[] = $prod;
        }

        $variantCount = ProductVariant::count();
        $this->command->info('   ✓ ' . count($created) . ' products created with ' . $variantCount . ' variants');

        // ─────────────────────────────────────────────
        // 5. SUMMARY
        // ─────────────────────────────────────────────
        $this->command->info('');
        $this->command->info('🎉 Product seeding complete!');
        $this->command->info('──────────────────────────');
        $this->command->info('   Products:  ' . count($created));
        $this->command->info('   Variants:  ' . $variantCount);
        $this->command->info('   Images:    ' . ProductImage::count());
        $this->command->info('   Categories: ' . Category::count());
        $this->command->info('');

        // Print product list for quick reference
        $this->command->info('📋 Product List:');
        $productsWithVariants = Product::with('variants')->whereIn('id', collect($created)->pluck('id'))->get();
        foreach ($productsWithVariants as $p) {
            $colorAttr = $p->variants->pluck('attributes')->filter()->map(fn($a) => $a['color'] ?? '')->unique()->filter()->implode(', ');
            $badge = $p->badge ? " [{$p->badge}]" : '';
            $qtyIcon = $p->quantity === 0 ? ' 🔴 OOS' : ($p->quantity <= 5 ? ' 🟡 LOW' : ' 🟢');
        $this->command->info("   {$qtyIcon} {$p->name}{$badge} — Qty: {$p->quantity} — Colors: {$colorAttr}");
    }

    // ─────────────────────────────────────────────
    // 6. WARM DASHBOARD CACHE
    // ─────────────────────────────────────────────
    $this->command->info('');
    $this->command->info('♨️  Warming dashboard cache...');
    try {
        Artisan::call('dashboard:warm-cache', ['--force' => true]);
        $this->command->info('   ✓ Dashboard cache warmed');
    } catch (\Throwable $e) {
        $this->command->warn('   ⚠ Cache warm skipped: ' . $e->getMessage());
    }
}
}
