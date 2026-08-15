<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add performance indexes for the promotions getActive() query:
     *
     *   Promotion::with(['products:id,name,slug,price', 'categories:id,name,slug'])
     *       ->where('is_active', true)
     *       ->where(fn($q) => $q->whereNull('end_date')->orWhere('end_date', '>=', now()))
     *       ->orderBy('priority', 'desc')
     *       ->get();
     *
     * The composite (is_active, end_date, priority) index covers the WHERE filter
     * on is_active + the range condition on end_date.
     *
     * The separate (priority) index helps the ORDER BY clause when MySQL chooses
     * a different access path, and it's useful for admin sorting as well.
     *
     * Also adds a composite index on the pivot tables for the eager-loaded
     * relationships (products, categories) which are used in the same response.
     */
    public function up(): void
    {
        Schema::table('promotions', function (Blueprint $table) {
            // Composite index covering the most common storefront query:
            //   WHERE is_active = 1 AND (end_date IS NULL OR end_date >= NOW())
            //   ORDER BY priority DESC
            $table->index(['is_active', 'end_date', 'priority'], 'promotions_active_date_priority_idx');

            // Standalone priority index helps sorting when filtered by other criteria
            $table->index(['priority'], 'promotions_priority_idx');
        });

        // Pivot table indexes for the eager-loaded relationship joins
        // (already have UNIQUE(promotion_id, product_id) / UNIQUE(promotion_id, category_id)
        //  which serves as an index on promotion_id for these lookups — no additional
        //  indexes needed on the pivot tables)
    }

    public function down(): void
    {
        Schema::table('promotions', function (Blueprint $table) {
            $table->dropIndex('promotions_active_date_priority_idx');
            $table->dropIndex('promotions_priority_idx');
        });
    }
};
