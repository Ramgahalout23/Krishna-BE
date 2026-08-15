<?php

namespace App\Jobs;

use App\Models\Order;
use App\Services\InvoiceService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class GenerateInvoiceJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 10;
    public int $timeout = 120;

    /**
     * Create a new job instance.
     */
    public function __construct(
        protected string $orderId
    ) {}

    /**
     * Execute the job.
     * Generates the invoice PDF and saves it to storage.
     */
    public function handle(InvoiceService $invoiceService): void
    {
        try {
            $pdf = $invoiceService->generateInvoice($this->orderId);
            $path = self::invoicePath($this->orderId);
            Storage::disk('local')->put($path, $pdf->output());
            Log::info("[GenerateInvoiceJob] Invoice generated for order {$this->orderId}");
        } catch (\Exception $e) {
            Log::error("[GenerateInvoiceJob] Failed to generate invoice for order {$this->orderId}: {$e->getMessage()}");
            throw $e;
        }
    }

    /**
     * Get the storage path for an order's invoice (based on order ID, no extra DB query needed).
     */
    public static function invoicePath(string $orderId): string
    {
        return "invoices/invoice-{$orderId}.pdf";
    }

    /**
     * Check if an invoice PDF already exists in storage.
     */
    public static function exists(string $orderId): bool
    {
        return Storage::disk('local')->exists(self::invoicePath($orderId));
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("[GenerateInvoiceJob] Permanently failed after {$this->tries} attempts for order {$this->orderId}: {$exception->getMessage()}");
    }
}
