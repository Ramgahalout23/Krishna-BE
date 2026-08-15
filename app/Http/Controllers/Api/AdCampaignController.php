<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\Traits\MapsCamelCaseFields;
use App\Services\AdAnalyticsService;
use App\Services\AIAdCopyService;
use App\Services\MetaAdsService;
use App\Services\GoogleAdsService;
use App\Services\WhatsAppAdsService;
use App\Models\AdCampaign;
use App\Models\AdCampaignProduct;
use App\Exceptions\AppError;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class AdCampaignController extends Controller
{
    use MapsCamelCaseFields;

    private array $fieldMappings = [
        'startDate' => 'start_date',
        'endDate' => 'end_date',
        'creativeUrl' => 'creative_url',
        'creativeType' => 'creative_type',
        'landingUrl' => 'landing_url',
        'targetAudience' => 'target_audience',
        'platformCampaignId' => 'platform_campaign_id',
        'lastSyncedAt' => 'last_synced_at',
    ];
    protected AdAnalyticsService $analyticsService;
    protected AIAdCopyService $aiAdCopyService;
    protected MetaAdsService $metaAdsService;
    protected GoogleAdsService $googleAdsService;
    protected WhatsAppAdsService $whatsAppAdsService;

    public function __construct()
    {
        $this->analyticsService = new AdAnalyticsService();
        $this->aiAdCopyService = new AIAdCopyService();
        $this->metaAdsService = new MetaAdsService();
        $this->googleAdsService = new GoogleAdsService();
        $this->whatsAppAdsService = new WhatsAppAdsService();
    }

    // ── Stats & Analytics ──

    public function getStats(Request $request): JsonResponse
    {
        $totalCampaigns = AdCampaign::count();
        $activeCampaigns = AdCampaign::where('status', 'ACTIVE')->count();
        $pausedCampaigns = AdCampaign::where('status', 'PAUSED')->count();
        $completedCampaigns = AdCampaign::where('status', 'COMPLETED')->count();
        $totalBudget = (float) AdCampaign::sum('budget');
        $totalSpent = (float) AdCampaign::sum('spent');
        $totalImpressions = (int) AdCampaign::sum('impressions');
        $totalClicks = (int) AdCampaign::sum('clicks');
        $totalConversions = (int) AdCampaign::sum('conversions');
        $ctr = $totalImpressions > 0 ? round(($totalClicks / $totalImpressions) * 100, 2) : 0;
        $spentPercent = $totalBudget > 0 ? round(($totalSpent / $totalBudget) * 100, 1) : 0;

        return response()->json(['success' => true, 'data' => [
            'total' => $totalCampaigns,
            'active' => $activeCampaigns,
            'paused' => $pausedCampaigns,
            'completed' => $completedCampaigns,
            'total_spent' => $totalSpent,
            'total_impressions' => $totalImpressions,
            'total_clicks' => $totalClicks,
            'total_conversions' => $totalConversions,
            // CamelCase aliases for frontend
            'totalCampaigns' => $totalCampaigns,
            'activeCampaigns' => $activeCampaigns,
            'totalBudget' => $totalBudget,
            'totalSpent' => $totalSpent,
            'totalImpressions' => $totalImpressions,
            'totalClicks' => $totalClicks,
            'ctr' => $ctr,
            'spentPercent' => $spentPercent,
        ]]);
    }

    /**
     * Get comprehensive performance report with cross-platform comparison.
     */
    public function getPerformanceReport(Request $request): JsonResponse
    {
        try {
            $report = $this->analyticsService->getPerformanceReport($request->input('days', 30));
            return response()->json(['success' => true, 'data' => $report]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Get brand preset performance analysis.
     */
    public function getBrandPresetPerformance(Request $request): JsonResponse
    {
        try {
            return response()->json(['success' => true, 'data' => $this->analyticsService->getBrandPresetPerformance()]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Get budget optimization insights.
     */
    public function getBudgetOptimization(Request $request): JsonResponse
    {
        try {
            return response()->json(['success' => true, 'data' => $this->analyticsService->getBudgetOptimization()]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Get ad template presets.
     */
    public function getAdTemplates(Request $request): JsonResponse
    {
        try {
            return response()->json(['success' => true, 'data' => $this->analyticsService->getAdTemplates()]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // ── Campaign CRUD ──

    public function getCampaigns(Request $request): JsonResponse
    {
        $query = AdCampaign::with('products');
        if ($request->has('platform')) { $query->where('platform', $request->platform); }
        if ($request->has('status')) { $query->where('status', $request->status); }

        // Support both 'search' (frontend) and keyword-based search
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('platform', 'like', "%{$search}%")
                  ->orWhere('notes', 'like', "%{$search}%");
            });
        }

        // Support both 'limit' (frontend) and 'per_page' (backend convention)
        $perPage = $request->input('per_page', $request->input('limit', 20));
        $paginator = $query->latest()->paginate($perPage);

        // Transform items: add camelCase aliases for frontend
        $paginator->getCollection()->transform(function ($campaign) {
            $campaign->platformCampaignId = $campaign->platform_campaign_id;
            $campaign->lastSyncedAt = $campaign->last_synced_at;
            $campaign->syncedAt = $campaign->synced_at;
            $campaign->startDate = $campaign->start_date;
            $campaign->endDate = $campaign->end_date;
            $campaign->creativeUrl = $campaign->creative_url;
            $campaign->creativeType = $campaign->creative_type;
            $campaign->landingUrl = $campaign->landing_url;
            $campaign->targetAudience = $campaign->target_audience;
            $campaign->platformStatus = $campaign->platform_status;
            return $campaign;
        });

        return response()->json([
            'success' => true,
            'data' => [
                'data' => $paginator->items(),
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'total' => $paginator->total(),
                'per_page' => $paginator->perPage(),
            ],
        ]);
    }

    public function getCampaignById(string $id): JsonResponse
    {
        try {
            $campaign = AdCampaign::with('products')->findOrFail($id);
            // Apply camelCase mapping matching getCampaigns() transform
            $campaign->platformCampaignId = $campaign->platform_campaign_id;
            $campaign->lastSyncedAt = $campaign->last_synced_at;
            $campaign->syncedAt = $campaign->synced_at;
            $campaign->startDate = $campaign->start_date;
            $campaign->endDate = $campaign->end_date;
            $campaign->creativeUrl = $campaign->creative_url;
            $campaign->creativeType = $campaign->creative_type;
            $campaign->landingUrl = $campaign->landing_url;
            $campaign->targetAudience = $campaign->target_audience;
            $campaign->platformStatus = $campaign->platform_status;
            return response()->json(['success' => true, 'data' => $campaign]);
        } catch (AppError $e) { return $e->render(); }
    }

    public function createCampaign(Request $request): JsonResponse
    {
        try {
            // Map frontend camelCase to backend snake_case
            $input = $this->mapCamelCase($request->all(), $this->fieldMappings);
            $request->replace($input);

            $validated = $request->validate([
                'name' => 'required|string|max:255', 'platform' => 'required|string|max:50',
                'objective' => 'nullable|string|max:100', 'target_audience' => 'nullable|string',
                'budget' => 'nullable|numeric|min:0', 'start_date' => 'nullable|date',
                'end_date' => 'nullable|date|after_or_equal:start_date', 'status' => 'nullable|in:DRAFT,ACTIVE,PAUSED,COMPLETED',
                'creative_url' => 'nullable|string', 'creative_type' => 'nullable|string|max:50',
                'landing_url' => 'nullable|string', 'notes' => 'nullable|string',
            ]);
            $validated['created_by'] = $request->user()->id ?? null;
            $campaign = AdCampaign::create($validated);
            return response()->json(['success' => true, 'data' => $campaign], 201);
        } catch (\Exception $e) { return response()->json(['success' => false, 'message' => $e->getMessage()], 422); }
    }

    public function updateCampaign(Request $request, string $id): JsonResponse
    {
        try {
            // Map frontend camelCase to backend snake_case
            $input = $this->mapCamelCase($request->all(), $this->fieldMappings);

            $campaign = AdCampaign::findOrFail($id);
            $campaign->update(collect($input)->only([
                'name', 'platform', 'objective', 'target_audience', 'budget',
                'start_date', 'end_date', 'status', 'creative_url', 'creative_type',
                'landing_url', 'notes'
            ])->toArray());
            return response()->json(['success' => true, 'data' => $campaign]);
        } catch (AppError $e) { return $e->render(); }
    }

    public function deleteCampaign(string $id): JsonResponse
    {
        try {
            AdCampaign::findOrFail($id)->delete();
            return response()->json(['success' => true, 'message' => 'Campaign deleted']);
        } catch (AppError $e) { return $e->render(); }
    }

    public function compareCampaigns(string $id1, string $id2): JsonResponse
    {
        try {
            $result = $this->analyticsService->compareCampaigns($id1, $id2);
            return response()->json(['success' => true, 'data' => $result]);
        } catch (\Exception $e) { return response()->json(['success' => false, 'message' => $e->getMessage()], 404); }
    }

    // ── AI Copy Generation ──

    public function generateAdCopy(Request $request): JsonResponse
    {
        try {
            return response()->json([
                'success' => true,
                'data' => $this->aiAdCopyService->generateAdCopy($request->all()),
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function generateAdVariants(Request $request): JsonResponse
    {
        try {
            return response()->json([
                'success' => true,
                'data' => $this->aiAdCopyService->generateVariants($request->all()),
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function generateFullStrategy(Request $request): JsonResponse
    {
        try {
            return response()->json([
                'success' => true,
                'data' => $this->aiAdCopyService->generateFullStrategy($request->all()),
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function suggestAudience(Request $request): JsonResponse
    {
        try {
            return response()->json([
                'success' => true,
                'data' => $this->aiAdCopyService->suggestAudience($request->all()),
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function generateBannerDesign(Request $request): JsonResponse
    {
        try {
            return response()->json([
                'success' => true,
                'data' => $this->aiAdCopyService->generateBannerDesign($request->all()),
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // ── Product Linking ──

    public function getCampaignProducts(string $id): JsonResponse
    {
        try {
            return response()->json(['success' => true, 'data' => AdCampaignProduct::with('product')->where('ad_campaign_id', $id)->get()]);
        } catch (AppError $e) { return $e->render(); }
    }

    public function linkProduct(Request $request, string $id): JsonResponse
    {
        try {
            $validated = $request->validate(['product_id' => 'required|string']);
            $link = AdCampaignProduct::create(['ad_campaign_id' => $id, 'product_id' => $validated['product_id']]);
            return response()->json(['success' => true, 'data' => $link], 201);
        } catch (\Exception $e) { return response()->json(['success' => false, 'message' => $e->getMessage()], 422); }
    }

    public function updateProductLink(Request $request, string $id, string $productId): JsonResponse
    {
        try {
            $link = AdCampaignProduct::where('ad_campaign_id', $id)->where('product_id', $productId)->firstOrFail();
            $link->update($request->only(['bid', 'status', 'creative_url']));
            return response()->json(['success' => true, 'data' => $link]);
        } catch (\Exception $e) { return response()->json(['success' => false, 'message' => 'Link not found'], 404); }
    }

    public function unlinkProduct(string $id, string $productId): JsonResponse
    {
        try {
            AdCampaignProduct::where('ad_campaign_id', $id)->where('product_id', $productId)->delete();
            return response()->json(['success' => true, 'message' => 'Product unlinked']);
        } catch (AppError $e) { return $e->render(); }
    }

    public function bulkLinkProducts(Request $request, string $id): JsonResponse
    {
        try {
            $validated = $request->validate(['product_ids' => 'required|array', 'product_ids.*' => 'string']);
            foreach ($validated['product_ids'] as $pid) {
                AdCampaignProduct::firstOrCreate(['ad_campaign_id' => $id, 'product_id' => $pid]);
            }
            return response()->json(['success' => true, 'message' => 'Products linked']);
        } catch (\Exception $e) { return response()->json(['success' => false, 'message' => $e->getMessage()], 422); }
    }

    public function generateCreativeFromProduct(string $id, string $productId): JsonResponse
    {
        return response()->json(['success' => true, 'data' => ['headline' => 'Creative from product', 'body' => 'Generated creative']]);
    }

    // ── Test Connections ──

    public function testMetaConnection(Request $request): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->metaAdsService->testConnection()]);
    }

    public function testGoogleConnection(Request $request): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->googleAdsService->testConnection()]);
    }

    public function getWhatsAppRecipients(Request $request): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->whatsAppAdsService->getRecipients()]);
    }

    // ── Push to Platform ──

    public function pushToMeta(string $id): JsonResponse
    {
        try {
            $campaign = AdCampaign::findOrFail($id);
            return response()->json(['success' => true, 'data' => $this->metaAdsService->pushCampaign($campaign)]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 404);
        }
    }

    public function syncMetaStats(string $id): JsonResponse
    {
        try {
            $campaign = AdCampaign::findOrFail($id);
            return response()->json(['success' => true, 'data' => $this->metaAdsService->syncStats($campaign)]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 404);
        }
    }

    public function pushToGoogle(string $id): JsonResponse
    {
        try {
            $campaign = AdCampaign::findOrFail($id);
            return response()->json(['success' => true, 'data' => $this->googleAdsService->pushCampaign($campaign)]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 404);
        }
    }

    public function syncGoogleStats(string $id): JsonResponse
    {
        try {
            $campaign = AdCampaign::findOrFail($id);
            return response()->json(['success' => true, 'data' => $this->googleAdsService->syncStats($campaign)]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 404);
        }
    }

    public function pushToWhatsApp(string $id): JsonResponse
    {
        try {
            $campaign = AdCampaign::findOrFail($id);
            return response()->json(['success' => true, 'data' => $this->whatsAppAdsService->sendBroadcast($campaign->name)]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 404);
        }
    }
}
