<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('coupon_analytics', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('coupon_id');
            $table->integer('usage_count')->default(0);
            $table->decimal('total_discount_given', 10, 2)->default(0);
            $table->integer('fraud_attempts')->default(0);
            $table->foreign('coupon_id')->references('id')->on('coupons')->onDelete('cascade');
            $table->timestamps();

            $table->index('coupon_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('coupon_analytics');
    }
};
