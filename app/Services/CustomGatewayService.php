<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\OrderTimeline;
use App\Exceptions\AppError;
use App\Models\Setting;
use App\Services\WebhookService;
use Illuminate\Support\Facades\Log;

class CustomGatewayService
{
    public function __construct(
        protected WebhookService $webhookService
    ) {}

    /**
     * Get all settings as key-value map.
     */
    private function getSettingsMap(): array
    {
        $settings = Setting::pluck('value', 'key')->toArray();
        return $settings;
    }

    /**
     * Get configured custom payment gateways from settings.
     */
    public function getGateways(): array
    {
        $settings = $this->getSettingsMap();
        $methodsStr = $settings['dynamic_payment_methods'] ?? '[]';

        $gateways = json_decode($methodsStr, true) ?? [];
        return array_values(array_filter($gateways, function ($g) {
            return !isset($g['enabled']) || $g['enabled'] !== false;
        }));
    }

    /**
     * Initiate payment with a custom gateway configured in admin settings.
     * Returns gateway details + optional redirectUrl for frontend redirect.
     */
    public function initiatePayment(string $gatewayId, string $orderId, float $amount, string $userId, ?string $returnUrl = null): array
    {
        $settings = $this->getSettingsMap();
        $methodsStr = $settings['dynamic_payment_methods'] ?? '[]';

        $gateways = json_decode($methodsStr, true);
        if (empty($gateways) || !is_array($gateways)) {
            throw AppError::validation('No custom payment gateways configured');
        }

        // Find the requested gateway
        $gateway = null;
        foreach ($gateways as $g) {
            if (strtoupper((string) ($g['id'] ?? '')) === strtoupper($gatewayId) && (!isset($g['enabled']) || $g['enabled'] !== false)) {
                $gateway = $g;
                break;
            }
        }

        if (!$gateway) {
            throw AppError::notFound("Gateway \"{$gatewayId}\" not found or has been disabled");
        }

        // Build credentials from gateway fields
        $credentials = [];
        if (!empty($gateway['fields']) && is_array($gateway['fields'])) {
            foreach ($gateway['fields'] as $field) {
                $credentials[$field['key']] = $field['value'] ?? '';
            }
        }

        // Store gateway info in payment metadata
        $payment = Payment::where('order_id', $orderId)->first();
        if ($payment) {
            $existingMeta = $payment->metadata ?? [];
            $payment->update([
                'metadata' => array_merge((array) $existingMeta, [
                    'gateway_id' => $gateway['id'],
                    'gateway_name' => $gateway['name'],
                    'gateway_type' => 'custom',
                ]),
            ]);
        }

        // Generate redirect URL from gateway's paymentUrl template
        $backendUrl = config('app.url') ?? 'http://localhost:8000';
        $frontendUrl = $returnUrl ?? config('app.frontend_url') ?? 'http://localhost:5173';
        $callbackUrl = $backendUrl . '/api/v1/payments/callback?orderId=' . urlencode($orderId)
            . '&returnUrl=' . urlencode($frontendUrl . '/order/thank-you/' . $orderId);

        $redirectUrl = null;
        if (!empty($gateway['paymentUrl'])) {
            $redirectUrl = $gateway['paymentUrl'];
            $redirectUrl = str_replace('{orderId}', $orderId, $redirectUrl);
            $redirectUrl = str_replace('{amount}', (string) round($amount), $redirectUrl);
            $redirectUrl = str_replace('{callbackUrl}', urlencode($callbackUrl), $redirectUrl);
            $redirectUrl = str_replace('{returnUrl}', urlencode($frontendUrl . '/order/thank-you/' . $orderId), $redirectUrl);

            // Replace credential placeholders
            foreach ($credentials as $key => $value) {
                $redirectUrl = str_replace('{' . $key . '}', urlencode($value), $redirectUrl);
            }
        }

        // Filter out sensitive fields for public response
        $publicFields = [];
        if (!empty($gateway['fields']) && is_array($gateway['fields'])) {
            foreach ($gateway['fields'] as $field) {
                if (($field['type'] ?? '') !== 'password') {
                    $publicFields[$field['key']] = $field['value'] ?? '';
                }
            }
        }

        Log::info("Custom gateway payment initiated: {$gateway['id']} for order: {$orderId}");

        return [
            'gateway_id' => $gateway['id'],
            'gateway_name' => $gateway['name'],
            'gateway_description' => $gateway['description'] ?? '',
            'public_fields' => $publicFields,
            'order_id' => $orderId,
            'redirect_url' => $redirectUrl,
        ];
    }

    /**
     * Process a webhook callback from a custom gateway.
     */
    public function processWebhook(array $body): void
    {
        $event = $body['event'] ?? null;
        $orderId = $body['orderId'] ?? $body['order_id'] ?? null;
        $transactionId = $body['transactionId'] ?? $body['transaction_id'] ?? null;

        if (!$event || !$orderId) {
            throw AppError::validation('Invalid webhook payload — event and orderId required');
        }

        $payment = Payment::where('order_id', $orderId)->first();
        if (!$payment) {
            throw AppError::notFound("No payment found for order {$orderId}");
        }

        switch ($event) {
            case 'payment.success':
                $payment->update([
                    'status' => 'COMPLETED',
                    'transaction_id' => $transactionId,
                    'gateway_response' => json_encode($body),
                ]);
                if ($payment->order) {
                    $payment->order->update(['status' => 'CONFIRMED']);
                }
                OrderTimeline::create([
                    'order_id' => $orderId,
                    'status' => 'CONFIRMED',
                    'description' => 'Payment received via custom gateway webhook',
                ]);

                // ── Webhook: payment.completed ──
                try {
                    $this->webhookService->dispatch('payment.completed', [
                        'payment_id' => $payment->id,
                        'order_id' => $orderId,
                        'transaction_id' => $transactionId,
                        'method' => $payment->method,
                        'amount' => (float) $payment->amount,
                        'status' => 'COMPLETED',
                        'completed_at' => now()->toIso8601String(),
                    ]);
                } catch (\Exception $e) {
                    Log::error('[Webhook] Failed to dispatch payment.completed', ['error' => $e->getMessage()]);
                }

                Log::info("Custom gateway webhook: payment success for order {$orderId}");
                break;

            case 'payment.failed':
                $payment->update([
                    'status' => 'FAILED',
                    'gateway_response' => json_encode($body),
                ]);
                Log::warning("Custom gateway webhook: payment failed for order {$orderId}");
                break;

            default:
                Log::warning("Unhandled custom gateway webhook event: {$event} for order {$orderId}");
        }
    }
}
