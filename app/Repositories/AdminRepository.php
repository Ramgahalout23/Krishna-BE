<?php

namespace App\Repositories;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Models\Coupon;
use App\Models\Banner;
use App\Models\Review;
use App\Models\Category;
use App\Models\Brand;
use App\Models\Inventory;
use App\Models\Promotion;
use App\Models\SupportTicket;
use App\Models\TicketMessage;
use App\Models\AbandonedCart;
use App\Models\ProductVariant;
use App\Models\InventoryHistory;
use App\Models\UserNotification;
use App\Services\AnalyticsSummaryService;
use App\Traits\CacheKeyRegistry;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class AdminRepository
{
    use CacheKeyRegistry;

    public function __construct(
        protected AnalyticsSummaryService $analyticsSummary
    ) {}

    // ── Dashboard ──

    public function getDashboardMetrics(?string $startDate = null, ?string $endDate = null): array
    {
        // Don't cache when date-filtered — cache key depends on range
        if ($startDate && $endDate) {
            return $this->analyticsSummary->getDashboardMetrics($startDate, $endDate);
        }

        return $this->cacheWithTracking('admin_dashboard_metrics', 300, function () {
            // Read from pre-aggregated summary table instead of scanning source tables
            return $this->analyticsSummary->getDashboardMetrics();
        });
    }

    public function getRecentOrders(int $limit = 5): Collection
    {
        return $this->cacheWithTracking("admin_recent_orders_{$limit}", 300, function () use ($limit) {
            return Order::with('user')->latest()->take($limit)->get();
        });
    }

    public function clearDashboardCache(): void
    {
        $this->clearTrackedCache();
    }

    /**
     * Pre-warm ALL dashboard and analytics caches.
     * Call this after seeding or deployment so the first user request is fast.
     *
     * First aggregates source data into daily_analytics_summary (1 scan per table),
     * then warms all cached analytics methods from the summary table.
     */
    public function warmDashboardCache(): void
    {
        // Step 1: Pre-aggregate last 30 days into summary table
        // This is a single scan per table (orders, users, reviews, page_views)
        // instead of the 15+ separate scans each getter would do
        $this->analyticsSummary->aggregateLastDays(30);

        // Step 2: Warm all cached analytics from the summary table
        $this->getDashboardMetrics();
        $this->getRecentOrders(5);
        $this->getRecentUsers(5);
        $this->getCombinedDailyTrend(7);
        $this->getCombinedDailyTrend(30);
        $this->getOrderStatusDistribution();
        $this->getHourlyDistribution();
        $this->getRevenueComparison();
        $this->getCustomerGrowth(12);
        $this->getConversionMetrics();
        $this->getPaymentMethodTrends(30);
        $this->getPaymentMethodStats();
        $this->getUserAnalytics();
        $this->getOrderRevenueStats();
        $this->getLowStockVariants();
    }

    // ── Analytics ──

    /**
     * Combined daily trend cache — reads from summary table ONCE per cache window.
     * Returns enriched rows with all fields needed by the 3 trend endpoints.
     */
    private function getCombinedDailyTrend(int $days = 30): array
    {
        return $this->cacheWithTracking("admin_daily_trend_combined_{$days}", 300, function () use ($days) {
            $start = now()->subDays($days)->format('Y-m-d');
            $end   = now()->format('Y-m-d');
            $rows  = $this->analyticsSummary->getDailyTrend($start, $end);

            return array_map(fn($row) => [
                'date'            => $row['date'],
                'orders'          => $row['orders'],
                'order_count'     => $row['orders'],
                'revenue'         => $row['revenue'],
                'discounts'       => $row['discounts'],
                'avg_order_value' => $row['orders'] > 0
                    ? round($row['revenue'] / $row['orders'], 2)
                    : 0,
            ], $rows);
        });
    }

    public function getSalesAnalytics(int $days = 30): array
    {
        $rows = $this->getCombinedDailyTrend($days);
        return array_map(fn($row) => [
            'date'            => $row['date'],
            'order_count'     => $row['order_count'],
            'revenue'         => $row['revenue'],
            'avg_order_value' => $row['avg_order_value'],
        ], $rows);
    }

    public function getDailySales(int $days = 30): array
    {
        $rows = $this->getCombinedDailyTrend($days);
        return array_map(fn($row) => [
            'date'      => $row['date'],
            'orders'    => $row['orders'],
            'revenue'   => $row['revenue'],
            'discounts' => $row['discounts'],
        ], $rows);
    }

    public function getRevenueTrends(int $days = 30): array
    {
        $rows = $this->getCombinedDailyTrend($days);
        return array_map(fn($row) => [
            'date'    => $row['date'],
            'revenue' => $row['revenue'],
        ], $rows);
    }

    /**
     * Get low stock / out of stock variants for dashboard alerts.
     * Cached for 300s to match other dashboard cache durations.
     */
    public function getLowStockVariants(): array
    {
        return $this->cacheWithTracking('admin_low_stock_variants', 300, function () {
            return ProductVariant::with('product:id,name')
                ->where('quantity', '<=', 5)
                ->orderBy('quantity')
                ->take(20)
                ->get()
                ->toArray();
        });
    }

    public function getProductAnalytics(int $limit = 20): Collection
    {
        return $this->cacheWithTracking("admin_product_analytics_{$limit}", 300, function () use ($limit) {
            $products = Product::withCount('orderItems as sales_count')
                ->where('status', 'PUBLISHED')
                ->orderByDesc('view_count')
                ->orderByDesc('sales_count')
                ->take($limit)
                ->get();

            // Map to frontend-expected format
            return $products->map(fn($p) => (object) [
                'productName' => $p->name,
                'unitsSold'   => (int) ($p->sales_count ?? 0),
                'revenue'     => (float) ($p->sales_count * $p->price ?? 0),
                'name'        => $p->name,
                'sales_count' => (int) ($p->sales_count ?? 0),
                'price'       => (float) $p->price,
                'view_count'  => (int) $p->view_count,
            ]);
        });
    }

    public function getUserAnalytics(): array
    {
        return $this->cacheWithTracking('admin_user_analytics', 300, function () {
            $totalUsers = User::count();
            $newUsersToday = User::whereDate('created_at', today())->count();
            $newUsersThisMonth = User::whereMonth('created_at', now()->month)->count();
            $verifiedUsers = User::where('is_email_verified', true)->count();
            $activeUsers = User::where('is_active', true)->count();

            $usersByRole = User::select('role', DB::raw('COUNT(*) as count'))
                ->groupBy('role')
                ->pluck('count', 'role')
                ->toArray();

            return compact('totalUsers', 'newUsersToday', 'newUsersThisMonth', 'verifiedUsers', 'activeUsers', 'usersByRole');
        });
    }

    public function getOrderStatusDistribution(): array
    {
        return $this->cacheWithTracking('admin_order_status_dist', 300, function () {
            $rows = Order::select('status', DB::raw('COUNT(*) as count'), DB::raw('SUM(total) as total'))
                ->groupBy('status')
                ->get();

            // Return array format matching frontend expectations: [{name, value}]
            return $rows->map(fn($r) => [
                'name'  => $r->status,
                'value' => (int) $r->count,
                'total' => (float) $r->total,
            ])->toArray();
        });
    }

    public function getPaymentMethodStats(): array
    {
        return $this->cacheWithTracking('admin_payment_method_stats', 300, function () {
            $rows = \App\Models\Payment::select('method as payment_method', DB::raw('COUNT(*) as count'), DB::raw('SUM(amount) as total'))
                ->groupBy('method')
                ->get();

            $totalCount = $rows->sum('count');

            // Return array format matching frontend expectations: [{method, percentage, count}]
            return $rows->map(fn($r) => [
                'method'     => $r->payment_method,
                'percentage' => $totalCount > 0 ? round(($r->count / $totalCount) * 100, 1) : 0,
                'count'      => (int) $r->count,
                'total'      => (float) $r->total,
            ])->toArray();
        });
    }

    public function getCustomerLifetimeValue(string $userId): array
    {
        return $this->cacheWithTracking("admin_clv_{$userId}", 600, function () use ($userId) {
            // Single aggregate query — instead of fetching ALL rows
            $stats = Order::where('user_id', $userId)
                ->whereIn('status', ['DELIVERED', 'CONFIRMED'])
                ->selectRaw('COALESCE(SUM(total), 0) as total_spent')
                ->selectRaw('COUNT(*) as order_count')
                ->selectRaw('COALESCE(MIN(created_at), \'\') as first_order')
                ->selectRaw('COALESCE(MAX(created_at), \'\') as last_order')
                ->first();

            $totalSpent = $stats ? (float) $stats->total_spent : 0;
            $orderCount = $stats ? (int) $stats->order_count : 0;
            $avgValue = $orderCount > 0 ? $totalSpent / $orderCount : 0;
            $firstOrder = $stats ? $stats->first_order : null;
            $lastOrder = $stats ? $stats->last_order : null;

            return compact('totalSpent', 'orderCount', 'avgValue', 'firstOrder', 'lastOrder');
        });
    }

    public function getTopCustomers(int $limit = 20): Collection
    {
        return $this->cacheWithTracking("admin_top_customers_{$limit}", 300, function () use ($limit) {
            return User::withCount(['orders' => function ($q) {
                    $q->whereIn('status', ['DELIVERED', 'CONFIRMED']);
                }])
                ->withSum(['orders' => function ($q) {
                    $q->whereIn('status', ['DELIVERED', 'CONFIRMED']);
                }], 'total')
                ->orderByDesc('orders_sum_total')
                ->take($limit)
                ->get();
        });
    }

    public function getCategoryPerformance(): Collection
    {
        return $this->cacheWithTracking('admin_category_performance', 300, function () {
            return Category::withCount(['products', 'products as total_sold' => function ($q) {
                    $q->whereHas('orderItems');
                }])
                ->withSum(['products as total_revenue' => function ($q) {
                    $q->whereHas('orderItems');
                }], 'price')
                ->get();
        });
    }



    public function getHourlyDistribution(): array
    {
        return $this->cacheWithTracking('admin_hourly_dist', 300, function () {
            $rows = Order::select(
                    DB::raw('EXTRACT(HOUR FROM created_at) as hour'),
                    DB::raw('COUNT(*) as order_count'),
                    DB::raw('SUM(total) as revenue')
                )
                ->where('created_at', '>=', now()->subDays(30))
                ->groupBy('hour')
                ->orderBy('hour')
                ->get();

            // Map to frontend-expected format: {hour: '14:00', orders: X, revenue: Y}
            return $rows->map(fn($r) => [
                'hour'    => str_pad($r->hour, 2, '0', STR_PAD_LEFT) . ':00',
                'orders'  => (int) $r->order_count,
                'revenue' => (float) $r->revenue,
            ])->toArray();
        });
    }

    public function getRevenueComparison(): array
    {
        return $this->cacheWithTracking('admin_revenue_comparison', 300, function () {
            // Read from summary table instead of scanning orders
            $thisMonthStart = now()->startOfMonth()->format('Y-m-d');
            $thisMonthEnd   = now()->format('Y-m-d');
            $lastMonthStart = now()->subMonth()->startOfMonth()->format('Y-m-d');
            $lastMonthEnd   = now()->subMonth()->endOfMonth()->format('Y-m-d');

            $thisMonthTotals = $this->analyticsSummary->getTotalsForRange($thisMonthStart, $thisMonthEnd);
            $lastMonthTotals = $this->analyticsSummary->getTotalsForRange($lastMonthStart, $lastMonthEnd);

            $thisMonth = $thisMonthTotals['total_revenue'];
            $lastMonth = $lastMonthTotals['total_revenue'];
            $growth = $lastMonth > 0 ? round((($thisMonth - $lastMonth) / $lastMonth) * 100, 2) : 0;

            // Build daily trend arrays for current and previous periods
            $currentDaily = $this->analyticsSummary->getDailyTrend($thisMonthStart, $thisMonthEnd);
            $previousDaily = $this->analyticsSummary->getDailyTrend($lastMonthStart, $lastMonthEnd);

            return [
                'current'        => $currentDaily,
                'previous'       => $previousDaily,
                'changePercent'  => $growth,
                'thisMonth'      => $thisMonth,
                'lastMonth'      => $lastMonth,
                'growth'         => $growth,
            ];
        });
    }

    public function getCustomerGrowth(int $months = 12): array
    {
        return $this->cacheWithTracking("admin_customer_growth_{$months}", 300, function () use ($months) {
            $start = now()->subMonths($months)->startOfMonth()->format('Y-m-d');
            $end   = now()->format('Y-m-d');

            $rows = \App\Models\DailyAnalyticsSummary::between($start, $end)
                ->orderBy('date')
                ->get();

            // Group by month and aggregate new_users + cumulative total_users
            $cumulative = 0;
            $result = [];
            foreach ($rows->groupBy(fn($r) => $r->date->format('Y-m')) as $month => $monthRows) {
                $firstRow = $monthRows->first();
                $monthNewUsers = $monthRows->sum('new_users');
                $cumulative = max($cumulative, $monthRows->max('total_users'));
                $result[] = [
                    'date'       => $firstRow->date->format('Y-m-d'),
                    'totalUsers' => $cumulative,
                    'newUsers'   => $monthNewUsers,
                ];
            }

            return $result;
        });
    }

    public function getConversionMetrics(): array
    {
        return $this->cacheWithTracking('admin_conversion_metrics', 300, function () {
            // Single query with subselects — 1 DB round trip instead of 4
            $pageViewsTable = (new \App\Models\PageView)->getTable();
            $cartItemsTable = (new \App\Models\CartItem)->getTable();
            $cartTable = (new \App\Models\AbandonedCart)->getTable();

            $stats = DB::select("
                SELECT
                    (SELECT COUNT(*) FROM {$pageViewsTable}) AS totalVisitors,
                    (SELECT COUNT(DISTINCT user_id) FROM {$cartItemsTable}) AS totalCarts,
                    (SELECT COUNT(*) FROM orders) AS totalOrders,
                    (SELECT COUNT(*) FROM orders WHERE status IN ('DELIVERED', 'CONFIRMED')) AS completedOrders,
                    (SELECT COUNT(*) FROM {$cartTable}) AS abandonedCarts
            ");

            $result = (array) $stats[0];
            $totalVisitors = (int) ($result['totalVisitors'] ?? 0);
            $totalCarts = (int) ($result['totalCarts'] ?? 0);
            $totalOrders = (int) ($result['totalOrders'] ?? 0);
            $completedOrders = (int) ($result['completedOrders'] ?? 0);
            $abandonedCarts = (int) ($result['abandonedCarts'] ?? 0);

            $cartToOrder = $totalCarts > 0 ? round(($totalOrders / $totalCarts) * 100, 2) : 0;
            $orderCompletion = $totalOrders > 0 ? round(($completedOrders / $totalOrders) * 100, 2) : 0;
            $conversionRate = $totalCarts > 0 ? round(($completedOrders / $totalCarts) * 100, 2) : 0;

            return [
                'totalVisitors'   => $totalVisitors,
                'totalCarts'      => $totalCarts,
                'totalOrders'     => $totalOrders,
                'completedOrders' => $completedOrders,
                'abandonedCarts'  => $abandonedCarts,
                'cartToOrder'     => $cartToOrder,
                'orderCompletion' => $orderCompletion,
                'conversionRate'  => $conversionRate,
            ];
        });
    }

    public function getPaymentMethodTrends(int $days = 30): array
    {
        return $this->cacheWithTracking("admin_payment_trends_{$days}", 300, function () use ($days) {
            return \App\Models\Payment::select(
                    'method as payment_method',
                    DB::raw('DATE(created_at) as date'),
                    DB::raw('COUNT(*) as count'),
                    DB::raw('SUM(amount) as total')
                )
                ->where('created_at', '>=', now()->subDays($days))
                ->groupBy('method', 'date')
                ->orderBy('date')
                ->get()
                ->toArray();
        });
    }

    public function getOrderRevenueStats(): array
    {
        return $this->cacheWithTracking('admin_order_revenue_stats', 300, function () {
            $totalRevenue = Order::whereIn('status', ['DELIVERED', 'CONFIRMED'])->sum('total');
            $totalOrders = Order::count();
            $avgOrderValue = $totalOrders > 0 ? $totalRevenue / $totalOrders : 0;
            $maxOrderValue = Order::max('total');
            $pendingRevenue = Order::where('status', 'PENDING')->sum('total');

            return compact('totalRevenue', 'totalOrders', 'avgOrderValue', 'maxOrderValue', 'pendingRevenue');
        });
    }

    // ── Staff ──

    public function getStaff(array $filters = []): Collection
    {
        $query = User::whereIn('role', ['SUPER_ADMIN', 'ADMIN', 'MANAGER', 'SUPPORT_AGENT', 'FINANCE']);

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('email', 'like', "%{$search}%")
                  ->orWhere('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%");
            });
        }

        if (!empty($filters['role'])) {
            $query->where('role', $filters['role']);
        }

        return $query->orderByDesc('created_at')->get();
    }

    public function createStaff(array $data): User
    {
        $user = User::create([
            'first_name' => $data['first_name'] ?? 'Staff',
            'last_name' => $data['last_name'] ?? 'Member',
            'email' => $data['email'],
            'password' => bcrypt($data['password'] ?? \Illuminate\Support\Str::random(16)),
            'role' => $data['role'] ?? 'MANAGER',
            'is_active' => $data['is_active'] ?? true,
            'is_email_verified' => true,
        ]);
        $this->clearDashboardCache();
        return $user;
    }

    public function updateStaff(string $id, array $data): User
    {
        $user = User::findOrFail($id);
        $user->update($data);
        $this->clearDashboardCache();
        return $user->fresh();
    }

    // ── Backups ──

    public function getBackupSettings(): array
    {
        $keys = ['backup_frequency', 'backup_time', 'backup_day_of_week', 'backup_last_run'];
        $settings = \App\Models\Setting::whereIn('key', $keys)->get()->keyBy('key')->toArray();

        return [
            'backup_frequency' => $settings['backup_frequency']['value'] ?? 'manual',
            'backup_time' => $settings['backup_time']['value'] ?? '02:00',
            'backup_day_of_week' => $settings['backup_day_of_week']['value'] ?? 'Monday',
            'backup_last_run' => $settings['backup_last_run']['value'] ?? null,
        ];
    }

    public function updateBackupSettings(array $data): array
    {
        foreach ($data as $key => $value) {
            if (!is_null($value)) {
                \App\Models\Setting::updateOrCreate(
                    ['key' => $key, 'module' => 'SYSTEM'],
                    ['value' => $value]
                );
            }
        }
        return $this->getBackupSettings();
    }

    public function createBackup(): array
    {
        $backupDir = storage_path('app/backups');
        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0755, true);
        }

        $filename = 'backup-' . now()->format('Y-m-d-H-i-s') . '.sql';
        $path = $backupDir . '/' . $filename;

        // Generate a basic backup info record
        $backup = [
            'filename' => $filename,
            'path' => $path,
            'size' => 0,
            'created_at' => now()->toDateTimeString(),
        ];

        // Update last run
        \App\Models\Setting::updateOrCreate(
            ['key' => 'backup_last_run', 'module' => 'SYSTEM'],
            ['value' => now()->toDateTimeString()]
        );

        return $backup;
    }

    public function listBackups(): array
    {
        $backupDir = storage_path('app/backups');
        if (!is_dir($backupDir)) {
            return [];
        }

        $files = array_diff(scandir($backupDir), ['.', '..']);
        $backups = [];

        foreach ($files as $file) {
            $filePath = $backupDir . '/' . $file;
            if (is_file($filePath)) {
                $backups[] = [
                    'filename' => $file,
                    'size' => filesize($filePath),
                    'created_at' => date('Y-m-d H:i:s', filemtime($filePath)),
                ];
            }
        }

        return array_reverse($backups);
    }

    public function deleteBackup(string $filename): bool
    {
        $backupDir = storage_path('app/backups');
        $filePath = $backupDir . '/' . basename($filename);

        if (file_exists($filePath)) {
            return unlink($filePath);
        }

        throw new \Exception('Backup file not found');
    }

    public function getBackupPath(string $filename): ?string
    {
        $backupDir = storage_path('app/backups');
        $filePath = $backupDir . '/' . basename($filename);

        return file_exists($filePath) ? $filePath : null;
    }



    public function getRecentUsers(int $limit = 5): Collection
    {
        return $this->cacheWithTracking("admin_recent_users_{$limit}", 300, function () use ($limit) {
            return User::latest()->take($limit)->get();
        });
    }

    public function getSystemHealth(): array
    {
        // ── Database Connectivity ──
        $databaseConnected = false;
        $databaseSize = 'N/A';
        try {
            DB::connection()->getPdo();
            $databaseConnected = true;
            // Get approximate database size
            $dbName = DB::connection()->getDatabaseName();
            $sizeResult = DB::select("
                SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS size_mb
                FROM information_schema.TABLES
                WHERE table_schema = ?
            ", [$dbName]);
            $databaseSize = !empty($sizeResult) ? round((float) $sizeResult[0]->size_mb, 2) . ' MB' : 'N/A';
        } catch (\Exception $e) {
            $databaseConnected = false;
        }

        // ── Cache Connectivity ──
        $cacheConnected = false;
        try {
            $testKey = '__health_check_test__';
            Cache::put($testKey, true, 1);
            $cacheConnected = Cache::get($testKey) === true;
            Cache::forget($testKey);
        } catch (\Exception $e) {
            $cacheConnected = false;
        }

        // ── Disk Space ──
        $diskTotal = disk_total_space(storage_path());
        $diskFree = disk_free_space(storage_path());
        $diskUsed = $diskTotal - $diskFree;
        $diskUsedPercent = $diskTotal > 0 ? round(($diskUsed / $diskTotal) * 100, 1) : 0;
        $diskSpace = sprintf(
            '%.1f GB / %.1f GB (%s%% used)',
            $diskUsed / 1024 / 1024 / 1024,
            $diskTotal / 1024 / 1024 / 1024,
            $diskUsedPercent
        );

        // ── Uptime ──
        $uptime = 'N/A';
        if (PHP_OS_FAMILY === 'Windows') {
            // Windows: use wmic
            try {
                $output = shell_exec('wmic path Win32_OperatingSystem get LastBootUpTime /value 2>&1');
                if ($output && preg_match('/LastBootUpTime=(\d+)/', $output, $m)) {
                    $bootTime = date_create_from_format('YmdHis', substr($m[1], 0, 14));
                    if ($bootTime) {
                        $diff = now()->diff($bootTime);
                        $uptime = $diff->days > 0 ? $diff->days . 'd ' : '';
                        $uptime .= $diff->h . 'h ' . $diff->i . 'm';
                    }
                }
            } catch (\Exception $e) {
                $uptime = 'N/A';
            }
        } else {
            // Linux/Mac: use /proc/uptime
            $uptimeFile = @file_get_contents('/proc/uptime');
            if ($uptimeFile !== false) {
                $seconds = (int) trim(explode(' ', $uptimeFile)[0]);
                $days = floor($seconds / 86400);
                $hours = floor(($seconds % 86400) / 3600);
                $minutes = floor(($seconds % 3600) / 60);
                $uptime = ($days > 0 ? $days . 'd ' : '') . $hours . 'h ' . $minutes . 'm';
            }
        }

        // ── Queue Health ──
        $failedJobCount = 0;
        $latestFailedJob = null;
        try {
            $failedJobCount = \Illuminate\Support\Facades\DB::table('failed_jobs')->count();
            $latest = \Illuminate\Support\Facades\DB::table('failed_jobs')
                ->orderBy('failed_at', 'desc')
                ->first(['uuid', 'queue', 'failed_at']);
            if ($latest) {
                $latestFailedJob = [
                    'uuid' => $latest->uuid,
                    'queue' => $latest->queue,
                    'failed_at' => $latest->failed_at,
                ];
            }
        } catch (\Exception $e) {
            $failedJobCount = -1;
        }

        // ── Backup Health ──
        $backupCount = 0;
        $lastBackupAt = null;
        $lastBackupSize = null;
        try {
            $backupDir = storage_path('app/backups');
            if (is_dir($backupDir)) {
                $files = array_diff(scandir($backupDir), ['.', '..']);
                $backupCount = count($files);
                if ($backupCount > 0) {
                    $latestFile = null;
                    $latestMtime = 0;
                    foreach ($files as $file) {
                        $filePath = $backupDir . '/' . $file;
                        $mtime = filemtime($filePath);
                        if ($mtime > $latestMtime) {
                            $latestMtime = $mtime;
                            $latestFile = $filePath;
                        }
                    }
                    if ($latestFile) {
                        $lastBackupAt = date('Y-m-d H:i:s', $latestMtime);
                        $lastBackupSize = filesize($latestFile);
                    }
                }
            }
        } catch (\Exception $e) {
            $backupCount = -1;
        }

        // ── Last backup from settings table ──
        $lastBackupSetting = null;
        try {
            $setting = \App\Models\Setting::where('key', 'backup_last_run')->first();
            if ($setting) {
                $lastBackupSetting = $setting->value;
            }
        } catch (\Exception $e) {}

        return [
            'databaseConnection' => $databaseConnected,
            'cacheConnection' => $cacheConnected,
            'diskSpace' => $diskSpace,
            'uptime' => $uptime,
            'databaseSize' => $databaseSize,
            'php_version' => phpversion(),
            'laravel_version' => app()->version(),
            'environment' => app()->environment(),
            'debug_mode' => config('app.debug'),
            // Queue health
            'queueHealth' => [
                'failed_job_count' => $failedJobCount,
                'has_failed_jobs' => $failedJobCount > 0,
                'latest_failed_job' => $latestFailedJob,
            ],
            // Backup health
            'backupHealth' => [
                'backup_count' => $backupCount,
                'last_backup_at' => $lastBackupSetting ?: $lastBackupAt,
                'last_backup_size' => $lastBackupSize,
            ],
        ];
    }

    // ── Products ──

    public function getProducts(array $filters = []): LengthAwarePaginator
    {
        // Map frontend 'limit' param to backend 'per_page' if 'per_page' is not set
        if (!isset($filters['per_page']) && isset($filters['limit'])) {
            $filters['per_page'] = $filters['limit'];
        }

        $query = Product::with(['category' => fn($q) => $q->select(['id', 'name']), 'inventory' => fn($q) => $q->select(['id', 'product_id', 'available_quantity'])])
            ->select(['id', 'name', 'slug', 'sku', 'price', 'old_price', 'cost', 'status', 'quantity', 'category_id', 'description', 'short_description', 'rating', 'review_count', 'badge', 'created_at']);

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('sku', 'like', "%{$search}%");
            });
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['category_id'])) {
            $query->where('category_id', $filters['category_id']);
        }

        $perPage = $filters['per_page'] ?? 15;
        $paginator = $query->latest()->paginate($perPage);

        // Map snake_case DB fields to camelCase expected by frontend
        $paginator->getCollection()->transform(function ($product) {
            $product->oldPrice = $product->old_price;
            $product->reviewCount = (int) ($product->review_count ?? 0);
            $product->shortDescription = $product->short_description;
            $product->categoryName = $product->category?->name;
            $product->stock = $product->quantity;
            return $product;
        });

        return $paginator;
    }

    public function getProductById(string $id): ?Product
    {
        return Product::with([
            'category:id,name,slug',
            'brand:id,name,slug',
            'images:id,product_id,url,display_order',
            'variants:id,product_id,name,price,quantity,is_active',
            'inventory:id,product_id,available_quantity,total_quantity,reserved_quantity',
            'reviews' => function ($q) {
                $q->latest()->take(20);
            },
        ])->find($id);
    }

    public function createProduct(array $data): Product
    {
        $product = Product::create($data);
        $this->clearDashboardCache();
        return $product;
    }

    public function updateProduct(string $id, array $data): Product
    {
        $product = Product::findOrFail($id);
        $product->update($data);
        $this->clearDashboardCache();
        return $product->fresh();
    }

    public function deleteProduct(string $id): bool
    {
        $result = Product::findOrFail($id)->delete();
        $this->clearDashboardCache();
        return $result;
    }

    public function getCategoriesAndBrands(): array
    {
        return [
            'categories' => Category::all(),
            'brands' => Brand::all(),
        ];
    }

    public function getLowStockProducts(int $threshold = 5): Collection
    {
        return Product::with('inventory')
            ->where('status', 'PUBLISHED')
            ->where(function ($q) use ($threshold) {
                $q->where('quantity', '<=', $threshold)
                  ->orWhereHas('inventory', function ($iq) use ($threshold) {
                      $iq->where('available_quantity', '<=', $threshold);
                  });
            })
            ->get();
    }

    // ── Orders ──

    public function getOrders(array $filters = []): LengthAwarePaginator
    {
        $query = Order::with(['user' => fn($q) => $q->select(['id', 'first_name', 'last_name'])])
            ->select(['id', 'order_number', 'user_id', 'total', 'status', 'created_at']);

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('order_number', 'like', "%{$search}%")
                  ->orWhereHas('user', function ($uq) use ($search) {
                      $uq->where('email', 'like', "%{$search}%")
                         ->orWhere('first_name', 'like', "%{$search}%")
                         ->orWhere('last_name', 'like', "%{$search}%");
                  });
            });
        }

        if (!empty($filters['start_date']) && !empty($filters['end_date'])) {
            $query->whereBetween('created_at', [$filters['start_date'], $filters['end_date']]);
        }

        $perPage = $filters['per_page'] ?? 15;
        return $query->latest()->paginate($perPage);
    }

    public function getOrderById(string $id): ?Order
    {
        return Order::with([
            'items.product:id,name,slug,price,quantity',
            'user:id,first_name,last_name,email',
            'payment:id,order_id,amount,method,status,created_at',
            'timeline:id,order_id,status,note,created_at',
            'shippingAddress',
            'billingAddress',
        ])->find($id);
    }

    public function updateOrderStatus(string $id, string $status): Order
    {
        $order = Order::findOrFail($id);
        $statusField = strtolower($status) . '_at';
        $updateData = ['status' => $status];
        if (in_array($statusField, (new Order)->getFillable())) {
            $updateData[$statusField] = now();
        }
        $order->update($updateData);
        $this->clearDashboardCache();
        return $order->fresh();
    }

    // ── Users ──

    public function getUsers(array $filters = []): LengthAwarePaginator
    {
        $query = User::select(['id', 'first_name', 'last_name', 'email', 'role', 'is_active', 'is_blocked', 'is_email_verified', 'phone_number', 'created_at']);

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('email', 'like', "%{$search}%")
                  ->orWhere('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%");
            });
        }

        // Support both 'role' (backend convention) and 'status' (frontend convention) param names
        $role = $filters['role'] ?? $filters['status'] ?? null;
        if ($role && $role !== 'ALL') {
            $query->where('role', $role);
        }

        $perPage = $filters['per_page'] ?? 15;
        $paginator = $query->latest()->paginate($perPage);

        // Map snake_case DB fields to camelCase expected by frontend
        $paginator->getCollection()->transform(function ($user) {
            $user->firstName = $user->first_name;
            $user->lastName = $user->last_name;
            $user->createdAt = $user->created_at;
            $user->blocked = (bool) ($user->is_blocked ?? false);
            $user->emailVerified = (bool) ($user->is_email_verified ?? false);
            $user->phone = $user->phone_number;
            return $user;
        });

        return $paginator;
    }

    public function getUserById(string $id): ?User
    {
        $user = User::with([
            'orders' => function ($q) { $q->latest()->take(20); },
            'wallet',
            'loyaltyPoints',
            'addresses',
        ])->withCount('orders')->find($id);

        // Map snake_case to camelCase for frontend
        if ($user) {
            $user->firstName = $user->first_name;
            $user->lastName = $user->last_name;
            $user->createdAt = $user->created_at;
            $user->blocked = (bool) ($user->is_blocked ?? false);
            $user->emailVerified = (bool) ($user->is_email_verified ?? false);
            $user->phone = $user->phone_number;
        }

        return $user;
    }

    public function updateUser(string $id, array $data): User
    {
        $user = User::findOrFail($id);
        $user->update($data);
        $this->clearDashboardCache();
        return $user->fresh();
    }

    // ── Coupons ──

    public function getCoupons(array $filters = []): LengthAwarePaginator
    {
        $query = Coupon::select(['id', 'code', 'type', 'discount_value', 'min_order_value', 'max_discount', 'is_active', 'expiry_date', 'start_date', 'created_at']);

        if (!empty($filters['search'])) {
            $query->where('code', 'like', "%{$filters['search']}%");
        }

        if (isset($filters['is_active'])) {
            $query->where('is_active', $filters['is_active']);
        }

        $perPage = $filters['per_page'] ?? 15;
        return $query->latest()->paginate($perPage);
    }

    public function createCoupon(array $data): Coupon
    {
        $coupon = Coupon::create($data);
        $this->clearDashboardCache();
        return $coupon;
    }

    public function updateCoupon(string $id, array $data): Coupon
    {
        $coupon = Coupon::findOrFail($id);
        $coupon->update($data);
        $this->clearDashboardCache();
        return $coupon->fresh();
    }

    public function getCouponById(string $id): ?Coupon
    {
        return Coupon::find($id);
    }

    public function getCouponByCode(string $code, ?string $excludeId = null): ?Coupon
    {
        $query = Coupon::where('code', $code);
        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }
        return $query->first();
    }

    public function deleteCoupon(string $id): bool
    {
        $result = Coupon::findOrFail($id)->delete();
        $this->clearDashboardCache();
        return $result;
    }

    // ── Categories ──

    public function getCategories(array $filters = []): Collection
    {
        $query = Category::withCount('products');

        if (isset($filters['is_active']) || isset($filters['isActive'])) {
            $isActive = $filters['is_active'] ?? $filters['isActive'];
            $query->where('is_active', filter_var($isActive, FILTER_VALIDATE_BOOLEAN));
        }

        return $query->get();
    }

    /**
     * Get categories with pagination, search, and status filtering.
     */
    public function getCategoriesPaginated(array $filters = []): LengthAwarePaginator
    {
        $query = Category::withCount('products');

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if (isset($filters['is_active']) || isset($filters['isActive'])) {
            $isActive = $filters['is_active'] ?? $filters['isActive'];
            $query->where('is_active', filter_var($isActive, FILTER_VALIDATE_BOOLEAN));
        }

        $perPage = $filters['per_page'] ?? $filters['limit'] ?? 15;
        $paginator = $query->orderBy('name')->paginate($perPage);

        // Map snake_case DB fields to camelCase expected by frontend
        $paginator->getCollection()->transform(function ($category) {
            $category->productCount = (int) ($category->products_count ?? 0);
            $category->isActive = $category->is_active ?? true;
            $category->parentId = $category->parent_id;
            return $category;
        });

        return $paginator;
    }

    public function createCategory(array $data): Category
    {
        $category = Category::create($data);
        $this->clearDashboardCache();
        \Illuminate\Support\Facades\Cache::forget('categories_all');
        return $category;
    }

    public function updateCategory(string $id, array $data): Category
    {
        $category = Category::findOrFail($id);
        $category->update($data);
        $this->clearDashboardCache();
        \Illuminate\Support\Facades\Cache::forget('categories_all');
        return $category->fresh();
    }

    public function deleteCategory(string $id): bool
    {
        $result = Category::findOrFail($id)->delete();
        $this->clearDashboardCache();
        \Illuminate\Support\Facades\Cache::forget('categories_all');
        return $result;
    }

    public function getCategoryById(string $id): ?Category
    {
        return Category::withCount('products')->find($id);
    }

    // ── Brands ──

    public function getBrands(array $filters = []): Collection
    {
        $query = Brand::withCount('products');

        if (isset($filters['is_active']) || isset($filters['isActive'])) {
            $isActive = $filters['is_active'] ?? $filters['isActive'];
            $query->where('is_active', filter_var($isActive, FILTER_VALIDATE_BOOLEAN));
        }

        $brands = $query->orderBy('name')->get();

        // Map snake_case DB fields to camelCase expected by frontend
        $brands->transform(function ($brand) {
            $brand->logoUrl = $brand->logo;
            $brand->active = $brand->is_active ?? true;
            $brand->productCount = (int) ($brand->products_count ?? 0);
            return $brand;
        });

        return $brands;
    }

    /**
     * Get brands with pagination, search, and status filtering.
     */
    public function getBrandsPaginated(array $filters = []): LengthAwarePaginator
    {
        $query = Brand::withCount('products');

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%");
            });
        }

        if (isset($filters['is_active']) || isset($filters['isActive'])) {
            $isActive = $filters['is_active'] ?? $filters['isActive'];
            $query->where('is_active', filter_var($isActive, FILTER_VALIDATE_BOOLEAN));
        }

        $perPage = $filters['per_page'] ?? $filters['limit'] ?? 15;
        $paginator = $query->orderBy('name')->paginate($perPage);

        // Map snake_case DB fields to camelCase expected by frontend
        $paginator->getCollection()->transform(function ($brand) {
            $brand->logoUrl = $brand->logo;
            $brand->active = $brand->is_active ?? true;
            $brand->productCount = (int) ($brand->products_count ?? 0);
            return $brand;
        });

        return $paginator;
    }

    public function createBrand(array $data): Brand
    {
        return Brand::create($data);
    }

    public function updateBrand(string $id, array $data): Brand
    {
        $brand = Brand::findOrFail($id);
        $brand->update($data);
        return $brand->fresh();
    }

    public function deleteBrand(string $id): bool
    {
        return Brand::findOrFail($id)->delete();
    }

    public function getBrandById(string $id): ?Brand
    {
        return Brand::withCount('products')->find($id);
    }

    // ── Banners ──

    public function getBanners(array $filters = []): Collection
    {
        $query = Banner::orderBy('position');

        if (isset($filters['is_active']) || isset($filters['isActive'])) {
            $isActive = $filters['is_active'] ?? $filters['isActive'];
            $query->where('is_active', filter_var($isActive, FILTER_VALIDATE_BOOLEAN));
        }

        return $query->get();
    }

    public function createBanner(array $data): Banner
    {
        return Banner::create($data);
    }

    public function updateBanner(string $id, array $data): Banner
    {
        $banner = Banner::findOrFail($id);
        $banner->update($data);
        return $banner->fresh();
    }

    public function deleteBanner(string $id): bool
    {
        return Banner::findOrFail($id)->delete();
    }

    public function getBannerById(string $id): ?Banner
    {
        return Banner::find($id);
    }

    // ── Reviews ──

    public function getReviews(array $filters = []): LengthAwarePaginator
    {
        $query = Review::with([
            'user' => fn($q) => $q->select(['id', 'first_name', 'last_name']),
            'product' => fn($q) => $q->select(['id', 'name']),
        ])->select(['id', 'product_id', 'user_id', 'rating', 'title', 'comment', 'is_moderated', 'is_verified', 'created_at'])->latest();

        if (isset($filters['is_moderated'])) {
            $query->where('is_moderated', $filters['is_moderated']);
        }

        if (!empty($filters['rating'])) {
            $query->where('rating', $filters['rating']);
        }

        $perPage = $filters['per_page'] ?? 15;
        return $query->paginate($perPage);
    }

    public function moderateReview(string $id, bool $isApproved): Review
    {
        $review = Review::findOrFail($id);
        $review->update([
            'is_moderated' => true,
            'is_verified' => $isApproved,
        ]);
        $this->clearDashboardCache();
        return $review->fresh();
    }

    /**
     * Get review analytics including rating distribution, moderation stats, and trends over time.
     *
     * Optimized: 3 DB queries instead of 9, using conditional aggregation and UNION ALL.
     *   Query 1 — overall stats + avg rating + rating distribution (single scan via conditional aggregation)
     *   Query 2 — daily + monthly trends merged via UNION ALL
     *   Query 3 — top reviewed products (needs JOIN, kept separate)
     */
    public function getReviewAnalytics(int $days = 30): array
    {
        return $this->cacheWithTracking("admin_review_analytics_{$days}", 300, function () use ($days) {
            $table = (new Review)->getTable();

            // ── Query 1: Overall stats + avg rating + rating distribution ──
            // Single scan with conditional aggregation replaces 6 separate COUNT queries
            $stats = DB::select("
                SELECT
                    COUNT(*) AS total_reviews,
                    SUM(CASE WHEN is_moderated = 1 AND is_flagged = 0 THEN 1 ELSE 0 END) AS total_approved,
                    SUM(CASE WHEN is_moderated = 0 AND is_flagged = 0 THEN 1 ELSE 0 END) AS total_pending,
                    SUM(CASE WHEN is_flagged = 1 THEN 1 ELSE 0 END) AS total_rejected,
                    COALESCE(ROUND(AVG(CASE WHEN is_moderated = 1 AND is_flagged = 0 THEN rating END), 2), 0) AS average_rating,
                    SUM(CASE WHEN rating = 5 AND is_moderated = 1 AND is_flagged = 0 THEN 1 ELSE 0 END) AS rating_5,
                    SUM(CASE WHEN rating = 4 AND is_moderated = 1 AND is_flagged = 0 THEN 1 ELSE 0 END) AS rating_4,
                    SUM(CASE WHEN rating = 3 AND is_moderated = 1 AND is_flagged = 0 THEN 1 ELSE 0 END) AS rating_3,
                    SUM(CASE WHEN rating = 2 AND is_moderated = 1 AND is_flagged = 0 THEN 1 ELSE 0 END) AS rating_2,
                    SUM(CASE WHEN rating = 1 AND is_moderated = 1 AND is_flagged = 0 THEN 1 ELSE 0 END) AS rating_1
                FROM {$table}
            ");

            $s = (array) $stats[0];
            $totalReviews  = (int) ($s['total_reviews'] ?? 0);
            $totalApproved = (int) ($s['total_approved'] ?? 0);
            $totalPending  = (int) ($s['total_pending'] ?? 0);
            $totalRejected = (int) ($s['total_rejected'] ?? 0);
            $avgRating     = (float) ($s['average_rating'] ?? 0);

            // Build rating distribution from conditional aggregation columns
            $ratingCounts = [
                5 => (int) ($s['rating_5'] ?? 0),
                4 => (int) ($s['rating_4'] ?? 0),
                3 => (int) ($s['rating_3'] ?? 0),
                2 => (int) ($s['rating_2'] ?? 0),
                1 => (int) ($s['rating_1'] ?? 0),
            ];
            $distribution = [];
            for ($i = 5; $i >= 1; $i--) {
                $count = $ratingCounts[$i];
                $distribution[] = [
                    'rating'     => $i,
                    'count'      => $count,
                    'percentage' => $totalApproved > 0 ? round(($count / $totalApproved) * 100, 1) : 0,
                ];
            }

            // ── Query 2: Daily + Monthly trends via UNION ALL (1 round trip instead of 2) ──
            $trendRows = DB::select("
                (
                    SELECT 'daily' AS interval_type,
                        DATE(created_at) AS label,
                        COUNT(*) AS total,
                        SUM(CASE WHEN is_moderated = 1 AND is_flagged = 0 THEN 1 ELSE 0 END) AS approved,
                        SUM(CASE WHEN is_moderated = 0 AND is_flagged = 0 THEN 1 ELSE 0 END) AS pending,
                        COALESCE(ROUND(AVG(CASE WHEN is_moderated = 1 AND is_flagged = 0 THEN rating END), 2), 0) AS avg_rating
                    FROM {$table}
                    WHERE created_at >= ?
                    GROUP BY DATE(created_at)
                )
                UNION ALL
                (
                    SELECT 'monthly' AS interval_type,
                        CONCAT(YEAR(created_at), '-', LPAD(MONTH(created_at), 2, '0')) AS label,
                        COUNT(*) AS total,
                        0 AS approved,
                        0 AS pending,
                        COALESCE(ROUND(AVG(CASE WHEN is_moderated = 1 AND is_flagged = 0 THEN rating END), 2), 0) AS avg_rating
                    FROM {$table}
                    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
                    GROUP BY YEAR(created_at), MONTH(created_at)
                )
                ORDER BY interval_type, label
            ", [now()->subDays($days)]);

            $dailyTrend   = [];
            $monthlyTrend = [];
            foreach ($trendRows as $row) {
                $r = (array) $row;
                if ($r['interval_type'] === 'daily') {
                    $dailyTrend[] = [
                        'date'       => $r['label'],
                        'total'      => (int) $r['total'],
                        'approved'   => (int) $r['approved'],
                        'pending'    => (int) $r['pending'],
                        'avg_rating' => (float) $r['avg_rating'],
                    ];
                } else {
                    $monthlyTrend[] = [
                        'month'      => $r['label'],
                        'total'      => (int) $r['total'],
                        'avg_rating' => (float) $r['avg_rating'],
                    ];
                }
            }

            // ── Query 3: Products with most reviews (needs JOIN, kept separate) ──
            $topReviewed = Review::where('is_moderated', true)->where('is_flagged', false)
                ->select('product_id', DB::raw('COUNT(*) as count'), DB::raw('ROUND(AVG(rating), 2) as avg_rating'))
                ->groupBy('product_id')
                ->orderByDesc('count')
                ->take(5)
                ->with('product:id,name')
                ->get()
                ->map(fn($r) => [
                    'product_name' => $r->product?->name ?? 'Unknown',
                    'count'        => $r->count,
                    'avg_rating'   => (float) $r->avg_rating,
                ])
                ->toArray();

            return [
                'total_reviews'         => $totalReviews,
                'total_approved'        => $totalApproved,
                'total_pending'         => $totalPending,
                'total_rejected'        => $totalRejected,
                'average_rating'        => $avgRating,
                'rating_distribution'   => $distribution,
                'daily_trend'           => $dailyTrend,
                'monthly_trend'         => $monthlyTrend,
                'top_reviewed_products' => $topReviewed,
            ];
        });
    }

    // ── Tickets (Support) ──

    public function getTickets(array $filters = []): LengthAwarePaginator
    {
        $query = SupportTicket::with(['user' => fn($q) => $q->select(['id', 'first_name', 'last_name'])])
            ->select(['id', 'user_id', 'title', 'status', 'priority', 'created_at']);

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['priority'])) {
            $query->where('priority', $filters['priority']);
        }

        $perPage = $filters['per_page'] ?? 15;
        return $query->latest()->paginate($perPage);
    }

    public function getTicketById(string $id): ?SupportTicket
    {
        return SupportTicket::with(['user', 'messages.user'])->find($id);
    }

    public function updateTicketStatus(string $id, string $status): SupportTicket
    {
        $ticket = SupportTicket::findOrFail($id);
        $ticket->update(['status' => $status]);
        return $ticket->fresh();
    }

    // ── Promotions ──

    public function getPromotions(array $filters = []): LengthAwarePaginator
    {
        $query = Promotion::select(['id', 'title', 'discount', 'status', 'start_date', 'end_date', 'created_at']);

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        $perPage = $filters['per_page'] ?? 15;
        return $query->latest()->paginate($perPage);
    }

    public function createPromotion(array $data): Promotion
    {
        return Promotion::create($data);
    }

    public function updatePromotion(string $id, array $data): Promotion
    {
        $promotion = Promotion::findOrFail($id);
        $promotion->update($data);
        return $promotion->fresh();
    }

    public function deletePromotion(string $id): bool
    {
        return Promotion::findOrFail($id)->delete();
    }

    public function getPromotionById(string $id): ?Promotion
    {
        return Promotion::find($id);
    }

    // ── Abandoned Carts ──

    public function getAbandonedCarts(array $filters = []): LengthAwarePaginator
    {
        $query = AbandonedCart::with(['user' => fn($q) => $q->select(['id', 'first_name', 'last_name'])])
            ->select(['id', 'user_id', 'last_active_at', 'reminder_sent', 'is_recovered', 'created_at']);

        if (!empty($filters['is_recovered'])) {
            $query->where('is_recovered', $filters['is_recovered'] === '1' || $filters['is_recovered'] === true);
        }

        $perPage = $filters['per_page'] ?? 15;
        return $query->latest()->paginate($perPage);
    }

    public function getAbandonedCartById(string $id): ?AbandonedCart
    {
        return AbandonedCart::with('user')->find($id);
    }

    public function getAbandonedCartStats(): array
    {
        return [
            'total_abandoned' => AbandonedCart::count(),
            'reminder_sent_count' => AbandonedCart::where('reminder_sent', true)->count(),
        ];
    }

    // ── Inventory ──

    public function getInventory(array $filters = []): LengthAwarePaginator
    {
        $query = Inventory::with(['product' => fn($q) => $q->select(['id', 'name', 'sku'])])
            ->select(['id', 'product_id', 'total_quantity', 'available_quantity', 'reserved_quantity']);

        if (!empty($filters['low_stock'])) {
            $query->where('available_quantity', '<=', 5);
        }

        $perPage = $filters['per_page'] ?? 15;
        return $query->latest()->paginate($perPage);
    }

    public function getInventoryByProduct(string $productId): ?Inventory
    {
        return Inventory::with('product')->where('product_id', $productId)->first();
    }

    public function addStock(string $productId, int $quantity, string $reason = ''): Inventory
    {
        $inventory = Inventory::firstOrCreate(
            ['product_id' => $productId],
            ['total_quantity' => 0, 'available_quantity' => 0, 'reserved_quantity' => 0]
        );
        $inventory->increment('total_quantity', $quantity);
        $inventory->increment('available_quantity', $quantity);
        $inventory->refresh();

        InventoryHistory::create([
            'inventory_id' => $inventory->id,
            'type' => 'ADD',
            'quantity' => $quantity,
            'reason' => $reason ?: 'Manual stock addition',
        ]);

        $this->clearDashboardCache();
        return $inventory->fresh();
    }

    public function reduceStock(string $productId, int $quantity, string $reason = ''): Inventory
    {
        $inventory = Inventory::where('product_id', $productId)->firstOrFail();

        if ($inventory->available_quantity < $quantity) {
            throw new \Exception('Insufficient stock available');
        }

        $inventory->decrement('total_quantity', $quantity);
        $inventory->decrement('available_quantity', $quantity);

        InventoryHistory::create([
            'inventory_id' => $inventory->id,
            'type' => 'REMOVE',
            'quantity' => $quantity,
            'reason' => $reason ?: 'Manual stock reduction',
        ]);

        $this->clearDashboardCache();
        return $inventory->fresh();
    }

    public function getInventoryMovement(string $productId): Collection
    {
        return InventoryHistory::whereHas('inventory', function ($q) use ($productId) {
            $q->where('product_id', $productId);
        })
            ->with('inventory.product')
            ->latest()
            ->take(50)
            ->get();
    }

    // ── Notifications ──

    public function getNotifications(): LengthAwarePaginator
    {
        return UserNotification::select(['id', 'user_id', 'type', 'title', 'message', 'is_read', 'created_at'])
            ->latest()
            ->paginate(15);
    }

    // ── Product Variants ──

    public function getVariantsByProduct(string $productId): Collection
    {
        return ProductVariant::where('product_id', $productId)->get();
    }

    public function createVariant(array $data): ProductVariant
    {
        return ProductVariant::create($data);
    }

    public function updateVariant(string $id, array $data): ProductVariant
    {
        $variant = ProductVariant::findOrFail($id);
        $variant->update($data);
        return $variant->fresh();
    }

    public function deleteVariant(string $id): bool
    {
        return ProductVariant::findOrFail($id)->delete();
    }
}