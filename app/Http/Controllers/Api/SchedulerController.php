<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Artisan;

class SchedulerController extends Controller
{
    /**
     * Run a manual backup.
     */
    public function backup(): JsonResponse
    {
        Artisan::call('backup:run', ['--force' => true]);
        // Backup creation affects backup listing + system health in dashboard
        app(\App\Repositories\AdminRepository::class)->clearDashboardCache();
        return response()->json([
            'success' => true,
            'message' => 'Backup scheduled',
            'output' => Artisan::output(),
        ]);
    }

    /**
     * Process scheduled ad campaigns.
     */
    public function ads(): JsonResponse
    {
        Artisan::call('ads:process-scheduled');
        // Ad campaigns can trigger order/analytics changes — refresh cache
        app(\App\Repositories\AdminRepository::class)->clearDashboardCache();
        return response()->json([
            'success' => true,
            'message' => 'Ad campaigns processed',
            'output' => Artisan::output(),
        ]);
    }

    /**
     * Process scheduled email campaigns.
     */
    public function campaigns(): JsonResponse
    {
        Artisan::call('campaigns:process-scheduled');
        // Campaign sends may affect user engagement metrics and order counts
        app(\App\Repositories\AdminRepository::class)->clearDashboardCache();
        return response()->json([
            'success' => true,
            'message' => 'Campaigns processed',
            'output' => Artisan::output(),
        ]);
    }

    /**
     * Check maintenance schedule.
     */
    public function maintenance(): JsonResponse
    {
        Artisan::call('maintenance:check-schedule');
        // Maintenance schedule changes affect settings cache
        app(\App\Repositories\AdminRepository::class)->clearDashboardCache();
        return response()->json([
            'success' => true,
            'message' => 'Maintenance check completed',
            'output' => Artisan::output(),
        ]);
    }
}
