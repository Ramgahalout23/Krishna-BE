<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('coupons', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code')->unique();
            $table->enum('type', ['PERCENTAGE', 'FIXED', 'FREE_SHIPPING', 'BOGO', 'BUY_X_GET_Y', 'FIRST_ORDER', 'CASHBACK', 'REFERRAL', 'WALLET_CASHBACK', 'AUTO_CART_DISCOUNT']);
            $table->enum('discount_type', ['FLAT', 'PERCENTAGE']);
            $table->decimal('discount_value', 10, 2);
            $table->decimal('min_order_value', 10, 2)->nullable();
            $table->decimal('max_discount', 10, 2)->nullable();
            $table->integer('usage_limit')->nullable();
            $table->integer('usage_per_user')->default(1);
            $table->integer('usage_count')->default(0);
            $table->boolean('is_active')->default(true);
            $table->dateTime('start_date')->nullable();
            $table->dateTime('expiry_date')->nullable();
            $table->longText('applicable_categories')->nullable();
            $table->longText('applicable_brands')->nullable();
            $table->longText('applicable_products')->nullable();
            $table->longText('applicable_roles')->nullable();
            $table->longText('applicable_payment_methods')->nullable();
            $table->boolean('is_new_user_only')->default(false);
            $table->boolean('is_auto_apply')->default(false);
            $table->boolean('is_stackable')->default(false);
            $table->boolean('is_single_use')->default(false);
            $table->boolean('is_bulk')->default(false);
            $table->dateTime('schedule_start')->nullable();
            $table->dateTime('schedule_end')->nullable();
            $table->uuid('campaign_id')->nullable();
            $table->string('fraud_protection_level')->nullable();
            $table->string('created_by')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index('code');
            $table->index('is_active');
            $table->index('expiry_date');
        });
    }

    public function down()
    {
        Schema::dropIfExists('coupons');
    }
};
