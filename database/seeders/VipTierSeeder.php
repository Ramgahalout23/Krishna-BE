<?php

namespace Database\Seeders;

use App\Models\VipTier;
use Illuminate\Database\Seeder;

class VipTierSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('👑 Seeding VIP tiers...');
        VipTier::create(['name' => 'Bronze', 'min_points' => 0, 'benefits' => '5% off on all orders']);
        VipTier::create(['name' => 'Silver', 'min_points' => 500, 'benefits' => '10% off + free shipping']);
        VipTier::create(['name' => 'Gold', 'min_points' => 1500, 'benefits' => '15% off + early access + free shipping']);
        VipTier::create(['name' => 'Platinum', 'min_points' => 5000, 'benefits' => '20% off + priority support + exclusive deals']);
        $this->command->info('   ✓ VIP tiers created');
    }
}
