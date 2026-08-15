<?php

namespace App\Services;

use App\Models\Seo;
use App\Models\Product;
use App\Models\Category;
use App\Models\Setting;
use App\Models\SeoScoreHistory;
use App\Exceptions\AppError;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class AdvancedSeoService
{
    /**
     * Batch-fetch multiple settings in a single query.
     */
    private function getSeoSettings(array $keys): array
    {
        return Setting::whereIn('key', $keys)->pluck('value', 'key')->toArray();
    }

    /**
     * Generate JSON-LD structured data for a product entity.
     */
    public function generateProductSchema(string $entityId): ?array
    {
        $product = Product::with('category', 'brand', 'variants')->find($entityId);
        if (!$product) return null;

        $seo = $this->getSeoSettings(['seo_organization_name', 'seo_organization_url']);
        $orgName = $seo['seo_organization_name'] ?? config('app.name');
        $orgUrl = $seo['seo_organization_url'] ?? url('/');
        $baseUrl = url('/');

        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => $product->name,
            'description' => strip_tags($product->description ?? ''),
            'sku' => $product->sku ?? $product->id,
            'mpn' => $product->id,
        ];

        if ($product->image) {
            $schema['image'] = $this->resolveUrl($product->image, $baseUrl);
        }

        if ($product->category) {
            $schema['category'] = $product->category->name;
        }

        if ($product->brand) {
            $schema['brand'] = [
                '@type' => 'Brand',
                'name' => $product->brand->name,
            ];
        }

        if ($product->price) {
            $schema['offers'] = [
                '@type' => 'Offer',
                'price' => (string) $product->price,
                'priceCurrency' => $product->currency ?? 'INR',
                'availability' => ($product->stock ?? 0) > 0
                    ? 'https://schema.org/InStock'
                    : 'https://schema.org/OutOfStock',
                'url' => $baseUrl . '/products/' . ($product->slug ?? $product->id),
            ];
        }

        if ($product->review_count > 0) {
            $schema['aggregateRating'] = [
                '@type' => 'AggregateRating',
                'ratingValue' => (string) ($product->rating ?? 0),
                'reviewCount' => (string) $product->review_count,
            ];
        }

        // Add shipping details and return policy
        $schema['shippingDetails'] = [
            '@type' => 'OfferShippingDetails',
            'shippingRate' => ['@type' => 'MonetaryAmount', 'value' => '0', 'currency' => $product->currency ?? 'INR'],
            'deliveryTime' => [
                '@type' => 'ShippingDeliveryTime',
                'handlingTime' => ['@type' => 'QuantitativeValue', 'minValue' => 1, 'maxValue' => 3, 'unitCode' => 'DAY'],
                'transitTime' => ['@type' => 'QuantitativeValue', 'minValue' => 2, 'maxValue' => 7, 'unitCode' => 'DAY'],
            ],
        ];

        $schema['hasMerchantReturnPolicy'] = [
            '@type' => 'MerchantReturnPolicy',
            'applicableCountry' => 'IN',
            'returnPolicyCountry' => 'IN',
            'returnPolicyCategory' => 'https://schema.org/MerchantReturnFiniteReturnWindow',
            'merchantReturnDays' => 7,
            'returnMethod' => 'https://schema.org/ReturnByMail',
            'returnFees' => 'https://schema.org/FreeReturn',
        ];

        // Organization as manufacturer
        $schema['manufacturer'] = [
            '@type' => 'Organization',
            'name' => $orgName,
            'url' => $orgUrl,
        ];

        return $schema;
    }

    /**
     * Generate Organization JSON-LD schema.
     */
    public function generateOrganizationSchema(): array
    {
        $seo = $this->getSeoSettings([
            'seo_organization_name', 'seo_organization_logo', 'seo_organization_url',
            'seo_social_links', 'store_phone',
        ]);
        $orgName = $seo['seo_organization_name'] ?? config('app.name');
        $orgLogo = $seo['seo_organization_logo'] ?? url('/logo.png');
        $orgUrl = $seo['seo_organization_url'] ?? url('/');
        $socialLinks = $seo['seo_social_links'] ?? '';

        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'Organization',
            'name' => $orgName,
            'url' => $orgUrl,
            'logo' => $orgLogo,
            'contactPoint' => [
                '@type' => 'ContactPoint',
                'telephone' => $seo['store_phone'] ?? '',
                'contactType' => 'customer service',
                'availableLanguage' => ['English', 'Hindi'],
            ],
            'sameAs' => [],
        ];

        if ($socialLinks) {
            $links = explode("\n", $socialLinks);
            foreach ($links as $link) {
                $link = trim($link);
                if (filter_var($link, FILTER_VALIDATE_URL)) {
                    $schema['sameAs'][] = $link;
                }
            }
        }

        return $schema;
    }

    /**
     * Generate BreadcrumbList JSON-LD schema from breadcrumb path array.
     */
    public function generateBreadcrumbSchema(array $crumbs): array
    {
        $items = [];
        $position = 1;
        $seo = $this->getSeoSettings(['seo_breadcrumb_separator']);
        $separator = $seo['seo_breadcrumb_separator'] ?? '/';

        foreach ($crumbs as $crumb) {
            $items[] = [
                '@type' => 'ListItem',
                'position' => $position,
                'name' => $crumb['name'] ?? $crumb,
                'item' => isset($crumb['url']) ? $crumb['url'] : null,
            ];
            $position++;
        }

        return [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => $items,
        ];
    }

    /**
     * Generate FAQ JSON-LD schema.
     */
    public function generateFAQSchema(array $faqs): array
    {
        $items = [];
        foreach ($faqs as $faq) {
            if (empty($faq['question']) || empty($faq['answer'])) continue;
            $items[] = [
                '@type' => 'Question',
                'name' => $faq['question'],
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => strip_tags($faq['answer']),
                ],
            ];
        }

        return [
            '@context' => 'https://schema.org',
            '@type' => 'FAQPage',
            'mainEntity' => $items,
        ];
    }

    /**
     * Generate Website JSON-LD schema.
     */
    public function generateWebsiteSchema(): array
    {
        $seo = $this->getSeoSettings(['seo_organization_name', 'seo_organization_url']);
        $orgName = $seo['seo_organization_name'] ?? config('app.name');
        $orgUrl = $seo['seo_organization_url'] ?? url('/');

        return [
            '@context' => 'https://schema.org',
            '@type' => 'WebSite',
            'name' => $orgName,
            'url' => $orgUrl,
            'potentialAction' => [
                '@type' => 'SearchAction',
                'target' => [
                    '@type' => 'EntryPoint',
                    'urlTemplate' => $orgUrl . '/products?search={search_term_string}',
                ],
                'query-input' => 'required name=search_term_string',
            ],
        ];
    }

    /**
     * Auto-generate all JSON-LD schemas for an entity.
     */
    public function autoGenerateSchemas(string $entityType, string $entityId): array
    {
        $schemas = [];

        // Always generate Website and Organization schemas
        $schemas['json_ld_website'] = $this->generateWebsiteSchema();
        $schemas['json_ld_organization'] = $this->generateOrganizationSchema();

        // Generate entity-specific schema
        if ($entityType === 'product') {
            $productSchema = $this->generateProductSchema($entityId);
            if ($productSchema) {
                $schemas['json_ld_product'] = $productSchema;
            }
            // Generate breadcrumbs for products
            $crumbs = $this->generateBreadcrumbs('product', $entityId);
            $schemas['json_ld_breadcrumb'] = $this->generateBreadcrumbSchema($crumbs);
        } elseif ($entityType === 'category') {
            // Generate breadcrumbs for categories
            $crumbs = $this->generateBreadcrumbs('category', $entityId);
            $schemas['json_ld_breadcrumb'] = $this->generateBreadcrumbSchema($crumbs);
        } elseif ($entityType === 'page') {
            // Generate breadcrumbs for pages
            $crumbs = $this->generateBreadcrumbs('page', $entityId);
            $schemas['json_ld_breadcrumb'] = $this->generateBreadcrumbSchema($crumbs);
        }

        return $schemas;
    }

    /**
     * Run a comprehensive SEO audit on an entity.
     */
    public function auditEntitySEO(string $entityType, string $entityId): array
    {
        $seo = Seo::where('entity_type', $entityType)
            ->where('entity_id', $entityId)
            ->first();

        $score = 0;
        $maxScore = 100;
        $breakdown = [];
        $suggestions = [];

        // 1. Meta Title (15 pts)
        $title = $seo?->meta_title ?? '';
        if (empty($title)) {
            $breakdown['meta_title'] = ['score' => 0, 'max' => 15, 'status' => 'missing', 'message' => 'Meta title is missing'];
            $suggestions[] = 'Add a meta title between 50-60 characters';
        } elseif (strlen($title) < 30) {
            $breakdown['meta_title'] = ['score' => 8, 'max' => 15, 'status' => 'too_short', 'message' => 'Meta title is too short (' . strlen($title) . ' chars)'];
            $suggestions[] = 'Extend meta title to 50-60 characters';
        } elseif (strlen($title) > 70) {
            $breakdown['meta_title'] = ['score' => 10, 'max' => 15, 'status' => 'too_long', 'message' => 'Meta title exceeds 70 characters'];
            $suggestions[] = 'Shorten meta title to under 60 characters';
        } else {
            $breakdown['meta_title'] = ['score' => 15, 'max' => 15, 'status' => 'good', 'message' => 'Meta title length is optimal'];
            $score += 15;
        }

        // 2. Meta Description (15 pts)
        $desc = $seo?->meta_description ?? '';
        if (empty($desc)) {
            $breakdown['meta_description'] = ['score' => 0, 'max' => 15, 'status' => 'missing', 'message' => 'Meta description is missing'];
            $suggestions[] = 'Add a meta description between 150-160 characters';
        } elseif (strlen($desc) < 120) {
            $breakdown['meta_description'] = ['score' => 8, 'max' => 15, 'status' => 'too_short', 'message' => 'Meta description is too short (' . strlen($desc) . ' chars)'];
            $suggestions[] = 'Extend meta description to 150-160 characters';
        } elseif (strlen($desc) > 165) {
            $breakdown['meta_description'] = ['score' => 10, 'max' => 15, 'status' => 'too_long', 'message' => 'Meta description exceeds 165 characters'];
            $suggestions[] = 'Shorten meta description to under 160 characters';
        } else {
            $breakdown['meta_description'] = ['score' => 15, 'max' => 15, 'status' => 'good', 'message' => 'Meta description length is optimal'];
            $score += 15;
        }

        // 3. Open Graph Tags (10 pts)
        $ogScore = 0;
        if ($seo?->og_title) $ogScore += 3;
        if ($seo?->og_description) $ogScore += 3;
        if ($seo?->og_image) $ogScore += 4;
        $breakdown['open_graph'] = ['score' => $ogScore, 'max' => 10, 'status' => $ogScore < 7 ? 'incomplete' : 'good', 'message' => $ogScore < 7 ? 'Open Graph tags are incomplete' : 'Open Graph tags are complete'];
        $score += $ogScore;
        if ($ogScore < 7) $suggestions[] = 'Complete all Open Graph tags (title, description, image)';

        // 4. Twitter Cards (10 pts)
        $twScore = 0;
        if ($seo?->twitter_title) $twScore += 3;
        if ($seo?->twitter_description) $twScore += 3;
        if ($seo?->twitter_image) $twScore += 4;
        $breakdown['twitter_cards'] = ['score' => $twScore, 'max' => 10, 'status' => $twScore < 7 ? 'incomplete' : 'good', 'message' => $twScore < 7 ? 'Twitter card tags are incomplete' : 'Twitter card tags are complete'];
        $score += $twScore;
        if ($twScore < 7) $suggestions[] = 'Complete all Twitter card tags (title, description, image)';

        // 5. Canonical URL (10 pts)
        $canonical = $seo?->canonical_url ?? '';
        if (empty($canonical)) {
            $breakdown['canonical_url'] = ['score' => 0, 'max' => 10, 'status' => 'missing', 'message' => 'Canonical URL is missing'];
            $suggestions[] = 'Add a canonical URL to prevent duplicate content issues';
        } else {
            $breakdown['canonical_url'] = ['score' => 10, 'max' => 10, 'status' => 'good', 'message' => 'Canonical URL is set'];
            $score += 10;
        }

        // 6. JSON-LD Structured Data (15 pts)
        $jsonScore = 0;
        if ($seo?->json_ld_product || $entityType !== 'product') $jsonScore += 5;
        if ($seo?->json_ld_organization) $jsonScore += 5;
        if ($seo?->json_ld_breadcrumb) $jsonScore += 5;
        $breakdown['structured_data'] = ['score' => $jsonScore, 'max' => 15, 'status' => $jsonScore < 10 ? 'incomplete' : 'good', 'message' => $jsonScore < 10 ? 'Structured data is incomplete' : 'Structured data is complete'];
        $score += $jsonScore;
        if ($jsonScore < 10) $suggestions[] = 'Add JSON-LD structured data (Product schema, Organization, Breadcrumb)';

        // 7. Meta Keywords (5 pts)
        $keywords = $seo?->meta_keywords ?? '';
        if (empty($keywords)) {
            $breakdown['meta_keywords'] = ['score' => 0, 'max' => 5, 'status' => 'missing', 'message' => 'Meta keywords are missing'];
        } else {
            $kwCount = count(array_filter(explode(',', $keywords)));
            if ($kwCount >= 3 && $kwCount <= 10) {
                $breakdown['meta_keywords'] = ['score' => 5, 'max' => 5, 'status' => 'good', 'message' => $kwCount . ' keywords found'];
                $score += 5;
            } else {
                $breakdown['meta_keywords'] = ['score' => 3, 'max' => 5, 'status' => 'suboptimal', 'message' => 'Aim for 3-10 keywords'];
                $score += 3;
            }
        }

        // 8. Robots Meta (5 pts)
        $robotsMeta = $seo?->robots_meta ?? '';
        if (empty($robotsMeta)) {
            $breakdown['robots_meta'] = ['score' => 3, 'max' => 5, 'status' => 'not_set', 'message' => 'Robots meta not explicitly set'];
            $score += 3;
        } else {
            $breakdown['robots_meta'] = ['score' => 5, 'max' => 5, 'status' => 'good', 'message' => 'Robots meta is set: ' . $robotsMeta];
            $score += 5;
        }

        // 9. Sitemap Priority (5 pts)
        $priority = $seo?->sitemap_priority ?? 0.5;
        if ($priority > 0) {
            $breakdown['sitemap_priority'] = ['score' => 5, 'max' => 5, 'status' => 'good', 'message' => 'Sitemap priority set to ' . $priority];
            $score += 5;
        } else {
            $breakdown['sitemap_priority'] = ['score' => 2, 'max' => 5, 'status' => 'low', 'message' => 'Sitemap priority is 0'];
            $suggestions[] = 'Increase sitemap priority for this entity';
        }

        // 10. Hreflang (5 pts)
        $hreflang = $seo?->hreflang_tags ?? '';
        if (!empty($hreflang)) {
            $breakdown['hreflang'] = ['score' => 5, 'max' => 5, 'status' => 'good', 'message' => 'Hreflang tags configured'];
            $score += 5;
        } else {
            $breakdown['hreflang'] = ['score' => 2, 'max' => 5, 'status' => 'not_set', 'message' => 'Hreflang tags not set'];
        }

        // 11. Content Language (5 pts)
        $lang = $seo?->content_language ?? '';
        if (!empty($lang)) {
            $breakdown['content_language'] = ['score' => 5, 'max' => 5, 'status' => 'good', 'message' => 'Content language set to ' . $lang];
            $score += 5;
        } else {
            $breakdown['content_language'] = ['score' => 2, 'max' => 5, 'status' => 'not_set', 'message' => 'Content language not set'];
        }

        $result = [
            'score' => $score,
            'max_score' => $maxScore,
            'percentage' => round(($score / $maxScore) * 100),
            'breakdown' => $breakdown,
            'suggestions' => $suggestions,
            'audited_at' => now()->toIso8601String(),
        ];

        // Save audit results to the SEO record
        if ($seo) {
            $seo->update([
                'seo_score' => $score,
                'seo_score_breakdown' => $breakdown,
                'seo_last_audited_at' => now(),
            ]);

            // Record score snapshot in history table for trend charts
            SeoScoreHistory::create([
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'score' => $score,
                'breakdown' => $breakdown,
            ]);
        }

        return $result;
    }

    /**
     * Run bulk SEO audit for all entities of a type.
     */
    public function bulkAudit(string $entityType): array
    {
        $seos = Seo::where('entity_type', $entityType)->get();
        $results = [];
        $totalScore = 0;

        foreach ($seos as $seo) {
            $audit = $this->auditEntitySEO($entityType, $seo->entity_id);
            $results[] = [
                'entity_id' => $seo->entity_id,
                'score' => $audit['score'],
                'percentage' => $audit['percentage'],
            ];
            $totalScore += $audit['score'];
        }

        $avgScore = count($results) > 0 ? round($totalScore / count($results), 1) : 0;

        return [
            'entity_type' => $entityType,
            'total_audited' => count($results),
            'average_score' => $avgScore,
            'average_percentage' => count($results) > 0 ? round(($avgScore / 100) * 100) : 0,
            'results' => $results,
            'audited_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Generate breadcrumb path array for an entity.
     */
    public function generateBreadcrumbs(string $entityType, string $entityId): array
    {
        $baseUrl = url('/');
        $crumbs = [];

        // Home
        $crumbs[] = ['name' => 'Home', 'url' => $baseUrl . '/'];

        if ($entityType === 'product') {
            $product = Product::find($entityId);
            if ($product && $product->category) {
                $crumbs[] = ['name' => $product->category->name, 'url' => $baseUrl . '/categories/' . $product->category->slug];
            }
            $crumbs[] = ['name' => $product?->name ?? 'Product', 'url' => $baseUrl . '/products/' . ($product?->slug ?? $entityId)];
        } elseif ($entityType === 'category') {
            $category = Category::find($entityId);
            $crumbs[] = ['name' => $category?->name ?? 'Category', 'url' => $baseUrl . '/categories/' . ($category?->slug ?? $entityId)];
        } elseif ($entityType === 'page') {
            $crumbs[] = ['name' => 'Page'];
        }

        return $crumbs;
    }

    /**
     * Push URL update to IndexNow (Bing/ChatGPT).
     */
    public function pushToIndexNow(string $url): bool
    {
        $seo = $this->getSeoSettings(['seo_enable_indexnow', 'seo_indexnow_key']);
        $enabled = $seo['seo_enable_indexnow'];
        if ($enabled !== 'true') return false;

        $host = parse_url($url, PHP_URL_HOST);
        // Use custom key from settings if set, otherwise fallback to md5(host)
        $key = $seo['seo_indexnow_key'];
        if (empty($key)) {
            $key = md5($host);
        }

        try {
            $response = Http::timeout(5)->post('https://api.indexnow.org/indexnow', [
                'host' => $host,
                'key' => $key,
                'keyLocation' => url("/{$key}.txt"),
                'urlList' => [$url],
            ]);

            return $response->successful();
        } catch (\Exception $e) {
            Log::warning("IndexNow push failed for {$url}: {$e->getMessage()}");
            return false;
        }
    }

    /**
     * Resolve a URL relative to the base URL.
     */
    private function resolveUrl(?string $path, string $baseUrl): ?string
    {
        if (empty($path)) return null;
        if (filter_var($path, FILTER_VALIDATE_URL)) return $path;
        return rtrim($baseUrl, '/') . '/' . ltrim($path, '/');
    }

    // ── Admin Settings ──

    public function getAdvancedSettings(): array
    {
        $keys = [
            'seo_google_analytics_id', 'seo_google_tag_manager_id', 'seo_facebook_pixel_id',
            'seo_organization_name', 'seo_organization_logo', 'seo_organization_url',
            'seo_social_links', 'seo_hreflang_default', 'seo_enable_auto_schema',
            'seo_enable_indexnow', 'seo_indexnow_key',
            'seo_breadcrumb_separator', 'seo_default_image',
            'seo_twitter_handle', 'seo_auto_audit_enabled', 'seo_audit_schedule',
            'seo_google_site_verification',
        ];
        $settings = Setting::whereIn('key', $keys)->pluck('value', 'key')->toArray();

        return [
            'google_analytics_id' => $settings['seo_google_analytics_id'] ?? '',
            'google_tag_manager_id' => $settings['seo_google_tag_manager_id'] ?? '',
            'facebook_pixel_id' => $settings['seo_facebook_pixel_id'] ?? '',
            'organization_name' => $settings['seo_organization_name'] ?? '',
            'organization_logo' => $settings['seo_organization_logo'] ?? '',
            'organization_url' => $settings['seo_organization_url'] ?? '',
            'social_links' => $settings['seo_social_links'] ?? '',
            'hreflang_default' => $settings['seo_hreflang_default'] ?? 'en',
            'enable_auto_schema' => $settings['seo_enable_auto_schema'] ?? 'true',
            'enable_indexnow' => $settings['seo_enable_indexnow'] ?? 'false',
            'indexnow_key' => $settings['seo_indexnow_key'] ?? '',
            'breadcrumb_separator' => $settings['seo_breadcrumb_separator'] ?? '/',
            'default_image' => $settings['seo_default_image'] ?? '',
            'twitter_handle' => $settings['seo_twitter_handle'] ?? '',
            'auto_audit_enabled' => $settings['seo_auto_audit_enabled'] ?? 'true',
            'audit_schedule' => $settings['seo_audit_schedule'] ?? 'weekly',
            'google_site_verification' => $settings['seo_google_site_verification'] ?? '',
        ];
    }

    public function updateAdvancedSettings(array $data): array
    {
        $map = [
            'google_analytics_id' => 'seo_google_analytics_id',
            'google_tag_manager_id' => 'seo_google_tag_manager_id',
            'facebook_pixel_id' => 'seo_facebook_pixel_id',
            'organization_name' => 'seo_organization_name',
            'organization_logo' => 'seo_organization_logo',
            'organization_url' => 'seo_organization_url',
            'social_links' => 'seo_social_links',
            'hreflang_default' => 'seo_hreflang_default',
            'enable_auto_schema' => 'seo_enable_auto_schema',
            'enable_indexnow' => 'seo_enable_indexnow',
            'indexnow_key' => 'seo_indexnow_key',
            'breadcrumb_separator' => 'seo_breadcrumb_separator',
            'default_image' => 'seo_default_image',
            'twitter_handle' => 'seo_twitter_handle',
            'auto_audit_enabled' => 'seo_auto_audit_enabled',
            'audit_schedule' => 'seo_audit_schedule',
            'google_site_verification' => 'seo_google_site_verification',
        ];

        foreach ($map as $camelKey => $dbKey) {
            if (isset($data[$camelKey])) {
                Setting::updateOrCreate(
                    ['key' => $dbKey, 'module' => 'SITE'],
                    ['value' => (string) $data[$camelKey]]
                );
            }
        }

        return $this->getAdvancedSettings();
    }
}
