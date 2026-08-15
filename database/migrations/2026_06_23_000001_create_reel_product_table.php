<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reel_product', function (Blueprint $table) {
            $table->uuid('reel_id');
            $table->uuid('product_id');
            $table->integer('display_order')->default(0);
            $table->timestamps();

            $table->primary(['reel_id', 'product_id']);
            $table->foreign('reel_id')->references('id')->on('reels')->onDelete('cascade');
            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reel_product');
    }
};
