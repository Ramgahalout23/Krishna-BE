<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Pusher\Pusher;

/**
 * RealtimeService — Unified real-time event service
 * 
 * Supports two drivers:
 *   1. "pusher"    — Uses Pusher PHP SDK (works on shared hosting, no Node.js needed)
 *   2. "websocket" — Uses existing SocketService (requires Node.js server)
 *   3. "disabled"  — No-ops, silently skips all events
 * 
 * The active driver is selected via the admin panel (settings table, realtime_driver key).
 * Falls back to 'disabled' if no driver is configured.
 */
class RealtimeService
{
    protected ?Pusher $pusher = null;

    public function __construct(
        protected SocketService $socketService,
        protected SettingsService $settingsService
    ) {
        $this->initPusher();
    }

    /**
     * Initialize Pusher SDK if configured.
     */
    protected function initPusher(): void
    {
        $appId = config('services.pusher.app_id') ?? env('PUSHER_APP_ID');
        $key = config('services.pusher.key') ?? env('PUSHER_APP_KEY');
        $secret = config('services.pusher.secret') ?? env('PUSHER_APP_SECRET');
        $cluster = config('services.pusher.options.cluster') ?? env('PUSHER_APP_CLUSTER', 'mt1');

        if ($appId && $key && $secret) {
            try {
                $this->pusher = new Pusher($key, $secret, $appId, [
                    'cluster' => $cluster,
                    'useTLS' => true,
                ]);
            } catch (\Throwable $e) {
                Log::warning('[Realtime] Failed to initialize Pusher: ' . $e->getMessage());
            }
        }
    }

    /**
     * Get the active driver from settings.
     * Options: 'pusher', 'websocket', 'disabled'
     */
    protected function getDriver(): string
    {
        $driver = $this->settingsService->get('realtime_driver', 'disabled');
        return in_array($driver, ['pusher', 'websocket', 'disabled']) ? $driver : 'disabled';
    }

    /**
     * Check if Pusher is available.
     */
    protected function pusherAvailable(): bool
    {
        return $this->pusher !== null;
    }

    /**
     * Trigger a Pusher event on the given channels.
     */
    protected function triggerPusher(string $event, array $data, array $channels): void
    {
        if (!$this->pusherAvailable()) {
            Log::warning('[Realtime] Pusher driver selected but not configured. Check PUSHER_APP_* env vars.');
            return;
        }

        try {
            $result = $this->pusher->trigger($channels, $event, $data);
            if (!$result) {
                Log::warning("[Realtime] Pusher trigger returned false for event {$event}");
            }
        } catch (\Throwable $e) {
            Log::error("[Realtime] Pusher trigger failed for event {$event}: {$e->getMessage()}");
        }
    }

    /**
     * Map event name to Pusher channel.
     */
    protected function getPusherChannels(string $event, ?string $userId = null, bool $isAdminEvent = false): array
    {
        $channels = [];
        if ($userId) {
            $channels[] = "private-user.{$userId}";
        }
        if ($isAdminEvent) {
            $channels[] = 'private-admin';
        }

        // If no specific channels, broadcast to admin
        if (empty($channels)) {
            $channels[] = 'private-admin';
        }

        return array_unique($channels);
    }

    // ── Public API Methods ──

    /**
     * Emit an order status update event.
     */
    public function emitOrderUpdate(string $event, array $data): void
    {
        $userId = $data['userId'] ?? null;
        $driver = $this->getDriver();

        if ($driver === 'pusher') {
            $channels = $this->getPusherChannels($event, $userId, true);
            $this->triggerPusher($event, $data, $channels);
        } elseif ($driver === 'websocket') {
            $this->socketService->emitOrderUpdate($event, $data);
            return; // SocketService already fires local events
        }

        // Fire local Laravel event for internal listeners (regardless of driver)
        $this->fireLocalEvent($event, $data);
    }

    /**
     * Emit a notification event.
     */
    public function emitNotification(string $event, array $data): void
    {
        $userId = $data['userId'] ?? null;
        $driver = $this->getDriver();

        if ($driver === 'pusher') {
            $channels = $this->getPusherChannels($event, $userId, true);
            $this->triggerPusher($event, $data, $channels);
        } elseif ($driver === 'websocket') {
            $this->socketService->emitNotification($event, $data);
            return;
        }

        $this->fireLocalEvent($event, $data);
    }

