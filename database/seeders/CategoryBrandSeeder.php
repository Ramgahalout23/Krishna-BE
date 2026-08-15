<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Brand;
use App\Models\Setting;
use Illuminate\Database\Seeder;

class CategoryBrandSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('📂 Seeding categories...');

        $storeName = 'THREVOLT';
        try {
            $val = Setting::where('module', 'SITE')->where('key', 'storeName')->value('value');
            if ($val) $storeName = $val;
        } catch (\Exception $e) {}

        $catData = [
            ['name' => 'Legendary Series', 'slug' => 'legendary-series', 'description' => 'Premium t-shirt collections with iconic designs — made for legends.', 'image' => 'https://images.unsplash.com/photo-1503342217505-b0a15ec3261c?q=80&w=400'],
            ['name' => 'Official Merchandise', 'slug' => 'official-merchandise', 'description' => 'Licensed and official merchandise — movies, music, sports & more.', 'image' => 'https://images.unsplash.com/photo-1529374255404-311a2a4f1fd9?q=80&w=400'],
            ['name' => 'Oversized Collection', 'slug' => 'oversized-collection', 'description' => 'Premium oversized fit t-shirts — drop shoulder, boxy & classic cuts.', 'image' => 'https://images.unsplash.com/photo-1583743814966-8936f5b7be1a?q=80&w=400'],
            ['name' => 'Accessories', 'slug' => 'accessories', 'description' => 'Complete your look — caps, bags, phone cases & more.', 'image' => 'https://images.unsplash.com/photo-1523381210434-271e8be1f52b?q=80&w=400'],
        ];
        $categories = [];
        foreach ($catData as $c) {
            $cat = Category::create($c + ['seo_title' => $c['name'] . ' | ' . $storeName, 'seo_description' => $c['description'], 'is_active' => true]);
            $categories[$c['slug']] = $cat->id;
        }

        $subData = [
            ['parent' => 'legendary-series', 'children' => [
                ['name' => "Men's Legendary", 'slug' => 'mens-legendary'],
                ['name' => "Women's Legendary", 'slug' => 'womens-legendary'],
                ['name' => 'Unisex Legendary', 'slug' => 'unisex-legendary'],
            ]],
            ['parent' => 'official-merchandise', 'children' => [
                ['name' => 'Movie Merchandise', 'slug' => 'movie-merchandise'],
                ['name' => 'Music Merchandise', 'slug' => 'music-merchandise'],
                ['name' => 'Sports Merchandise', 'slug' => 'sports-merchandise'],
            ]],
            ['parent' => 'oversized-collection', 'children' => [
                ['name' => 'Drop Shoulder', 'slug' => 'drop-shoulder'],
                ['name' => 'Boxy Fit', 'slug' => 'boxy-fit'],
                ['name' => 'Classic Oversized', 'slug' => 'classic-oversized'],
            ]],
            ['parent' => 'accessories', 'children' => [
                ['name' => 'Caps', 'slug' => 'caps'],
                ['name' => 'Bags', 'slug' => 'bags'],
                ['name' => 'Phone Cases', 'slug' => 'phone-cases'],
            ]],
        ];
        foreach ($subData as $group) {
            foreach ($group['children'] as $child) {
                Category::create([
                    'name' => $child['name'],
                    'slug' => $child['slug'],
                    'description' => $child['name'] . ' — part of ' . $group['parent'],
                    'parent_id' => $categories[$group['parent']],
                    'seo_title' => $child['name'] . ' | ' . $storeName,
                    'is_active' => true,
                ]);
            }
        }
        $this->command->info('   ✓ Categories + Subcategories created');

        $this->command->info('🏷️  Seeding brands...');
        $brandData = [
            ['name' => $storeName, 'slug' => 'threvolt', 'logo' => 'https://images.unsplash.com/photo-1583743814966-8936f5b7be1a?q=80&w=200', 'description' => 'In-house premium brand'],
            ['name' => 'Urban Threads', 'slug' => 'urban-threads', 'logo' => 'https://images.unsplash.com/photo-1521572163474-6864f9cf17ab?q=80&w=200', 'description' => 'Streetwear essentials'],
            ['name' => 'NeoPrint', 'slug' => 'neo-print', 'logo' => 'https://images.unsplash.com/photo-1503341504253-dff4815485f1?q=80&w=200', 'description' => 'Graphic prints specialist'],
        ];
        foreach ($brandData as $b) {
            Brand::create($b);
        }
        $this->command->info('   ✓ Brands created');
    }
}
