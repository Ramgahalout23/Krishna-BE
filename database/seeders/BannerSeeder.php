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
                'title' => "IN TREND\nOVERSIZED TEE", 'subtitle' => 'GET 10% OFF on your first order — use code WELCOME20',
                'tagline' => 'Featured', 'description' => 'Premium 240 GSM cotton oversized tee. Drop shoulder. Made for legends.',
                'image_url' => 'https://images.unsplash.com/photo-1552374196-1ab2a1c593e8?q=80&w=2000',
                'link_url' => '/products?category=oversized-collection', 'type' => 'HERO', 'position' => 1,
                'button_text' => 'Shop Now', 'button_link' => '/products?category=oversized-collection', 'cta' => 'Shop Now',
                'align' => 'left', 'text_dark' => false,
            ],
            [
                'title' => "FLAT 50% OFF\nGRAPHIC TEES", 'subtitle' => 'Anime, vintage, abstract — express yourself without saying a word.',
                'tagline' => 'Limited Time', 'description' => 'Limited time offer on selected items',
                'image_url' => 'https://images.unsplash.com/photo-1529374255404-311a2a4f1fd9?q=80&w=2000',
                'link_url' => '/products?category=legendary-series', 'type' => 'HERO', 'position' => 2,
                'button_text' => 'Shop Now', 'button_link' => '/products?category=legendary-series', 'cta' => 'Shop Now',
                'align' => 'center', 'text_dark' => false,
            ],
            [
                'title' => "NEW DROP\nPREMIUM POLO", 'subtitle' => 'Pique cotton. Ribbed collar. Classic comfort redefined.',
                'tagline' => 'Just Launched', 'description' => 'Pique cotton polo with ribbed collar. Premium casual-formal style.',
                'image_url' => 'https://images.unsplash.com/photo-1581655353564-df123a1eb820?q=80&w=2000',
                'link_url' => '/products?category=official-merchandise', 'type' => 'HERO', 'position' => 3,
                'button_text' => 'Shop Now', 'button_link' => '/products?category=official-merchandise', 'cta' => 'Shop Now',
                'align' => 'right', 'text_dark' => false,
            ],
            [
                'title' => 'Flash Sale - Up to 70% Off', 'description' => 'Limited time offer on selected items',
                'image_url' => 'https://images.unsplash.com/photo-1618354691373-d851c5c3a990?q=80&w=1200',
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
