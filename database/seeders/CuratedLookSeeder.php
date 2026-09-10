<?php

namespace Database\Seeders;

use App\Models\CuratedLook;
use Illuminate\Database\Seeder;

class CuratedLookSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('🎨 Seeding curated collections...');

        $img = fn(int $id, int $w = 800): string => "https://images.pexels.com/photos/{$id}/pexels-photo-{$id}.jpeg?auto=compress&cs=tinysrgb&w={$w}";

        $curatedLooks = [
            [
                'name' => 'Birthday Gift Bundles',
                'slug' => 'birthday-gift-bundles',
                'description' => 'Toys, gifts and goodies for the little birthday star.',
                'image_url' => $img(32362463),
                'display_order' => 0,
                'is_active' => true,
            ],
            [
                'name' => 'Smart Living Tech',
                'slug' => 'smart-living-tech',
                'description' => 'Earbuds, bands and chargers that keep up with your day.',
                'image_url' => $img(34241799),
                'display_order' => 1,
                'is_active' => true,
            ],
            [
                'name' => 'Kitchen Refresh',
                'slug' => 'kitchen-refresh',
                'description' => 'Cookware, containers and dinnerware for a happier home.',
                'image_url' => $img(15245007),
                'display_order' => 2,
                'is_active' => true,
            ],
            [
                'name' => 'New Arrivals',
                'slug' => 'new-arrivals',
                'description' => 'The freshest drops across the mart — shop them first.',
                'image_url' => $img(264985),
                'display_order' => 3,
                'is_active' => true,
            ],
        ];

        foreach ($curatedLooks as $look) {
            CuratedLook::create($look);
        }
        $this->command->info('   ✓ ' . count($curatedLooks) . ' curated collections created');
    }
}
