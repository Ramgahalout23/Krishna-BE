<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add the last batch of missing performance indexes identified during
     * the comprehensive index audit:
     *
     * 1. webhooks.is_active
     *    Query: Webhook::where('is_active', true)->get()
     *    Called from WebhookService on every order creation/status update.
     *
     * 2. product_images(product_id, display_order)
     *    Query: ProductImage::where('product_id', $id)->orderBy('display_order')
     *    Used in HomepageController, ProductRepository, ReelController, etc.
     *
     * 3. support_tickets(user_id, created_at)
     *    Query: SupportTicket::where('user_id', $userId)->latest()->get()
     *    Used in TicketRepository::getUserTickets().
     */
    public function up(): void
    {
        // ── 1. webhooks.is_active ──
        if (Schema::hasTable('webhooks')) {
            Schema::table('webhooks', function (Blueprint $table) {
                $table->index('is_active');
            });
        }

        // ── 2. product_images(product_id, display_order) ──
        if (Schema::hasTable('product_images')) {
            Schema::table('product_images', function (Blueprint $table) {
                $table->index(['product_id', 'display_order']);
            });
        }

        // ── 3. support_tickets(user_id, created_at) ──
        if (Schema::hasTable('support_tickets')) {
            Schema::table('support_tickets', function (Blueprint $table) {
                $table->index(['user_id', 'created_at']);
            });
        }
    }

    /**
     * Reverse the indexes.
     */
    public function down(): void
    {
        if (Schema::hasTable('webhooks')) {
            Schema::table('webhooks', function (Blueprint $table) {
                $table->dropIndex(['is_active']);
            });
        }

        if (Schema::hasTable('product_images')) {
            Schema::table('product_images', function (Blueprint $table) {
                $table->dropIndex(['product_id', 'display_order']);
            });
        }

        if (Schema::hasTable('support_tickets')) {
            Schema::table('support_tickets', function (Blueprint $table) {
                $table->dropIndex(['user_id', 'created_at']);
            });
        }
    }
};
