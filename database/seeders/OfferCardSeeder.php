<?php

namespace Database\Seeders;

use App\Models\Promotion;
use Illuminate\Database\Seeder;

class OfferCardSeeder extends Seeder
{
    /**
     * Seed default offer cards into the promotions table.
     * These appear on product detail pages as offer card sliders.
     *
     * Usage: php artisan db:seed --class=OfferCardSeeder
     */
    public function run(): void
    {
        $this->command->info('🏷️  Seeding default offer cards...');

        $offers = [
            [
                'title'           => 'Smart Deal',
                'type'            => 'SEASONAL',
                'discount'        => 10,
                'status'          => 'ACTIVE',
                'is_active'       => true,
                'offer_badge'     => 'BUY 2',
                'offer_highlight' => 'GET 10% OFF',
                'offer_tagline'   => 'Auto-applied at checkout',
                'offer_theme'     => 'smart-deal',
                'auto_apply'      => true,
            ],
            [
                'title'           => 'Prepaid Offer',
                'type'            => 'SEASONAL',
                'discount'        => 10,
                'status'          => 'ACTIVE',
                'is_active'       => true,
                'offer_badge'     => null,
                'offer_highlight' => 'EXTRA 10% OFF',
                'offer_tagline'   => 'On prepaid orders · Auto-applied',
                'offer_theme'     => 'prepaid-offer',
                'auto_apply'      => true,
            ],
            [
                'title'           => 'Summer Bonus',
                'type'            => 'SEASONAL',
                'discount'        => 15,
                'status'          => 'ACTIVE',
                'is_active'       => true,
                'offer_badge'     => 'FREE GIFT',
                'offer_highlight' => 'Scrunchies ₹150',
                'offer_tagline'   => 'AUTO · Added to Cart',
                'offer_theme'     => 'summer-bonus',
                'auto_apply'      => true,
            ],
            [
                'title'           => 'Hot Deal',
                'type'            => 'SEASONAL',
                'discount'        => 20,
                'status'          => 'ACTIVE',
                'is_active'       => true,
                'offer_badge'     => null,
                'offer_highlight' => "FLAT 20%\nOFF",
                'offer_tagline'   => 'Limited time · On all orders',
                'offer_theme'     => 'red',
                'auto_apply'      => true,
            ],
            [
                'title'           => 'Premium Offer',
                'type'            => 'SEASONAL',
                'discount'        => 15,
                'status'          => 'ACTIVE',
                'is_active'       => true,
                'offer_badge'     => null,
                'offer_highlight' => "EXTRA 15%\nOFF",
                'offer_tagline'   => 'Exclusive · Members only',
                'offer_theme'     => 'black',
                'auto_apply'      => true,
            ],
        ];

        foreach ($offers as $data) {
            Promotion::firstOrCreate(
                ['title' => $data['title']],
                $data
            );
        }

        $this->command->info('   ✓ ' . count($offers) . ' offer cards seeded');
    }
}
