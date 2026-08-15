<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Drop any foreign key constraints referencing tokenable_id
        try {
            $constraints = DB::select("
                SELECT CONSTRAINT_NAME
                FROM information_schema.KEY_COLUMN_USAGE
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = 'personal_access_tokens'
                  AND COLUMN_NAME = 'tokenable_id'
                  AND REFERENCED_TABLE_NAME IS NOT NULL
            ");
            foreach ($constraints as $constraint) {
                DB::statement("ALTER TABLE personal_access_tokens DROP FOREIGN KEY `{$constraint->CONSTRAINT_NAME}`");
            }
        } catch (\Exception $e) {
            // Table may not have foreign keys — safe to ignore
        }

        // Drop any indexes on tokenable_id (morphs creates an index)
        try {
            $indexes = DB::select("
                SELECT DISTINCT INDEX_NAME
                FROM information_schema.STATISTICS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = 'personal_access_tokens'
                  AND COLUMN_NAME = 'tokenable_id'
            ");
            foreach ($indexes as $index) {
                if ($index->INDEX_NAME !== 'PRIMARY') {
                    DB::statement("ALTER TABLE personal_access_tokens DROP INDEX `{$index->INDEX_NAME}`");
                }
            }
        } catch (\Exception $e) {
            // Safe to ignore
        }

        // Change tokenable_id from BIGINT UNSIGNED to CHAR(36) for UUID support
        DB::statement('ALTER TABLE personal_access_tokens MODIFY tokenable_id CHAR(36)');

        // Re-add the morphs index
        Schema::table('personal_access_tokens', function ($table) {
            $table->index(['tokenable_id', 'tokenable_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('personal_access_tokens', function ($table) {
            $table->dropIndex(['tokenable_id', 'tokenable_type']);
        });

        DB::statement('ALTER TABLE personal_access_tokens MODIFY tokenable_id BIGINT UNSIGNED NULL');

        Schema::table('personal_access_tokens', function ($table) {
            $table->index(['tokenable_id', 'tokenable_type']);
        });
    }
};
