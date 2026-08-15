<?php

// ── Admin Dashboard & Analytics Routes ──
// Included from routes/api.php within Route::middleware(['auth:sanctum', 'admin'])->prefix('admin')

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\AnalyticsController;

// ── Dashboard & Analytics ──
Route::get('/dashboard/metrics', [DashboardController::class, 'dashboardMetrics']);
Route::get('/dashboard/summary', [DashboardController::class, 'dashboardSummary']);
Route::get('/dashboard/health', [DashboardController::class, 'systemHealth']);
Route::get('/dashboard/activity-logs', [DashboardController::class, 'activityLogs']);
Route::get('/dashboard/full', [DashboardController::class, 'fullDashboard']);
Route::get('/analytics/full', [AnalyticsController::class, 'fullAnalytics']);
Route::get('/analytics/revenue-trends', [AnalyticsController::class, 'revenueTrends']);
Route::get('/analytics/sales', [AnalyticsController::class, 'salesAnalytics']);
Route::get('/analytics/products', [AnalyticsController::class, 'productAnalytics']);
Route::get('/analytics/users', [AnalyticsController::class, 'userAnalytics']);
Route::get('/analytics/order-status', [AnalyticsController::class, 'orderStatusDistribution']);
Route::get('/analytics/payment-methods', [AnalyticsController::class, 'paymentMethodStats']);
Route::get('/analytics/customers/{userId}/lifetime-value', [AnalyticsController::class, 'customerLifetimeValue']);
Route::get('/analytics/top-customers', [AnalyticsController::class, 'topCustomers']);
Route::get('/analytics/categories', [AnalyticsController::class, 'categoryPerformance']);
Route::get('/analytics/daily-sales', [AnalyticsController::class, 'dailySales']);
Route::get('/analytics/hourly-distribution', [AnalyticsController::class, 'hourlyDistribution']);
Route::get('/analytics/revenue-comparison', [AnalyticsController::class, 'revenueComparison']);
Route::get('/analytics/customer-growth', [AnalyticsController::class, 'customerGrowth']);
Route::get('/analytics/conversion-metrics', [AnalyticsController::class, 'conversionMetrics']);
Route::get('/analytics/reviews', [AnalyticsController::class, 'reviewAnalytics']);
Route::get('/analytics/payment-method-trends', [AnalyticsController::class, 'paymentMethodTrends']);
Route::get('/orders/revenue', [AnalyticsController::class, 'orderRevenue']);

// ── Audit Logs ──
Route::get('/audit-logs', [DashboardController::class, 'auditLogs']);
