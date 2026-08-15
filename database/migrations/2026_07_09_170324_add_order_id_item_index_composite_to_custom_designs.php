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
        Schema::table('custom_designs', function (Blueprint $table) {
            // MySQL InnoDB requires an index backing a foreign key constraint.
            // The new composite index (order_id, item_index) has order_id as its
            // leftmost column, so it can serve the FK — but we need to drop and
            // re-add the FK to switch which index backs it.

            // 1. Drop the foreign key constraint
            $table->dropForeign(['order_id']);

            // 2. Drop the individual order_id index (no longer needed;
            //    the composite index covers single-column lookups on order_id too)
            $table->dropIndex(['order_id']);

            // 3. Add composite index that optimises the common query pattern:
            //    CustomDesign::where('order_id', $id)->get()->keyBy('item_index')
            //    CustomDesign::whereIn('order_id', $ids)->get()->groupBy('order_id')
            $table->index(['order_id', 'item_index']);

            // 4. Re-add the foreign key — MySQL will use the composite index
            //    (order_id, item_index) since order_id is the leftmost column.
            $table->foreign('order_id')->references('id')->on('orders')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('custom_designs', function (Blueprint $table) {
            // 1. Drop the foreign key (backed by the composite index)
            $table->dropForeign(['order_id']);

            // 2. Drop the composite index
            $table->dropIndex(['order_id', 'item_index']);

            // 3. Restore the individual order_id index (needed for FK)
            $table->index('order_id');

            // 4. Re-add the foreign key
            $table->foreign('order_id')->references('id')->on('orders')->onDelete('cascade');
        });
    }
};
