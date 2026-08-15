<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AdminService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AnalyticsController extends Controller
{
    public function __construct(
        protected AdminService $adminService
    ) {}

    public function revenueTrends(Request $request): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->adminService->getRevenueTrends($request->days ?? 30)]);
    }

    public function salesAnalytics(Request $request): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->adminService->getSalesAnalytics($request->days ?? 30)]);
    }

    public function productAnalytics(Request $request): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->adminService->getProductAnalytics($request->limit ?? 20)]);
    }

    public function userAnalytics(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->adminService->getUserAnalytics()]);
    }

    public function orderStatusDistribution(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->adminService->getOrderStatusDistribution()]);
    }

    public function paymentMethodStats(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->adminService->getPaymentMethodStats()]);
    }

    public function customerLifetimeValue(string $userId): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->adminService->getCustomerLifetimeValue($userId)]);
    }

    public function topCustomers(Request $request): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->adminService->getTopCustomers($request->limit ?? 20)]);
    }

    public function categoryPerformance(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->adminService->getCategoryPerformance()]);
    }

    public function dailySales(Request $request): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->adminService->getDailySales($request->days ?? 30)]);
    }

    public function hourlyDistribution(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->adminService->getHourlyDistribution()]);
    }

    public function revenueComparison(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->adminService->getRevenueComparison()]);
    }

    public function customerGrowth(Request $request): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->adminService->getCustomerGrowth($request->months ?? 12)]);
    }

    public function conversionMetrics(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->adminService->getConversionMetrics()]);
    }

    public function paymentMethodTrends(Request $request): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->adminService->getPaymentMethodTrends($request->days ?? 30)]);
    }

    public function orderRevenue(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->adminService->getOrderRevenueStats()]);
    }

    /**
     * Consolidated analytics endpoint — returns ALL analytics data in a single API call.
     * Replaces 15 individual API calls with 1, drastically reducing network overhead.
     * Each data section is individually cached (300s TTL) on the server side.
     */
    public function fullAnalytics(Request $request): JsonResponse
    {
        $startDate = $request->input('startDate');
        $endDate   = $request->input('endDate');
        $days      = $request->integer('days', 30);

        return response()->json([
            'success' => true,
            'data'    => $this->adminService->getFullAnalytics($startDate, $endDate, $days),
        ]);
    }

    /**
     * Get review analytics for the admin dashboard.
     * GET /api/v1/admin/analytics/reviews?days=30
     */
    public function reviewAnalytics(Request $request): JsonResponse
    {
        $days = $request->integer('days', 30);
        return response()->json(['success' => true, 'data' => $this->adminService->getReviewAnalytics($days)]);
    }
}
