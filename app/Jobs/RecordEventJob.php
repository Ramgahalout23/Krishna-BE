<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class RecordEventJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    /**
     * Create a new job instance.
     */
    public function __construct(
        protected string $sessionId,
        protected string $eventType,
        protected ?string $eventName,
        protected ?string $category,
        protected ?string $label,
        protected ?string $value,
        protected ?string $url,
        protected ?string $metadata,
        protected ?string $userId
    ) {}

    /**
     * Execute the job — insert event using raw DB for maximum speed.
     */
    public function handle(): void
    {
        try {
            DB::table('user_events')->insert([
                'id' => (string) Str::orderedUuid(),
                'session_id' => $this->sessionId,
                'user_id' => $this->userId,
                'event_type' => $this->eventType,
                'event_name' => $this->eventName ?? '',
                'category' => $this->category,
                'label' => $this->label,
                'value' => $this->value,
                'url' => $this->url,
                'metadata' => $this->metadata ? (is_string($this->metadata) ? $this->metadata : json_encode($this->metadata)) : null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('[RecordEventJob] Failed to record event', [
                'session_id' => $this->sessionId,
                'event_type' => $this->eventType,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("[RecordEventJob] Permanently failed for event {$this->eventType}: {$exception->getMessage()}");
    }
}
