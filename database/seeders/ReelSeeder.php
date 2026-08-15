<?php

namespace Database\Seeders;

use App\Models\Reel;
use App\Models\Product;
use Illuminate\Database\Seeder;

class ReelSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('🎬 Seeding reels...');

        $reelData = [
            [
                'title' => 'Summer Collection 2024',
                'description' => 'Light fabrics and breezy fits for the season ahead. Shop the latest drops.',
                'video_url' => 'https://interactive-examples.mdn.mozilla.net/media/cc0-videos/flower.mp4',
                'image_url' => 'https://images.unsplash.com/photo-1523381210434-271e8be1f52b?q=80&w=600',
                'link_url' => '/products?category=oversized-collection',
                'display_order' => 0,
                'is_active' => true,
            ],
            [
                'title' => 'Streetwear Icons',
                'description' => 'Bold graphics and oversized silhouettes that define urban style.',
                'video_url' => 'https://test-videos.co.uk/vids/bigbuckbunny/mp4/h264/720/Big_Buck_Bunny_720_10s_1MB.mp4',
                'image_url' => 'https://images.unsplash.com/photo-1572495641004-28421ae7c9d2?q=80&w=600',
                'link_url' => '/products?category=legendary-series',
                'display_order' => 1,
                'is_active' => true,
            ],
            [
                'title' => 'Minimal Luxe Edit',
                'description' => 'Clean lines. Subtle details. Understated elegance for everyday.',
                'video_url' => 'https://test-videos.co.uk/vids/bigbuckbunny/mp4/h264/720/Big_Buck_Bunny_720_10s_2MB.mp4',
                'image_url' => 'https://images.unsplash.com/photo-1552374196-1ab2a1c593e8?q=80&w=600',
                'link_url' => '/products',
                'display_order' => 2,
                'is_active' => true,
            ],
            [
                'title' => 'New Drops Available Now',
                'description' => 'The freshest styles just landed — be the first to wear them.',
                'video_url' => 'https://test-videos.co.uk/vids/bigbuckbunny/mp4/h264/720/Big_Buck_Bunny_720_10s_5MB.mp4',
                'image_url' => 'https://images.unsplash.com/photo-1529374255404-311a2a4f1fd9?q=80&w=600',
                'link_url' => '/products/section/new-arrivals',
                'display_order' => 3,
                'is_active' => true,
            ],
            [
                'title' => 'Oversized Tees — The Edit',
                'description' => 'Drop shoulder, boxy fit, premium cotton. The ultimate comfort wear.',
                'video_url' => 'https://interactive-examples.mdn.mozilla.net/media/cc0-videos/flower.mp4',
                'image_url' => 'https://images.unsplash.com/photo-1583743814966-8936f5b7be1a?q=80&w=600',
                'link_url' => '/products?category=oversized-collection',
                'display_order' => 4,
                'is_active' => true,
            ],
        ];

        $products = Product::pluck('id')->toArray();

        foreach ($reelData as $reel) {
            $reelModel = Reel::create($reel);

            // Attach random products if any exist
            if (!empty($products)) {
                $syncData = [];
                $selected = (array)array_rand(array_flip($products), min(3, count($products)));
                foreach ($selected as $order => $productId) {
                    $syncData[$productId] = ['display_order' => $order];
                }
                $reelModel->products()->sync($syncData);
            }
        }
        $this->command->info('   ✓ ' . count($reelData) . ' reels created');
    }
}
