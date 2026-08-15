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

class RecordSessionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    /**
     * Create a new job instance.
     */
    public function __construct(
        protected ?string $sessionId,
        protected ?string $userId,
        protected string $ip,
        protected string $userAgent,
        protected ?string $device,
        protected ?string $browser,
        protected ?string $os,
        protected ?string $referrer,
        protected ?string $landingPage,
        protected ?string $source = null,
        protected ?string $utmSource = null,
        protected ?string $utmMedium = null,
        protected ?string $utmCampaign = null,
        protected ?string $utmTerm = null,
        protected ?string $utmContent = null,
    ) {}

    /**
     * Execute the job — upsert session using raw DB for maximum speed.
     * Uses updateOrInsert (upsert) to handle page refreshes gracefully.
     */
    public function handle(): void
    {
        try {
            $now = now();
            $exists = DB::table('user_sessions')->where('session_id', $this->sessionId)->exists();

            $data = [
                'user_id' => $this->userId,
                'ip_address' => $this->ip,
                'user_agent' => $this->userAgent,
                'device' => $this->device,
                'browser' => $this->browser,
                'os' => $this->os,
                'referrer' => $this->referrer,
                'source' => $this->source,
                'utm_source' => $this->utmSource,
                'utm_medium' => $this->utmMedium,
                'utm_campaign' => $this->utmCampaign,
                'utm_term' => $this->utmTerm,
                'utm_content' => $this->utmContent,
                'landing_page' => $this->landingPage,
                'start_time' => $now,
                'is_active' => true,
            ];

            if ($exists) {
                DB::table('user_sessions')
                    ->where('session_id', $this->sessionId)
                    ->update(array_merge($data, ['updated_at' => $now]));
            } else {
                DB::table('user_sessions')->insert(array_merge($data, [
                    'id' => (string) Str::orderedUuid(),
                    'session_id' => $this->sessionId,
                    'duration' => 0,
                    'page_views' => 0,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]));
            }
        } catch (\Throwable $e) {
            Log::warning('[RecordSessionJob] Failed to record session', [
                'session_id' => $this->sessionId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("[RecordSessionJob] Permanently failed for session {$this->sessionId}: {$exception->getMessage()}");
    }
}
