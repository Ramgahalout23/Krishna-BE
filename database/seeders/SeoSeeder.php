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

        $storeName = 'dotoydo';
        try {
            $val = Setting::where('module', 'SITE')->where('key', 'storeName')->value('value');
            if ($val) $storeName = $val;
        } catch (\Exception $e) {}

        Seo::create([
            'entity_type' => 'HOME', 'entity_id' => 'home',
            'meta_title' => "{$storeName} - Your Everyday Mart | Toys, Electronics, Home & More",
            'meta_description' => 'Shop toys & games, electronics, home and kitchen essentials and sports gear at dotoydo. Quality products, fast delivery, easy returns, free shipping above ₹499.',
            'meta_keywords' => 'toys, games, toys online, buy toys india, electronics, home kitchen, mart, dotoydo',
        ]);
        Sitemap::create(['url' => 'https://dotoydo.com/sitemap.xml', 'last_modified' => now()]);
        RobotsTxt::create(['content' => "User-agent: *\nAllow: /\nSitemap: https://dotoydo.com/sitemap.xml"]);
        $this->command->info('   ✓ SEO data created');
    }
}
