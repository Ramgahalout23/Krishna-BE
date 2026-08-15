<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use App\Models\ActivityLog;
use App\Models\CartItem;
use App\Models\WishlistItem;
use App\Models\OrderTimeline;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Shipping;
use App\Models\Order;
use App\Models\ProductVariant;
use App\Models\ProductImage;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\Category;
use App\Models\Brand;
use App\Models\Banner;
use App\Models\Page;
use App\Models\Setting;
use App\Models\Coupon;
use App\Models\CouponAnalytics;
use App\Models\Review;
use App\Models\Discount;
use App\Models\ShippingZone;
use App\Models\ShippingRate;
use App\Models\VipTier;
use App\Models\Address;
use App\Models\Wallet;
use App\Models\LoyaltyPoint;
use App\Models\Subscriber;
use App\Models\Campaign;
use App\Models\Seo;
use App\Models\Sitemap;
use App\Models\RobotsTxt;
use App\Models\CuratedLook;
use App\Models\Reel;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    public function run()
    {
        // =====================
        // Clear existing data
        // =====================
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        ActivityLog::truncate();
        CartItem::truncate();
        WishlistItem::truncate();
        OrderTimeline::truncate();
        OrderItem::truncate();
        Payment::truncate();
        Shipping::truncate();
        Order::truncate();
        ProductVariant::truncate();
        ProductImage::truncate();
        Inventory::truncate();
        Product::truncate();
        Category::truncate();
        Brand::truncate();
        Banner::truncate();
        Page::truncate();
        Setting::truncate();
        Coupon::truncate();
        CouponAnalytics::truncate();
        Review::truncate();
        Discount::truncate();
        ShippingZone::truncate();
        ShippingRate::truncate();
        VipTier::truncate();
        Address::truncate();
        DB::table('notifications')->truncate();
        Wallet::truncate();
        LoyaltyPoint::truncate();
        Subscriber::truncate();
        Campaign::truncate();
        Seo::truncate();
        Sitemap::truncate();
        RobotsTxt::truncate();
        CuratedLook::truncate();
        Reel::truncate();
        User::where('email', '!=', 'admin@threvolt.com')->delete();
        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        $this->command->info('🗑️  Existing data cleared');

        // =====================
        // Call all focused seeders
        // =====================
        $this->call(AdminTestUserSeeder::class);
        $this->call(CategoryBrandSeeder::class);
        $this->call(CurrencyExchangeRateSeeder::class);
        $this->call(SettingsSeeder::class);
        $this->call(PageSeeder::class);
        $this->call(BannerSeeder::class);
        $this->call(CustomerSeeder::class);
        $this->call(VipTierSeeder::class);
        $this->call(ShippingZoneSeeder::class);
        $this->call(CouponSeeder::class);
        $this->call(SeoSeeder::class);
        $this->call(SubscriberSeeder::class);
        $this->call(CuratedLookSeeder::class);
        $this->call(TranslationSeeder::class);
        $this->call(NotificationSeeder::class);
        $this->call(StoreOfferSeeder::class);

        // Product-dependent seeders — must run after ProductSeeder
        $this->call(ProductSeeder::class);
        $this->call(ReviewSeeder::class);
        $this->call(ReelSeeder::class);
        $this->call(OrderSeeder::class);

        // =====================
        // Summary
        // =====================
        $this->command->info("\n🎉 Database seeding completed successfully!\n");
        $this->command->info("📊 Summary:");
        $this->command->info("- Admin: admin@threvolt.com / Admin@123");
        $this->command->info("- Customer: customer@threvolt.com / Demo@123");
        $this->command->info("- Products: " . Product::count());
        $this->command->info("- Orders: " . Order::count());
        $this->command->info("- Coupons: " . Coupon::count());
        $this->command->info("- Customers: " . User::where('role', 'CUSTOMER')->count());
        $this->command->info("- Settings: " . Setting::count());

        // Pre-warm all dashboard caches so first visit is fast (no cold cache)
        $this->command->info('♨️  Warming dashboard cache...');
        try {
            Artisan::call('dashboard:warm-cache', ['--force' => true]);
            $this->command->info('   ✓ Dashboard cache warmed');
        } catch (\Throwable $e) {
            $this->command->warn('   ⚠ Cache warm skipped: ' . $e->getMessage());
        }
    }
}
