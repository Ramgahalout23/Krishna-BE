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

        $storeName = 'dotoydo';
        try {
            $val = Setting::where('module', 'SITE')->where('key', 'storeName')->value('value');
            if ($val) $storeName = $val;
        } catch (\Exception $e) {}

        $catData = [
            ['name' => 'Toys & Games', 'slug' => 'toys-games', 'description' => 'Soft toys, action figures, building blocks, puzzles, RC cars & more — fun for every age.', 'image' => 'https://images.pexels.com/photos/8409851/pexels-photo-8409851.jpeg?auto=compress&cs=tinysrgb&w=800'],
            ['name' => 'Electronics & Gadgets', 'slug' => 'electronics', 'description' => 'Earbuds, speakers, smart bands, power banks & everyday tech essentials.', 'image' => 'https://images.pexels.com/photos/34241799/pexels-photo-34241799.jpeg?auto=compress&cs=tinysrgb&w=800'],
            ['name' => 'Home & Kitchen', 'slug' => 'home-kitchen', 'description' => 'Cookware, storage, bedding, dinnerware & everything your home needs.', 'image' => 'https://images.pexels.com/photos/15245007/pexels-photo-15245007.jpeg?auto=compress&cs=tinysrgb&w=800'],
            ['name' => 'Sports & Outdoors', 'slug' => 'sports-outdoors', 'description' => 'Fitness gear, yoga mats, racket sports & outdoor equipment for an active life.', 'image' => 'https://images.pexels.com/photos/4793328/pexels-photo-4793328.jpeg?auto=compress&cs=tinysrgb&w=800'],
        ];
        $categories = [];
        foreach ($catData as $c) {
            $cat = Category::create($c + ['seo_title' => $c['name'] . ' | ' . $storeName, 'seo_description' => $c['description'], 'is_active' => true]);
            $categories[$c['slug']] = $cat->id;
        }

        $subData = [
            ['parent' => 'toys-games', 'children' => [
                ['name' => 'Soft Toys & Plush', 'slug' => 'soft-toys-plush'],
                ['name' => 'Action Figures & Collectibles', 'slug' => 'action-figures'],
                ['name' => 'Building & Construction', 'slug' => 'building-construction'],
                ['name' => 'Board Games & Puzzles', 'slug' => 'board-games-puzzles'],
                ['name' => 'Remote Control & Vehicles', 'slug' => 'rc-vehicles'],
                ['name' => 'Educational Toys', 'slug' => 'educational-toys'],
            ]],
            ['parent' => 'electronics', 'children' => [
                ['name' => 'Audio & Headphones', 'slug' => 'audio-headphones'],
                ['name' => 'Wearables & Smart Devices', 'slug' => 'wearables'],
                ['name' => 'Mobile & Charging Accessories', 'slug' => 'mobile-charging'],
                ['name' => 'Power Banks', 'slug' => 'power-banks'],
            ]],
            ['parent' => 'home-kitchen', 'children' => [
                ['name' => 'Cookware', 'slug' => 'cookware'],
                ['name' => 'Storage & Organizers', 'slug' => 'storage-organizers'],
                ['name' => 'Bedding & Linen', 'slug' => 'bedding-linen'],
                ['name' => 'Dinnerware', 'slug' => 'dinnerware'],
            ]],
            ['parent' => 'sports-outdoors', 'children' => [
                ['name' => 'Fitness Equipment', 'slug' => 'fitness-equipment'],
                ['name' => 'Yoga & Exercise', 'slug' => 'yoga-exercise'],
                ['name' => 'Racket Sports', 'slug' => 'racket-sports'],
                ['name' => 'Outdoor & Camping', 'slug' => 'outdoor-camping'],
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
            ['name' => $storeName, 'slug' => 'dotoydo', 'logo' => 'https://images.pexels.com/photos/6219117/pexels-photo-6219117.jpeg?auto=compress&cs=tinysrgb&w=200', 'description' => 'In-house mart brand — everyday essentials under one roof'],
            ['name' => 'PlayNest', 'slug' => 'playnest', 'logo' => 'https://images.pexels.com/photos/38807149/pexels-photo-38807149.jpeg?auto=compress&cs=tinysrgb&w=200', 'description' => 'Toys & games for curious kids'],
            ['name' => 'TechVibe', 'slug' => 'techvibe', 'logo' => 'https://images.pexels.com/photos/33298188/pexels-photo-33298188.jpeg?auto=compress&cs=tinysrgb&w=200', 'description' => 'Smart gadgets for everyday life'],
            ['name' => 'HomeCraft', 'slug' => 'homecraft', 'logo' => 'https://images.pexels.com/photos/4096909/pexels-photo-4096909.jpeg?auto=compress&cs=tinysrgb&w=200', 'description' => 'Home & kitchen essentials'],
            ['name' => 'FitForte', 'slug' => 'fitforte', 'logo' => 'https://images.pexels.com/photos/29224210/pexels-photo-29224210.jpeg?auto=compress&cs=tinysrgb&w=200', 'description' => 'Sports & fitness gear'],
        ];
        foreach ($brandData as $b) {
            Brand::create($b);
        }
        $this->command->info('   ✓ Brands created');
    }
}
