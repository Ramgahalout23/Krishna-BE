<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add missing composite indexes identified in the performance index audit.
     *
     * Existing coverage already includes:
     *   - orders(user_id, created_at)        ← migration 2026_07_10_000002
     *   - wishlist_items(user_id, product_id) ← migration 2024_01_01_000001
     *   - reviews product composite           ← migration 2026_06_15_000001 (incl. is_flagged)
     *
     * This migration adds the remaining high-impact composites:
     *   1. order_items(product_id, created_at)     — admin top-products dashboard
     *   2. users(email, is_active)                 — auth/login lookups
     *   3. notifications(user_id, type, is_read)   — filter by type + read status
     *   4. reviews(product_id, is_moderated, created_at) — product review pages
     *   5. product_variants(product_id, price)     — variant price-range queries
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

    public function up(): void
    {
        // ── 1. order_items(product_id, created_at) ──
        // Query: OrderItem::select('product_id', ...)->groupBy('product_id')
        //         ->orderByDesc(...)->take($limit)  ← admin top-products dashboard
        // Used in AdminRepository::getProductAnalytics() to rank top-sold products.
        if (Schema::hasTable('order_items')) {
            if (!$this->indexExists('order_items', 'order_items_product_created_index')) {
                Schema::table('order_items', function (Blueprint $table) {
                    $table->index(['product_id', 'created_at'], 'order_items_product_created_index');
                });
            }
        }

        // ── 2. users(email, is_active) ──
        // Query: User::where('email', $email)->where('is_active', true)->first()
        // Used in Auth/Login flows — the most common user lookup pattern.
        // Individual indexes on email and is_active exist, but a composite
        // index allows both WHERE conditions to be resolved in a single seek.
        if (Schema::hasTable('users')) {
            if (!$this->indexExists('users', 'users_email_active_index')) {
                Schema::table('users', function (Blueprint $table) {
                    $table->index(['email', 'is_active'], 'users_email_active_index');
                });
            }
        }

        // ── 3. notifications(user_id, type, is_read) ──
        // Query: UserNotification::where('user_id', $id)->where('type', $type)
        //         ->where('is_read', false)->latest()
        // Used in NotificationRepository::getNotificationsByType() + unread filtering.
        // Existing composite indexes cover (user_id, type, created_at) and
        // (user_id, is_read, created_at), but not the combined three-column filter.
        if (Schema::hasTable('notifications')) {
            if (!$this->indexExists('notifications', 'notifications_user_type_read_index')) {
                Schema::table('notifications', function (Blueprint $table) {
                    $table->index(['user_id', 'type', 'is_read'], 'notifications_user_type_read_index');
                });
            }
        }

        // ── 4. reviews(product_id, is_moderated, created_at) ──
        // Query: Review::where('product_id', $id)->where('is_moderated', true)
        //         ->orderBy('created_at', 'desc')->paginate()
        // Used in ReviewRepository::getProductReviews() and storefront review display.
        // Note: existing reviews_product_flagged_moderated_created_index includes
        // is_flagged between product_id and is_moderated, so queries that don't
        // filter by is_flagged can't use it as a composite covering index.
        if (Schema::hasTable('reviews')) {
            if (!$this->indexExists('reviews', 'reviews_product_moderated_created_index')) {
                Schema::table('reviews', function (Blueprint $table) {
                    $table->index(['product_id', 'is_moderated', 'created_at'], 'reviews_product_moderated_created_index');
                });
            }
        }

        // ── 5. product_variants(product_id, price) ──
        // Query: ProductVariant::where('product_id', $id)->orderBy('price')
        // Used in ProductRepository::getVariantsByProduct() and price-range filtering
        // on product detail pages.
        if (Schema::hasTable('product_variants')) {
            if (!$this->indexExists('product_variants', 'product_variants_product_price_index')) {
                Schema::table('product_variants', function (Blueprint $table) {
                    $table->index(['product_id', 'price'], 'product_variants_product_price_index');
                });
            }
        }
    }

    public function down(): void
    {
        $indexes = [
            'order_items'      => ['order_items_product_created_index'],
            'users'            => ['users_email_active_index'],
            'notifications'    => ['notifications_user_type_read_index'],
            'reviews'          => ['reviews_product_moderated_created_index'],
            'product_variants' => ['product_variants_product_price_index'],
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
