<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('coupon_usages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('coupon_id');
            $table->uuid('user_id')->nullable();
            $table->uuid('order_id')->nullable();
            $table->decimal('amount', 10, 2);
            $table->boolean('is_valid')->default(true);
            $table->string('reason')->nullable();
            $table->foreign('coupon_id')->references('id')->on('coupons')->onDelete('cascade');
            $table->foreign('order_id')->references('id')->on('orders');
            $table->foreign('user_id')->references('id')->on('users');
            $table->timestamps();

            $table->index('coupon_id');
            $table->index('order_id');
            $table->index('user_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('coupon_usages');
    }
};
