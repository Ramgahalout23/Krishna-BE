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

        $img = fn(int $id, int $w = 600): string => "https://images.pexels.com/photos/{$id}/pexels-photo-{$id}.jpeg?auto=compress&cs=tinysrgb&w={$w}";

        $reelData = [
            [
                'title' => 'Top Toy Picks This Week',
                'description' => 'Plush buddies, RC cars and building sets kids are asking for. Watch, tap, and add to cart.',
                'video_url' => 'https://interactive-examples.mdn.mozilla.net/media/cc0-videos/flower.mp4',
                'image_url' => $img(38807149),
                'link_url' => '/products?category=toys-games',
                'display_order' => 0,
                'is_active' => true,
            ],
            [
                'title' => 'Gadgets That Make Life Easier',
                'description' => 'Earbuds, smart bands and power banks — everyday tech at honest prices.',
                'video_url' => 'https://test-videos.co.uk/vids/bigbuckbunny/mp4/h264/720/Big_Buck_Bunny_720_10s_1MB.mp4',
                'image_url' => $img(33298188),
                'link_url' => '/products?category=electronics',
                'display_order' => 1,
                'is_active' => true,
            ],
            [
                'title' => 'Playtime, Unboxed',
                'description' => 'Fresh toy drops just landed — be the first to grab them.',
                'video_url' => 'https://test-videos.co.uk/vids/bigbuckbunny/mp4/h264/720/Big_Buck_Bunny_720_10s_2MB.mp4',
                'image_url' => $img(8409851),
                'link_url' => '/products',
                'display_order' => 2,
                'is_active' => true,
            ],
            [
                'title' => 'Home Comforts, Simplified',
                'description' => 'Cozy bedding, clever storage and cookware for every kitchen.',
                'video_url' => 'https://test-videos.co.uk/vids/bigbuckbunny/mp4/h264/720/Big_Buck_Bunny_720_10s_5MB.mp4',
                'image_url' => $img(28513849),
                'link_url' => '/products?category=home-kitchen',
                'display_order' => 3,
                'is_active' => true,
            ],
            [
                'title' => 'Move More, Shop Smart',
                'description' => 'Yoga mats, rackets and home gym gear for an active everyday.',
                'video_url' => 'https://interactive-examples.mdn.mozilla.net/media/cc0-videos/flower.mp4',
                'image_url' => $img(4793328),
                'link_url' => '/products?category=sports-outdoors',
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
