<?php

namespace App\Jobs;

use App\Services\BackupService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CreateBackupJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $backoff = 60;
    public int $timeout = 300; // 5 minutes — backups can take a while

    protected string $backupId;

    public function __construct(?string $backupId = null)
    {
        $this->backupId = $backupId ?? self::generateBackupId();
    }

    public function handle(BackupService $backupService): void
    {
        Log::info("[CreateBackupJob] Starting backup {$this->backupId}");

        try {
            $this->saveStatus('processing');

            $result = $backupService->createBackup();

            $this->saveResult($result);
            $this->saveStatus('completed');

            Log::info("[CreateBackupJob] Backup {$this->backupId} completed: {$result['filename']}");
        } catch (\Exception $e) {
            $this->saveResult([
                'error' => $e->getMessage(),
                'failed_at' => now()->toDateTimeString(),
            ]);
            $this->saveStatus('failed');
            Log::error("[CreateBackupJob] Backup {$this->backupId} failed: {$e->getMessage()}");
            throw $e;
        }
    }

    protected function saveStatus(string $status): void
    {
        Storage::disk('local')->put(
            "backups/{$this->backupId}/status.txt",
            $status
        );
    }

    protected function saveResult(array $result): void
    {
        Storage::disk('local')->put(
            "backups/{$this->backupId}/result.json",
            json_encode($result, JSON_PRETTY_PRINT)
        );
    }

    public static function getStatus(string $backupId): string
    {
        $path = "backups/{$backupId}/status.txt";
        if (!Storage::disk('local')->exists($path)) {
            return 'not_found';
        }
        return trim(Storage::disk('local')->get($path));
    }

    public static function getResult(string $backupId): ?array
    {
        $path = "backups/{$backupId}/result.json";
        if (!Storage::disk('local')->exists($path)) {
            return null;
        }
        return json_decode(Storage::disk('local')->get($path), true);
    }

    public static function generateBackupId(): string
    {
        return 'bkp-' . Str::uuid();
    }

    public function failed(\Throwable $exception): void
    {
        $this->saveStatus('failed');
        Log::error("[CreateBackupJob] Permanently failed after {$this->tries} attempts for backup {$this->backupId}: {$exception->getMessage()}");
    }
}
