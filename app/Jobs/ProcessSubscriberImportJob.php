<?php

namespace App\Jobs;

use App\Services\MarketingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProcessSubscriberImportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $backoff = 30;
    public int $timeout = 600; // 10 minutes — large imports take time

    protected string $importId;

    public function __construct(
        protected string $csvFilePath,
        ?string $importId = null
    ) {
        $this->importId = $importId ?? self::generateImportId();
    }

    public function handle(MarketingService $marketingService): void
    {
        Log::info("[ProcessSubscriberImportJob] Starting subscriber import {$this->importId}");

        try {
            $this->saveStatus('processing');

            if (!Storage::disk('local')->exists($this->csvFilePath)) {
                throw new \RuntimeException("CSV file not found at {$this->csvFilePath}");
            }
            $csvContent = Storage::disk('local')->get($this->csvFilePath);

            $result = $marketingService->importSubscribersCSV($csvContent);

            $this->saveResult($result);

            Storage::disk('local')->delete($this->csvFilePath);

            Log::info("[ProcessSubscriberImportJob] Subscriber import {$this->importId} completed");
        } catch (\Exception $e) {
            $this->saveResult([
                'imported' => 0,
                'skipped' => 0,
                'errors' => 1,
                'error_details' => [['message' => $e->getMessage()]],
            ]);
            Log::error("[ProcessSubscriberImportJob] Subscriber import {$this->importId} failed: {$e->getMessage()}");
            throw $e;
        }
    }

    protected function saveStatus(string $status): void
    {
        Storage::disk('local')->put(
            "subscriber-imports/{$this->importId}/status.txt",
            $status
        );
    }

    protected function saveResult(array $result): void
    {
        Storage::disk('local')->put(
            "subscriber-imports/{$this->importId}/result.json",
            json_encode($result, JSON_PRETTY_PRINT)
        );
        $this->saveStatus($result['errors'] > 0 ? 'completed_with_errors' : 'completed');
    }

    public static function getResult(string $importId): ?array
    {
        $path = "subscriber-imports/{$importId}/result.json";
        if (!Storage::disk('local')->exists($path)) {
            return null;
        }
        return json_decode(Storage::disk('local')->get($path), true);
    }

    public static function getStatus(string $importId): string
    {
        $path = "subscriber-imports/{$importId}/status.txt";
        if (!Storage::disk('local')->exists($path)) {
            return 'not_found';
        }
        return trim(Storage::disk('local')->get($path));
    }

    public static function generateImportId(): string
    {
        return 'sub-imp-' . Str::uuid();
    }

    public function failed(\Throwable $exception): void
    {
        $this->saveStatus('failed');
        Log::error("[ProcessSubscriberImportJob] Permanently failed after {$this->tries} attempts for import {$this->importId}: {$exception->getMessage()}");
    }
}
