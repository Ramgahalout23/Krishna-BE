<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_analytics_summary', function (Blueprint $table) {
            $table->id();
            $table->date('date')->unique();

            // ── Orders Summary ──
            $table->unsignedInteger('total_orders')->default(0);
            $table->unsignedInteger('delivered_orders')->default(0);
            $table->unsignedInteger('confirmed_orders')->default(0);
            $table->unsignedInteger('pending_orders')->default(0);
            $table->unsignedInteger('cancelled_orders')->default(0);
            $table->decimal('total_revenue', 15, 2)->default(0.00);
            $table->decimal('total_discount', 15, 2)->default(0.00);
            $table->decimal('avg_order_value', 10, 2)->default(0.00);

            // ── Users Summary ──
            $table->unsignedInteger('new_users')->default(0);
            $table->unsignedInteger('total_users')->default(0);
            $table->unsignedInteger('email_verified_users')->default(0);
            $table->unsignedInteger('active_users')->default(0);

            // ── Products Summary ──
            $table->unsignedInteger('total_products')->default(0);
            $table->unsignedInteger('published_products')->default(0);

            // ── Reviews Summary ──
            $table->unsignedInteger('new_reviews')->default(0);
            $table->unsignedInteger('approved_reviews')->default(0);
            $table->unsignedInteger('pending_reviews')->default(0);
            $table->unsignedInteger('rejected_reviews')->default(0);
            $table->decimal('avg_rating', 3, 2)->default(0.00);
            $table->unsignedInteger('rating_5_count')->default(0);
            $table->unsignedInteger('rating_4_count')->default(0);
            $table->unsignedInteger('rating_3_count')->default(0);
            $table->unsignedInteger('rating_2_count')->default(0);
            $table->unsignedInteger('rating_1_count')->default(0);

            // ── Page Views / Tracking ──
            $table->unsignedInteger('page_views')->default(0);
            $table->unsignedInteger('unique_visitors')->default(0);

            // ── Coupons ──
            $table->unsignedInteger('active_coupons')->default(0);

            $table->timestamps();

            $table->index('date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_analytics_summary');
    }
};
