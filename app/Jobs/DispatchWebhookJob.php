<?php

namespace App\Jobs;

use App\Services\WebhookService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class DispatchWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 10;

    /**
     * Create a new job instance.
     */
    public function __construct(
        protected string $event,
        protected array $payload
    ) {}

    /**
     * Execute the job — dispatches the webhook event to all active subscribers.
     */
    public function handle(WebhookService $webhookService): void
    {
        $webhookService->dispatch($this->event, $this->payload);

        Log::info("[DispatchWebhookJob] Dispatched webhook event {$this->event}");
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("[DispatchWebhookJob] Permanently failed for event {$this->event} after {$this->tries} attempts: {$exception->getMessage()}");
    }
}
