<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('abandoned_carts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id')->nullable();
            $table->string('session_id')->nullable();
            $table->longText('cart_data');
            $table->timestamp('last_active_at');
            $table->boolean('reminder_sent')->default(false);
            $table->foreign('user_id')->references('id')->on('users');
            $table->timestamps();

            $table->index('session_id');
            $table->index('user_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('abandoned_carts');
    }
};
