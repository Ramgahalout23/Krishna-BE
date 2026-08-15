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

class RecordPageViewJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    /**
     * Create a new job instance.
     */
    public function __construct(
        protected string $url,
        protected ?string $userId,
        protected ?string $sessionId,
        protected ?string $referrer,
        protected ?string $title,
        protected ?string $userAgent,
        protected ?string $device,
        protected ?string $source = null,
        protected ?string $utmSource = null,
        protected ?string $utmMedium = null,
        protected ?string $utmCampaign = null,
        protected ?string $utmTerm = null,
        protected ?string $utmContent = null,
    ) {}

    /**
     * Execute the job — insert page view using raw DB for maximum speed.
     * Uses DB::insert() instead of Eloquent to skip model hydration overhead.
     */
    public function handle(): void
    {
        try {
            DB::table('page_views')->insert([
                'id' => (string) Str::orderedUuid(),
                'user_id' => $this->userId,
                'session_id' => $this->sessionId,
                'url' => $this->url,
                'title' => $this->title,
                'referrer' => $this->referrer,
                'source' => $this->source,
                'utm_source' => $this->utmSource,
                'utm_medium' => $this->utmMedium,
                'utm_campaign' => $this->utmCampaign,
                'utm_term' => $this->utmTerm,
                'utm_content' => $this->utmContent,
                'user_agent' => $this->userAgent,
                'device' => $this->device,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('[RecordPageViewJob] Failed to record page view', [
                'url' => $this->url,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("[RecordPageViewJob] Permanently failed for {$this->url}: {$exception->getMessage()}");
    }
}
