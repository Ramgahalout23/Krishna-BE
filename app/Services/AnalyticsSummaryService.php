<?php

namespace App\Services;

use App\Models\DailyAnalyticsSummary;
use App\Models\User;
use App\Models\Product;
use App\Models\Review;
use App\Models\PageView;
use App\Models\Coupon;
use Illuminate\Support\Facades\DB;

class AnalyticsSummaryService
{
    /**
     * Aggregate metrics for a given date and upsert into the summary table.
     */
    public function aggregateForDate(string $date): DailyAnalyticsSummary
    {
        $start = $date . ' 00:00:00';
        $end   = $date . ' 23:59:59';

        // ── Orders ──
        $orders = DB::table('orders')
            ->selectRaw('COUNT(*) as total')
            ->selectRaw("SUM(CASE WHEN status IN ('DELIVERED', 'CONFIRMED') THEN 1 ELSE 0 END) as delivered")
            ->selectRaw("SUM(CASE WHEN status = 'CONFIRMED' THEN 1 ELSE 0 END) as confirmed")
            ->selectRaw("SUM(CASE WHEN status = 'PENDING' THEN 1 ELSE 0 END) as pending")
            ->selectRaw("SUM(CASE WHEN status = 'CANCELLED' THEN 1 ELSE 0 END) as cancelled")
            ->selectRaw('COALESCE(SUM(total), 0) as revenue')
            ->selectRaw('COALESCE(SUM(CASE WHEN discount > 0 THEN discount ELSE 0 END), 0) as discount')
            ->whereBetween('created_at', [$start, $end])
            ->first();

        $totalOrders    = (int) ($orders->total ?? 0);
        $deliveredOrders = (int) ($orders->delivered ?? 0);
        $confirmedOrders = (int) ($orders->confirmed ?? 0);
        $pendingOrders   = (int) ($orders->pending ?? 0);
        $cancelledOrders = (int) ($orders->cancelled ?? 0);
        $totalRevenue    = (float) ($orders->revenue ?? 0);
        $totalDiscount   = (float) ($orders->discount ?? 0);
        $avgOrderValue   = $totalOrders > 0 ? round($totalRevenue / $totalOrders, 2) : 0;

        // ── Users ──
        $newUsers = User::whereBetween('created_at', [$start, $end])->count();
        $totalUsers = User::count();
        $verifiedUsers = User::where('is_email_verified', true)->count();
        $activeUsers = User::where('is_active', true)->count();

        // ── Products ──
        $totalProducts = Product::count();
        $publishedProducts = Product::where('status', 'PUBLISHED')->count();

        // ── Reviews ──
        $reviews = Review::whereBetween('created_at', [$start, $end])
            ->selectRaw('COUNT(*) as total')
            ->selectRaw("SUM(CASE WHEN is_moderated = 1 AND is_flagged = 0 THEN 1 ELSE 0 END) as approved")
            ->selectRaw("SUM(CASE WHEN is_moderated = 0 AND is_flagged = 0 THEN 1 ELSE 0 END) as pending")
            ->selectRaw("SUM(CASE WHEN is_flagged = 1 THEN 1 ELSE 0 END) as rejected")
            ->selectRaw("COALESCE(AVG(CASE WHEN is_moderated = 1 AND is_flagged = 0 THEN rating END), 0) as avg_rating")
            ->selectRaw("SUM(CASE WHEN rating = 5 AND is_moderated = 1 AND is_flagged = 0 THEN 1 ELSE 0 END) as r5")
            ->selectRaw("SUM(CASE WHEN rating = 4 AND is_moderated = 1 AND is_flagged = 0 THEN 1 ELSE 0 END) as r4")
            ->selectRaw("SUM(CASE WHEN rating = 3 AND is_moderated = 1 AND is_flagged = 0 THEN 1 ELSE 0 END) as r3")
            ->selectRaw("SUM(CASE WHEN rating = 2 AND is_moderated = 1 AND is_flagged = 0 THEN 1 ELSE 0 END) as r2")
            ->selectRaw("SUM(CASE WHEN rating = 1 AND is_moderated = 1 AND is_flagged = 0 THEN 1 ELSE 0 END) as r1")
            ->first();

        $newReviews      = (int) ($reviews->total ?? 0);
        $approvedReviews = (int) ($reviews->approved ?? 0);
        $pendingReviews  = (int) ($reviews->pending ?? 0);
        $rejectedReviews = (int) ($reviews->rejected ?? 0);
        $avgRating       = round((float) ($reviews->avg_rating ?? 0), 2);

        // ── Page views / tracking ──
        $pageViews = PageView::whereBetween('created_at', [$start, $end])->count();
        $uniqueVisitors = PageView::whereBetween('created_at', [$start, $end])
            ->distinct('session_id')
            ->count('session_id');

        // ── Coupons ──
        $activeCoupons = Coupon::where('is_active', true)->count();

        // ── Upsert ──
        return DailyAnalyticsSummary::updateOrCreate(
            ['date' => $date],
            [
                // Orders
                'total_orders'      => $totalOrders,
                'delivered_orders'  => $deliveredOrders,
                'confirmed_orders'  => $confirmedOrders,
                'pending_orders'    => $pendingOrders,
                'cancelled_orders'  => $cancelledOrders,
                'total_revenue'     => $totalRevenue,
                'total_discount'    => $totalDiscount,
                'avg_order_value'   => $avgOrderValue,
                // Users
                'new_users'          => $newUsers,
                'total_users'        => $totalUsers,
                'email_verified_users' => $verifiedUsers,
                'active_users'       => $activeUsers,
                // Products
                'total_products'     => $totalProducts,
                'published_products' => $publishedProducts,
                // Reviews
                'new_reviews'        => $newReviews,
                'approved_reviews'   => $approvedReviews,
                'pending_reviews'    => $pendingReviews,
                'rejected_reviews'   => $rejectedReviews,
                'avg_rating'         => $avgRating,
                'rating_5_count'     => (int) ($reviews->r5 ?? 0),
                'rating_4_count'     => (int) ($reviews->r4 ?? 0),
                'rating_3_count'     => (int) ($reviews->r3 ?? 0),
                'rating_2_count'     => (int) ($reviews->r2 ?? 0),
                'rating_1_count'     => (int) ($reviews->r1 ?? 0),
                // Page views
                'page_views'         => $pageViews,
                'unique_visitors'    => $uniqueVisitors,
                // Coupons
                'active_coupons'     => $activeCoupons,
            ]
        );
    }

