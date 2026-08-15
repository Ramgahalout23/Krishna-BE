<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add indexes that are truly missing after auditing all tables.
     *
     * Products/orders/categories already have indexes on slug, status, category_id, created_at.
     * This migration fills the remaining gaps found during performance audit.
     */
    public function up(): void
    {
        // users: index on created_at for customer growth analytics
        // (WHERE created_at >= ? GROUP BY month)
        Schema::table('users', function (Blueprint $table) {
            $table->index('created_at', 'users_created_at_index');
        });

        // payments: composite index on (method, created_at) for payment method trends
        // (WHERE method = ? AND created_at >= ? GROUP BY date)
        Schema::table('payments', function (Blueprint $table) {
            $table->index(['method', 'created_at'], 'payments_method_created_at_index');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('users_created_at_index');
        });
        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex('payments_method_created_at_index');
        });
    }
};
