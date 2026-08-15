<?php

namespace Database\Seeders;

use App\Models\Review;
use App\Models\Product;
use App\Models\User;
use App\Repositories\ProductRepository;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;

class ReviewSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('⭐ Seeding reviews...');

        $products = Product::pluck('id')->toArray();
        $users = User::where('role', 'CUSTOMER')->pluck('id')->toArray();

        if (empty($products)) {
            $this->command->warn('   ⚠ No products found — skipping reviews');
            return;
        }
        if (empty($users)) {
            $users = [User::where('email', 'customer@threvolt.com')->value('id')];
        }

        $reviewData = [
            ['rating' => 5, 'title' => 'Amazing quality!', 'comment' => 'Fabric is super soft and the fit is perfect. Highly recommended!'],
            ['rating' => 4, 'title' => 'Great value', 'comment' => 'Good quality t-shirt at affordable price.'],
            ['rating' => 5, 'title' => 'Best purchase ever', 'comment' => 'The oversized fit is exactly what I wanted!'],
            ['rating' => 5, 'title' => 'Worth every rupee', 'comment' => 'Premium quality fabric. Color is exactly as shown.'],
            ['rating' => 4, 'title' => 'Awesome design', 'comment' => 'The graphic is amazing. Everyone asks about it.'],
            ['rating' => 4, 'title' => 'Nice polo', 'comment' => 'Great fit and fabric quality.'],
            ['rating' => 5, 'title' => 'Best value pack', 'comment' => 'Three high-quality tees at an amazing price.'],
            ['rating' => 4, 'title' => 'Great cap', 'comment' => 'Structured fit, looks premium.'],
        ];

        // Track which products get reviews to update ratings later
        $affectedProductIds = [];

        foreach ($reviewData as $r) {
            $productId = $products[array_rand($products)];
            Review::create([
                'product_id' => $productId,
                'user_id' => $users[array_rand($users)],
                'rating' => $r['rating'],
                'title' => $r['title'],
                'comment' => $r['comment'],
                'is_verified' => true, 'is_moderated' => true,
            ]);
            $affectedProductIds[$productId] = true;
        }

        // Update rating & review_count for all affected products
        $productRepo = app(ProductRepository::class);
        foreach (array_keys($affectedProductIds) as $pid) {
            $productRepo->updateProductRating($pid);
        }

        // Invalidate all product caches so the new ratings appear immediately
        $version = Cache::get('products_cache_version', 0);
        Cache::forever('products_cache_version', $version + 1);
        Cache::forget('homepage_all');

        $this->command->info('   ✓ Reviews created — ratings updated for ' . count($affectedProductIds) . ' products, caches cleared');
    }
}
