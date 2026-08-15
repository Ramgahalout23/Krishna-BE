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
            // Drop the individual user_id index — the new composite index on
            // (user_id, created_at) covers single-column lookups on user_id
            // as well (user_id is the leftmost column in the composite index).
            $table->dropIndex(['user_id']);

            // Add composite index that optimises the common query pattern:
            //   CustomDesign::where('user_id', $id)->orderBy('created_at', 'desc')
            // used in CustomDesignController::userDesigns().
            // Also covers the eager-loading lookup in CustomDesignController::index().
            $table->index(['user_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('custom_designs', function (Blueprint $table) {
            // Drop the composite index
            $table->dropIndex(['user_id', 'created_at']);

            // Restore the individual user_id index
            $table->index('user_id');
        });
    }
};
