<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Make banners.image_url nullable to support Title-Only banners (no image needed).
     * Uses raw SQL to avoid requiring doctrine/dbal for column modification.
     */
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE banners MODIFY COLUMN image_url VARCHAR(255) NULL');
        } elseif ($driver === 'pgsql') {
            DB::statement('ALTER TABLE banners ALTER COLUMN image_url DROP NOT NULL');
        } elseif ($driver === 'sqlite') {
            // SQLite requires recreating the table; skip for simplicity
        }
    }

    /**
     * Reverse the migrations.
     * Restore banners.image_url to NOT nullable.
     */
    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        // Set existing null values to empty string before making the column NOT NULL
        DB::table('banners')->whereNull('image_url')->update(['image_url' => '']);

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE banners MODIFY COLUMN image_url VARCHAR(255) NOT NULL');
        } elseif ($driver === 'pgsql') {
            DB::statement('ALTER TABLE banners ALTER COLUMN image_url SET NOT NULL');
        } elseif ($driver === 'sqlite') {
            // skip
        }
    }
};
