<?php

namespace App\Services;

use App\Jobs\SendSmsJob;
use App\Models\Setting;
use App\Services\NotificationTemplateService;
use App\Traits\CacheKeyRegistry;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Twilio\Rest\Client;

class SMSService
{
    use CacheKeyRegistry;

    private ?Client $client = null;
    private ?string $clientAccountSid = null;
    private ?string $twilioPhoneNumber = null;
    private array $settings = [];
    private int $lastSettingsFetch = 0;
    private const SETTINGS_CACHE_TTL = 300; // 5 minutes

    public function __construct(
        protected NotificationTemplateService $notificationTemplateService
    ) {}

    /**
     * Check if SMS is enabled in settings.
     */
    public function isSmsEnabled(): bool
    {
        try {
            return $this->cacheWithTracking('sms_enabled', 300, function () {
                $smsEnabled = Setting::where('module', 'SITE')->where('key', 'smsEnabled')->first();
                if ($smsEnabled?->value !== 'true') return false;

                $settings = $this->fetchTwilioSettings();
                return !empty($settings['accountSid']) && !empty($settings['authToken']) && !empty($settings['phoneNumber']);
            });
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Check Twilio service health.
     */
    public function checkHealth(): array
    {
        try {
            $settings = $this->fetchTwilioSettings();
            if (empty($settings['accountSid']) || empty($settings['authToken'])) {
                return ['status' => 'not_configured', 'message' => 'Twilio not configured'];
            }

            $start = microtime(true);
            $client = $this->getClient();
            if (!$client) {
                return ['status' => 'error', 'message' => 'Failed to create Twilio client'];
            }

            $client->api->v2010->accounts($settings['accountSid'])->fetch();
            $latencyMs = round((microtime(true) - $start) * 1000);

            return [
                'status' => 'connected',
                'message' => 'Twilio account connected',
                'latency_ms' => $latencyMs,
            ];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    /**
     * Send an SMS message.
     * Dispatches a SendSmsJob to the queue for async processing.
     * This is the public-facing method used by controllers and other services.
     */
    public function sendSMS(string $to, string $body): bool
    {
        try {
            $normalizedTo = $this->normalizePhoneNumber($to);
            if (strlen($normalizedTo) < 5) {
                Log::warning("[SMS] Invalid phone number: {$to}");
                return false;
            }

            SendSmsJob::dispatch($normalizedTo, $body);
            Log::info("[SMS] Dispatched SMS job for {$normalizedTo}");
            return true;
        } catch (\Exception $e) {
            Log::error("[SMS] Failed to dispatch SMS job for {$to}: {$e->getMessage()}");
            return false;
        }
    }

    /**
     * Send an SMS synchronously (called by SendSmsJob).
     * This is the actual sending logic — always runs in the queue worker.
     */
    public function sendSMSSync(string $to, string $body): bool
    {
        try {
            $client = $this->getClient();
            if (!$client) {
                Log::warning("[SMS] No Twilio client — SMS not sent to {$to}");
                return false;
            }

            $settings = $this->fetchTwilioSettings();
            if (empty($settings['phoneNumber'])) {
                Log::warning('[SMS] No Twilio phone number configured');
                return false;
            }

            $normalizedTo = $this->normalizePhoneNumber($to);
            if (strlen($normalizedTo) < 5) {
                Log::warning("[SMS] Invalid phone number: {$to}");
                return false;
            }

            $message = $client->messages->create($normalizedTo, [
                'from' => $settings['phoneNumber'],
                'body' => $body,
            ]);

            Log::info("[SMS] Sent to {$normalizedTo}: {$message->sid}");
            return true;
        } catch (\Exception $e) {
            Log::error("[SMS] Failed to send to {$to}: {$e->getMessage()}");
            return false;
        }
    }

    /**
     * Send OTP via SMS.
     */
    public function sendOTP(string $phoneNumber, string $otpCode): bool
    {
        $rendered = $this->notificationTemplateService->renderTemplate('sms_otp', [
            'otpCode' => $otpCode,
            'expiryMinutes' => '5',
        ]);
        $body = ($rendered['rendered'] ?? false) ? $rendered['body'] : "Your verification code is: {$otpCode}. This code expires in 5 minutes. Do not share this with anyone.";
        return $this->sendSMS($phoneNumber, $body);
    }

    /**
     * Send order confirmation SMS.
     */
    public function sendOrderConfirmationSMS(string $phoneNumber, string $customerName, string $orderNumber, float $total): bool
    {
        $rendered = $this->notificationTemplateService->renderTemplate('sms_order_confirmation', [
            'customerName' => $customerName,
            'orderNumber' => $orderNumber,
            'total' => number_format($total, 2),
        ]);
        $body = ($rendered['rendered'] ?? false) ? $rendered['body'] : "Hi {$customerName}, your order {$orderNumber} is confirmed! Total: $" . number_format($total, 2) . ". We'll notify you when it ships. Thank you for shopping with us!";
        return $this->sendSMS($phoneNumber, $body);
    }

    /**
     * Send order status update SMS.
     */
    public function sendOrderStatusUpdateSMS(string $phoneNumber, string $customerName, string $orderNumber, string $newStatus): bool
    {
        $statusLabels = [
            'PENDING' => 'Pending', 'CONFIRMED' => 'Confirmed', 'PROCESSING' => 'Processing',
            'SHIPPED' => 'Shipped', 'DELIVERED' => 'Delivered', 'CANCELLED' => 'Cancelled',
            'RETURNED' => 'Returned', 'RETURN_REQUESTED' => 'Return Requested',
        ];
        $label = $statusLabels[$newStatus] ?? $newStatus;

        // Try the specific status template first, fall back to generic
        $templateId = match ($newStatus) {
            'SHIPPED' => 'sms_order_shipped',
            'DELIVERED' => 'sms_order_delivered',
            'CANCELLED' => 'sms_order_cancelled',
            default => 'sms_order_status_update',
        };

        $rendered = $this->notificationTemplateService->renderTemplate($templateId, [
            'customerName' => $customerName,
            'orderNumber' => $orderNumber,
            'newStatus' => $newStatus,
            'statusLabel' => $label,
        ]);

        if ($rendered['rendered'] ?? false) {
            return $this->sendSMS($phoneNumber, $rendered['body']);
        }

        // Fallback to hardcoded if template is inactive
        $body = match ($newStatus) {
            'SHIPPED' => "Hi {$customerName}, your order {$orderNumber} has been shipped! Track your package in your account dashboard.",
            'DELIVERED' => "Hi {$customerName}, your order {$orderNumber} has been delivered! We hope you love your purchase.",
            'CANCELLED' => "Hi {$customerName}, your order {$orderNumber} has been cancelled. Contact support for more details.",
            default => "Hi {$customerName}, your order {$orderNumber} status updated to: {$label}.",
        };

        return $this->sendSMS($phoneNumber, $body);
    }



    /**
     * Get or create Twilio client.
     */
    private function getClient(): ?Client
    {
        try {
            $settings = $this->fetchTwilioSettings();
            if (empty($settings['accountSid']) || empty($settings['authToken'])) {
                return null;
            }

            if (!$this->client || $this->clientAccountSid !== $settings['accountSid']) {
                $this->client = new Client($settings['accountSid'], $settings['authToken']);
                $this->clientAccountSid = $settings['accountSid'];
                $this->twilioPhoneNumber = $settings['phoneNumber'] ?? null;
            }

            return $this->client;
        } catch (\Exception $e) {
            Log::error('[SMS] Failed to create Twilio client: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Fetch Twilio settings from DB with cache.
     */
    private function fetchTwilioSettings(): array
    {
        $now = time();
        if (!empty($this->settings) && ($now - $this->lastSettingsFetch) < self::SETTINGS_CACHE_TTL) {
            return $this->settings;
        }

        try {
            $keys = ['twilioAccountSid', 'twilioAuthToken', 'twilioPhoneNumber'];
            $dbSettings = Setting::whereIn('key', $keys)->pluck('value', 'key')->toArray();

            $this->settings = [
                'accountSid' => $dbSettings['twilioAccountSid'] ?? config('services.twilio.account_sid') ?? env('TWILIO_ACCOUNT_SID', ''),
                'authToken' => $dbSettings['twilioAuthToken'] ?? config('services.twilio.auth_token') ?? env('TWILIO_AUTH_TOKEN', ''),
                'phoneNumber' => $dbSettings['twilioPhoneNumber'] ?? config('services.twilio.phone_number') ?? env('TWILIO_PHONE_NUMBER', ''),
            ];
            $this->lastSettingsFetch = $now;
        } catch (\Exception $e) {
            Log::warning('[SMS] Failed to fetch Twilio settings from DB, using config fallback');
            $this->settings = [
                'accountSid' => config('services.twilio.account_sid') ?? env('TWILIO_ACCOUNT_SID', ''),
                'authToken' => config('services.twilio.auth_token') ?? env('TWILIO_AUTH_TOKEN', ''),
                'phoneNumber' => config('services.twilio.phone_number') ?? env('TWILIO_PHONE_NUMBER', ''),
            ];
        }

        return $this->settings;
    }

    /**
     * Normalize phone number to E.164 format.
     */
    private function normalizePhoneNumber(string $phone): string
    {
        $trimmed = trim($phone);
        if (empty($trimmed)) return '';
        return str_starts_with($trimmed, '+') ? $trimmed : "+{$trimmed}";
    }
}
