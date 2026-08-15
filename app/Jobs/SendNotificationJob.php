<?php

namespace App\Jobs;

use App\Services\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 5; // seconds between retries

    /**
     * Create a new job instance.
     *
     * @param string|null $userId Null for system notifications
     * @param string $type Notification type (e.g., 'order', 'system', 'promotion')
     * @param string $title Notification title
     * @param string $message Notification message body
     * @param array $data Additional data payload
     */
    public function __construct(
        protected ?string $userId,
        protected string $type,
        protected string $title,
        protected string $message,
        protected array $data = []
    ) {}

    /**
     * Execute the job.
     * Resolves NotificationService from the container and creates the notification synchronously.
     * Throws an exception on failure so the queue worker retries the job.
     */
    public function handle(NotificationService $notificationService): void
    {
        if ($this->userId) {
            $notificationService->createNotificationSync(
                $this->userId,
                $this->type,
                $this->title,
                $this->message,
                $this->data
            );
        } else {
            $notificationService->createSystemNotificationSync(
                $this->type,
                $this->title,
                $this->message,
                $this->data
            );
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        $target = $this->userId ?? 'system';
        Log::error("[SendNotificationJob] Permanently failed after {$this->tries} attempts for {$target}: {$this->type} - {$this->title} - {$exception->getMessage()}");
    }
}
