<?php

namespace App\Jobs;

use App\Repositories\AdminRepository;
use App\Services\ProductImportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProcessProductImportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $backoff = 30;
    public int $timeout = 600; // 10 minutes — large imports take time

    /**
     * Create a new job instance.
     *
     * @param string $csvFilePath Storage path to the CSV file (saved before dispatch to avoid large payload)
     * @param string $importId Unique identifier to track this import
     */
    public function __construct(
        protected string $csvFilePath,
        protected string $importId
    ) {}

    /**
     * Execute the job.
     * Reads the CSV file from storage, parses and imports products, then stores the result.
     */
    public function handle(ProductImportService $importService, AdminRepository $adminRepository): void
    {
        Log::info("[ProcessProductImportJob] Starting import {$this->importId}");

        try {
            // Mark as processing
            $this->saveStatus('processing');

            // Read CSV from storage (avoids large payload in queue)
            if (!Storage::disk('local')->exists($this->csvFilePath)) {
                throw new \RuntimeException("CSV file not found at {$this->csvFilePath}");
            }
            $csvContent = Storage::disk('local')->get($this->csvFilePath);

            $result = $importService->importFromCSV($csvContent);

            // Clear dashboard cache since products/inventory changed
            $adminRepository->clearDashboardCache();

            // Save the result to storage
            $this->saveResult($result);

            // Clean up the uploaded CSV file
            Storage::disk('local')->delete($this->csvFilePath);

            Log::info("[ProcessProductImportJob] Import {$this->importId} completed: {$result['imported']} imported, {$result['skipped']} skipped, {$result['errors']} errors");
        } catch (\Exception $e) {
            $this->saveResult([
                'imported' => 0,
                'skipped' => 0,
                'errors' => 1,
                'error_details' => [['row' => 0, 'message' => $e->getMessage()]],
                'imported_products' => [],
            ]);
            Log::error("[ProcessProductImportJob] Import {$this->importId} failed: {$e->getMessage()}");
            throw $e;
        }
    }

    /**
     * Save current status to storage.
     */
    protected function saveStatus(string $status): void
    {
        Storage::disk('local')->put(
            "imports/{$this->importId}/status.txt",
            $status
        );
    }

    /**
     * Save import result to storage.
     */
    protected function saveResult(array $result): void
    {
        Storage::disk('local')->put(
            "imports/{$this->importId}/result.json",
            json_encode($result, JSON_PRETTY_PRINT)
        );
        $this->saveStatus($result['errors'] > 0 ? 'completed_with_errors' : 'completed');
    }

    /**
     * Get the result of an import by ID.
     */
    public static function getResult(string $importId): ?array
    {
        $path = "imports/{$importId}/result.json";
        if (!Storage::disk('local')->exists($path)) {
            return null;
        }
        return json_decode(Storage::disk('local')->get($path), true);
    }

    /**
     * Get the status of an import by ID.
     */
    public static function getStatus(string $importId): string
    {
        $path = "imports/{$importId}/status.txt";
        if (!Storage::disk('local')->exists($path)) {
            return 'not_found';
        }
        return trim(Storage::disk('local')->get($path));
    }

    /**
     * Generate a unique import ID.
     */
    public static function generateImportId(): string
    {
        return 'imp-' . Str::uuid();
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        $this->saveStatus('failed');
        Log::error("[ProcessProductImportJob] Permanently failed after {$this->tries} attempts for import {$this->importId}: {$exception->getMessage()}");
    }
}
