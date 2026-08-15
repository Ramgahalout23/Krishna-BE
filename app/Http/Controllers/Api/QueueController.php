<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

class QueueController extends Controller
{
    /**
     * List all failed jobs with pagination.
     */
    public function failedJobs(Request $request): JsonResponse
    {
        try {
            $perPage = (int) $request->input('per_page', 15);

            $failedJobs = DB::table('failed_jobs')
                ->latest('failed_at')
                ->paginate(min($perPage, 100));

            return response()->json([
                'success' => true,
                'data' => [
                    'items' => $failedJobs->items(),
                    'total' => $failedJobs->total(),
                    'page' => $failedJobs->currentPage(),
                    'per_page' => $failedJobs->perPage(),
                    'total_pages' => $failedJobs->lastPage(),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Retry a specific failed job by its UUID.
     * The UUID comes from the failed_jobs.uuid column.
     */
    public function retryFailedJob(string $uuid): JsonResponse
    {
        try {
            Artisan::call('queue:retry', ['id' => $uuid]);
            return response()->json([
                'success' => true,
                'message' => "Job {$uuid} requeued for retry",
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Retry all failed jobs.
     */
    public function retryAllFailedJobs(): JsonResponse
    {
        try {
            Artisan::call('queue:retry', ['id' => 'all']);
            return response()->json([
                'success' => true,
                'message' => 'All failed jobs requeued for retry',
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Flush all failed jobs (delete them from the table).
     */
    public function flushFailedJobs(): JsonResponse
    {
        try {
            Artisan::call('queue:flush');
            return response()->json([
                'success' => true,
                'message' => 'All failed jobs flushed',
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
}
