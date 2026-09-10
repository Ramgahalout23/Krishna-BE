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

class ProductSeeder extends Seeder
{
    /**
     * Run the ProductSeeder.
     *
     * Seeds categories, brands, and mart products (toys, electronics,
     * home & kitchen, sports & outdoors) with color variants.
     *
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
            ['key' => 'toys',    'name' => 'Toys & Games',            'slug' => 'toys-games',    'desc' => 'Soft toys, figures, blocks, puzzles & RC fun'],
            ['key' => 'elec',    'name' => 'Electronics & Gadgets',   'slug' => 'electronics',    'desc' => 'Earbuds, speakers, smart bands & power banks'],
            ['key' => 'home',    'name' => 'Home & Kitchen',          'slug' => 'home-kitchen',   'desc' => 'Cookware, storage, bedding & dinnerware'],
            ['key' => 'sports',  'name' => 'Sports & Outdoors',       'slug' => 'sports-outdoors', 'desc' => 'Fitness gear, yoga mats & racket sports'],
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
        $storeName = 'dotoydo';
        try {
            $val = Setting::where('module', 'SITE')->where('key', 'storeName')->value('value');
            if ($val) $storeName = $val;
        } catch (\Exception $e) {}
        $brand = Brand::firstOrCreate(
            ['slug' => 'dotoydo'],
            ['name' => $storeName, 'description' => 'In-house mart brand',
             'logo' => 'https://images.pexels.com/photos/6219117/pexels-photo-6219117.jpeg?auto=compress&cs=tinysrgb&w=200']
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

        $img = function (int $id, int $w = 800): string {
            return "https://images.pexels.com/photos/{$id}/pexels-photo-{$id}.jpeg?auto=compress&cs=tinysrgb&w={$w}";
        };

        $productDefs = [
            // ── Toys & Games ──
            [
                'name' => 'Cuddly Plush Teddy Bear — 32 cm',
                'slug' => 'cuddly-plush-teddy-bear-32cm',
                'cat' => 'toys',
                'price' => 499, 'old_price' => 799,
                'img' => $img(38807149), 'img2' => $img(35579568),
                'badge' => 'Bestseller',
                'rating' => 4.9, 'featured' => true, 'qty' => 60,
                'colors' => [
                    ['color' => 'Brown', 'price' => 499],
                    ['color' => 'Cream', 'price' => 499],
                    ['color' => 'Pink', 'price' => 529],
                ],
            ],
            [
                'name' => 'Stunt RC Car — 2.4 GHz Remote Control',
                'slug' => 'stunt-rc-car-2-4ghz',
                'cat' => 'toys',
                'price' => 1299, 'old_price' => 1999,
                'img' => $img(9227215), 'img2' => $img(34080822),
                'badge' => 'Trending',
                'rating' => 4.7, 'featured' => true, 'qty' => 40,
                'colors' => [
                    ['color' => 'Yellow', 'price' => 1299],
                    ['color' => 'Red', 'price' => 1299],
                    ['color' => 'Blue', 'price' => 1349],
                ],
            ],
            [
                'name' => 'Wooden Building Blocks — 120-Piece Set',
                'slug' => 'wooden-building-blocks-120pcs',
                'cat' => 'toys',
                'price' => 699, 'old_price' => 1099,
                'img' => $img(31061852), 'img2' => $img(6693306),
                'badge' => 'Educational',
                'rating' => 4.8, 'featured' => true, 'qty' => 75,
                'colors' => [],
            ],
            [
                'name' => 'Family Board Games Combo — Ludo & Snakes and Ladders',
                'slug' => 'family-board-games-combo',
                'cat' => 'toys',
                'price' => 449, 'old_price' => 699,
                'img' => $img(207924), 'img2' => $img(37983584),
                'badge' => 'Value',
                'rating' => 4.6, 'featured' => false, 'qty' => 100,
                'colors' => [],
            ],
            [
                'name' => 'Collectible Superhero Figure — Limited Drop',
                'slug' => 'collectible-superhero-figure',
                'cat' => 'toys',
                'price' => 899, 'old_price' => 1299,
                'img' => $img(8000986), 'img2' => $img(7258495),
                'badge' => 'Collector',
                'rating' => 4.8, 'featured' => false, 'qty' => 0,
                'colors' => [],
            ],
            [
                'name' => 'Speed Cube Puzzle — 3×3 Smooth Turn',
                'slug' => 'speed-cube-puzzle-3x3',
                'cat' => 'toys',
                'price' => 199, 'old_price' => 349,
                'img' => $img(19673918), 'img2' => $img(10285256),
                'badge' => null,
                'rating' => 4.5, 'featured' => false, 'qty' => 3,
                'colors' => [],
            ],

            // ── Electronics & Gadgets ──
            [
                'name' => 'Wireless Earbuds with Charging Case — BT 5.3',
                'slug' => 'wireless-earbuds-bt53',
                'cat' => 'elec',
                'price' => 899, 'old_price' => 1499,
                'img' => $img(33298188), 'img2' => $img(30981655),
                'badge' => 'Bestseller',
                'rating' => 4.6, 'featured' => true, 'qty' => 120,
                'colors' => [
                    ['color' => 'Black', 'price' => 899],
                    ['color' => 'White', 'price' => 899],
                ],
            ],
            [
                'name' => 'Portable Bluetooth Speaker — 10W Deep Bass',
                'slug' => 'portable-bluetooth-speaker-10w',
                'cat' => 'elec',
                'price' => 1199, 'old_price' => 1799,
                'img' => $img(34241799), 'img2' => $img(33693664),
                'badge' => 'Trending',
                'rating' => 4.5, 'featured' => false, 'qty' => 80,
                'colors' => [
                    ['color' => 'Black', 'price' => 1199],
                    ['color' => 'Camo', 'price' => 1249],
                ],
            ],
            [
                'name' => 'Smart Fitness Band — Heart Rate & SpO₂',
                'slug' => 'smart-fitness-band',
                'cat' => 'elec',
                'price' => 999, 'old_price' => 1599,
                'img' => $img(6846257), 'img2' => $img(1080751),
                'badge' => 'New',
                'rating' => 4.4, 'featured' => true, 'qty' => 90,
                'colors' => [
                    ['color' => 'Black', 'price' => 999],
                    ['color' => 'Teal', 'price' => 1049],
                ],
            ],
            [
                'name' => 'Power Bank 20000mAh — Fast Charging',
                'slug' => 'power-bank-20000mah',
                'cat' => 'elec',
                'price' => 1099, 'old_price' => 1699,
                'img' => $img(8137314), 'img2' => $img(10104284),
                'badge' => null,
                'rating' => 4.7, 'featured' => false, 'qty' => 110,
                'colors' => [
                    ['color' => 'Black', 'price' => 1099],
                    ['color' => 'Blue', 'price' => 1099],
                ],
            ],

            // ── Home & Kitchen ──
            [
                'name' => 'Non-Stick Cookware Set — 6-Piece',
                'slug' => 'non-stick-cookware-set-6pc',
                'cat' => 'home',
                'price' => 1999, 'old_price' => 2999,
                'img' => $img(4509047), 'img2' => $img(29462835),
                'badge' => 'Hot Deal',
                'rating' => 4.5, 'featured' => true, 'qty' => 35,
                'colors' => [],
            ],
            [
                'name' => 'Insulated Steel Water Bottle — 1 Litre',
                'slug' => 'insulated-steel-water-bottle-1l',
                'cat' => 'home',
                'price' => 649, 'old_price' => 999,
                'img' => $img(7879895), 'img2' => $img(7879832),
                'badge' => null,
                'rating' => 4.6, 'featured' => false, 'qty' => 150,
                'colors' => [
                    ['color' => 'Steel', 'price' => 649],
                    ['color' => 'Black', 'price' => 649],
                ],
            ],
            [
                'name' => 'Premium Cotton Bedsheet Set — King Size',
                'slug' => 'premium-cotton-bedsheet-king',
                'cat' => 'home',
                'price' => 999, 'old_price' => 1799,
                'img' => $img(28513849), 'img2' => $img(30618181),
                'badge' => null,
                'rating' => 4.4, 'featured' => false, 'qty' => 70,
                'colors' => [
                    ['color' => 'White', 'price' => 999],
                    ['color' => 'Beige', 'price' => 1049],
                ],
            ],
            [
                'name' => 'Food Storage Containers — Set of 8',
                'slug' => 'food-storage-containers-set-8',
                'cat' => 'home',
                'price' => 549, 'old_price' => 899,
                'img' => $img(4096909), 'img2' => $img(14206966),
                'badge' => 'Value',
                'rating' => 4.5, 'featured' => false, 'qty' => 130,
                'colors' => [],
            ],
            [
                'name' => 'Ceramic Dinnerware Set — 12-Piece',
                'slug' => 'ceramic-dinnerware-set-12pc',
                'cat' => 'home',
                'price' => 1599, 'old_price' => 2499,
                'img' => $img(14320923), 'img2' => $img(4234527),
                'badge' => 'New',
                'rating' => 4.7, 'featured' => true, 'qty' => 45,
                'colors' => [],
            ],

            // ── Sports & Outdoors ──
            [
                'name' => 'Premium Yoga Mat — 6mm Non-Slip',
                'slug' => 'premium-yoga-mat-6mm',
                'cat' => 'sports',
                'price' => 799, 'old_price' => 1299,
                'img' => $img(4793328), 'img2' => $img(4028197),
                'badge' => 'Bestseller',
                'rating' => 4.8, 'featured' => true, 'qty' => 95,
                'colors' => [
                    ['color' => 'Purple', 'price' => 799],
                    ['color' => 'Yellow', 'price' => 799],
                ],
            ],
            [
                'name' => 'Badminton Racket Set — 2 Rackets + 3 Shuttlecocks',
                'slug' => 'badminton-racket-set',
                'cat' => 'sports',
                'price' => 999, 'old_price' => 1499,
                'img' => $img(12887090), 'img2' => $img(32874231),
                'badge' => null,
                'rating' => 4.6, 'featured' => false, 'qty' => 60,
                'colors' => [],
            ],
            [
                'name' => 'Hex Dumbbell Pair — 5 kg',
                'slug' => 'hex-dumbbell-pair-5kg',
                'cat' => 'sports',
                'price' => 1399, 'old_price' => 1999,
                'img' => $img(29224210), 'img2' => $img(4959808),
                'badge' => null,
                'rating' => 4.7, 'featured' => false, 'qty' => 40,
                'colors' => [],
            ],
            [
                'name' => 'Resistance Bands Set — 5 Resistance Levels',
                'slug' => 'resistance-bands-set-5',
                'cat' => 'sports',
                'price' => 499, 'old_price' => 799,
                'img' => $img(8846345), 'img2' => $img(6516206),
                'badge' => 'Value',
                'rating' => 4.5, 'featured' => false, 'qty' => 140,
                'colors' => [],
            ],
        ];

        $created = [];

        foreach ($productDefs as $i => $def) {
            $prod = Product::create([
                'name'              => $def['name'],
                'slug'              => $def['slug'],
                'description'       => "{$def['name']}. Trusted quality, safe materials, and honest pricing — shop it at {$storeName}, your everyday mart.",
                'short_description' => substr("{$def['name']} — quality you can trust.", 0, 100),
                'price'             => $def['price'],
                'old_price'         => $def['old_price'],
                'cost'              => round($def['price'] * 0.45, 2),
                'quantity'          => $def['qty'],
                'sku'               => 'DOT-' . str_pad($i + 1, 4, '0', STR_PAD_LEFT),
                'category_id'       => $cats[$def['cat']],
                'brand_id'          => $brand->id,
                'status'            => 'PUBLISHED',
                'is_featured'       => $def['featured'],
                'badge'             => $def['badge'],
                'rating'            => $def['rating'],
                'review_count'      => 0,
                'seo_title'         => $def['name'] . ' | ' . $storeName,
                'seo_description'   => "Shop {$def['name']} at {$storeName}. Quality checked, safe for family, with easy returns and fast delivery.",
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

            // ── Variants (color options — one variant per color) ──
            if (!empty($def['colors'])) {
                $basePerColor = (int) floor($def['qty'] / count($def['colors']));
                foreach ($def['colors'] as $config) {
                    $vqty = $def['qty'] === 0
                        ? 0
                        : ($def['qty'] <= 5 ? max(0, min($def['qty'], rand(0, 2))) : max(1, $basePerColor));

                    ProductVariant::create([
                        'product_id' => $prod->id,
                        'name'       => $def['name'] . ' - ' . $config['color'],
                        'sku'        => $prod->sku . '-' . strtoupper(substr(str_replace(' ', '', $config['color']), 0, 4)),
                        'attributes' => json_encode(['color' => $config['color']]),
                        'price'      => $config['price'],
                        'quantity'   => $vqty,
                    ]);
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
