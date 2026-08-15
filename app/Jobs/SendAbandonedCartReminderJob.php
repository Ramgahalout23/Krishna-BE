<?php

namespace App\Jobs;

use App\Services\AbandonedCartService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendAbandonedCartReminderJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 10;
    public int $timeout = 60;

    public function __construct(
        protected string $cartId
    ) {}

    public function handle(AbandonedCartService $abandonedCartService): void
    {
        Log::info("[SendAbandonedCartReminderJob] Sending reminder for cart {$this->cartId}");

        try {
            $abandonedCartService->sendReminder($this->cartId);
            Log::info("[SendAbandonedCartReminderJob] Reminder sent for cart {$this->cartId}");
        } catch (\Exception $e) {
            Log::error("[SendAbandonedCartReminderJob] Failed to send reminder for cart {$this->cartId}: {$e->getMessage()}");
            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("[SendAbandonedCartReminderJob] Permanently failed after {$this->tries} attempts for cart {$this->cartId}: {$exception->getMessage()}");
    }
}
