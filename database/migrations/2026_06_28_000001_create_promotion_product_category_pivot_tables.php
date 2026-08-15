<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('promotion_product', function (Blueprint $table) {
            $table->id();
            $table->uuid('promotion_id');
            $table->uuid('product_id');
            $table->timestamps();

            $table->foreign('promotion_id')->references('id')->on('promotions')->onDelete('cascade');
            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
            $table->unique(['promotion_id', 'product_id']);
        });

        Schema::create('promotion_category', function (Blueprint $table) {
            $table->id();
            $table->uuid('promotion_id');
            $table->uuid('category_id');
            $table->timestamps();

            $table->foreign('promotion_id')->references('id')->on('promotions')->onDelete('cascade');
            $table->foreign('category_id')->references('id')->on('categories')->onDelete('cascade');
            $table->unique(['promotion_id', 'category_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('promotion_product');
        Schema::dropIfExists('promotion_category');
    }
};
