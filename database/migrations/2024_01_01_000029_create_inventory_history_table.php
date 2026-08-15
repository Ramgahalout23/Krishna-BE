<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('inventory_histories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('inventory_id');
            $table->string('type');
            $table->integer('quantity');
            $table->string('reason')->nullable();
            $table->string('reference_id')->nullable();
            $table->foreign('inventory_id')->references('id')->on('inventories')->onDelete('cascade');
            $table->timestamps();

            $table->index('inventory_id');
            $table->index('type');
        });
    }

    public function down()
    {
        Schema::dropIfExists('inventory_histories');
    }
};
