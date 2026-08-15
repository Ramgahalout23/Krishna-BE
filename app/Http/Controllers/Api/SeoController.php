<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\SeoService;
use App\Services\AdvancedSeoService;
use App\Exceptions\AppError;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SeoController extends Controller
{
    public function __construct(
        protected SeoService $seoService,
        protected AdvancedSeoService $advancedSeoService
    ) {}

    public function show(string $page): JsonResponse
    {
        try {
            return response()->json(['success' => true, 'data' => $this->seoService->getByPage($page)]);
        } catch (AppError $e) { return $e->render(); }
    }

    public function update(Request $request, string $page): JsonResponse
    {
        try {
            $validated = $request->validate(['title' => 'nullable|string', 'description' => 'nullable|string', 'keywords' => 'nullable|string']);
            return response()->json(['success' => true, 'message' => 'SEO updated', 'data' => $this->seoService->update($page, $validated)]);
        } catch (AppError $e) { return $e->render(); }
    }

    // ── Entity SEO ──

    public function showEntitySEO(string $entityType, string $entityId): JsonResponse
    {
        $seo = $this->seoService->getSEO($entityType, $entityId);
        if (!$seo) {
            return response()->json(['success' => true, 'message' => 'No SEO data found', 'data' => null]);
        }
        return response()->json(['success' => true, 'data' => $seo]);
    }

    public function updateEntitySEO(Request $request, string $entityType, string $entityId): JsonResponse
    {
        // Accept both camelCase (from frontend) and snake_case keys
        $data = array_merge(
            $request->only([
                'title', 'metaTitle', 'meta_title',
                'description', 'metaDescription', 'meta_description',
                'keywords', 'metaKeywords', 'meta_keywords',
                'og_title', 'ogTitle',
                'og_description', 'ogDescription',
                'og_image', 'ogImage',
                'twitter_title', 'twitterTitle',
                'twitter_description', 'twitterDescription',
                'twitter_image', 'twitterImage',
                'canonical_url', 'canonicalUrl',
                'robots_meta',
                'content_language',
                'sitemap_priority',
                'sitemap_changefreq',
                'hreflang_tags',
                'json_ld_product', 'json_ld_organization', 'json_ld_breadcrumb',
                'json_ld_faq', 'json_ld_website',
            ]),
            ['entity_type' => $entityType, 'entity_id' => $entityId]
        );
        $result = $this->seoService->updateSEO($data);

        // Auto-generate JSON-LD schemas if enabled
        $autoSchema = \App\Models\Setting::where('key', 'seo_enable_auto_schema')->value('value');
        if ($autoSchema === 'true') {
            $schemas = $this->advancedSeoService->autoGenerateSchemas($entityType, $entityId);
            if (!empty($schemas)) {
                $seoRecord = \App\Models\Seo::where('entity_type', $entityType)
                    ->where('entity_id', $entityId)->first();
                if ($seoRecord) {
                    $seoRecord->update($schemas);
                    $result = $seoRecord->fresh()->toArray();
                }
            }
        }

        return response()->json(['success' => true, 'message' => 'SEO data saved', 'data' => $result]);
    }

    // ── Global SEO ──

    public function globalSEO(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->seoService->getGlobalSEO()]);
    }

    public function updateGlobalSEO(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'nullable|string',
            'description' => 'nullable|string',
            'keywords' => 'nullable|string',
        ]);
        return response()->json(['success' => true, 'message' => 'Global SEO settings updated', 'data' => $this->seoService->updateGlobalSEO($validated)]);
    }

    // ── Sitemap ──

    public function sitemap(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->seoService->getSitemap()]);
    }

    public function refreshSitemap(): JsonResponse
    {
        return response()->json(['success' => true, 'message' => 'Sitemap refreshed', 'data' => $this->seoService->refreshSitemap()]);
    }

    public function sitemapRaw(Request $request): \Illuminate\Http\Response
    {
        $baseUrl = $request->getSchemeAndHttpHost();
        $xml = $this->seoService->generateSitemapXML($baseUrl);
        return response($xml, 200)->header('Content-Type', 'application/xml');
    }

    // ── Robots.txt ──

    public function robotsTxt(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->seoService->getRobotsTxt()]);
    }

    public function updateRobotsTxt(Request $request): JsonResponse
    {
        $validated = $request->validate(['content' => 'required|string']);
        return response()->json(['success' => true, 'message' => 'Robots.txt updated', 'data' => $this->seoService->updateRobotsTxt($validated['content'])]);
    }

    public function robotsTxtRaw(): \Illuminate\Http\Response
    {
        $data = $this->seoService->getRobotsTxt();
        return response($data['content'], 200)->header('Content-Type', 'text/plain');
    }

    // ── List / Delete ──

    public function listSEO(Request $request, string $entityType): JsonResponse
    {
        $page = $request->page ?? 1;
        $limit = $request->limit ?? 50;
        return response()->json(['success' => true, 'data' => $this->seoService->listSEO($entityType, (int)$page, (int)$limit)]);
    }

    public function destroySEO(string $id): JsonResponse
    {
        try {
            $this->seoService->deleteSEO($id);
            return response()->json(['success' => true, 'message' => 'SEO record deleted']);
        } catch (AppError $e) { return $e->render(); }
    }

    // ════════════════════════════════════════════════════════
    // 🚀 ADVANCED SEO ENDPOINTS
    // ════════════════════════════════════════════════════════

    // ── Advanced Settings ──

    public function advancedSettings(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->advancedSeoService->getAdvancedSettings()]);
    }

    public function updateAdvancedSettings(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'google_analytics_id' => 'nullable|string|max:100',
            'google_tag_manager_id' => 'nullable|string|max:100',
            'facebook_pixel_id' => 'nullable|string|max:100',
            'organization_name' => 'nullable|string|max:255',
            'organization_logo' => 'nullable|string|max:500',
            'organization_url' => 'nullable|url|max:500',
            'social_links' => 'nullable|string',
            'hreflang_default' => 'nullable|string|max:10',
            'enable_auto_schema' => 'nullable|in:true,false',
            'enable_indexnow' => 'nullable|in:true,false',
            'indexnow_key' => 'nullable|string|max:128',
            'google_site_verification' => 'nullable|string|max:255',
            'breadcrumb_separator' => 'nullable|string|max:10',
            'default_image' => 'nullable|string|max:500',
            'twitter_handle' => 'nullable|string|max:50',
            'auto_audit_enabled' => 'nullable|in:true,false',
            'audit_schedule' => 'nullable|in:daily,weekly,monthly',
        ]);
        return response()->json([
            'success' => true,
            'message' => 'Advanced SEO settings updated',
            'data' => $this->advancedSeoService->updateAdvancedSettings($validated),
        ]);
    }

    // ── JSON-LD Schema Generation ──

    public function generateProductSchema(string $entityId): JsonResponse
    {
        $schema = $this->advancedSeoService->generateProductSchema($entityId);
        if (!$schema) {
            return response()->json(['success' => false, 'message' => 'Product not found'], 404);
        }
        return response()->json(['success' => true, 'data' => $schema]);
    }

    public function generateOrganizationSchema(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->advancedSeoService->generateOrganizationSchema()]);
    }

    public function generateWebsiteSchema(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->advancedSeoService->generateWebsiteSchema()]);
    }

    public function generateBreadcrumbSchema(Request $request): JsonResponse
    {
        $validated = $request->validate(['crumbs' => 'required|array']);
        return response()->json(['success' => true, 'data' => $this->advancedSeoService->generateBreadcrumbSchema($validated['crumbs'])]);
    }

    public function generateFAQSchema(Request $request): JsonResponse
    {
        $validated = $request->validate(['faqs' => 'required|array']);
        return response()->json(['success' => true, 'data' => $this->advancedSeoService->generateFAQSchema($validated['faqs'])]);
    }

    public function autoGenerateSchemas(string $entityType, string $entityId): JsonResponse
    {
        $schemas = $this->advancedSeoService->autoGenerateSchemas($entityType, $entityId);

        // Save schemas to the SEO record
        $seo = \App\Models\Seo::where('entity_type', $entityType)
            ->where('entity_id', $entityId)->first();
        if ($seo && !empty($schemas)) {
            $seo->update($schemas);
        }

        return response()->json(['success' => true, 'message' => 'Schemas generated and saved', 'data' => $schemas]);
    }

    // ── SEO Audit ──

    public function auditEntitySEO(string $entityType, string $entityId): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->advancedSeoService->auditEntitySEO($entityType, $entityId),
        ]);
    }

    public function bulkAuditSEO(Request $request): JsonResponse
    {
        $validated = $request->validate(['entity_type' => 'required|string|in:product,category,page']);
        return response()->json([
            'success' => true,
            'data' => $this->advancedSeoService->bulkAudit($validated['entity_type']),
        ]);
    }

    // ── Breadcrumbs ──

    public function generateBreadcrumbs(string $entityType, string $entityId): JsonResponse
    {
        $crumbs = $this->advancedSeoService->generateBreadcrumbs($entityType, $entityId);
        $schema = $this->advancedSeoService->generateBreadcrumbSchema($crumbs);

        return response()->json([
            'success' => true,
            'data' => [
                'crumbs' => $crumbs,
                'schema' => $schema,
            ],
        ]);
    }

    // ── IndexNow ──

    public function pushIndexNow(Request $request): JsonResponse
    {
        $validated = $request->validate(['url' => 'required|url']);
        $pushed = $this->advancedSeoService->pushToIndexNow($validated['url']);

        return response()->json([
            'success' => $pushed,
            'message' => $pushed ? 'URL pushed to IndexNow' : 'IndexNow push failed or disabled',
        ]);
    }

    // ── Sitemap (Enhanced) ──

    public function getSitemapFromDB(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->seoService->getSitemapFromDB()]);
    }

    // ════════════════════════════════════════════════════════
    // 📊 SEO DASHBOARD
    // ════════════════════════════════════════════════════════

    public function dashboard(): JsonResponse
    {
        try {
            // ── Entity counts ──
            $totalProducts = \App\Models\Product::count();
            $totalCategories = \App\Models\Category::count();
            $totalPages = class_exists(\App\Models\Page::class) ? \App\Models\Page::count() : 0;
            $totalEntities = $totalProducts + $totalCategories + $totalPages;

            // ── SEO records count ──
            $seoCount = \App\Models\Seo::count();
            $seoWithScores = \App\Models\Seo::whereNotNull('seo_score')->count();

            // ── Entities WITH vs WITHOUT SEO ──
            $productSeoCount = \App\Models\Seo::where('entity_type', 'product')->count();
            $categorySeoCount = \App\Models\Seo::where('entity_type', 'category')->count();
            $pageSeoCount = \App\Models\Seo::where('entity_type', 'page')->count();

            // ── Average SEO score ──
            $avgScore = \App\Models\Seo::whereNotNull('seo_score')->avg('seo_score');

            // ── Score distribution ──
            $excellent = \App\Models\Seo::whereNotNull('seo_score')->where('seo_score', '>=', 80)->count();
            $good = \App\Models\Seo::whereNotNull('seo_score')->whereBetween('seo_score', [60, 79])->count();
            $needsWork = \App\Models\Seo::whereNotNull('seo_score')->whereBetween('seo_score', [40, 59])->count();
            $poor = \App\Models\Seo::whereNotNull('seo_score')->where('seo_score', '<', 40)->count();

            // ── Recent SEO updates ──
            $recentUpdates = \App\Models\Seo::orderBy('updated_at', 'desc')
                ->take(10)
                ->get(['id', 'entity_type', 'entity_id', 'meta_title', 'seo_score', 'updated_at'])
                ->toArray();

            // ── Global SEO status ──
            $globalSeo = $this->seoService->getGlobalSEO();

            // ── Robots.txt status ──
            $robots = $this->seoService->getRobotsTxt();

            // ── Sitemap stats ──
            $sitemapEntries = \App\Models\Sitemap::count();
            $lastSitemapGen = \App\Models\Sitemap::max('created_at');

            // ── Advanced settings status ──
            $advSettings = $this->advancedSeoService->getAdvancedSettings();

            // ── Score trend (daily avg over last 30 days) ──
            $trendDays = 30;
            $trendRaw = \App\Models\SeoScoreHistory::selectRaw("DATE(created_at) as date, AVG(score) as avg_score, COUNT(*) as snapshots")
                ->where('created_at', '>=', now()->subDays($trendDays))
                ->groupBy('date')
                ->orderBy('date', 'asc')
                ->get();
            $scoreTrend = $trendRaw->map(fn($r) => [
                'date' => $r->date,
                'avg_score' => round((float) $r->avg_score, 1),
                'snapshots' => (int) $r->snapshots,
            ])->values();

            // Compute week-over-week change
            $weekAgoAvg = \App\Models\SeoScoreHistory::where('created_at', '>=', now()->subDays(7))
                ->where('created_at', '<', now())->avg('score');
            $prevWeekAvg = \App\Models\SeoScoreHistory::where('created_at', '>=', now()->subDays(14))
                ->where('created_at', '<', now()->subDays(7))->avg('score');
            $trendDirection = null;
            $trendChange = 0;
            if ($weekAgoAvg && $prevWeekAvg) {
                $trendChange = round($weekAgoAvg - $prevWeekAvg, 1);
                $trendDirection = $trendChange > 2 ? 'up' : ($trendChange < -2 ? 'down' : 'stable');
            }

            return response()->json(['success' => true, 'data' => [
                'overview' => [
                    'total_entities' => $totalEntities,
                    'total_products' => $totalProducts,
                    'total_categories' => $totalCategories,
                    'total_pages' => $totalPages,
                    'seo_records_count' => $seoCount,
                    'seo_coverage_pct' => $totalEntities > 0 ? round(($seoCount / $totalEntities) * 100, 1) : 0,
                ],
                'seo_coverage' => [
                    'products' => ['total' => $totalProducts, 'with_seo' => $productSeoCount, 'coverage_pct' => $totalProducts > 0 ? round(($productSeoCount / $totalProducts) * 100, 1) : 0],
                    'categories' => ['total' => $totalCategories, 'with_seo' => $categorySeoCount, 'coverage_pct' => $totalCategories > 0 ? round(($categorySeoCount / $totalCategories) * 100, 1) : 0],
                    'pages' => ['total' => $totalPages, 'with_seo' => $pageSeoCount, 'coverage_pct' => $totalPages > 0 ? round(($pageSeoCount / $totalPages) * 100, 1) : 0],
                ],
                'scores' => [
                    'average_score' => round($avgScore ?? 0, 1),
                    'scored_entities' => $seoWithScores,
                    'distribution' => [
                        'excellent' => $excellent,
                        'good' => $good,
                        'needs_work' => $needsWork,
                        'poor' => $poor,
                    ],
                ],
                'score_trend' => [
                    'daily' => $scoreTrend,
                    'week_over_week_change' => $trendChange,
                    'direction' => $trendDirection,
                ],
                'global_seo' => $globalSeo,
                'robots' => [
                    'has_custom_robots' => !empty($robots['content']) && $robots['content'] !== "User-agent: *\nAllow: /\n",
                    'last_updated' => $robots['updated_at'],
                ],
                'sitemap' => [
                    'entries_count' => $sitemapEntries,
                    'last_generated' => $lastSitemapGen,
                ],
                'advanced' => $advSettings,
                'recent_updates' => $recentUpdates,
            ]]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Failed to load SEO dashboard', 'error' => $e->getMessage()], 500);
        }
    }

    // ── Entity SEO (Enhanced with advanced fields) ──

    public function getFullEntitySEO(string $entityType, string $entityId): JsonResponse
    {
        $seo = $this->seoService->getSEO($entityType, $entityId);
        if (!$seo) {
            return response()->json(['success' => true, 'message' => 'No SEO data found', 'data' => null]);
        }

        // Add audit and breadcrumbs
        $audit = $this->advancedSeoService->auditEntitySEO($entityType, $entityId);
        $breadcrumbs = $this->advancedSeoService->generateBreadcrumbs($entityType, $entityId);

        return response()->json([
            'success' => true,
            'data' => array_merge($seo, [
                'audit' => $audit,
                'breadcrumbs' => $breadcrumbs,
            ]),
        ]);
    }
}
