<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            // Add the item_index column (positional index within the order)
            $table->unsignedInteger('item_index')->default(0)->after('variant_id');

            // Composite index matching the query pattern used when relating
            // custom designs to order items by (order_id, item_index).
            $table->index(['order_id', 'item_index']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropIndex(['order_id', 'item_index']);
            $table->dropColumn('item_index');
        });
    }
};
