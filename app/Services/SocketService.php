<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SocketService
{
    /**
     * Get the Node.js socket.io server URL from config or env.
     * Falls back to the API_BASE_URL or localhost:3000 in dev.
     */
    private function getSocketServerUrl(): string
    {
        $url = config('services.socket.server_url') ?? env('SOCKET_SERVER_URL');
        if ($url) {
            return rtrim($url, '/');
        }

        // In production, this is a hard requirement — no fallback
        if (app()->environment('production')) {
            Log::error('[Socket] CRITICAL: SOCKET_SERVER_URL is not configured. ' .
                'Set SOCKET_SERVER_URL in .env (e.g. SOCKET_SERVER_URL=https://your-node-server.com). ' .
                'Without it, Laravel-originated real-time events will not reach connected clients.');
            return '';
        }

        return 'http://localhost:3000';
    }

    /**
     * Get the internal socket key for authenticating with the Node.js server.
     */
    private function getSocketKey(): ?string
    {
        return config('services.socket.internal_key') ?? env('INTERNAL_SOCKET_KEY');
    }

    /**
     * POST a socket event to the Node.js socket.io bridge endpoint.
     */
    private function dispatchToNode(string $event, array $data, ?string $userId = null, bool $isAdminEvent = false): void
    {
        $serverUrl = $this->getSocketServerUrl();
        if (empty($serverUrl)) {
            // Not configured — silently skip (production will log an error in getSocketServerUrl)
            return;
        }

        $socketKey = $this->getSocketKey();

        $channels = [];
        if ($userId) {
            $channels[] = "user:{$userId}";
        }
        if ($isAdminEvent || $userId) {
            $channels[] = 'admin';
        } else {
            // Default to admin room if no specific user
            $channels[] = 'admin';
        }

        $payload = [
            'event' => $event,
            'channels' => array_unique($channels),
            'data' => $data,
            'socketKey' => $socketKey,
        ];

        try {
            $response = Http::timeout(5)
                ->withHeaders([
                    'X-Socket-Key' => $socketKey ?? '',
                    'Content-Type' => 'application/json',
                ])
                ->post("{$serverUrl}/api/socket/dispatch", $payload);

            if (!$response->successful()) {
                Log::warning("[Socket] Node.js bridge returned {$response->status()} for event {$event}", [
                    'body' => $response->body(),
                ]);
            }
        } catch (\Throwable $e) {
            // Node.js server may not be running — log and continue silently
            Log::debug("[Socket] Failed to reach Node.js bridge for event {$event}: {$e->getMessage()}");
        }
    }

    /**
     * Emit an order status update event.
     */
    public function emitOrderUpdate(string $event, array $data): void
    {
        $userId = $data['userId'] ?? null;
        $orderId = $data['orderId'] ?? 'unknown';

        $this->dispatchToNode($event, $data, $userId, true);

        // Also fire the local Laravel event for any internal listeners
        try {
            $eventClass = $this->resolveEventClass($event);
            if ($eventClass !== null) {
                event(new $eventClass($data));
            }
            Log::debug("[Socket] Order event {$event} emitted for order {$orderId}");
        } catch (\Throwable $e) {
            Log::error("[Socket] Failed to emit order event {$event}: {$e->getMessage()}");
        }
    }

    /**
     * Emit a notification event.
     */
    public function emitNotification(string $event, array $data): void
    {
        $userId = $data['userId'] ?? null;

        $this->dispatchToNode($event, $data, $userId, true);

        try {
            $eventClass = $this->resolveEventClass($event);
            if ($eventClass !== null) {
                event(new $eventClass($data));
            }
            $userId = $data['userId'] ?? 'unknown';
            Log::debug("[Socket] Notification event {$event} emitted for user {$userId}");
        } catch (\Throwable $e) {
            Log::error("[Socket] Failed to emit notification: {$e->getMessage()}");
        }
    }

    /**
     * Emit a chat message event.
     */
    public function emitChatMessage(array $data): void
    {
        $userId = $data['userId'] ?? null;
        $ticketId = $data['ticketId'] ?? 'unknown';

        $this->dispatchToNode('chat:message', $data, $userId, true);

        try {
            $eventClass = $this->resolveEventClass('chat:message');
            if ($eventClass !== null) {
                event(new $eventClass($data));
            }
            Log::debug("[Socket] Chat message emitted for ticket {$ticketId}");
        } catch (\Throwable $e) {
            Log::error("[Socket] Failed to emit chat message: {$e->getMessage()}");
        }
    }

    /**
     * Emit an ad campaign event.
     */
    public function emitAdEvent(string $event, array $data): void
    {
        $campaignId = $data['campaignId'] ?? 'unknown';

        $this->dispatchToNode($event, $data, null, true);

        try {
            $eventClass = $this->resolveEventClass($event);
            if ($eventClass !== null) {
                event(new $eventClass($data));
            }
            Log::debug("[Socket] Ad event {$event} emitted for campaign {$campaignId}");
        } catch (\Throwable $e) {
            Log::error("[Socket] Failed to emit ad event {$event}: {$e->getMessage()}");
        }
    }

    /**
     * Emit a log entry event (real-time log streaming).
     * Always broadcasts to the admin channel.
     */
    public function emitLogEvent(string $event, array $data): void
    {
        $this->dispatchToNode($event, $data, null, true);

        $level = $data['level'] ?? 'UNKNOWN';
        $file = $data['file'] ?? 'laravel.log';
        Log::debug("[Socket] Log event {$event} emitted: {$level} — {$file}");
    }

    /**
     * Resolve event class from event name.
     */
    private function resolveEventClass(string $eventName): ?string
    {
        $map = [
            'order:statusUpdated' => \App\Events\OrderStatusUpdated::class,
            'order:created' => \App\Events\OrderCreated::class,
            'order:cancelled' => \App\Events\OrderCancelled::class,
            'notification:new' => \App\Events\NotificationCreated::class,
            'notification:updated' => \App\Events\NotificationUpdated::class,
            'chat:message' => \App\Events\ChatMessageSent::class,
            'ad:created' => \App\Events\AdCampaignCreated::class,
            'ad:published' => \App\Events\AdCampaignPublished::class,
            'ad:statsSynced' => \App\Events\AdCampaignStatsSynced::class,
        ];

        return $map[$eventName] ?? null;
    }
}
