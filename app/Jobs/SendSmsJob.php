<?php

namespace App\Jobs;

use App\Services\SMSService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendSmsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 10; // seconds between retries

    /**
     * Create a new job instance.
     */
    public function __construct(
        protected string $to,
        protected string $body
    ) {}

    /**
     * Execute the job.
     * Resolves SMSService from the container and sends the SMS synchronously.
     * Throws an exception on failure so the queue worker retries the job.
     */
    public function handle(SMSService $smsService): void
    {
        $success = $smsService->sendSMSSync($this->to, $this->body);

        if (!$success) {
            throw new \RuntimeException("Failed to send SMS to {$this->to}");
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("[SendSmsJob] Permanently failed after {$this->tries} attempts for {$this->to}: {$exception->getMessage()}");
    }
}
