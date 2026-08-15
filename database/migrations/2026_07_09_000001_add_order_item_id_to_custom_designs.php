<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('custom_designs', function (Blueprint $table) {
            $table->uuid('order_item_id')->nullable()->after('order_id');

            // Only add the foreign key if the order_items table exists
            if (Schema::hasTable('order_items')) {
                $table->foreign('order_item_id')
                    ->references('id')
                    ->on('order_items')
                    ->onDelete('set null');
            }

            $table->index('order_item_id');
        });
    }

    public function down(): void
    {
        Schema::table('custom_designs', function (Blueprint $table) {
            $table->dropForeign(['order_item_id']);
            $table->dropIndex(['order_item_id']);
            $table->dropColumn('order_item_id');
        });
    }
};
