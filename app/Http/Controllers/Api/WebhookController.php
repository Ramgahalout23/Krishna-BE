<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Webhook;
use App\Models\WebhookLog;
use App\Services\WebhookService;
use App\Exceptions\AppError;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class WebhookController extends Controller
{
    public function __construct(protected WebhookService $webhookService) {}

    /**
     * Admin: List all webhooks.
     */
    public function index(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => Webhook::latest()->get()]);
    }

    /**
     * Admin: Create webhook.
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'url' => 'required|url',
                'events' => 'nullable|array',
                'is_active' => 'nullable|boolean',
            ]);

            $webhook = Webhook::create([
                'name' => $validated['name'],
                'url' => $validated['url'],
                'events' => $validated['events'] ?? [],
                'secret' => Str::random(32),
                'is_active' => $validated['is_active'] ?? true,
            ]);

            return response()->json(['success' => true, 'message' => 'Webhook created', 'data' => $webhook], 201);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    /**
     * Admin: Update webhook.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        try {
            $webhook = Webhook::findOrFail($id);
            $validated = $request->validate([
                'name' => 'sometimes|string|max:255',
                'url' => 'sometimes|url',
                'events' => 'nullable|array',
                'is_active' => 'nullable|boolean',
                'regenerate_secret' => 'nullable|boolean',
            ]);

            if (isset($validated['regenerate_secret']) && $validated['regenerate_secret']) {
                $validated['secret'] = Str::random(32);
            }

            $webhook->update($validated);
            return response()->json(['success' => true, 'message' => 'Webhook updated', 'data' => $webhook]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    /**
     * Admin: Delete webhook.
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            $webhook = Webhook::findOrFail($id);
            $webhook->logs()->delete();
            $webhook->delete();
            return response()->json(['success' => true, 'message' => 'Webhook deleted']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 404);
        }
    }

    /**
     * Admin: Get webhook logs.
     */
    public function logs(string $id): JsonResponse
    {
        try {
            $webhook = Webhook::findOrFail($id);
            $logs = WebhookLog::where('webhook_id', $id)
                ->latest('attempted_at')
                ->paginate(50);

            return response()->json(['success' => true, 'data' => $logs]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Webhook not found'], 404);
        }
    }

    /**
     * Admin: Test webhook by sending a ping.
     */
    public function test(string $id): JsonResponse
    {
        try {
            $webhook = Webhook::findOrFail($id);
            $this->webhookService->sendToWebhook($webhook, 'test.ping', [
                'message' => 'Webhook test from LUXE admin',
                'timestamp' => now()->toIso8601String(),
            ]);

            return response()->json(['success' => true, 'message' => 'Test event dispatched']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 404);
        }
    }
}
