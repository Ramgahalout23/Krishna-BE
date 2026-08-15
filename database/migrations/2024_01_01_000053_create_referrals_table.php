<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('referrals', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('referrer_id');
            $table->uuid('referee_id');
            $table->uuid('coupon_id')->nullable();
            $table->integer('reward_points')->default(0);
            $table->foreign('referrer_id')->references('id')->on('users');
            $table->foreign('referee_id')->references('id')->on('users');
            $table->timestamps();

            $table->index('referrer_id');
            $table->index('referee_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('referrals');
    }
};
