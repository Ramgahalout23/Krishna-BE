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
            $users = [User::where('email', 'customer@dotoydo.com')->value('id')];
        }

        $reviewData = [
            ['rating' => 5, 'title' => 'Amazing quality!', 'comment' => 'Exactly as shown in the pictures and my kid absolutely loves it. Highly recommended!'],
            ['rating' => 4, 'title' => 'Great value', 'comment' => 'Good quality product at an affordable price.'],
            ['rating' => 5, 'title' => 'Best purchase ever', 'comment' => 'Arrived fast and well packed. Worth every rupee!'],
            ['rating' => 5, 'title' => 'Worth every rupee', 'comment' => 'Premium quality. Color is exactly as shown.'],
            ['rating' => 4, 'title' => 'Awesome product', 'comment' => 'Very happy with the purchase. Everyone asks where I got it from.'],
            ['rating' => 4, 'title' => 'Nice buy', 'comment' => 'Great product and quick delivery.'],
            ['rating' => 5, 'title' => 'Best value pack', 'comment' => 'Ordered two and both are fantastic at an amazing price.'],
            ['rating' => 4, 'title' => 'Great gift', 'comment' => 'Perfect gift for my nephew. Packaging was lovely.'],
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
