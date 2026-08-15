<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('shipping_rates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('zone_id');
            $table->decimal('min_weight', 10, 3)->nullable();
            $table->decimal('max_weight', 10, 3)->nullable();
            $table->decimal('min_price', 10, 2)->nullable();
            $table->decimal('max_price', 10, 2)->nullable();
            $table->decimal('cost', 10, 2);
            $table->decimal('free_shipping_above', 10, 2)->nullable();
            $table->foreign('zone_id')->references('id')->on('shipping_zones');
            $table->timestamps();

            $table->index('zone_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('shipping_rates');
    }
};