    /**
     * Aggregate the last N days (including today).
     * Returns the number of days aggregated.
     */
    public function aggregateLastDays(int $days): int
    {
        $count = 0;
        for ($i = 0; $i < $days; $i++) {
            $date = now()->subDays($i)->format('Y-m-d');
            $this->aggregateForDate($date);
            $count++;
        }
        return $count;
    }

    /**
     * Aggregate a date range (inclusive).
     */
    public function aggregateRange(string $startDate, string $endDate): int
    {
        $start = \Carbon\Carbon::parse($startDate);
        $end   = \Carbon\Carbon::parse($endDate);
        $count = 0;

        for ($date = $start; $date->lte($end); $date->addDay()) {
            $this->aggregateForDate($date->format('Y-m-d'));
            $count++;
        }

        return $count;
    }

    // ── Read methods ──

    /**
     * Get all summary rows between two dates (inclusive).
     */
    public function getSummaryBetween(string $startDate, string $endDate)
    {
        return DailyAnalyticsSummary::between($startDate, $endDate)
            ->orderBy('date')
            ->get();
    }

    /**
     * Get aggregated totals across a date range.
     * Returns a single row of summed metrics.
     */
    public function getTotalsForRange(string $startDate, string $endDate): array
    {
        $rows = DailyAnalyticsSummary::between($startDate, $endDate)->get();

        if ($rows->isEmpty()) {
            return $this->emptyTotalsArray();
        }

        $sum = $rows->reduce(function ($carry, $row) {
            $carry['total_orders']     += $row->total_orders;
            $carry['delivered_orders'] += $row->delivered_orders;
            $carry['pending_orders']   += $row->pending_orders;
            $carry['cancelled_orders'] += $row->cancelled_orders;
            $carry['total_revenue']    += $row->total_revenue;
            $carry['total_discount']   += $row->total_discount;
            $carry['new_users']        += $row->new_users;
            $carry['new_reviews']      += $row->new_reviews;
            $carry['approved_reviews'] += $row->approved_reviews;
            $carry['pending_reviews']  += $row->pending_reviews;
            $carry['rejected_reviews'] += $row->rejected_reviews;
            $carry['page_views']       += $row->page_views;
            $carry['unique_visitors']  += $row->unique_visitors;
            return $carry;
        }, $this->emptyTotalsArray());

        $sum['avg_order_value'] = $sum['total_orders'] > 0
            ? round($sum['total_revenue'] / $sum['total_orders'], 2)
            : 0;

        // Use the latest row's running totals for users/products/coupons
        $latest = $rows->last();
        $sum['total_users']        = $latest->total_users;
        $sum['email_verified_users'] = $latest->email_verified_users;
        $sum['active_users']       = $latest->active_users;
        $sum['total_products']     = $latest->total_products;
        $sum['published_products'] = $latest->published_products;
        $sum['active_coupons']     = $latest->active_coupons;

        return $sum;
    }

