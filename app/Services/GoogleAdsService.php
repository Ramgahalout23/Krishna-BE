<?php

namespace App\Services;

use App\Models\AdCampaign;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GoogleAdsService
{
    private ?string $clientId = null;
    private ?string $clientSecret = null;
    private ?string $developerToken = null;
    private ?string $refreshToken = null;
    private ?string $customerId = null;
    private ?string $loginCustomerId = null;
    private bool $initialized = false;
    private ?string $accessToken = null;
    private int $tokenExpiry = 0;
    private string $apiVersion = 'v16';
    private string $baseUrl = 'https://googleads.googleapis.com';
    private string $oauthUrl = 'https://oauth2.googleapis.com';

    public function __construct()
    {
        $this->clientId = config('services.google_ads.client_id') ?? env('GOOGLE_ADS_CLIENT_ID');
        $this->clientSecret = config('services.google_ads.client_secret') ?? env('GOOGLE_ADS_CLIENT_SECRET');
        $this->developerToken = config('services.google_ads.developer_token') ?? env('GOOGLE_ADS_DEVELOPER_TOKEN');
        $this->refreshToken = config('services.google_ads.refresh_token') ?? env('GOOGLE_ADS_REFRESH_TOKEN');
        $this->customerId = config('services.google_ads.customer_id') ?? env('GOOGLE_ADS_CUSTOMER_ID');
        $this->loginCustomerId = config('services.google_ads.login_customer_id');

        if ($this->clientId && $this->clientSecret && $this->developerToken && $this->refreshToken && $this->customerId) {
            $this->initialized = true;
        }
    }

    public function isConfigured(): bool
    {
        return $this->initialized;
    }

    /**
     * Initialize with explicit config (overrides env).
     */
    public function init(array $config): void
    {
        $this->clientId = $config['client_id'] ?? $this->clientId;
        $this->clientSecret = $config['client_secret'] ?? $this->clientSecret;
        $this->developerToken = $config['developer_token'] ?? $this->developerToken;
        $this->refreshToken = $config['refresh_token'] ?? $this->refreshToken;
        $this->customerId = $config['customer_id'] ?? $this->customerId;
        $this->loginCustomerId = $config['login_customer_id'] ?? $this->loginCustomerId;
        $this->initialized = true;
        Log::info("Google Ads service initialized for account: {$this->customerId}");
    }

    private function checkInit(): void
    {
        if (!$this->initialized) {
            throw new \Exception('GoogleAdsService not initialized. Call init(config) or set env vars.');
        }
    }

    /**
     * Get or refresh OAuth 2.0 access token.
     */
    private function getAccessToken(): string
    {
        if ($this->accessToken && time() * 1000 < $this->tokenExpiry) {
            return $this->accessToken;
        }

        try {
            $response = Http::asForm()->post("{$this->oauthUrl}/token", [
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
                'refresh_token' => $this->refreshToken,
                'grant_type' => 'refresh_token',
            ]);

            if (!$response->successful()) {
                throw new \Exception('OAuth token refresh failed: ' . $response->body());
            }

            $this->accessToken = $response->json('access_token');
            $this->tokenExpiry = (int) (time() * 1000 + ($response->json('expires_in', 3600) - 60) * 1000);
            Log::info('Google Ads OAuth token refreshed');
            return $this->accessToken;
        } catch (\Exception $e) {
            Log::error("Google Ads OAuth error: {$e->getMessage()}");
            throw new \Exception("Google Ads auth error: {$e->getMessage()}");
        }
    }

    /**
     * Get headers for Google Ads API requests.
     */
    private function getHeaders(): array
    {
        $token = $this->getAccessToken();
        return [
            'Authorization' => 'Bearer ' . $token,
            'Content-Type' => 'application/json',
            'developer-token' => $this->developerToken,
            'login-customer-id' => $this->loginCustomerId ?? $this->customerId,
        ];
    }

    /**
     * Test connection to Google Ads API.
     * Fetches customer account info to verify credentials.
     */
    public function testConnection(): array
    {
        if (!$this->isConfigured()) {
            return ['connected' => false, 'message' => 'Google Ads API not configured'];
        }

        try {
            $headers = $this->getHeaders();
            $customerIdClean = str_replace('-', '', $this->customerId);

            $query = "SELECT customer.id, customer.descriptive_name, customer.currency_code, customer.time_zone FROM customer WHERE customer.id = {$customerIdClean}";

            $response = Http::withHeaders($headers)
                ->timeout(15)
                ->post("{$this->baseUrl}/{$this->apiVersion}/customers/{$customerIdClean}/googleAds:search", [
                    'query' => $query,
                ]);

            if ($response->successful()) {
                $results = $response->json('results', []);
                if (!empty($results)) {
                    $cust = $results[0]['customer'] ?? [];
                    return [
                        'connected' => true,
                        'customer_id' => $this->customerId,
                        'account_name' => $cust['descriptiveName'] ?? null,
                        'currency_code' => $cust['currencyCode'] ?? null,
                        'time_zone' => $cust['timeZone'] ?? null,
                    ];
                }
                return ['connected' => true, 'customer_id' => $this->customerId];
            }

            return ['connected' => false, 'message' => 'Google Ads API error: ' . $response->body()];
        } catch (\Exception $e) {
            Log::error("Google Ads connection test failed: {$e->getMessage()}");
            return ['connected' => false, 'message' => "Connection error: {$e->getMessage()}"];
        }
    }

    /**
     * Extract YouTube video ID from various URL formats.
     */
    private function extractYoutubeVideoId(string $url): ?string
    {
        if (empty($url)) return null;

        if (preg_match('/youtu\.be\/([a-zA-Z0-9_-]+)/', $url, $m)) return $m[1];
        if (preg_match('/youtube\.com\/.*[?&]v=([a-zA-Z0-9_-]+)/', $url, $m)) return $m[1];
        if (preg_match('/youtube\.com\/embed\/([a-zA-Z0-9_-]+)/', $url, $m)) return $m[1];
        if (preg_match('/youtube\.com\/shorts\/([a-zA-Z0-9_-]+)/', $url, $m)) return $m[1];
        if (preg_match('/^[a-zA-Z0-9_-]{11}$/', $url)) return $url;

        return null;
    }

    /**
     * Create a YouTube video campaign.
     */
    public function createYouTubeCampaign(array $params): array
    {
        $this->checkInit();
        $headers = $this->getHeaders();
        $customerIdClean = str_replace('-', '', $this->customerId);

        $videoId = $this->extractYoutubeVideoId($params['video_url'] ?? $params['videoUrl']);
        if (!$videoId) {
            throw new \Exception('Invalid YouTube video URL');
        }

        $adFormat = [
            'INSTREAM' => 'IN_STREAM',
            'BUMPER' => 'IN_STREAM',
            'OUTSTREAM' => 'OUT_STREAM',
            'MASTHEAD' => 'MASTHEAD',
        ][$params['ad_format'] ?? 'INSTREAM'] ?? 'IN_STREAM';

        try {
            // Step 1: Create Campaign
            $campaignName = ($params['name'] ?? 'Campaign') . ' - YouTube';
            $campaignBudget = (int) round(($params['budget'] ?? 0) * 1000000);

            $campaignResponse = Http::withHeaders($headers)
                ->timeout(30)
                ->post("{$this->baseUrl}/{$this->apiVersion}/customers/{$customerIdClean}/campaigns:mutate", [
                    'operations' => [[
                        'create' => [
                            'name' => $campaignName,
                            'advertisingChannelType' => 'VIDEO',
                            'status' => 'PAUSED',
                            'campaignBudget' => [
                                'name' => "{$campaignName} Budget",
                                'amountMicros' => $campaignBudget,
                                'deliveryMethod' => 'STANDARD',
                                'explicitlyShared' => false,
                            ],
                        ],
                    ]],
                ]);

            if (!$campaignResponse->successful()) {
                throw new \Exception('Campaign creation failed: ' . $campaignResponse->body());
            }

            $campaignResourceName = $campaignResponse->json('results.0.resourceName');
            if (!$campaignResourceName) throw new \Exception('Failed to create campaign');
            $campaignId = basename($campaignResourceName);

            // Step 2: Create Ad Group
            $adGroupResponse = Http::withHeaders($headers)
                ->timeout(30)
                ->post("{$this->baseUrl}/{$this->apiVersion}/customers/{$customerIdClean}/adGroups:mutate", [
                    'operations' => [[
                        'create' => [
                            'name' => ($params['name'] ?? 'Campaign') . ' - Ad Group',
                            'campaign' => $campaignResourceName,
                            'type' => 'VIDEO_TRUE_VIEW_IN_STREAM',
                            'status' => 'PAUSED',
                            'targetingSetting' => [
                                'targetRestrictions' => [[
                                    'targetingDimension' => 'AUDIENCE',
                                    'bidOnly' => true,
                                ]],
                            ],
                        ],
                    ]],
                ]);

            if (!$adGroupResponse->successful()) {
                throw new \Exception('Ad group creation failed: ' . $adGroupResponse->body());
            }

            $adGroupResourceName = $adGroupResponse->json('results.0.resourceName');
            if (!$adGroupResourceName) throw new \Exception('Failed to create ad group');
            $adGroupId = basename($adGroupResourceName);

            // Step 3: Create Video Ad
            $landingUrl = $params['landing_url'] ?? $params['landingUrl'] ?? '';
            $adBody = [
                'operations' => [[
                    'create' => [
                        'adGroup' => $adGroupResourceName,
                        'status' => 'PAUSED',
                        'ad' => [
                            'name' => ($params['name'] ?? 'Campaign') . ' - Video Ad',
                            'finalUrls' => [$landingUrl],
                            'video' => [
                                'videoId' => $videoId,
                                'adFormat' => $adFormat,
                            ],
                        ],
                    ],
                ]],
            ];

            if (!empty($params['companion_banner_url'] ?? $params['companionBannerUrl'])) {
                $adBody['operations'][0]['create']['ad']['companionBanner'] = [
                    'imageUrl' => $params['companion_banner_url'] ?? $params['companionBannerUrl'],
                ];
            }

            $adResponse = Http::withHeaders($headers)
                ->timeout(30)
                ->post("{$this->baseUrl}/{$this->apiVersion}/customers/{$customerIdClean}/adGroupAds:mutate", $adBody);

            if (!$adResponse->successful()) {
                throw new \Exception('Ad creation failed: ' . $adResponse->body());
            }

            $adResourceName = $adResponse->json('results.0.resourceName');
            $adId = $adResourceName ? basename($adResourceName) : '';

            Log::info("Google Ads YouTube campaign created: {$campaignResourceName}");

            return [
                'campaign_id' => $campaignId,
                'ad_group_id' => $adGroupId,
                'ad_id' => $adId,
                'resource_name' => $campaignResourceName,
                'status' => 'PAUSED',
            ];
        } catch (\Exception $e) {
            Log::error("Google Ads YouTube campaign creation failed: {$e->getMessage()}");
            throw $e;
        }
    }

    /**
     * Create a Display Network campaign.
     */
    public function createDisplayCampaign(array $params): array
    {
        $this->checkInit();
        $headers = $this->getHeaders();
        $customerIdClean = str_replace('-', '', $this->customerId);

        try {
            $campaignName = ($params['name'] ?? 'Campaign') . ' - Display';
            $campaignBudget = (int) round(($params['budget'] ?? 0) * 1000000);

            // Step 1: Create Campaign
            $campaignResponse = Http::withHeaders($headers)
                ->timeout(30)
                ->post("{$this->baseUrl}/{$this->apiVersion}/customers/{$customerIdClean}/campaigns:mutate", [
                    'operations' => [[
                        'create' => [
                            'name' => $campaignName,
                            'advertisingChannelType' => 'DISPLAY',
                            'status' => 'PAUSED',
                            'campaignBudget' => [
                                'name' => "{$campaignName} Budget",
                                'amountMicros' => $campaignBudget,
                                'deliveryMethod' => 'STANDARD',
                                'explicitlyShared' => false,
                            ],
                        ],
                    ]],
                ]);

            if (!$campaignResponse->successful()) {
                throw new \Exception('Display campaign creation failed: ' . $campaignResponse->body());
            }

            $campaignResourceName = $campaignResponse->json('results.0.resourceName');
            $campaignId = basename($campaignResourceName);

            // Step 2: Create Ad Group
            $adGroupResponse = Http::withHeaders($headers)
                ->timeout(30)
                ->post("{$this->baseUrl}/{$this->apiVersion}/customers/{$customerIdClean}/adGroups:mutate", [
                    'operations' => [[
                        'create' => [
                            'name' => ($params['name'] ?? 'Campaign') . ' - Ad Group',
                            'campaign' => $campaignResourceName,
                            'type' => 'DISPLAY_STANDARD',
                            'status' => 'PAUSED',
                        ],
                    ]],
                ]);

            if (!$adGroupResponse->successful()) {
                throw new \Exception('Ad group creation failed: ' . $adGroupResponse->body());
            }

            $adGroupResourceName = $adGroupResponse->json('results.0.resourceName');
            $adGroupId = basename($adGroupResourceName);

            // Step 3: Create Display Ad
            $landingUrl = $params['landing_url'] ?? $params['landingUrl'] ?? '';
            $imageUrl = $params['image_url'] ?? $params['imageUrl'] ?? '';

            $adResponse = Http::withHeaders($headers)
                ->timeout(30)
                ->post("{$this->baseUrl}/{$this->apiVersion}/customers/{$customerIdClean}/adGroupAds:mutate", [
                    'operations' => [[
                        'create' => [
                            'adGroup' => $adGroupResourceName,
                            'status' => 'PAUSED',
                            'ad' => [
                                'name' => ($params['name'] ?? 'Campaign') . ' - Display Ad',
                                'finalUrls' => [$landingUrl],
                                'responsiveDisplayAd' => [
                                    'marketingImages' => [['url' => $imageUrl, 'assetName' => 'main_image']],
                                    'shortHeadline' => substr($params['name'] ?? 'Campaign', 0, 25),
                                    'longHeadline' => substr($params['name'] ?? 'Campaign', 0, 90),
                                    'description' => substr("Check out " . ($params['name'] ?? ''), 0, 90),
                                    'businessName' => substr($params['name'] ?? 'Campaign', 0, 25),
                                ],
                            ],
                        ],
                    ]],
                ]);

            if (!$adResponse->successful()) {
                throw new \Exception('Display ad creation failed: ' . $adResponse->body());
            }

            $adResourceName = $adResponse->json('results.0.resourceName');
            $adId = $adResourceName ? basename($adResourceName) : '';

            Log::info("Google Ads Display campaign created: {$campaignResourceName}");

            return [
                'campaign_id' => $campaignId,
                'ad_group_id' => $adGroupId,
                'ad_id' => $adId,
                'resource_name' => $campaignResourceName,
                'status' => 'PAUSED',
            ];
        } catch (\Exception $e) {
            Log::error("Google Ads Display campaign creation failed: {$e->getMessage()}");
            throw $e;
        }
    }

    /**
     * Update campaign status on Google Ads.
     */
    public function updateCampaignStatus(string $resourceName, string $status): array
    {
        $this->checkInit();
        $headers = $this->getHeaders();
        $customerIdClean = str_replace('-', '', $this->customerId);

        $adStatus = $status === 'ACTIVE' ? 'ENABLED' : 'PAUSED';

        try {
            $response = Http::withHeaders($headers)
                ->timeout(15)
                ->post("{$this->baseUrl}/{$this->apiVersion}/customers/{$customerIdClean}/campaigns:mutate", [
                    'operations' => [[
                        'update' => [
                            'resourceName' => $resourceName,
                            'status' => $adStatus,
                        ],
                        'updateMask' => 'status',
                    ]],
                ]);

            if (!$response->successful()) {
                throw new \Exception('Status update failed: ' . $response->body());
            }

            Log::info("Google Ads campaign {$resourceName} status updated to {$status}");
            return ['resource_name' => $resourceName, 'status' => $status];
        } catch (\Exception $e) {
            Log::error("Failed to update Google Ads campaign status: {$e->getMessage()}");
            throw new \Exception("Google Ads update error: {$e->getMessage()}");
        }
    }

    /**
     * Get campaign metrics from Google Ads.
     */
    public function getCampaignMetrics(string $resourceName): array
    {
        $this->checkInit();
        $headers = $this->getHeaders();
        $customerIdClean = str_replace('-', '', $this->customerId);

        $query = "SELECT campaign.id, campaign.name, campaign.status, metrics.impressions, metrics.clicks, metrics.cost_micros, metrics.ctr, metrics.average_cpc, metrics.video_views, metrics.video_view_rate, metrics.conversions, metrics.conversions_value FROM campaign WHERE campaign.resource_name = '{$resourceName}' AND segments.date DURING LAST_30_DAYS";

        try {
            $response = Http::withHeaders($headers)
                ->timeout(15)
                ->post("{$this->baseUrl}/{$this->apiVersion}/customers/{$customerIdClean}/googleAds:search", [
                    'query' => $query,
                ]);

            if (!$response->successful()) {
                throw new \Exception('Metrics fetch failed: ' . $response->body());
            }

            $results = $response->json('results', []);
            if (empty($results)) {
                return ['impressions' => 0, 'clicks' => 0, 'cost_micros' => 0, 'ctr' => 0, 'cpc' => 0, 'video_views' => 0, 'video_view_rate' => 0, 'conversions' => 0, 'conversion_value' => 0];
            }

            $metrics = $results[0]['metrics'] ?? [];
            return [
                'impressions' => (int) ($metrics['impressions'] ?? 0),
                'clicks' => (int) ($metrics['clicks'] ?? 0),
                'cost_micros' => (float) ($metrics['costMicros'] ?? 0),
                'ctr' => (float) ($metrics['ctr'] ?? 0),
                'cpc' => (float) ($metrics['averageCpc'] ?? 0),
                'video_views' => (int) ($metrics['videoViews'] ?? 0),
                'video_view_rate' => (float) ($metrics['videoViewRate'] ?? 0),
                'conversions' => (float) ($metrics['conversions'] ?? 0),
                'conversion_value' => (float) ($metrics['conversionsValue'] ?? 0),
            ];
        } catch (\Exception $e) {
            Log::error("Failed to fetch Google Ads metrics: {$e->getMessage()}");
            throw new \Exception("Google Ads metrics error: {$e->getMessage()}");
        }
    }

    /**
     * Delete a campaign from Google Ads.
     */
    public function deleteCampaign(string $resourceName): array
    {
        $this->checkInit();
        $headers = $this->getHeaders();
        $customerIdClean = str_replace('-', '', $this->customerId);

        try {
            $response = Http::withHeaders($headers)
                ->timeout(15)
                ->post("{$this->baseUrl}/{$this->apiVersion}/customers/{$customerIdClean}/campaigns:mutate", [
                    'operations' => [['remove' => $resourceName]],
                ]);

            if (!$response->successful()) {
                throw new \Exception('Delete failed: ' . $response->body());
            }

            Log::info("Google Ads campaign {$resourceName} removed");
            return ['deleted' => true];
        } catch (\Exception $e) {
            Log::error("Failed to delete Google Ads campaign: {$e->getMessage()}");
            throw new \Exception("Google Ads delete error: {$e->getMessage()}");
        }
    }

    /**
     * Push campaign to Google Ads (wrapper for backward compatibility with AdCampaignController).
     */
    public function pushCampaign(AdCampaign $campaign): array
    {
        try {
            $result = $this->createYouTubeCampaign([
                'name' => $campaign->name,
                'budget' => (float) ($campaign->budget ?? 0),
                'video_url' => '',
                'landing_url' => $campaign->landing_url ?? '',
                'start_date' => $campaign->start_date?->format('Y-m-d'),
                'end_date' => $campaign->end_date?->format('Y-m-d'),
            ]);

            $campaign->update(['google_campaign_id' => $result['campaign_id']]);
            return ['success' => true, 'message' => 'Campaign pushed to Google Ads', 'google_campaign_id' => $result['campaign_id']];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Sync stats from Google Ads.
     */
    public function syncStats(AdCampaign $campaign): array
    {
        if (!$campaign->google_campaign_id) {
            return ['success' => false, 'message' => 'No Google campaign ID'];
        }

        try {
            $metrics = $this->getCampaignMetrics("customers/{$this->customerId}/campaigns/{$campaign->google_campaign_id}");
            $campaign->update([
                'impressions' => $metrics['impressions'],
                'clicks' => $metrics['clicks'],
                'spent' => $metrics['cost_micros'] / 1000000,
                'conversions' => (int) $metrics['conversions'],
            ]);

            return ['success' => true, 'message' => 'Stats synced from Google Ads'];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
}
