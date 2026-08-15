<?php

namespace App\Jobs;

use App\Services\SocketService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class EmitSocketEventJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 5;

    /**
     * Create a new job instance.
     */
    public function __construct(
        protected string $event,
        protected array $data
    ) {}

    /**
     * Execute the job — emits a socket event to the Node.js bridge.
     */
    public function handle(SocketService $socketService): void
    {
        $socketService->emitOrderUpdate($this->event, $this->data);

        Log::info("[EmitSocketEventJob] Emitted socket event {$this->event}");
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("[EmitSocketEventJob] Permanently failed for event {$this->event} after {$this->tries} attempts: {$exception->getMessage()}");
    }
}
