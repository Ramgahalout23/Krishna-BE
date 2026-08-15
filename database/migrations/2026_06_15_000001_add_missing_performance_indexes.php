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
        // ── reviews indexes ──
        // is_flagged is used in almost all review queries to filter flagged reviews
        if (Schema::hasTable('reviews')) {
            if (!$this->indexExists('reviews', 'reviews_is_flagged_index')) {
                Schema::table('reviews', function (Blueprint $table) {
                    $table->index('is_flagged');
                });
            }

            // is_verified used in verifiedReviews() endpoint
            if (!$this->indexExists('reviews', 'reviews_is_verified_index')) {
                Schema::table('reviews', function (Blueprint $table) {
                    $table->index('is_verified');
                });
            }

            // created_at used in latest() ordering for reviews
            if (!$this->indexExists('reviews', 'reviews_created_at_index')) {
                Schema::table('reviews', function (Blueprint $table) {
                    $table->index('created_at');
                });
            }

            // Composite index for the common query pattern: product_id + is_flagged + is_moderated + created_at DESC
            if (!$this->indexExists('reviews', 'reviews_product_flagged_moderated_created_index')) {
                Schema::table('reviews', function (Blueprint $table) {
                    $table->index(['product_id', 'is_flagged', 'is_moderated', 'created_at'], 'reviews_product_flagged_moderated_created_index');
                });
            }
        }

        // ── notifications indexes ──
        if (Schema::hasTable('notifications')) {
            // created_at used in latest() ordering for notifications
            if (!$this->indexExists('notifications', 'notifications_created_at_index')) {
                Schema::table('notifications', function (Blueprint $table) {
                    $table->index('created_at');
                });
            }

            // Composite index for common query: user_id + is_read + created_at (getUnreadNotifications)
            if (!$this->indexExists('notifications', 'notifications_user_read_created_index')) {
                Schema::table('notifications', function (Blueprint $table) {
                    $table->index(['user_id', 'is_read', 'created_at'], 'notifications_user_read_created_index');
                });
            }
        }

        // ── support_tickets indexes ──
        if (Schema::hasTable('support_tickets')) {
            // order_id FK — used to find tickets by order
            if (!$this->indexExists('support_tickets', 'support_tickets_order_id_index')) {
                Schema::table('support_tickets', function (Blueprint $table) {
                    $table->index('order_id');
                });
            }

            // assigned_to FK — used to find tickets assigned to staff
            if (!$this->indexExists('support_tickets', 'support_tickets_assigned_to_index')) {
                Schema::table('support_tickets', function (Blueprint $table) {
                    $table->index('assigned_to');
                });
            }

            // category — used for filtering support tickets
            if (!$this->indexExists('support_tickets', 'support_tickets_category_index')) {
                Schema::table('support_tickets', function (Blueprint $table) {
                    $table->index('category');
                });
            }
        }

        // ── order_items indexes ──
        if (Schema::hasTable('order_items')) {
            // variant_id — used in batch stock restoration during order cancellation
            if (!$this->indexExists('order_items', 'order_items_variant_id_index')) {
                Schema::table('order_items', function (Blueprint $table) {
                    $table->index('variant_id');
                });
            }
        }

        // ── addresses indexes ──
        if (Schema::hasTable('addresses')) {
            // Composite index for getUserDefaultAddress() query: user_id + is_default
            if (!$this->indexExists('addresses', 'addresses_user_default_index')) {
                Schema::table('addresses', function (Blueprint $table) {
                    $table->index(['user_id', 'is_default'], 'addresses_user_default_index');
                });
            }
        }

        // ── campaigns indexes ──
        if (Schema::hasTable('campaigns')) {
            // status used in campaign listing/filtering
            if (!$this->indexExists('campaigns', 'campaigns_status_index')) {
                Schema::table('campaigns', function (Blueprint $table) {
                    $table->index('status');
                });
            }

            // Composite index for listing campaigns by status + created_at
            if (!$this->indexExists('campaigns', 'campaigns_status_created_at_index')) {
                Schema::table('campaigns', function (Blueprint $table) {
                    $table->index(['status', 'created_at'], 'campaigns_status_created_at_index');
                });
            }
        }

        // ── coupons indexes ──
        if (Schema::hasTable('coupons')) {
            // code used in getByCode lookups (already has unique probably, but ensure index)
            if (!$this->indexExists('coupons', 'coupons_code_index')) {
                Schema::table('coupons', function (Blueprint $table) {
                    $table->index('code');
                });
            }
        }

        // ── cart_items composite index for guest cart merge operations ──
        if (Schema::hasTable('cart_items')) {
            // session_id + user_id composite for guest cart merge queries
            if (!$this->indexExists('cart_items', 'cart_items_session_user_index')) {
                Schema::table('cart_items', function (Blueprint $table) {
                    $table->index(['session_id', 'user_id'], 'cart_items_session_user_index');
                });
            }

            // saved_for_later index for cart queries filtering by saved_for_later
            if (!Schema::hasColumn('cart_items', 'saved_for_later')) {
                // column might not exist on all environments
            } elseif (!$this->indexExists('cart_items', 'cart_items_saved_for_later_index')) {
                Schema::table('cart_items', function (Blueprint $table) {
                    $table->index('saved_for_later');
                });
            }
        }

        // ── user_events indexes ──
        if (Schema::hasTable('user_events')) {
            // event_name used for filtering events
            if (!$this->indexExists('user_events', 'user_events_event_name_index')) {
                Schema::table('user_events', function (Blueprint $table) {
                    $table->index('event_name');
                });
            }
        }

        // ── recently_viewed_products composite index ──
        if (Schema::hasTable('recently_viewed_products')) {
            // Composite index for common query: user_id + viewed_at DESC
            if (!$this->indexExists('recently_viewed_products', 'recently_viewed_user_viewed_index')) {
                Schema::table('recently_viewed_products', function (Blueprint $table) {
                    $table->index(['user_id', 'viewed_at'], 'recently_viewed_user_viewed_index');
                });
            }
        }

        // ── product_variants composite index for stock queries ──
        if (Schema::hasTable('product_variants')) {
            // Composite index for order creation stock checks: product_id + quantity
            if (!$this->indexExists('product_variants', 'product_variants_product_quantity_index')) {
                Schema::table('product_variants', function (Blueprint $table) {
                    $table->index(['product_id', 'quantity'], 'product_variants_product_quantity_index');
                });
            }
        }

        // ── wallet_transactions indexes ──
        if (Schema::hasTable('wallet_transactions')) {
            // created_at for ordering transactions
            if (!$this->indexExists('wallet_transactions', 'wallet_transactions_created_at_index')) {
                Schema::table('wallet_transactions', function (Blueprint $table) {
                    $table->index('created_at');
                });
            }
        }

        // ── loyalty_transactions indexes ──
        if (Schema::hasTable('loyalty_transactions')) {
            // created_at for ordering transactions
            if (!$this->indexExists('loyalty_transactions', 'loyalty_transactions_created_at_index')) {
                Schema::table('loyalty_transactions', function (Blueprint $table) {
                    $table->index('created_at');
                });
            }
        }

        // ── inventory_history indexes ──
        if (Schema::hasTable('inventory_history')) {
            // created_at for ordering history
            if (!$this->indexExists('inventory_history', 'inventory_history_created_at_index')) {
                Schema::table('inventory_history', function (Blueprint $table) {
                    $table->index('created_at');
                });
            }
        }
    }

    public function down(): void
    {
        $indexes = [
            'reviews' => [
                'reviews_is_flagged_index',
                'reviews_is_verified_index',
                'reviews_created_at_index',
                'reviews_product_flagged_moderated_created_index',
            ],
            'notifications' => [
                'notifications_created_at_index',
                'notifications_user_read_created_index',
            ],
            'support_tickets' => [
                'support_tickets_order_id_index',
                'support_tickets_assigned_to_index',
                'support_tickets_category_index',
            ],
            'order_items' => [
                'order_items_variant_id_index',
            ],
            'addresses' => [
                'addresses_user_default_index',
            ],
            'campaigns' => [
                'campaigns_status_index',
                'campaigns_status_created_at_index',
            ],
            'coupons' => [
                'coupons_code_index',
            ],
            'cart_items' => [
                'cart_items_session_user_index',
                'cart_items_saved_for_later_index',
            ],
            'user_events' => [
                'user_events_event_name_index',
            ],
            'recently_viewed_products' => [
                'recently_viewed_user_viewed_index',
            ],
            'product_variants' => [
                'product_variants_product_quantity_index',
            ],
            'wallet_transactions' => [
                'wallet_transactions_created_at_index',
            ],
            'loyalty_transactions' => [
                'loyalty_transactions_created_at_index',
            ],
            'inventory_history' => [
                'inventory_history_created_at_index',
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