    /**
     * Get all-time dashboard metrics.
     *
     * All-time COUNT/SUM queries use direct source table queries (they're fast —
     * no GROUP BY, simple index scans). Running totals (users, products, coupons)
     * use the latest summary row.
     */
    /**
     * Get dashboard metrics, optionally filtered by date range.
     *
     * When $startDate and $endDate are provided, reads from the pre-aggregated
     * daily_analytics_summary table for efficient date-range queries.
     * When omitted, uses all-time source table queries.
     *
     * Returns fields matching frontend expectations:
     *   totalRevenue, totalOrders, totalUsers, totalProducts, pendingOrders,
     *   totalReviews, totalCoupons, avgOrderValue, activeUsers, ordersToday,
     *   pendingReviews, lowStockCount, newUsers, revenueChangePercent, ordersChangePercent
     */
    public function getDashboardMetrics(?string $startDate = null, ?string $endDate = null): array
    {
        // Count low-stock products (quantity <= 5 or available_quantity <= 5)
        $lowStockCount = \App\Models\Product::where('status', 'PUBLISHED')
            ->where(function ($q) {
                $q->where('quantity', '<=', 5)
                  ->orWhereHas('inventory', function ($iq) {
                      $iq->where('available_quantity', '<=', 5);
                  });
            })->count();

        // If a date range is specified, use the pre-aggregated summary table
        if ($startDate && $endDate) {
            $totals = $this->getTotalsForRange($startDate, $endDate);

            $ordersToday = \App\Models\Order::whereDate('created_at', today())->count();

            return [
                'totalRevenue'         => $totals['total_revenue'],
                'totalOrders'          => $totals['total_orders'],
                'totalUsers'           => $totals['total_users'],
                'totalProducts'        => $totals['published_products'],
                'pendingOrders'        => $totals['pending_orders'],
                'totalReviews'         => $totals['approved_reviews'] + $totals['pending_reviews'] + $totals['rejected_reviews'],
                'totalCoupons'         => $totals['active_coupons'],
                'avgOrderValue'        => $totals['avg_order_value'],
                'activeUsers'          => $totals['active_users'],
                'ordersToday'          => $ordersToday,
                'pendingReviews'       => $totals['pending_reviews'],
                'lowStockCount'        => $lowStockCount,
                'newUsers'             => $totals['new_users'],
                'revenueChangePercent' => null,
                'ordersChangePercent'  => null,
            ];
        }

        // All-time simple COUNT/SUM queries — fast, index-only, no GROUP BY
        $totalRevenue  = (float) \App\Models\Order::whereIn('status', ['DELIVERED', 'CONFIRMED'])->sum('total');
        $totalOrders   = \App\Models\Order::count();
        $pendingOrders = \App\Models\Order::where('status', 'PENDING')->count();
        $totalReviews  = \App\Models\Review::count();
        $pendingReviews = \App\Models\Review::where('is_moderated', false)->where('is_flagged', false)->count();
        $ordersToday   = \App\Models\Order::whereDate('created_at', today())->count();
        $newUsers      = \App\Models\User::whereDate('created_at', today())->count();

        // Running totals from the latest summary row
        $latest = DailyAnalyticsSummary::latestFirst()->first(['total_users', 'published_products', 'active_coupons', 'active_users']);

        $avgOrderValue = $totalOrders > 0 ? round($totalRevenue / $totalOrders, 2) : 0;

        $totalUsers    = $latest?->total_users ?? \App\Models\User::count();
        $activeUsers   = $latest?->active_users ?? \App\Models\User::where('is_active', true)->count();

        return [
            'totalRevenue'         => $totalRevenue,
            'totalOrders'          => $totalOrders,
            'totalUsers'           => $totalUsers,
            'totalProducts'        => $latest?->published_products ?? \App\Models\Product::where('status', 'PUBLISHED')->count(),
            'pendingOrders'        => $pendingOrders,
            'totalReviews'         => $totalReviews,
            'totalCoupons'         => $latest?->active_coupons ?? \App\Models\Coupon::where('is_active', true)->count(),
            'avgOrderValue'        => $avgOrderValue,
            'activeUsers'          => $activeUsers,
            'ordersToday'          => $ordersToday,
            'pendingReviews'       => $pendingReviews,
            'lowStockCount'        => $lowStockCount,
            'newUsers'             => $newUsers,
            'revenueChangePercent' => null,
            'ordersChangePercent'  => null,
        ];
    }

