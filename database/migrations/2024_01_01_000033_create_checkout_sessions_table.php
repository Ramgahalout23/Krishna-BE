<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('checkout_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id')->nullable();
            $table->string('session_id')->nullable();
            $table->longText('cart_data');
            $table->uuid('shipping_address_id')->nullable();
            $table->uuid('billing_address_id')->nullable();
            $table->string('shipping_method')->nullable();
            $table->enum('payment_method', ['STRIPE', 'RAZORPAY', 'PAYPAL', 'COD', 'CUSTOM'])->nullable();
            $table->uuid('coupon_id')->nullable();
            $table->decimal('wallet_used', 10, 2)->default(0);
            $table->boolean('partial_payment')->default(false);
            $table->text('order_notes')->nullable();
            $table->string('invoice_id')->nullable();
            $table->string('status')->default('PENDING');
            $table->foreign('user_id')->references('id')->on('users');
            $table->foreign('coupon_id')->references('id')->on('coupons');
            $table->timestamps();

            $table->index('user_id');
            $table->index('session_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('checkout_sessions');
    }
};
