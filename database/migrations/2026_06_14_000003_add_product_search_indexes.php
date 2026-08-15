<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private function indexExists(string $table, string $indexName): bool
    {
        try {
            $result = DB::select("
                SELECT COUNT(*) as cnt
                FROM information_schema.STATISTICS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = ?
                  AND INDEX_NAME = ?
            ", [$table, $indexName]);
            return !empty($result) && $result[0]->cnt > 0;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function up(): void
    {
        // FULLTEXT index on products.name for faster product search
        // This allows using MATCH(name) AGAINST('query') instead of slow LIKE '%...%'
        if (!$this->indexExists('products', 'products_name_fulltext')) {
            try {
                DB::statement('ALTER TABLE products ADD FULLTEXT INDEX products_name_fulltext (name)');
            } catch (\Exception $e) {
                // Fallback: standard index if FULLTEXT not supported
                if (!$this->indexExists('products', 'products_name_index')) {
                    Schema::table('products', function (Blueprint $table) {
                        $table->index('name');
                    });
                }
            }
        }

        // Index on review_count for best-seller queries
        if (!$this->indexExists('products', 'products_review_count_index')) {
            Schema::table('products', function (Blueprint $table) {
                $table->index('review_count');
            });
        }

        // Composite index on (status, view_count) for best-seller listing queries
        if (!$this->indexExists('products', 'products_status_view_count_index')) {
            Schema::table('products', function (Blueprint $table) {
                $table->index(['status', 'view_count']);
            });
        }

        // Composite index on (status, created_at) for new-arrivals pagination
        if (!$this->indexExists('products', 'products_status_created_at_index')) {
            Schema::table('products', function (Blueprint $table) {
                $table->index(['status', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        $indexes = [
            'products_name_fulltext',
            'products_name_index',
            'products_review_count_index',
            'products_status_view_count_index',
            'products_status_created_at_index',
        ];

        foreach ($indexes as $index) {
            if ($this->indexExists('products', $index)) {
                try {
                    Schema::table('products', function (Blueprint $table) use ($index) {
                        $table->dropIndex($index);
                    });
                } catch (\Exception $e) {
                    // Ignore errors on rollback
                }
            }
        }
    }
};
