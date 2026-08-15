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
            // Drop the individual status index — the new composite index on
            // (status, created_at) covers single-column lookups on status
            // as well (status is the leftmost column in the composite index).
            $table->dropIndex(['status']);

            // Add composite index that optimises the admin list page query:
            //   CustomDesign::where('status', $s)->orderBy('created_at', 'desc')
            // used in CustomDesignController::index() when a status filter is active.
            // Also covers the stats() count queries via the leftmost prefix rule.
            $table->index(['status', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('custom_designs', function (Blueprint $table) {
            // Drop the composite index
            $table->dropIndex(['status', 'created_at']);

            // Restore the individual status index
            $table->index('status');
        });
    }
};
