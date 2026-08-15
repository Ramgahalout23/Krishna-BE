<?php

namespace App\Services;

use App\Models\Webhook;
use App\Models\WebhookLog;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WebhookService
{
    /**
     * Dispatch an event to all active webhooks that subscribe to it.
     */
    public function dispatch(string $event, array $payload): void
    {
        $webhooks = Webhook::where('is_active', true)->get();

        foreach ($webhooks as $webhook) {
            $events = $webhook->events ?? [];
            if (empty($events) || in_array($event, $events)) {
                $this->send($webhook, $event, $payload);
            }
        }
    }

    /**
     * Send a test event to a specific webhook.
     */
    public function sendToWebhook(Webhook $webhook, string $event, array $payload): void
    {
        $this->send($webhook, $event, $payload);
    }

    /**
     * Send webhook payload to a single webhook URL.
     */
    protected function send(Webhook $webhook, string $event, array $payload): void
    {
        try {
            $body = [
                'event' => $event,
                'data' => $payload,
                'timestamp' => now()->toIso8601String(),
            ];

            $headers = [
                'Content-Type' => 'application/json',
                'X-Webhook-Event' => $event,
                'X-Webhook-Timestamp' => now()->timestamp,
            ];

            if ($webhook->secret) {
                $signature = hash_hmac('sha256', json_encode($body), $webhook->secret);
                $headers['X-Webhook-Signature'] = $signature;
            }

            $response = Http::timeout(10)
                ->withHeaders($headers)
                ->post($webhook->url, $body);

            WebhookLog::create([
                'webhook_id' => $webhook->id,
                'event' => $event,
                'payload' => json_encode($payload),
                'response_status' => $response->status(),
                'response_body' => substr($response->body(), 0, 5000),
                'success' => $response->successful(),
                'attempted_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning("[Webhook] Failed to send {$event} to {$webhook->url}: {$e->getMessage()}");

            WebhookLog::create([
                'webhook_id' => $webhook->id,
                'event' => $event,
                'payload' => json_encode($payload),
                'response_status' => null,
                'response_body' => $e->getMessage(),
                'success' => false,
                'attempted_at' => now(),
            ]);
        }
    }

    /**
     * Get all webhooks with optional pagination.
     */
    public function getAll(array $filters = []): array
    {
        $query = Webhook::query();
        $perPage = $filters['per_page'] ?? 20;
        return $query->latest()->paginate($perPage)->toArray();
    }

    /**
     * Get webhook logs with pagination.
     */
    public function getLogs(string $webhookId, array $filters = []): array
    {
        $query = WebhookLog::where('webhook_id', $webhookId);
        $perPage = $filters['per_page'] ?? 50;
        return $query->latest('attempted_at')->paginate($perPage)->toArray();
    }
}
