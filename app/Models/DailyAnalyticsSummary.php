<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DailyAnalyticsSummary extends Model
{
    protected $table = 'daily_analytics_summary';

    protected $fillable = [
        'date',
        // Orders
        'total_orders', 'delivered_orders', 'confirmed_orders', 'pending_orders', 'cancelled_orders',
        'total_revenue', 'total_discount', 'avg_order_value',
        // Users
        'new_users', 'total_users', 'email_verified_users', 'active_users',
        // Products
        'total_products', 'published_products',
        // Reviews
        'new_reviews', 'approved_reviews', 'pending_reviews', 'rejected_reviews',
        'avg_rating', 'rating_5_count', 'rating_4_count', 'rating_3_count', 'rating_2_count', 'rating_1_count',
        // Page Views
        'page_views', 'unique_visitors',
        // Coupons
        'active_coupons',
    ];

    protected $casts = [
        'date' => 'date:Y-m-d',
        'total_orders' => 'integer',
        'delivered_orders' => 'integer',
        'confirmed_orders' => 'integer',
        'pending_orders' => 'integer',
        'cancelled_orders' => 'integer',
        'total_revenue' => 'decimal:2',
        'total_discount' => 'decimal:2',
        'avg_order_value' => 'decimal:2',
        'new_users' => 'integer',
        'total_users' => 'integer',
        'email_verified_users' => 'integer',
        'active_users' => 'integer',
        'total_products' => 'integer',
        'published_products' => 'integer',
        'new_reviews' => 'integer',
        'approved_reviews' => 'integer',
        'pending_reviews' => 'integer',
        'rejected_reviews' => 'integer',
        'avg_rating' => 'decimal:2',
        'rating_5_count' => 'integer',
        'rating_4_count' => 'integer',
        'rating_3_count' => 'integer',
        'rating_2_count' => 'integer',
        'rating_1_count' => 'integer',
        'page_views' => 'integer',
        'unique_visitors' => 'integer',
        'active_coupons' => 'integer',
    ];

    /**
     * Scope: get summaries between two dates (inclusive).
     */
    public function scopeBetween(\Illuminate\Database\Eloquent\Builder $query, string $start, string $end): \Illuminate\Database\Eloquent\Builder
    {
        return $query->whereBetween('date', [$start, $end]);
    }

    /**
     * Scope: get summaries for a specific month.
     */
    public function scopeForMonth(\Illuminate\Database\Eloquent\Builder $query, int $year, int $month): \Illuminate\Database\Eloquent\Builder
    {
        return $query->whereYear('date', $year)->whereMonth('date', $month);
    }

    /**
     * Scope: get summaries ordered by date descending.
     */
    public function scopeLatestFirst(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->orderBy('date', 'desc');
    }
}
