<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('curated_look_product', function (Blueprint $table) {
            $table->uuid('curated_look_id');
            $table->uuid('product_id');
            $table->integer('display_order')->default(0);
            $table->timestamps();

            $table->primary(['curated_look_id', 'product_id']);

            $table->foreign('curated_look_id')
                  ->references('id')
                  ->on('curated_looks')
                  ->onDelete('cascade');

            $table->foreign('product_id')
                  ->references('id')
                  ->on('products')
                  ->onDelete('cascade');

            $table->index('curated_look_id');
            $table->index('product_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('curated_look_product');
    }
};
