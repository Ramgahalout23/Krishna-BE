<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('products', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description');
            $table->string('short_description')->nullable();
            $table->decimal('price', 10, 2);
            $table->decimal('old_price', 10, 2)->nullable();
            $table->decimal('cost', 10, 2)->nullable();
            $table->string('badge')->nullable();
            $table->integer('quantity')->default(0);
            $table->string('sku')->unique();
            $table->string('barcode')->nullable();
            $table->decimal('weight', 10, 3)->nullable();
            $table->uuid('category_id');
            $table->uuid('brand_id')->nullable();
            $table->enum('status', ['DRAFT', 'PUBLISHED', 'ARCHIVED'])->default('DRAFT');
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_new')->default(false);
            $table->boolean('is_sale')->default(false);
            $table->boolean('is_digital')->default(false);
            $table->string('seo_title')->nullable();
            $table->text('seo_description')->nullable();
            $table->text('seo_keywords')->nullable();
            $table->longText('tags')->nullable();
            $table->integer('view_count')->default(0);
            $table->decimal('rating', 3, 2)->nullable();
            $table->integer('review_count')->default(0);
            $table->foreign('category_id')->references('id')->on('categories')->onDelete('cascade');
            $table->foreign('brand_id')->references('id')->on('brands')->onDelete('set null');
            $table->timestamps();

            $table->index('slug');
            $table->index('status');
            $table->index('is_featured');
            $table->index('category_id');
            $table->index('brand_id');
            $table->index(['status', 'is_featured', 'created_at']);
            $table->index(['category_id', 'status']);
            $table->index(['status', 'price']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('products');
    }
};
