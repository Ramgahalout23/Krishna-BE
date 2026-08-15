<?php

namespace Database\Seeders;

use App\Models\Coupon;
use App\Models\CouponAnalytics;
use Illuminate\Database\Seeder;

class CouponSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('🎫 Seeding coupons...');
        $couponList = [
            ['code' => 'WELCOME20', 'type' => 'PERCENTAGE', 'discount_type' => 'PERCENTAGE', 'discount_value' => 20, 'min_order_value' => 499, 'is_new_user_only' => true],
            ['code' => 'FLAT300', 'type' => 'FIXED', 'discount_type' => 'FLAT', 'discount_value' => 300, 'min_order_value' => 999],
            ['code' => 'FREESHIP', 'type' => 'FREE_SHIPPING', 'discount_type' => 'FLAT', 'discount_value' => 0, 'min_order_value' => 499, 'is_auto_apply' => true],
            ['code' => 'SAVE30', 'type' => 'PERCENTAGE', 'discount_type' => 'PERCENTAGE', 'discount_value' => 30, 'max_discount' => 500, 'is_stackable' => true],
            ['code' => 'FIRST50', 'type' => 'FIRST_ORDER', 'discount_type' => 'PERCENTAGE', 'discount_value' => 50, 'max_discount' => 200, 'is_new_user_only' => true],
        ];

        foreach ($couponList as $c) {
            $coupon = Coupon::create(array_merge($c, [
                'start_date' => now(), 'expiry_date' => now()->addYear(),
                'is_active' => true,
            ]));
            CouponAnalytics::create(['coupon_id' => $coupon->id]);
        }
        $this->command->info('   ✓ Coupons created');
    }
}
