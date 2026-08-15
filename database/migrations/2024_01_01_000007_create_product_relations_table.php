<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('product_relations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('product_id');
            $table->uuid('related_product_id');
            $table->string('relation_type')->default('SIMILAR');
            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
            $table->foreign('related_product_id')->references('id')->on('products')->onDelete('cascade');
            $table->timestamps();

            $table->unique(['product_id', 'related_product_id']);
            $table->index('product_id');
            $table->index('related_product_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('product_relations');
    }
};
