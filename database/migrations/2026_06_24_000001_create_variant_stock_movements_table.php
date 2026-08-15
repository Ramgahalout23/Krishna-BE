<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('variant_stock_movements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('variant_id');
            $table->uuid('product_id');
            $table->string('type'); // ADD, REDUCE, SET, ADJUST
            $table->integer('quantity'); // Positive number (how much changed)
            $table->integer('stock_before')->default(0);
            $table->integer('stock_after')->default(0);
            $table->string('reason')->nullable(); // restock, return, damage, correction, sale, adjustment, etc.
            $table->text('notes')->nullable();
            $table->string('reference_type')->nullable(); // 'order', 'return', 'manual'
            $table->string('reference_id')->nullable();
            $table->uuid('created_by')->nullable(); // staff/user who made the change
            $table->timestamps();

            $table->foreign('variant_id')->references('id')->on('product_variants')->onDelete('cascade');
            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
            $table->index('variant_id');
            $table->index('product_id');
            $table->index('type');
            $table->index('created_at');
        });
    }

    public function down()
    {
        Schema::dropIfExists('variant_stock_movements');
    }
};
