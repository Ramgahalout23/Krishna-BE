<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\OrderTimeline;
use App\Services\WebhookService;
use App\Exceptions\AppError;
use Illuminate\Support\Facades\Log;
use Razorpay\Api\Api;
use Razorpay\Api\Errors\SignatureVerificationError;

class RazorpayService
{
    private ?Api $api = null;
    private ?string $keyId = null;
    private ?string $keySecret = null;

    public function __construct(
        protected WebhookService $webhookService
    ) {}

    /**
     * Initialize Razorpay SDK with credentials from settings or config.
     */
    public function init(?string $keyId = null, ?string $keySecret = null): void
    {
        $this->keyId = $keyId ?? config('services.razorpay.key_id') ?? env('RAZORPAY_KEY_ID');
        $this->keySecret = $keySecret ?? config('services.razorpay.key_secret') ?? env('RAZORPAY_KEY_SECRET');

        if ($this->keyId && $this->keySecret) {
            $this->api = new Api($this->keyId, $this->keySecret);
        }
    }

    /**
     * Check Razorpay API health.
     */
    public function checkHealth(): array
    {
        try {
            $this->ensureInitialized();

            $start = microtime(true);
            $this->api->order->all(['count' => 1]);
            $latencyMs = round((microtime(true) - $start) * 1000);

            return [
                'status' => 'connected',
                'message' => "Razorpay API connected (key: " . substr($this->keyId, 0, 8) . "...)",
                'latency_ms' => $latencyMs,
            ];
        } catch (\Exception $e) {
            Log::error("[Razorpay] Health check failed: {$e->getMessage()}");
            return [
                'status' => 'error',
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Create a Razorpay order.
     */
    public function createOrder(string $orderId, float $amount, string $currency = 'INR'): array
    {
        $this->ensureInitialized();

        try {
            $razorpayOrder = $this->api->order->create([
                'amount' => (int) round($amount * 100), // Amount in paise
                'currency' => $currency,
                'receipt' => $orderId,
                'notes' => ['order_id' => $orderId],
            ]);

            // Store razorpay_order_id in payment metadata
            $payment = Payment::where('order_id', $orderId)->first();
            if ($payment) {
                $existingMeta = $payment->metadata ?? [];
                $payment->update([
                    'metadata' => array_merge((array) $existingMeta, [
                        'razorpay_order_id' => $razorpayOrder->id,
                    ]),
                ]);
            }

            Log::info("Razorpay order created: {$razorpayOrder->id} for order: {$orderId}");

            return [
                'razorpay_order_id' => $razorpayOrder->id,
                'key_id' => $this->keyId,
                'amount' => (int) $razorpayOrder->amount,
                'currency' => $razorpayOrder->currency,
            ];
        } catch (\Exception $e) {
            Log::error("[Razorpay] Order creation failed for {$orderId}: {$e->getMessage()}");
            throw new AppError(500, "Razorpay order creation failed: {$e->getMessage()}");
        }
    }

    /**
     * Verify Razorpay payment signature and update order/payment status.
     */
    public function verifyPayment(
        string $orderId,
        string $razorpayPaymentId,
        string $razorpayOrderId,
        string $signature
    ): array {
        try {
            $this->ensureInitialized();

            // Verify signature using HMAC SHA256
            try {
                $this->api->utility->verifyPaymentSignature([
                    'razorpay_order_id' => $razorpayOrderId,
                    'razorpay_payment_id' => $razorpayPaymentId,
                    'razorpay_signature' => $signature,
                ]);
            } catch (SignatureVerificationError $e) {
                Log::warning("[Razorpay] Signature mismatch for order {$orderId}");
                throw AppError::validation('Payment verification failed — invalid signature');
            }

            // Find payment by orderId
            $payment = Payment::where('order_id', $orderId)->first();
            if (!$payment) {
                throw AppError::notFound('Payment not found for this order');
            }

            if ($payment->status === 'COMPLETED') {
                throw AppError::conflict('Payment is already processed');
            }

            // Update payment status
            $payment->update([
                'status' => 'COMPLETED',
                'transaction_id' => $razorpayPaymentId,
                'gateway_response' => json_encode([
                    'razorpay_payment_id' => $razorpayPaymentId,
                    'razorpay_order_id' => $razorpayOrderId,
                    'signature' => $signature,
                ]),
            ]);

            // Update order status
            $payment->order?->update(['status' => 'CONFIRMED']);

            // Create order timeline entry
            OrderTimeline::create([
                'order_id' => $orderId,
                'status' => 'CONFIRMED',
                'description' => 'Payment received and verified via Razorpay',
            ]);

            // ── Webhook: payment.completed ──
            try {
                $this->webhookService->dispatch('payment.completed', [
                    'payment_id' => $payment->id,
                    'order_id' => $orderId,
                    'transaction_id' => $razorpayPaymentId,
                    'method' => 'RAZORPAY',
                    'amount' => (float) $payment->amount,
                    'razorpay_order_id' => $razorpayOrderId,
                    'status' => 'COMPLETED',
                    'completed_at' => now()->toIso8601String(),
                ]);
            } catch (\Exception $e) {
                Log::error('[Webhook] Failed to dispatch payment.completed', ['error' => $e->getMessage()]);
            }

            Log::info("Razorpay payment verified: {$razorpayPaymentId} for order: {$orderId}");

            return $payment->toArray();
        } catch (AppError $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error("[Razorpay] Payment verification failed for {$orderId}: {$e->getMessage()}");
            throw new AppError(500, "Payment verification failed: {$e->getMessage()}");
        }
    }

    /**
     * Fetch payment details from Razorpay.
     */
    public function fetchPayment(string $razorpayPaymentId): array
    {
        $this->ensureInitialized();

        try {
            $payment = $this->api->payment->fetch($razorpayPaymentId);
            return $payment->toArray();
        } catch (\Exception $e) {
            Log::error("[Razorpay] Fetch payment failed: {$e->getMessage()}");
            throw new AppError(500, "Failed to fetch payment details: {$e->getMessage()}");
        }
    }

    /**
     * Ensure Razorpay SDK is initialized with credentials.
     */
    private function ensureInitialized(): void
    {
        if ($this->api === null) {
            $this->init();
        }
        if ($this->api === null) {
            throw AppError::validation('Razorpay is not configured. Set RAZORPAY_KEY_ID and RAZORPAY_KEY_SECRET in .env or admin settings.');
        }
    }
}
