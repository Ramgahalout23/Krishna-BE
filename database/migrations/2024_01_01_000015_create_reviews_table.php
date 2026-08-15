<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('reviews', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('product_id');
            $table->uuid('user_id');
            $table->uuid('order_id')->nullable();
            $table->integer('rating');
            $table->string('title');
            $table->text('comment');
            $table->json('images')->nullable();
            $table->boolean('is_verified')->default(false);
            $table->boolean('is_moderated')->default(false);
            $table->boolean('is_flagged')->default(false);
            $table->integer('helpful')->default(0);
            $table->integer('unhelpful')->default(0);
            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users');
            $table->timestamps();

            $table->index('product_id');
            $table->index('user_id');
            $table->index('is_moderated');
            $table->index(['product_id', 'is_moderated', 'created_at']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('reviews');
    }
};
