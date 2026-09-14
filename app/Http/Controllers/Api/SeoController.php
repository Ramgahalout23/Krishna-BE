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
            // All of the COUNT/AVG aggregation happens inside one cached service
            // call (see SeoService::getDashboardStats) — this endpoint is polled
            // every 60s by the auto-refreshing dashboard.
            $stats = $this->seoService->getDashboardStats();
            $counts = $stats['counts'];

            // ── Global SEO status ── (already cached individually)
            $globalSeo = $this->seoService->getGlobalSEO();

            // ── Robots.txt status ──
            $robots = $this->seoService->getRobotsTxt();

            // ── Advanced settings status ──
            $advSettings = $this->advancedSeoService->getAdvancedSettings();

            $coveragePct = fn(int $withSeo, int $total) => $total > 0 ? round(($withSeo / $total) * 100, 1) : 0;

            return response()->json(['success' => true, 'data' => [
                'overview' => [
                    'total_entities' => $counts['total_entities'],
                    'total_products' => $counts['total_products'],
                    'total_categories' => $counts['total_categories'],
                    'total_pages' => $counts['total_pages'],
                    'seo_records_count' => $counts['seo_records_count'],
                    'seo_coverage_pct' => $coveragePct($counts['seo_records_count'], $counts['total_entities']),
                ],
                'seo_coverage' => [
                    'products' => [
                        'total' => $counts['total_products'],
                        'with_seo' => $counts['products_with_seo'],
                        'coverage_pct' => $coveragePct($counts['products_with_seo'], $counts['total_products']),
                    ],
                    'categories' => [
                        'total' => $counts['total_categories'],
                        'with_seo' => $counts['categories_with_seo'],
                        'coverage_pct' => $coveragePct($counts['categories_with_seo'], $counts['total_categories']),
                    ],
                    'pages' => [
                        'total' => $counts['total_pages'],
                        'with_seo' => $counts['pages_with_seo'],
                        'coverage_pct' => $coveragePct($counts['pages_with_seo'], $counts['total_pages']),
                    ],
                ],
                'scores' => [
                    'average_score' => $stats['average_score'],
                    'scored_entities' => $counts['seo_with_scores'],
                    'distribution' => $stats['distribution'],
                ],
                'score_trend' => $stats['score_trend'],
                'global_seo' => $globalSeo,
                'robots' => [
                    'has_custom_robots' => !empty($robots['content']) && $robots['content'] !== "User-agent: *\nAllow: /\n",
                    'last_updated' => $robots['updated_at'],
                ],
                'sitemap' => $stats['sitemap'],
                'advanced' => $advSettings,
                'recent_updates' => $stats['recent_updates'],
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
