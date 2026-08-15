<?php

namespace Database\Seeders;

use App\Models\ShippingZone;
use App\Models\ShippingRate;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ShippingZoneSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('🚚 Seeding shipping zones...');
        $zone1 = ShippingZone::create(['name' => 'Metro Cities', 'countries' => json_encode(['India']), 'states' => json_encode(['Delhi', 'Maharashtra', 'Karnataka', 'Tamil Nadu', 'Telangana'])]);
        $zone2 = ShippingZone::create(['name' => 'Tier 2 Cities', 'countries' => json_encode(['India']), 'states' => json_encode(['Gujarat', 'Rajasthan', 'West Bengal', 'Madhya Pradesh', 'Kerala'])]);

        ShippingRate::insert([
            ['id' => Str::uuid(), 'zone_id' => $zone1->id, 'min_weight' => 0, 'max_weight' => 500, 'cost' => 49, 'free_shipping_above' => 499, 'created_at' => now(), 'updated_at' => now()],
            ['id' => Str::uuid(), 'zone_id' => $zone1->id, 'min_weight' => 501, 'max_weight' => 1000, 'cost' => 79, 'free_shipping_above' => 799, 'created_at' => now(), 'updated_at' => now()],
            ['id' => Str::uuid(), 'zone_id' => $zone2->id, 'min_weight' => 0, 'max_weight' => 500, 'cost' => 79, 'free_shipping_above' => 699, 'created_at' => now(), 'updated_at' => now()],
            ['id' => Str::uuid(), 'zone_id' => $zone2->id, 'min_weight' => 501, 'max_weight' => 1000, 'cost' => 99, 'free_shipping_above' => 999, 'created_at' => now(), 'updated_at' => now()],
        ]);
        $this->command->info('   ✓ Shipping zones & rates created');
    }
}
