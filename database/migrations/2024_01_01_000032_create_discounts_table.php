<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('discounts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->enum('discount_type', ['FLAT', 'PERCENTAGE']);
            $table->decimal('discount_value', 10, 2);
            $table->string('applicable_type')->default('CATEGORY');
            $table->uuid('category_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->dateTime('start_date')->nullable();
            $table->dateTime('expiry_date')->nullable();
            $table->foreign('category_id')->references('id')->on('categories');
            $table->timestamps();

            $table->index('category_id');
            $table->index('is_active');
        });
    }

    public function down()
    {
        Schema::dropIfExists('discounts');
    }
};
