<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('ad_campaign_products', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('ad_campaign_id');
            $table->uuid('product_id');
            $table->longText('ad_copy')->nullable();
            $table->string('ad_headline')->nullable();
            $table->text('ad_description')->nullable();
            $table->string('call_to_action')->default('SHOP_NOW');
            $table->decimal('discount_offered', 10, 2)->nullable();
            $table->integer('sort_order')->default(0);
            $table->foreign('ad_campaign_id')->references('id')->on('ad_campaigns')->onDelete('cascade');
            $table->timestamps();

            $table->index('ad_campaign_id');
            $table->index('product_id');
            $table->unique(['ad_campaign_id', 'product_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('ad_campaign_products');
    }
};
