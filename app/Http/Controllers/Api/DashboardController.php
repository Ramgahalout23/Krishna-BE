<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AdminService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(
        protected AdminService $adminService
    ) {}

    public function dashboardMetrics(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->adminService->getDashboard()]);
    }

    /**
     * Consolidated dashboard endpoint — returns ALL dashboard data in a single API call.
     * Replaces 14+ individual API calls with 1, drastically reducing network overhead.
     * Each data section is individually cached (300s TTL) on the server side.
     */
    public function fullDashboard(Request $request): JsonResponse
    {
        $startDate = $request->input('startDate');
        $endDate   = $request->input('endDate');

        return response()->json([
            'success' => true,
            'data'    => $this->adminService->getFullDashboard($startDate, $endDate),
        ]);
    }

    public function dashboardSummary(Request $request): JsonResponse
    {
        $startDate = $request->input('startDate');
        $endDate   = $request->input('endDate');

        return response()->json([
            'success' => true,
            'data'    => $this->adminService->getDashboardSummary($startDate, $endDate),
        ]);
    }

    public function recentOrders(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->adminService->getRecentOrders()]);
    }

    public function activityLogs(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->adminService->getActivityLogs()]);
    }

    public function systemHealth(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->adminService->getSystemHealth()]);
    }

    public function auditLogs(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->adminService->getActivityLogs()]);
    }
}
