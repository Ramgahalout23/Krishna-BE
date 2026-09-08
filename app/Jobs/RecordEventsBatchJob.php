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

/**
 * Bulk-insert a batch of tracking events in ONE query (instead of one
 * INSERT per event). Pairs with POST /tracking/events and the frontend
 * batch flush to cut request + DB round-trips dramatically.
 */
class RecordEventsBatchJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    /** Metadata is clamped so one event can't bloat a row with a huge JSON blob. */
    private const MAX_METADATA_LENGTH = 4096;

    public function __construct(
        protected array $events
    ) {}

    /**
     * Execute the job — one multi-row insert (chunked for safety).
     */
    public function handle(): void
    {
        if (empty($this->events)) {
            return;
        }

        $now = now();
        $rows = [];

        foreach ($this->events as $event) {
            $metadata = $event['metadata'] ?? null;
            if ($metadata !== null && !is_string($metadata)) {
                $metadata = json_encode($metadata);
            }
            if ($metadata !== null && mb_strlen($metadata) > self::MAX_METADATA_LENGTH) {
                $metadata = mb_substr($metadata, 0, self::MAX_METADATA_LENGTH);
            }

            $rows[] = [
                'id' => (string) Str::orderedUuid(),
                'session_id' => $event['session_id'],
                'user_id' => $event['user_id'] ?? null,
                'event_type' => $event['event_type'] ?? 'custom',
                'event_name' => $event['event_name'] ?? '',
                'category' => $event['category'] ?? null,
                'label' => $event['label'] ?? null,
                'value' => $event['value'] ?? null,
                'url' => $event['url'] ?? null,
                'metadata' => $metadata,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        try {
            // Chunk the insert so even a full batch stays well within
            // max_allowed_packet on shared MySQL.
            foreach (array_chunk($rows, 100) as $chunk) {
                DB::table('user_events')->insert($chunk);
            }
        } catch (\Throwable $e) {
            Log::warning('[RecordEventsBatchJob] Failed to record event batch', [
                'count' => count($rows),
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('[RecordEventsBatchJob] Permanently failed: ' . $exception->getMessage());
    }
}
