<?php

namespace App\Services;

use App\Models\AdCampaign;
use FacebookAds\Api;
use FacebookAds\Object\AdAccount;
use FacebookAds\Object\Campaign;
use FacebookAds\Object\Fields\CampaignFields;

use Illuminate\Support\Facades\Log;

class MetaAdsService
{
    private ?Api $api = null;
    private ?string $accessToken = null;
    private ?string $adAccountId = null;
    private ?string $appId = null;
    private ?string $appSecret = null;
    private string $apiVersion = 'v18.0';

    public function __construct()
    {
        $this->accessToken = config('services.meta.access_token') ?? env('META_ADS_ACCESS_TOKEN');
        $this->adAccountId = config('services.meta.ad_account_id') ?? env('META_ADS_ACCOUNT_ID');
        $this->appId = config('services.meta.app_id') ?? env('META_APP_ID');
        $this->appSecret = config('services.meta.app_secret') ?? env('META_APP_SECRET');
    }

    /**
     * Initialize the Facebook PHP Business SDK.
     */
    private function initSDK(): void
    {
        if ($this->api === null && $this->accessToken && $this->appId && $this->appSecret) {
            Api::init($this->appId, $this->appSecret, $this->accessToken); // Uses SDK's default API version
            $this->api = Api::instance();
        }
    }

    public function isConfigured(): bool
    {
        return !empty($this->accessToken) && !empty($this->adAccountId) && !empty($this->appId) && !empty($this->appSecret);
    }

    /**
     * Test connection to Meta Marketing API using the SDK.
     */
    public function testConnection(): array
    {
        if (!$this->isConfigured()) {
            return ['connected' => false, 'message' => 'Meta API not configured — set META_APP_ID, META_APP_SECRET, META_ADS_ACCESS_TOKEN, META_ADS_ACCOUNT_ID'];
        }

        try {
            $this->initSDK();

            $account = new AdAccount("act_{$this->adAccountId}");
            $account->read(['id', 'name', 'account_status', 'currency']);

            return [
                'connected' => true,
                'message' => "Connected to account: {$account->name}",
                'account_id' => $account->id,
                'currency' => $account->currency ?? null,
                'status' => $account->account_status,
            ];
        } catch (\Exception $e) {
            Log::error("[MetaAds] SDK connection failed: {$e->getMessage()}");
            return ['connected' => false, 'message' => "Meta SDK connection failed: {$e->getMessage()}"];
        }
    }

    /**
     * Push campaign to Meta Ads using the SDK.
     */
    public function pushCampaign(AdCampaign $campaign): array
    {
        if (!$this->isConfigured()) {
            return ['success' => false, 'message' => 'Meta API not configured'];
        }

        try {
            $this->initSDK();

            $account = new AdAccount("act_{$this->adAccountId}");

            $campaignFields = [
                CampaignFields::NAME => $campaign->name,
                CampaignFields::OBJECTIVE => $this->mapObjective($campaign->objective),
                CampaignFields::STATUS => $campaign->status === 'ACTIVE' ? Campaign::STATUS_ACTIVE : Campaign::STATUS_PAUSED,
                CampaignFields::SPECIAL_AD_CATEGORIES => [],
            ];

            if ($campaign->budget && $campaign->budget > 0) {
                $campaignFields[CampaignFields::DAILY_BUDGET] = (int) round((float) $campaign->budget * 100);
            }

            $metaCampaign = $account->createCampaign([], $campaignFields);
            $metaCampaignId = $metaCampaign->id;

            $campaign->update(['meta_campaign_id' => $metaCampaignId]);

            Log::info("[MetaAds] Campaign '{$campaign->name}' pushed to Meta, ID: {$metaCampaignId}");

            return ['success' => true, 'message' => 'Campaign pushed to Meta', 'meta_id' => $metaCampaignId];
        } catch (\Exception $e) {
            Log::error("[MetaAds] Push failed: {$e->getMessage()}");
            return ['success' => false, 'message' => "Meta push error: {$e->getMessage()}"];
        }
    }

    /**
     * Sync stats from Meta Ads using the SDK.
     */
    public function syncStats(AdCampaign $campaign): array
    {
        if (!$this->isConfigured() || !$campaign->meta_campaign_id) {
            return ['success' => false, 'message' => 'Not configured or no Meta campaign ID'];
        }

        try {
            $this->initSDK();

            $metaCampaign = new Campaign($campaign->meta_campaign_id);
            $insights = $metaCampaign->getInsights([
                'fields' => ['impressions', 'clicks', 'spend', 'reach', 'actions'],
                'date_preset' => 'last_30d',
            ]);

            if ($insights && count($insights) > 0) {
                $data = $insights->getResponse()->getContent()[0] ?? [];

                if (!empty($data)) {
                    $conversions = 0;
                    foreach ($data['actions'] ?? [] as $action) {
                        if (in_array($action['action_type'], ['purchase', 'lead', 'complete_registration', 'add_to_cart'])) {
                            $conversions += (int) $action['value'];
                        }
                    }

                    $campaign->update([
                        'impressions' => (int) ($data['impressions'] ?? $campaign->impressions),
                        'clicks' => (int) ($data['clicks'] ?? $campaign->clicks),
                        'spent' => (float) ($data['spend'] ?? $campaign->spent),
                        'reach' => (int) ($data['reach'] ?? $campaign->reach),
                        'conversions' => $conversions ?: $campaign->conversions,
                    ]);

                    Log::info("[MetaAds] Stats synced for campaign {$campaign->meta_campaign_id}");

                    return ['success' => true, 'message' => 'Stats synced from Meta'];
                }
            }

            return ['success' => false, 'message' => 'Stats synced but no data returned from Meta'];
        } catch (\Exception $e) {
            Log::error("[MetaAds] Sync failed: {$e->getMessage()}");
            return ['success' => false, 'message' => "Meta sync error: {$e->getMessage()}"];
        }
    }

    /**
     * Map internal objective to Meta API objective.
     */
    private function mapObjective(?string $objective): string
    {
        $map = [
            'awareness' => 'BRAND_AWARENESS',
            'traffic' => 'TRAFFIC',
            'engagement' => 'ENGAGEMENT',
            'leads' => 'LEAD_GENERATION',
            'sales' => 'SALES',
            'video' => 'VIDEO_VIEWS',
            'catalog_sales' => 'SALES',
        ];
        return $map[$objective] ?? 'SALES';
    }
}