    /**
     * Emit a chat message event.
     */
    public function emitChatMessage(array $data): void
    {
        $userId = $data['userId'] ?? null;
        $driver = $this->getDriver();

        if ($driver === 'pusher') {
            $channels = $this->getPusherChannels('chat:message', $userId, true);
            $this->triggerPusher('chat:message', $data, $channels);
        } elseif ($driver === 'websocket') {
            $this->socketService->emitChatMessage($data);
            return;
        }

        $this->fireLocalEvent('chat:message', $data);
    }

    /**
     * Emit an ad campaign event.
     */
    public function emitAdEvent(string $event, array $data): void
    {
        $driver = $this->getDriver();

        if ($driver === 'pusher') {
            $channels = $this->getPusherChannels($event, null, true);
            $this->triggerPusher($event, $data, $channels);
        } elseif ($driver === 'websocket') {
            $this->socketService->emitAdEvent($event, $data);
            return;
        }

        $this->fireLocalEvent($event, $data);
    }

    /**
     * Emit a log entry event for real-time log streaming.
     */
    public function emitLogEvent(string $event, array $data): void
    {
        $driver = $this->getDriver();

        if ($driver === 'pusher') {
            $channels = ['private-admin'];
            $this->triggerPusher($event, $data, $channels);
        } elseif ($driver === 'websocket') {
            $this->socketService->emitLogEvent($event, $data);
            return;
        }

        Log::debug("[Realtime] Log event {$event} (driver: {$driver})");
    }

    /**
     * Fire the local Laravel event (used by both drivers and for internal listeners).
     */
    protected function fireLocalEvent(string $event, array $data): void
    {
        $map = [
            'order:statusUpdated' => \App\Events\OrderStatusUpdated::class,
            'order:created'       => \App\Events\OrderCreated::class,
            'order:cancelled'     => \App\Events\OrderCancelled::class,
            'notification:new'    => \App\Events\NotificationCreated::class,
            'notification:updated' => \App\Events\NotificationUpdated::class,
            'chat:message'        => \App\Events\ChatMessageSent::class,
            'ad:created'          => \App\Events\AdCampaignCreated::class,
            'ad:published'        => \App\Events\AdCampaignPublished::class,
            'ad:statsSynced'      => \App\Events\AdCampaignStatsSynced::class,
        ];

        if (isset($map[$event])) {
            try {
                event(new $map[$event]($data));
            } catch (\Throwable $e) {
                Log::debug("[Realtime] Failed to fire local event {$event}: {$e->getMessage()}");
            }
        }
    }

    /**
     * Test the current realtime driver connection.
     * Used by admin panel to verify configuration.
     */
    public function testConnection(): array
    {
        $driver = $this->getDriver();

        if ($driver === 'disabled') {
            return ['success' => false, 'message' => 'Realtime is disabled'];
        }

        if ($driver === 'pusher') {
            if (!$this->pusherAvailable()) {
                return ['success' => false, 'message' => 'Pusher not configured. Set PUSHER_APP_ID, PUSHER_APP_KEY, PUSHER_APP_SECRET in .env'];
            }
            try {
                $this->pusher->trigger(['private-test'], 'test:connection', ['message' => 'Connection test', 'timestamp' => now()->toIso8601String()]);
                return ['success' => true, 'message' => 'Pusher connected successfully'];
            } catch (\Throwable $e) {
                return ['success' => false, 'message' => 'Pusher connection failed: ' . $e->getMessage()];
            }
        }

        if ($driver === 'websocket') {
            $url = $this->socketService->getSocketServerUrl();
            if (empty($url)) {
                return ['success' => false, 'message' => 'WebSocket server not configured. Set SOCKET_SERVER_URL in .env'];
            }
            return ['success' => true, 'message' => "WebSocket server configured at {$url}"];
        }

        return ['success' => false, 'message' => 'Unknown driver'];
    }
}
