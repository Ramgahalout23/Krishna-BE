<?php

namespace Database\Seeders;

use App\Models\CuratedLook;
use Illuminate\Database\Seeder;

class CuratedLookSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('🎨 Seeding curated looks...');

        $curatedLooks = [
            [
                'name' => 'Summer Essentials',
                'slug' => 'summer-essentials',
                'description' => 'Light fabrics and breezy fits for the season ahead.',
                'image_url' => 'https://images.unsplash.com/photo-1523381210434-271e8be1f52b?q=80&w=800',
                'display_order' => 0,
                'is_active' => true,
            ],
            [
                'name' => 'Streetwear Icons',
                'slug' => 'streetwear-icons',
                'description' => 'Bold graphics and oversized silhouettes that define urban style.',
                'image_url' => 'https://images.unsplash.com/photo-1572495641004-28421ae7c9d2?q=80&w=800',
                'display_order' => 1,
                'is_active' => true,
            ],
            [
                'name' => 'Minimal Luxe',
                'slug' => 'minimal-luxe',
                'description' => 'Clean lines. Subtle details. Understated elegance for everyday.',
                'image_url' => 'https://images.unsplash.com/photo-1552374196-1ab2a1c593e8?q=80&w=800',
                'display_order' => 2,
                'is_active' => true,
            ],
            [
                'name' => 'New Arrivals',
                'slug' => 'new-arrivals',
                'description' => 'The freshest drops — be the first to wear them.',
                'image_url' => 'https://images.unsplash.com/photo-1529374255404-311a2a4f1fd9?q=80&w=800',
                'display_order' => 3,
                'is_active' => true,
            ],
        ];

        foreach ($curatedLooks as $look) {
            CuratedLook::create($look);
        }
        $this->command->info('   ✓ ' . count($curatedLooks) . ' curated looks created');
    }
}
