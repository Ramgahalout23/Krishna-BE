<?php

namespace Database\Seeders;

use App\Models\Banner;
use Illuminate\Database\Seeder;

class BannerSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('🖼️  Seeding banners...');
        $banners = [
            [
                'title' => "PLAY\nEVERY DAY", 'subtitle' => 'Toys & games for every age — plush buddies, RC racers, puzzles & more.',
                'tagline' => 'New Season', 'description' => 'Fresh toy drops from the dotoydo mart.',
                'image_url' => 'https://images.pexels.com/photos/32362463/pexels-photo-32362463.jpeg?auto=compress&cs=tinysrgb&w=2000',
                'link_url' => '/products?category=toys-games', 'type' => 'HERO', 'position' => 1,
                'button_text' => 'Shop Toys', 'button_link' => '/products?category=toys-games', 'cta' => 'Shop Toys',
                'align' => 'left', 'text_dark' => true,
            ],
            [
                'title' => "GIFT SALE\nUP TO 40% OFF", 'subtitle' => 'Surprise someone special — gifts for birthdays, festivals & every happy moment.',
                'tagline' => 'Limited Time', 'description' => 'Limited time offer on selected items',
                'image_url' => 'https://images.pexels.com/photos/264985/pexels-photo-264985.jpeg?auto=compress&cs=tinysrgb&w=2000',
                'link_url' => '/products?sale=true', 'type' => 'HERO', 'position' => 2,
                'button_text' => 'Shop Sale', 'button_link' => '/products?sale=true', 'cta' => 'Shop Sale',
                'align' => 'center', 'text_dark' => false,
            ],
            [
                'title' => "TECH\nFOR LESS", 'subtitle' => 'Earbuds, speakers, smart bands & power banks — everyday tech at mart prices.',
                'tagline' => 'Just Launched', 'description' => 'Everyday electronics for work, home & travel.',
                'image_url' => 'https://images.pexels.com/photos/33242733/pexels-photo-33242733.jpeg?auto=compress&cs=tinysrgb&w=2000',
                'link_url' => '/products?category=electronics', 'type' => 'HERO', 'position' => 3,
                'button_text' => 'Shop Electronics', 'button_link' => '/products?category=electronics', 'cta' => 'Shop Electronics',
                'align' => 'right', 'text_dark' => true,
            ],
            [
                'title' => 'Flash Sale - Up to 60% Off', 'description' => 'Limited time offer across toys, home & sports',
                'image_url' => 'https://images.pexels.com/photos/6219117/pexels-photo-6219117.jpeg?auto=compress&cs=tinysrgb&w=1200',
                'link_url' => '/products?sale=true', 'type' => 'SALE', 'position' => 1,
                'button_text' => 'Shop Now', 'button_link' => '/products?sale=true',
            ],
        ];

        foreach ($banners as $b) {
            Banner::create(array_merge($b, ['is_active' => true, 'start_date' => now(), 'end_date' => now()->addYear()]));
        }
        $this->command->info('   ✓ Banners created');
    }
}
