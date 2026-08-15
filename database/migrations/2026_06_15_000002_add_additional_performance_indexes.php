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
        // ── notifications indexes ──
        if (Schema::hasTable('notifications')) {
            // Composite index for getNotificationsByType():
            //   WHERE user_id = ? AND type = ? ORDER BY created_at DESC
            if (!$this->indexExists('notifications', 'notifications_user_type_created_index')) {
                Schema::table('notifications', function (Blueprint $table) {
                    $table->index(['user_id', 'type', 'created_at'], 'notifications_user_type_created_index');
                });
            }
        }

        // ── pages indexes ──
        if (Schema::hasTable('pages')) {
            // Composite index for getPublished():
            //   WHERE is_published = true ORDER BY title
            // Covers both the WHERE filter and the ORDER BY clause, avoiding a filesort.
            if (!$this->indexExists('pages', 'pages_published_title_index')) {
                Schema::table('pages', function (Blueprint $table) {
                    $table->index(['is_published', 'title'], 'pages_published_title_index');
                });
            }

            // Index for getAllPaginated() admin query:
            //   ORDER BY updated_at DESC
            if (!$this->indexExists('pages', 'pages_updated_at_index')) {
                Schema::table('pages', function (Blueprint $table) {
                    $table->index('updated_at');
                });
            }
        }
    }

    public function down(): void
    {
        $indexes = [
            'notifications' => [
                'notifications_user_type_created_index',
            ],
            'pages' => [
                'pages_published_title_index',
                'pages_updated_at_index',
            ],
        ];

        foreach ($indexes as $table => $indexNames) {
            if (!Schema::hasTable($table)) continue;
            foreach ($indexNames as $index) {
                if ($this->indexExists($table, $index)) {
                    try {
                        Schema::table($table, function (Blueprint $t) use ($index) {
                            $t->dropIndex($index);
                        });
                    } catch (\Exception $e) {
                        // Ignore errors on rollback
                    }
                }
            }
        }
    }
};
