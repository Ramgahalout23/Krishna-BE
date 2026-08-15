<?php

namespace App\Services;

use App\Models\Subscriber;
use App\Models\WhatsAppTemplate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppAdsService
{
    private ?string $phoneNumberId = null;
    private ?string $accessToken = null;
    private ?string $businessAccountId = null;
    private bool $initialized = false;
    private string $apiVersion = 'v21.0';
    private string $baseUrl = 'https://graph.facebook.com';

    public function __construct()
    {
        $this->phoneNumberId = config('services.whatsapp.phone_number_id') ?? env('WHATSAPP_PHONE_NUMBER_ID');
        $this->accessToken = config('services.whatsapp.access_token') ?? env('WHATSAPP_ACCESS_TOKEN');
        $this->businessAccountId = config('services.whatsapp.business_account_id') ?? env('WHATSAPP_BUSINESS_ACCOUNT_ID');

        if ($this->phoneNumberId && $this->accessToken) {
            $this->initialized = true;
        }
    }

    /**
     * Initialize with explicit config.
     */
    public function init(string $accessToken, string $phoneNumberId, ?string $businessAccountId = null): void
    {
        $this->accessToken = $accessToken;
        $this->phoneNumberId = $phoneNumberId;
        $this->businessAccountId = $businessAccountId;
        $this->initialized = true;
        Log::info("WhatsApp Ads SDK initialized for phone: {$phoneNumberId}");
    }

    public function isConfigured(): bool
    {
        return $this->initialized && !empty($this->accessToken) && !empty($this->phoneNumberId);
    }

    private function checkInit(): void
    {
        if (!$this->initialized) {
            throw new \Exception('WhatsAppAdsService not initialized. Call init(accessToken, phoneNumberId) first.');
        }
    }

    private function getHeaders(): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->accessToken,
            'Content-Type' => 'application/json',
        ];
    }

    /**
     * Get WhatsApp recipients (active subscribers with phone).
     */
    public function getRecipients(): array
    {
        try {
            return Subscriber::where('status', 'ACTIVE')
                ->whereNotNull('phone')
                ->get()
                ->toArray();
        } catch (\Exception $e) {
            Log::warning("[WhatsApp] Failed to fetch recipients: {$e->getMessage()}");
            return [];
        }
    }

    /**
     * Send a message template via WhatsApp Cloud API.
     */
    public function sendTemplateMessage(string $to, string $templateName, array $parameters = []): array
    {
        if (!$this->isConfigured()) {
            return ['success' => false, 'message' => 'WhatsApp API not configured'];
        }

        try {
            $body = [
                'messaging_product' => 'whatsapp',
                'to' => $to,
                'type' => 'template',
                'template' => [
                    'name' => $templateName,
                    'language' => ['code' => 'en_US'],
                    'components' => [],
                ],
            ];

            if (!empty($parameters)) {
                $body['template']['components'][] = [
                    'type' => 'body',
                    'parameters' => array_map(function ($key, $val) {
                        return ['type' => 'text', 'text' => $val];
                    }, array_keys($parameters), $parameters),
                ];
            }

            $response = Http::withHeaders($this->getHeaders())
                ->post("{$this->baseUrl}/{$this->apiVersion}/{$this->phoneNumberId}/messages", $body);

            if ($response->successful()) {
                $msgId = $response->json('messages.0.id');
                return ['success' => true, 'message' => 'Message sent', 'wa_id' => $msgId];
            }

            return ['success' => false, 'message' => 'WhatsApp API error: ' . $response->body()];
        } catch (\Exception $e) {
            Log::error("[WhatsApp] Send failed: {$e->getMessage()}");
            return ['success' => false, 'message' => "WhatsApp error: {$e->getMessage()}"];
        }
    }

    /**
     * Send marketing broadcast to all active subscribers.
     */
    public function sendBroadcast(string $templateName, array $parameters = []): array
    {
        $recipients = $this->getRecipients();
        $results = ['sent' => 0, 'failed' => 0, 'total' => count($recipients)];

        foreach ($recipients as $recipient) {
            if (empty($recipient['phone'])) continue;

            $result = $this->sendTemplateMessage($recipient['phone'], $templateName, $parameters);
            if ($result['success']) {
                $results['sent']++;
            } else {
                $results['failed']++;
            }
        }

        return $results;
    }

    /**
     * Send bulk broadcast to specified recipients with concurrency control (matching TS behavior).
     * Processes in batches to respect rate limits with 1-second delay between batches.
     */
    public function sendBulkBroadcast(array $recipients, string $templateName, array $parameters = [], int $concurrency = 5): array
    {
        $this->checkInit();
        $results = [];
        $sent = 0;
        $failed = 0;

        // Process in batches
        $batches = array_chunk($recipients, $concurrency);

        foreach ($batches as $batchIndex => $batch) {
            $batchResults = [];

            foreach ($batch as $recipient) {
                $phone = $recipient['phone'] ?? $recipient;
                $recipientParams = $parameters;

                // Merge recipient-specific parameters with template defaults
                if (isset($recipient['params']) && is_array($recipient['params'])) {
                    $recipientParams = array_merge($parameters, $recipient['params']);
                }

                try {
                    $result = $this->sendTemplateMessage($phone, $templateName, $recipientParams);
                    if ($result['success']) {
                        $sent++;
                    } else {
                        $failed++;
                    }
                    $batchResults[] = [
                        'phone' => $phone,
                        'success' => $result['success'],
                        'error' => $result['success'] ? null : ($result['message'] ?? 'Failed to send'),
                    ];
                } catch (\Exception $e) {
                    $failed++;
                    $batchResults[] = [
                        'phone' => $phone,
                        'success' => false,
                        'error' => $e->getMessage(),
                    ];
                }
            }

            $results = array_merge($results, $batchResults);

            // Rate limit: wait 1 second between batches (except last)
            if ($batchIndex < count($batches) - 1) {
                usleep(1000000); // 1 second
            }
        }

        Log::info("WhatsApp broadcast: {$sent} sent, {$failed} failed");

        return [
            'results' => $results,
            'sent' => $sent,
            'failed' => $failed,
        ];
    }

    /**
     * Create a WhatsApp message template (stub - requires Meta Business Platform approval).
     */
    public function createTemplate(array $data): array
    {
        return [
            'success' => true,
            'message' => 'WhatsApp template creation requires Meta Business Platform approval',
            'template_name' => $data['name'] ?? 'unnamed',
        ];
    }

    /**
     * Verify WhatsApp Business phone number.
     * GET /{phone-number-id} to verify the phone number is registered.
     */
    public function verifyPhoneNumber(): array
    {
        $this->checkInit();

        try {
            $response = Http::withHeaders($this->getHeaders())
                ->get("{$this->baseUrl}/{$this->apiVersion}/{$this->phoneNumberId}");

            if ($response->successful()) {
                $data = $response->json();
                return [
                    'valid' => true,
                    'phone_number' => $data['displayPhoneNumber'] ?? $data['id'],
                    'name_status' => $data['nameStatus'] ?? null,
                    'code_verification_status' => $data['codeVerificationStatus'] ?? null,
                ];
            }

            return ['valid' => false, 'message' => 'Phone number verification failed: ' . $response->body()];
        } catch (\Exception $e) {
            Log::error("[WhatsApp] Verification failed: {$e->getMessage()}");
            return ['valid' => false, 'message' => "Verification error: {$e->getMessage()}"];
        }
    }

    /**
     * Get message templates from WhatsApp Business Account.
     */
    public function getMessageTemplates(): array
    {
        $this->checkInit();

        if (empty($this->businessAccountId)) {
            throw new \Exception('Business Account ID is required to fetch templates');
        }

        try {
            $response = Http::withHeaders($this->getHeaders())
                ->get("{$this->baseUrl}/{$this->apiVersion}/{$this->businessAccountId}/message_templates");

            if ($response->successful()) {
                return $response->json('data', []);
            }

            throw new \Exception('Failed to fetch templates: ' . $response->body());
        } catch (\Exception $e) {
            Log::error("[WhatsApp] Fetch templates failed: {$e->getMessage()}");
            throw new \Exception("WhatsApp templates error: {$e->getMessage()}");
        }
    }
}
