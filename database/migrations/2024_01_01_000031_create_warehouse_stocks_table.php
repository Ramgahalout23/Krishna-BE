<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('warehouse_stocks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('inventory_id');
            $table->uuid('warehouse_id');
            $table->integer('quantity')->default(0);
            $table->foreign('inventory_id')->references('id')->on('inventories')->onDelete('cascade');
            $table->foreign('warehouse_id')->references('id')->on('warehouses');
            $table->timestamps();

            $table->unique(['inventory_id', 'warehouse_id']);
            $table->index('inventory_id');
            $table->index('warehouse_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('warehouse_stocks');
    }
};
