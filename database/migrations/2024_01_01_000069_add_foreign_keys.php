<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // Orders -> Coupons FK (coupons table is created in 000012, so this must be after)
        Schema::table('orders', function (Blueprint $table) {
            $table->foreign('coupon_id')->references('id')->on('coupons')->onDelete('set null');
        });

        // Users -> VIP Tiers FK
        Schema::table('users', function (Blueprint $table) {
            $table->foreign('vip_tier_id')->references('id')->on('vip_tiers')->onDelete('set null');
        });

        // Coupons -> Coupon Campaigns FK
        Schema::table('coupons', function (Blueprint $table) {
            $table->foreign('campaign_id')->references('id')->on('coupon_campaigns')->onDelete('set null');
        });

        // Referrals -> Coupons FK
        Schema::table('referrals', function (Blueprint $table) {
            $table->foreign('coupon_id')->references('id')->on('coupons')->onDelete('set null');
        });

        // Cart Items -> Guest Carts FK
        Schema::table('cart_items', function (Blueprint $table) {
            $table->foreign('guest_cart_id')->references('id')->on('guest_carts')->onDelete('cascade');
        });

        // Support Tickets -> assigned_to (users) FK
        Schema::table('support_tickets', function (Blueprint $table) {
            $table->foreign('assigned_to')->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down()
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['coupon_id']);
        });
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['vip_tier_id']);
        });
        Schema::table('coupons', function (Blueprint $table) {
            $table->dropForeign(['campaign_id']);
        });
        Schema::table('referrals', function (Blueprint $table) {
            $table->dropForeign(['coupon_id']);
        });
        Schema::table('cart_items', function (Blueprint $table) {
            $table->dropForeign(['guest_cart_id']);
        });
        Schema::table('support_tickets', function (Blueprint $table) {
            $table->dropForeign(['assigned_to']);
        });
    }
};
