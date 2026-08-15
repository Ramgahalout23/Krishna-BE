<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // reel_product has no explicit indexes beyond the composite PK and InnoDB auto-FK indexes.
        // Add explicit indexes for both FK columns + a covering index for the sorted join query.
        Schema::table('reel_product', function (Blueprint $table) {
            // Explicit index for reverse lookup: WHERE product_id = ?
            $table->index('product_id');
            // Covering index for: WHERE reel_id = ? ORDER BY display_order
            // (eliminates filesort; reel_id alone is already covered by the composite PK)
            $table->index(['reel_id', 'display_order']);
        });

        // curated_look_product has explicit FK indexes but is missing a covering index
        // for: WHERE curated_look_id = ? ORDER BY display_order
        Schema::table('curated_look_product', function (Blueprint $table) {
            $table->index(['curated_look_id', 'display_order']);
        });
    }

    public function down(): void
    {
        Schema::table('reel_product', function (Blueprint $table) {
            $table->dropIndex(['product_id']);
            $table->dropIndex(['reel_id', 'display_order']);
        });

        Schema::table('curated_look_product', function (Blueprint $table) {
            $table->dropIndex(['curated_look_id', 'display_order']);
        });
    }
};
