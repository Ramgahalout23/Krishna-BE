<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Check if an index exists on a table.
     */
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

    /**
     * Add missing indexes identified from AdminRepository query patterns.
     *
     * Note: orders.status and orders.created_at are ALREADY indexed
     * in the original 2024_01_01_000009_create_orders_table migration.
     */
    public function up(): void
    {
        if (!$this->indexExists('users', 'users_role_index')) {
            Schema::table('users', function (Blueprint $table) {
                $table->index('role');
            });
        }

        if (!$this->indexExists('payments', 'payments_method_index')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->index('method');
            });
        }

        if (!$this->indexExists('payments', 'payments_created_at_index')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->index('created_at');
            });
        }

        if (!$this->indexExists('abandoned_carts', 'abandoned_carts_reminder_sent_index')) {
            Schema::table('abandoned_carts', function (Blueprint $table) {
                $table->index('reminder_sent');
            });
        }

        if (!$this->indexExists('abandoned_carts', 'abandoned_carts_last_active_at_index')) {
            Schema::table('abandoned_carts', function (Blueprint $table) {
                $table->index('last_active_at');
            });
        }

        if (!$this->indexExists('order_items', 'order_items_created_at_index')) {
            Schema::table('order_items', function (Blueprint $table) {
                $table->index('created_at');
            });
        }

        if (Schema::hasTable('activity_logs')) {
            if (!$this->indexExists('activity_logs', 'activity_logs_created_at_index')) {
                Schema::table('activity_logs', function (Blueprint $table) {
                    $table->index('created_at');
                });
            }
        }

        // ── Additional indexes for dashboard/analytics queries ──

        // users.created_at → getCustomerGrowth GROUP BY + WHERE
        if (!$this->indexExists('users', 'users_created_at_index')) {
            Schema::table('users', function (Blueprint $table) {
                $table->index('created_at');
            });
        }

        // orders.user_id → getCustomerLifetimeValue WHERE
        if (!$this->indexExists('orders', 'orders_user_id_index')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->index('user_id');
            });
        }

        // page_views.created_at → getConversionMetrics COUNT + WHERE
        if (Schema::hasTable('page_views')) {
            if (!$this->indexExists('page_views', 'page_views_created_at_index')) {
                Schema::table('page_views', function (Blueprint $table) {
                    $table->index('created_at');
                });
            }
        }

        // cart_items.user_id → getConversionMetrics DISTINCT count
        if (Schema::hasTable('cart_items')) {
            if (!$this->indexExists('cart_items', 'cart_items_user_id_index')) {
                Schema::table('cart_items', function (Blueprint $table) {
                    $table->index('user_id');
                });
            }
        }

        // products.status → getDashboardMetrics COUNT + getProductAnalytics WHERE
        if (!$this->indexExists('products', 'products_status_index')) {
            Schema::table('products', function (Blueprint $table) {
                $table->index('status');
            });
        }

        // products.view_count → getProductAnalytics ORDER BY
        if (!$this->indexExists('products', 'products_view_count_index')) {
            Schema::table('products', function (Blueprint $table) {
                $table->index('view_count');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tables = [
            'users' => ['users_role_index'],
            'payments' => ['payments_method_index', 'payments_created_at_index'],
            'abandoned_carts' => ['abandoned_carts_reminder_sent_index', 'abandoned_carts_last_active_at_index'],
            'order_items' => ['order_items_created_at_index'],
            'activity_logs' => ['activity_logs_created_at_index'],
        ];

        $extraTables = [
            'users' => ['users_created_at_index'],
            'orders' => ['orders_user_id_index'],
            'page_views' => ['page_views_created_at_index'],
            'cart_items' => ['cart_items_user_id_index'],
            'products' => ['products_status_index', 'products_view_count_index'],
        ];

        $allTables = array_merge_recursive($tables, $extraTables);

        foreach ($allTables as $table => $indexes) {
            if (!Schema::hasTable($table)) continue;
            foreach ($indexes as $index) {
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
