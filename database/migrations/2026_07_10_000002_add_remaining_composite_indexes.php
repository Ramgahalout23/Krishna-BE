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
        // ── 1. orders(user_id, created_at) ──
        // Query: Order::where('user_id', $userId)->latest()->paginate()
        // Used in OrderRepository::getUserOrders() and similar user order history queries.
        if (Schema::hasTable('orders')) {
            if (!$this->indexExists('orders', 'orders_user_created_index')) {
                Schema::table('orders', function (Blueprint $table) {
                    $table->index(['user_id', 'created_at'], 'orders_user_created_index');
                });
            }
        }

        // ── 2. payments(order_id, status) ──
        // Query: Payment::where('order_id', $id)->where('status', 'COMPLETED')
        // Used in PaymentRepository::findByOrder(), order status checks, and refund lookups.
        if (Schema::hasTable('payments')) {
            if (!$this->indexExists('payments', 'payments_order_status_index')) {
                Schema::table('payments', function (Blueprint $table) {
                    $table->index(['order_id', 'status'], 'payments_order_status_index');
                });
            }
        }

        // ── 3. order_items(order_id, product_id) ──
        // Query: OrderItem::where('order_id', $id)->with('product')
        // Used in OrderRepository::getOrderItems(), order detail views, and stock restoration.
        if (Schema::hasTable('order_items')) {
            if (!$this->indexExists('order_items', 'order_items_order_product_index')) {
                Schema::table('order_items', function (Blueprint $table) {
                    $table->index(['order_id', 'product_id'], 'order_items_order_product_index');
                });
            }
        }

        // ── 4. refunds(payment_id, status) ──
        // Query: Refund::where('payment_id', $id)->latest()
        // Used in PaymentRepository::getRefundsByPaymentId()
        if (Schema::hasTable('refunds')) {
            if (!$this->indexExists('refunds', 'refunds_payment_status_index')) {
                Schema::table('refunds', function (Blueprint $table) {
                    $table->index(['payment_id', 'status'], 'refunds_payment_status_index');
                });
            }

            // created_at for latest() ordering on refunds
            if (!$this->indexExists('refunds', 'refunds_created_at_index')) {
                Schema::table('refunds', function (Blueprint $table) {
                    $table->index('created_at');
                });
            }
        }

        // ── 5. coupon_usages(coupon_id, user_id) ──
        // Query: CouponUsage::where('coupon_id', $id)->where('user_id', $userId)
        // Used in CouponService::validate() for per-user usage limit checks
        if (Schema::hasTable('coupon_usages')) {
            if (!$this->indexExists('coupon_usages', 'coupon_usages_coupon_user_index')) {
                Schema::table('coupon_usages', function (Blueprint $table) {
                    $table->index(['coupon_id', 'user_id'], 'coupon_usages_coupon_user_index');
                });
            }
        }

        // ── 6. abandoned_carts(user_id, recovered) ──
        // Query: AbandonedCart::where('user_id', $id)->latest()
        // Used in AbandonedCartRepository::getUserAbandonedCarts()
        if (Schema::hasTable('abandoned_carts')) {
            if (!$this->indexExists('abandoned_carts', 'abandoned_carts_user_recovered_index')) {
                Schema::table('abandoned_carts', function (Blueprint $table) {
                    $table->index(['user_id', 'recovered'], 'abandoned_carts_user_recovered_index');
                });
            }
        }

        // ── 7. subscribers(status, created_at) ──
        // Query: Subscriber::where('status', 'ACTIVE')->latest()->paginate()
        // Used in MarketingRepository::findAllSubscribers() and findWhatsAppRecipients()
        if (Schema::hasTable('subscribers')) {
            if (!$this->indexExists('subscribers', 'subscribers_status_created_index')) {
                Schema::table('subscribers', function (Blueprint $table) {
                    $table->index(['status', 'created_at'], 'subscribers_status_created_index');
                });
            }
        }
    }

    public function down(): void
    {
        $indexes = [
            'orders'           => ['orders_user_created_index'],
            'payments'         => ['payments_order_status_index'],
            'order_items'      => ['order_items_order_product_index'],
            'refunds'          => ['refunds_payment_status_index', 'refunds_created_at_index'],
            'coupon_usages'    => ['coupon_usages_coupon_user_index'],
            'abandoned_carts'  => ['abandoned_carts_user_recovered_index'],
            'subscribers'      => ['subscribers_status_created_index'],
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
