<?php

namespace Database\Seeders;

use App\Models\Seo;
use App\Models\Setting;
use App\Models\Sitemap;
use App\Models\RobotsTxt;
use Illuminate\Database\Seeder;

class SeoSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('🔍 Seeding SEO data...');

        $storeName = 'THREVOLT';
        try {
            $val = Setting::where('module', 'SITE')->where('key', 'storeName')->value('value');
            if ($val) $storeName = $val;
        } catch (\Exception $e) {}

        Seo::create([
            'entity_type' => 'HOME', 'entity_id' => 'home',
            'meta_title' => "{$storeName} - India's Boldest T-Shirt Brand",
            'meta_description' => 'Premium quality t-shirts with bold designs. Free shipping on orders above ₹499.',
            'meta_keywords' => 't-shirts, oversize tees, graphic tees, streetwear',
        ]);
        Sitemap::create(['url' => 'https://threvolt.com/sitemap.xml', 'last_modified' => now()]);
        RobotsTxt::create(['content' => "User-agent: *\nAllow: /\nSitemap: https://threvolt.com/sitemap.xml"]);
        $this->command->info('   ✓ SEO data created');
    }
}
