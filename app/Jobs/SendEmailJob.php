<?php

namespace App\Jobs;

use App\Services\EmailService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 10; // seconds between retries

    /**
     * Create a new job instance.
     */
    public function __construct(
        protected string $to,
        protected string $subject,
        protected string $html,
        protected ?string $text = null
    ) {}

    /**
     * Execute the job.
     * Resolves EmailService from the container and sends the email synchronously.
     * Throws an exception on failure so the queue worker retries the job.
     */
    public function handle(EmailService $emailService): void
    {
        $success = $emailService->sendEmailSync($this->to, $this->subject, $this->html, $this->text);

        if (!$success) {
            throw new \RuntimeException("Failed to send email to {$this->to}: {$this->subject}");
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("[SendEmailJob] Permanently failed after {$this->tries} attempts for {$this->to}: {$this->subject} - {$exception->getMessage()}");
    }
}
