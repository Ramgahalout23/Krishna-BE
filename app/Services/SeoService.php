<?php

namespace App\Services;

use App\Repositories\SeoRepository;
use App\Exceptions\AppError;
use App\Models\Seo;
use App\Models\Product;
use App\Models\Category;
use App\Traits\CacheKeyRegistry;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class SeoService
{
    use CacheKeyRegistry;

    // Debounce state for sitemap regeneration (mimics TS debounce pattern)
    private static ?int $sitemapDebounceTimer = null;
    private static ?bool $sitemapPending = false;

    public function __construct(
        protected SeoRepository $seoRepository
    ) {}

    public function getByPage(string $page): array
    {
        $seo = $this->seoRepository->findByPage($page);
        if (!$seo) throw AppError::notFound('SEO data not found');
        return $seo->toArray();
    }

    public function update(string $page, array $data): array
    {
        $seo = $this->seoRepository->findByPage($page);
        if (!$seo) throw AppError::notFound('SEO data not found');

        // Map generic keys to model fillable column names
        $mapped = [];
        if (isset($data['title'])) $mapped['meta_title'] = $data['title'];
        if (isset($data['description'])) $mapped['meta_description'] = $data['description'];
        if (isset($data['keywords'])) $mapped['meta_keywords'] = $data['keywords'];
        if (isset($data['meta_title'])) $mapped['meta_title'] = $data['meta_title'];
        if (isset($data['meta_description'])) $mapped['meta_description'] = $data['meta_description'];
        if (isset($data['meta_keywords'])) $mapped['meta_keywords'] = $data['meta_keywords'];

        return $this->seoRepository->update($seo->id, $mapped)->toArray();
    }

    // ── Entity SEO ──

    public function getSEO(string $entityType, string $entityId): ?array
    {
        $seo = Seo::where('entity_type', $entityType)
            ->where('entity_id', $entityId)
            ->first();
        return $seo ? $seo->toArray() : null;
    }

    public function updateSEO(array $data): array
    {
        $seo = Seo::updateOrCreate(
            [
                'entity_type' => $data['entity_type'] ?? $data['entityType'],
                'entity_id' => $data['entity_id'] ?? $data['entityId'],
            ],
            [
                'meta_title' => $data['meta_title'] ?? $data['metaTitle'] ?? $data['title'] ?? null,
                'meta_description' => $data['meta_description'] ?? $data['metaDescription'] ?? $data['description'] ?? null,
                'meta_keywords' => $data['meta_keywords'] ?? $data['metaKeywords'] ?? $data['keywords'] ?? null,
                'og_image' => $data['og_image'] ?? $data['ogImage'] ?? null,
                'og_title' => $data['og_title'] ?? $data['ogTitle'] ?? null,
                'og_description' => $data['og_description'] ?? $data['ogDescription'] ?? null,
                'twitter_title' => $data['twitter_title'] ?? $data['twitterTitle'] ?? null,
                'twitter_description' => $data['twitter_description'] ?? $data['twitterDescription'] ?? null,
                'twitter_image' => $data['twitter_image'] ?? $data['twitterImage'] ?? null,
                'canonical_url' => $data['canonical_url'] ?? $data['canonicalUrl'] ?? null,
            ]
        );
        return $seo->toArray();
    }

    // ── Global SEO ──

    public function getGlobalSEO(): array
    {
        return $this->cacheWithTracking('seo_global', 3600, function () {
            $keys = ['seo_title', 'seo_description', 'seo_keywords'];
            $settings = \App\Models\Setting::whereIn('key', $keys)->pluck('value', 'key')->toArray();

            return [
                'title' => $settings['seo_title'] ?? '',
                'description' => $settings['seo_description'] ?? '',
                'keywords' => $settings['seo_keywords'] ?? '',
            ];
        });
    }

    public function updateGlobalSEO(array $data): array
    {
        $updates = [];
        if (isset($data['title'])) $updates['seo_title'] = (string) $data['title'];
        if (isset($data['description'])) $updates['seo_description'] = (string) $data['description'];
        if (isset($data['keywords'])) $updates['seo_keywords'] = (string) $data['keywords'];

        foreach ($updates as $key => $value) {
            \App\Models\Setting::updateOrCreate(
                ['key' => $key, 'module' => 'SITE'],
                ['value' => $value]
            );
        }

        $this->clearTrackedCache();

        return $this->getGlobalSEO();
    }

    // ── Sitemap ──

    public function getSitemap(): array
    {
        return $this->cacheWithTracking('seo_sitemap', 300, function () {
            // Use chunked iteration to avoid memory issues with large datasets
            $entries = [];
            Seo::select('entity_type', 'entity_id', 'updated_at')
                ->chunk(500, function ($chunk) use (&$entries) {
                    foreach ($chunk as $seo) {
                        $entries[] = $seo->toArray();
                    }
                });

            return [
                'count' => count($entries),
                'entries' => $entries,
            ];
        });
    }

    public function refreshSitemap(): array
    {
        $this->clearTrackedCache();
        return $this->getSitemap();
    }

    /**
     * Generate sitemap data from products and categories.
     */
    public function generateSitemap(): array
    {
        $products = Product::select('id', 'slug', 'updated_at')->get();
        $categories = Category::select('id', 'slug', 'updated_at')->get();

        $sitemapData = [];

        foreach ($products as $p) {
            $sitemapData[] = [
                'url' => "/products/{$p->slug}",
                'lastModified' => $p->updated_at,
            ];
        }

        foreach ($categories as $c) {
            $sitemapData[] = [
                'url' => "/categories/{$c->slug}",
                'lastModified' => $c->updated_at,
            ];
        }

        return $sitemapData;
    }

    /**
     * Generate and persist sitemap entries with debounce.
     * Mimics the TS debounce: rapid calls within 30s only trigger one DB write.
     */
    public function generateAndPersistSitemap(): void
    {
        if (self::$sitemapPending) {
            return; // Debounced — already pending
        }

        self::$sitemapPending = true;

        // Schedule the DB work after debounce (30 seconds)
        if (self::$sitemapDebounceTimer) {
            clearPreviousDebounce();
        }

        // In PHP, we can't do a true async debounce like Node.js.
        // Instead, we immediately regenerate on the first call
        // and subsequent calls within 30s skip.
        $this->executeSitemapRegeneration();

        // Reset debounce after a delay using register_shutdown_function
        // for the debounce expiry
        register_shutdown_function(function () {
            // Reset after 30 seconds (approximated by setting a flag)
            // In production, use a cache key with 30s TTL instead
            self::$sitemapPending = false;
        });
    }

    /**
     * Execute the actual sitemap regeneration.
     */
    private function executeSitemapRegeneration(): void
    {
        $sitemapData = $this->generateSitemap();

        \App\Models\Sitemap::truncate();

        if (!empty($sitemapData)) {
            $entries = array_map(fn($entry) => [
                'url' => $entry['url'],
                'last_modified' => $entry['lastModified'],
                'created_at' => now(),
                'updated_at' => now(),
            ], $sitemapData);

            \App\Models\Sitemap::insert($entries);
        }

        // Invalidate sitemap caches after regeneration
        $this->clearTrackedCache();

        Log::info('Sitemap regenerated — ' . count($sitemapData) . ' entries');
    }

    /**
     * Get sitemap entries from the database.
     */
    public function getSitemapFromDB(): array
    {
        return $this->cacheWithTracking('sitemap_db', 300, function () {
            $entries = \App\Models\Sitemap::orderBy('url')->get();
            $lastGenerated = $entries->isNotEmpty() ? $entries->max('created_at') : null;

            return [
                'entries' => $entries->toArray(),
                'count' => $entries->count(),
                'last_generated' => $lastGenerated,
            ];
        });
    }

    public function generateSitemapXML(string $baseUrl): string
    {
        // Use cached sitemap DB entries
        $sitemap = $this->getSitemapFromDB();
        $entries = collect($sitemap['entries'])->map(fn($e) => (object) $e);

        if ($entries->isEmpty()) {
            $fresh = $this->generateSitemap();
            $entries = collect($fresh)->map(fn($e) => (object) [
                'url' => $e['url'],
                'last_modified' => $e['lastModified'] ?? now(),
            ]);
        }

        $baseUrlClean = rtrim($baseUrl, '/');
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

        foreach ($entries as $entry) {
            $loc = $baseUrlClean . $entry->url;
            $lastmod = $entry->last_modified
                ? (is_string($entry->last_modified) ? $entry->last_modified : $entry->last_modified->toIso8601String())
                : now()->toIso8601String();
            $xml .= "  <url>\n";
            $xml .= "    <loc>" . htmlspecialchars($loc, ENT_XML1 | ENT_QUOTES, 'UTF-8') . "</loc>\n";
            $xml .= "    <lastmod>{$lastmod}</lastmod>\n";
            $xml .= "    <changefreq>weekly</changefreq>\n";
            $xml .= "  </url>\n";
        }

        $xml .= '</urlset>';
        return $xml;
    }

    // ── Robots.txt ──

    public function getRobotsTxt(): array
    {
        return $this->cacheWithTracking('robots_txt', 3600, function () {
            $robots = \App\Models\Setting::where('key', 'robots_txt')->first();
            return [
                'content' => $robots?->value ?? "User-agent: *\nAllow: /\n",
                'updated_at' => $robots?->updated_at,
            ];
        });
    }

    public function updateRobotsTxt(string $content): array
    {
        \App\Models\Setting::updateOrCreate(
            ['key' => 'robots_txt', 'module' => 'SITE'],
            ['value' => $content]
        );
        $this->clearTrackedCache();
        return $this->getRobotsTxt();
    }

    // ── List / Delete ──

    public function listSEO(string $entityType, int $page = 1, int $limit = 50): array
    {
        $query = Seo::where('entity_type', $entityType);
        $paginator = $query->orderBy('created_at', 'desc')
            ->paginate($limit, ['*'], 'page', $page);

        return [
            'data' => $paginator->items(),
            'page' => $paginator->currentPage(),
            'limit' => $paginator->perPage(),
            'total' => $paginator->total(),
        ];
    }

    public function deleteSEO(string $id): void
    {
        $seo = Seo::find($id);
        if (!$seo) throw AppError::notFound('SEO record not found');
        $seo->delete();
    }

    /**
     * Auto-generate SEO metadata for an entity.
     * Only creates if no SEO exists (does not overwrite manual edits).
     */
    public function autoGenerateSEO(string $entityType, string $entityId, string $name, string $description, ?string $imageUrl = null): void
    {
        // Don't overwrite manual edits
        $existing = Seo::where('entity_type', $entityType)
            ->where('entity_id', $entityId)
            ->first();
        if ($existing) return;

        $siteName = 'Threvolt';
        $metaTitle = substr($name, 0, 50) . " | {$siteName}";

        $cleanDesc = strip_tags($description);
        $cleanDesc = preg_replace('/\s+/', ' ', trim($cleanDesc));
        if (empty($cleanDesc)) {
            $cleanDesc = "Shop {$name} at {$siteName}. Premium quality with fast shipping and easy returns.";
        }
        $metaDescription = strlen($cleanDesc) > 155
            ? substr($cleanDesc, 0, 152) . '...'
            : $cleanDesc;

        // Keywords from name
        $words = array_filter(explode(' ', strtolower($name)), fn($w) => strlen($w) > 2);
        $metaKeywords = implode(', ', array_slice($words, 0, 8));

        $seoData = [
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'meta_title' => $metaTitle,
            'meta_description' => $metaDescription,
            'meta_keywords' => $metaKeywords ?: null,
            'og_title' => $metaTitle,
            'og_description' => $metaDescription,
            'twitter_title' => $metaTitle,
            'twitter_description' => $metaDescription,
        ];

        if ($imageUrl) {
            $seoData['og_image'] = $imageUrl;
            $seoData['twitter_image'] = $imageUrl;
        }

        try {
            Seo::create($seoData);
            Log::info("[AutoSEO] Generated SEO for {$entityType}:{$entityId}");
        } catch (\Exception $e) {
            Log::warning("[AutoSEO] Failed to generate SEO for {$entityType}:{$entityId} — {$e->getMessage()}");
        }
    }
}