    /**
     * Get daily trend data from summary table (last N days).
     */
    public function getDailyTrend(string $startDate, string $endDate): array
    {
        return DailyAnalyticsSummary::between($startDate, $endDate)
            ->orderBy('date')
            ->get()
            ->map(fn($row) => [
                'date'       => $row->date->format('Y-m-d'),
                'orders'     => $row->total_orders,
                'revenue'    => (float) $row->total_revenue,
                'discounts'  => (float) $row->total_discount,
            ])
            ->toArray();
    }

    /**
     * Get monthly trend data from summary table (last N months).
     */
    public function getMonthlyTrend(int $months): array
    {
        $start = now()->subMonths($months)->startOfMonth()->format('Y-m-d');
        $end   = now()->format('Y-m-d');

        $rows = DailyAnalyticsSummary::between($start, $end)
            ->orderBy('date')
            ->get();

        // Group by month and aggregate
        $monthly = [];
        foreach ($rows as $row) {
            $key = $row->date->format('Y-m');
            if (!isset($monthly[$key])) {
                $monthly[$key] = ['total' => 0, 'approved' => 0, 'avg_rating_sum' => 0, 'avg_rating_count' => 0];
            }
            $monthly[$key]['total'] += $row->new_reviews;
            $monthly[$key]['approved'] += $row->approved_reviews;
            $monthly[$key]['avg_rating_sum'] += $row->avg_rating * $row->approved_reviews;
            $monthly[$key]['avg_rating_count'] += $row->approved_reviews;
        }

        $result = [];
        foreach ($monthly as $month => $data) {
            $result[] = [
                'month'      => $month,
                'total'      => $data['total'],
                'avg_rating' => $data['avg_rating_count'] > 0
                    ? round($data['avg_rating_sum'] / $data['avg_rating_count'], 2)
                    : 0,
            ];
        }

        // Sort by month
        ksort($result);
        return array_values($result);
    }

    // ── Helpers ──

    private function emptyTotalsArray(): array
    {
        return [
            'total_orders'       => 0,
            'delivered_orders'   => 0,
            'pending_orders'     => 0,
            'cancelled_orders'   => 0,
            'total_revenue'      => 0.0,
            'total_discount'     => 0.0,
            'avg_order_value'    => 0.0,
            'new_users'          => 0,
            'total_users'        => 0,
            'email_verified_users' => 0,
            'active_users'       => 0,
            'total_products'     => 0,
            'published_products' => 0,
            'new_reviews'        => 0,
            'approved_reviews'   => 0,
            'pending_reviews'    => 0,
            'rejected_reviews'   => 0,
            'page_views'         => 0,
            'unique_visitors'    => 0,
            'active_coupons'     => 0,
        ];
    }
}
