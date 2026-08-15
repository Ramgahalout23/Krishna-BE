<?php

namespace App\Jobs;

use App\Models\ProductVariant;
use App\Services\BarcodeLabelService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class GenerateBarcodeLabelsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 10;

    /**
     * Create a new job instance.
     *
     * @param array $variantIds Array of variant UUIDs
     * @param string $batchId Unique batch identifier for this generation
     */
    public function __construct(
        protected array $variantIds,
        protected string $batchId
    ) {}

    /**
     * Execute the job.
     * Generates a barcode label PDF and saves it to storage.
     */
    public function handle(BarcodeLabelService $barcodeLabelService): void
    {
        $variants = ProductVariant::with('product:id,name')
            ->whereIn('id', $this->variantIds)
            ->get();

        if ($variants->isEmpty()) {
            Log::warning("[GenerateBarcodeLabelsJob] No variants found for batch {$this->batchId}");
            $this->markFailed();
            return;
        }

        $pdf = $barcodeLabelService->generateBatchVariantLabelsPdf($this->variantIds);
        $filename = "{$this->batchId}.pdf";
        $path = "barcodes/{$filename}";

        Storage::disk('local')->put($path, $pdf->output());

        Log::info("[GenerateBarcodeLabelsJob] Generated barcode PDF for batch {$this->batchId} — {$variants->count()} variants");
    }

    /**
     * Mark the batch as failed by writing a failed marker file.
     */
    protected function markFailed(): void
    {
        Storage::disk('local')->put("barcodes/{$this->batchId}.failed", 'failed');
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("[GenerateBarcodeLabelsJob] Failed for batch {$this->batchId}: {$exception->getMessage()}");
        $this->markFailed();
    }

    /**
     * Check if the batch PDF is ready.
     */
    public static function isReady(string $batchId): bool
    {
        return Storage::disk('local')->exists("barcodes/{$batchId}.pdf");
    }

    /**
     * Check if the batch has failed.
     */
    public static function hasFailed(string $batchId): bool
    {
        return Storage::disk('local')->exists("barcodes/{$batchId}.failed");
    }

    /**
     * Get the storage path for the batch PDF.
     */
    public static function getPdfPath(string $batchId): string
    {
        return Storage::disk('local')->path("barcodes/{$batchId}.pdf");
    }

    /**
     * Get the download filename for the batch.
     */
    public static function getDownloadFilename(string $batchId, int $variantCount): string
    {
        return "barcode-variants-{$variantCount}-" . now()->format('Y-m-d') . ".pdf";
    }
}
