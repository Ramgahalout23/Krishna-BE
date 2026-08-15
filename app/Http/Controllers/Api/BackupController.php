<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\CreateBackupJob;
use App\Services\BackupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BackupController extends Controller
{
    public function __construct(
        protected BackupService $backupService
    ) {}

    public function backupSettings(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->backupService->getBackupSettings()]);
    }

    public function backupSettingsUpdate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'backup_frequency' => 'nullable|string|in:manual,daily,weekly',
            'backup_time' => 'nullable|string',
            'backup_day_of_week' => 'nullable|string',
        ]);
        $data = $this->backupService->updateBackupSettings($validated);
        return response()->json(['success' => true, 'message' => 'Backup settings updated', 'data' => $data]);
    }

    public function backupCreate(): JsonResponse
    {
        try {
            $backupId = CreateBackupJob::generateBackupId();
            CreateBackupJob::dispatch($backupId);
            return response()->json(['success' => true, 'message' => 'Backup queued for processing', 'data' => ['backup_id' => $backupId, 'status' => 'queued']], 202);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function backupsList(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->backupService->listBackups()]);
    }

    public function backupDownload(string $filename): JsonResponse
    {
        $path = $this->backupService->getBackupPath($filename);
        if (!$path) {
            return response()->json(['success' => false, 'message' => 'Backup file not found'], 404);
        }
        return response()->download($path, $filename);
    }

    public function backupDelete(string $filename): JsonResponse
    {
        try {
            $this->backupService->deleteBackup($filename);
            return response()->json(['success' => true, 'message' => 'Backup deleted']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 404);
        }
    }

    public function backupStatus(string $backupId): JsonResponse
    {
        $status = CreateBackupJob::getStatus($backupId);
        $result = CreateBackupJob::getResult($backupId);

        return response()->json([
            'success' => true,
            'data' => [
                'backup_id' => $backupId,
                'status' => $status,
                'result' => $result,
            ],
        ]);
    }
}
