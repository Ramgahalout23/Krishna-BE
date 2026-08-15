<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('recently_viewed_products', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id')->nullable();
            $table->uuid('product_id');
            $table->timestamp('viewed_at')->useCurrent();
            $table->foreign('product_id')->references('id')->on('products');
            $table->foreign('user_id')->references('id')->on('users');
            $table->timestamps();

            $table->index('product_id');
            $table->index('user_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('recently_viewed_products');
    }
};
