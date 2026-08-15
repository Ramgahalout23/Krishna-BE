<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Make banners.title nullable to support Image-Only banners (no title needed).
     * Uses raw SQL to avoid requiring doctrine/dbal for column modification.
     */
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE banners MODIFY COLUMN title VARCHAR(255) NULL');
        } elseif ($driver === 'pgsql') {
            DB::statement('ALTER TABLE banners ALTER COLUMN title DROP NOT NULL');
        }
    }

    /**
     * Reverse the migrations.
     * Restore banners.title to NOT nullable.
     */
    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        // Set existing null values to empty string before making the column NOT NULL
        DB::table('banners')->whereNull('title')->update(['title' => '']);

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE banners MODIFY COLUMN title VARCHAR(255) NOT NULL');
        } elseif ($driver === 'pgsql') {
            DB::statement('ALTER TABLE banners ALTER COLUMN title SET NOT NULL');
        }
    }
};
