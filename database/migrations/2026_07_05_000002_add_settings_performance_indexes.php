<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add performance indexes for the settings getAllAsArray() query:
     *
     *   Setting::pluck('value', 'key')
     *
     * The existing index on (key) alone doesn't cover the value column.
     * Adding a composite (key, value(255)) index allows the pluck query
     * to use a covering index scan instead of a full table read, which
     * is significantly faster when value contains large TEXT blobs.
     *
     * Also adds a (module, key) index for the module-scoped queries
     * in SettingsService::getSetting() and getAllSettings().
     */
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            // Index for module-scoped queries
            // (already has UNIQUE(module, key), but a non-unique index
            //  is more efficient for range scans used by module queries)
            $table->index(['module', 'key'], 'settings_module_key_idx');
        });
    }

    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->dropIndex('settings_module_key_idx');
        });
    }
};
