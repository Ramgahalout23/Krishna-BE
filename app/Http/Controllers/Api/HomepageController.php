<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CuratedLook;
use App\Models\Product;
use App\Models\Reel;
use App\Models\Review;
use App\Models\Setting;
use App\Models\Banner;
use App\Models\Category;
use App\Models\Promotion;
use App\Traits\CacheKeyRegistry;
use App\Repositories\ProductRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class HomepageController extends Controller
{
    use CacheKeyRegistry;
    /**
     * Snake-to-camelCase mapping for banner fields.
     * The frontend HeroBanner component expects camelCase keys.
     */
    private const BANNER_CAMEL_MAP = [
        'image_url'        => 'imageUrl',
        'link_url'         => 'linkUrl',
        'display_mode'     => 'displayMode',
        'is_active'        => 'isActive',
        'text_dark'        => 'textDark',
        'show_on_mobile'   => 'showOnMobile',
        'show_on_desktop'  => 'showOnDesktop',
        'background_color' => 'backgroundColor',
        'text_color'       => 'textColor',
        'button_text'      => 'buttonText',
        'button_link'      => 'buttonLink',
        'start_date'       => 'startDate',
        'end_date'         => 'endDate',
        'created_by'       => 'createdBy',
    ];

    /**
     * Snake-to-camelCase mapping for promotion fields.
     * The frontend FlashSaleSection expects camelCase keys.
     */
    private const PROMOTION_CAMEL_MAP = [
        'image_url'       => 'imageUrl',
        'link_url'        => 'linkUrl',
        'start_date'      => 'startDate',
        'end_date'        => 'endDate',
        'is_active'       => 'isActive',
        'show_on_mobile'  => 'showOnMobile',
        'show_on_desktop' => 'showOnDesktop',
        'min_purchase'    => 'minPurchase',
        'max_discount'    => 'maxDiscount',
        'coupon_code'     => 'couponCode',
        'created_by'      => 'createdBy',
    ];

    /**
     * Decode variant attributes and compute sizes/colors on product arrays.
     * Mirrors ProductService::decodeVariantAttributesInList to ensure homepage
     * products have properly decoded variant attributes and computed size/color arrays.
     */
    /**
     * Snake-to-camelCase mapping for product fields.
     * The frontend ProductCard component expects camelCase keys.
     */
    private const PRODUCT_CAMEL_MAP = [
        'old_price'          => 'oldPrice',
        'short_description'  => 'shortDescription',
        'review_count'       => 'reviewCount',
        'is_featured'        => 'isFeatured',
        'image_url'          => 'imageUrl',
        'category_id'        => 'categoryId',
        'brand_id'           => 'brandId',
        'created_at'         => 'createdAt',
        'updated_at'         => 'updatedAt',
        'display_order'      => 'displayOrder',
    ];

    /**
     * Columns the storefront homepage cards actually render. Kept deliberately
     * small — the homepage never shows product descriptions, SEO metadata,
     * cost, SKUs, barcodes, etc., so shipping them only bloats the payload.
     * (Variants are loaded for reels only, where the inline picker needs them.)
     */
    private const HOMEPAGE_PRODUCT_COLUMNS = [
        'id', 'name', 'slug', 'price', 'old_price', 'badge', 'quantity',
        'rating', 'review_count', 'description', 'short_description',
        'is_new', 'is_sale', 'is_featured', 'category_id', 'created_at', 'updated_at',
    ];

    /**
     * Eager-loads the relations the homepage product card + quick view need:
     * category name for the chip, image URLs for the gallery. No variants —
     * the homepage card adds to bag directly (variants are for the reels
     * picker and product detail page, which have their own queries).
     */
    private const HOMEPAGE_PRODUCT_WITH = [
        'category:id,name,slug,image',
        'images:id,product_id,url,alt,display_order',
    ];

    /**
     * Add camelCase keys to a single product array (preserving original snake_case keys).
     */
    private function productToCamelCase(array $product): array
    {
        foreach (self::PRODUCT_CAMEL_MAP as $snake => $camel) {
            if (array_key_exists($snake, $product)) {
                $product[$camel] = $product[$snake];
            }
        }
        return $product;
    }

    private function decodeVariants(array $products): array
    {
        return array_map(function (array $product): array {
            // Map snake_case DB fields to camelCase for the frontend
            $product = $this->productToCamelCase($product);

            if (isset($product['variants']) && is_array($product['variants'])) {
                $sizes = [];
                $colors = [];

                foreach ($product['variants'] as &$variant) {
                    if (is_string($variant['attributes'] ?? null)) {
                        $variant['attributes'] = json_decode($variant['attributes'], true) ?? [];
                    }

                    $attrs = $variant['attributes'] ?? [];
                    if (!empty($attrs['size'])) {
                        $sizes[] = $attrs['size'];
                    }
                    if (!empty($attrs['color'])) {
                        $colors[] = $attrs['color'];
                    }
                }
                unset($variant);

                $product['sizes'] = array_values(array_unique($sizes));
                $product['colors'] = array_values(array_unique($colors));
            }
            return $product;
        }, array_filter($products, 'is_array'));
    }

    /**
     * Add camelCase keys to a single banner array (preserving original snake_case keys).
     */
    private function bannerToCamelCase(array $b): array
    {
        foreach (self::BANNER_CAMEL_MAP as $snake => $camel) {
            if (array_key_exists($snake, $b)) {
                $b[$camel] = $b[$snake];
            }
        }
        return $b;
    }

    /**
     * Add camelCase keys to a single promotion array (preserving original snake_case keys).
     */
    private function promotionToCamelCase(array $p): array
    {
        foreach (self::PROMOTION_CAMEL_MAP as $snake => $camel) {
            if (array_key_exists($snake, $p)) {
                $p[$camel] = $p[$snake];
            }
        }
        return $p;
    }

    /**
     * Consolidated homepage endpoint — returns ALL data needed to render the storefront
     * homepage in a single response.
     *
     * This eliminates 15+ separate HTTP requests (each booting Laravel from scratch)
     * and dramatically improves page load time.
     *
     * CRITICAL: Uses DIRECT model queries instead of service-layer calls. Each service
     * (BannerService, ProductService, etc.) has its OWN Cache::remember() which would
     * create a cascade of redundant file cache operations. Since the entire homepage
     * response is cached under 'homepage_all', inner caching is unnecessary.
     *
     * GET /api/v1/homepage
     */
    public function __invoke(): JsonResponse
    {
        $data = $this->cacheWithTracking('homepage_all', 300, function () {

            // Each section is wrapped in try/catch so a failure in one area
            // doesn't crash the entire homepage.

            // ── 1. Settings (single batched query) ──
            $settings = [];
            try {
                $settings = Setting::pluck('value', 'key')->toArray();
            } catch (\Exception $e) {
                logger()->warning('Homepage: settings fetch failed', ['error' => $e->getMessage()]);
            }

            // ── 2. Banners (with camelCase mapping for frontend) ──
            // Fetch ALL active banners grouped by type so the frontend can
            // display each type in its appropriate homepage section.
            // (The flat hero-only 'banners' key was removed — the frontend
            // reads bannersByType['hero'] instead, avoiding duplicate data.)
            $bannersByType = [];
            try {
                $allBanners = Banner::where('is_active', true)
                    ->where(function ($q) {
                        $q->whereNull('start_date')->orWhere('start_date', '<=', now());
                    })
                    ->where(function ($q) {
                        $q->whereNull('end_date')->orWhere('end_date', '>=', now());
                    })
                    ->orderBy('type')
                    ->orderBy('position')
                    ->get()
                    ->toArray();

                $allBanners = array_map(fn($b) => $this->bannerToCamelCase($b), $allBanners);

                // Group banners by their type field
                foreach ($allBanners as $b) {
                    $type = strtolower($b['type'] ?? 'hero');
                    $bannersByType[$type][] = $b;
                }
            } catch (\Exception $e) {
                logger()->warning('Homepage: banners fetch failed', ['error' => $e->getMessage()]);
            }

            // ── 3. Featured Products ──
            // Variant 'images' is only needed on the product detail page —
            // dropping it here (and in newArrivals/bestSellers below) keeps
            // the homepage response much smaller.
            $featured = [];
            try {
                $featured = $this->decodeVariants(Product::with(self::HOMEPAGE_PRODUCT_WITH)
                    ->select(self::HOMEPAGE_PRODUCT_COLUMNS)
                    ->where('status', 'PUBLISHED')
                    ->where('is_featured', true)
                    ->where('id', '!=', ProductRepository::CUSTOM_TEE_PRODUCT_ID)
                    ->latest()
                    ->take(8)
                    ->get()
                    ->toArray());
            } catch (\Exception $e) {
                logger()->warning('Homepage: featured fetch failed', ['error' => $e->getMessage()]);
            }

            // ── 4. New Arrivals ──
            $newArrivals = [];
            try {
                $newArrivals = $this->decodeVariants(Product::with(self::HOMEPAGE_PRODUCT_WITH)
                    ->select(self::HOMEPAGE_PRODUCT_COLUMNS)
                    ->where('status', 'PUBLISHED')
                    ->where('id', '!=', ProductRepository::CUSTOM_TEE_PRODUCT_ID)
                    ->latest()
                    ->take(8)
                    ->get()
                    ->toArray());
            } catch (\Exception $e) {
                logger()->warning('Homepage: newArrivals fetch failed', ['error' => $e->getMessage()]);
            }

            // ── 5. Best Sellers ──
            $bestSellers = [];
            try {
                $bestSellersEnabled = $settings['bestSellersEnabled'] ?? 'true';
                if ($bestSellersEnabled !== 'false' && $bestSellersEnabled !== '0') {
                    $bestSellers = $this->decodeVariants(Product::with(self::HOMEPAGE_PRODUCT_WITH)
                        ->select(self::HOMEPAGE_PRODUCT_COLUMNS)
                        ->where('status', 'PUBLISHED')
                        ->where('id', '!=', ProductRepository::CUSTOM_TEE_PRODUCT_ID)
                        ->orderBy('view_count', 'desc')
                        ->take(8)
                        ->get()
                        ->toArray());
                }
            } catch (\Exception $e) {
                logger()->warning('Homepage: bestSellers fetch failed', ['error' => $e->getMessage()]);
            }

            // ── 6. Categories ──
            $categories = [];
            try {
                $categories = Category::select('id', 'name', 'slug', 'image', 'parent_id')
                    ->withCount('products')
                    // Show populated categories first so the grid never leads with "0 items"
                    ->orderByDesc('products_count')
                    ->get()
                    ->map(function ($category) {
                        $arr = $category->toArray();
                        // Frontend shows "N items" per category tile — ship a clean camelCase count
                        $arr['itemCount'] = $category->products_count ?? 0;
                        unset($arr['products_count']);
                        return $arr;
                    })
                    ->values()
                    ->toArray();
            } catch (\Exception $e) {
                logger()->warning('Homepage: categories fetch failed', ['error' => $e->getMessage()]);
            }

            // ── 7. Homepage Reviews ──
            $reviews = [];
            try {
                $reviewsEnabled = $settings['reviewsEnabled'] ?? 'true';
                if ($reviewsEnabled !== 'false' && $reviewsEnabled !== '0') {
                    // Only the 3 most recent reviews are rendered on the homepage
                    // (Testimonials slices to 3) — no need to ship 20.
                    $reviewModels = Review::with(['user' => fn($q) => $q->select('id', 'first_name', 'last_name', 'email', 'avatar')])
                        ->with('product:id,name')
                        ->where('is_moderated', true)
                        ->where('is_flagged', false)
                        ->latest()
                        ->take(5)
                        ->get();

                    $totalReviews = Review::where('is_moderated', true)
                        ->where('is_flagged', false)
                        ->count();
                    $averageRating = round(
                        Review::where('is_moderated', true)
                            ->where('is_flagged', false)
                            ->avg('rating') ?? 0,
                        1
                    );

                    $reviews = [
                        'reviews' => $reviewModels->toArray(),
                        'stats' => [
                            'average_rating' => $averageRating,
                            'total_reviews' => $totalReviews,
                        ],
                    ];
                }
            } catch (\Exception $e) {
                logger()->warning('Homepage: reviews fetch failed', ['error' => $e->getMessage()]);
            }

            // ── 8. Reels ──
            $reels = [];
            try {
                $reelsEnabled = $settings['reelsEnabled'] ?? 'true';
                if ($reelsEnabled !== 'false' && $reelsEnabled !== '0') {
                    $reels = Reel::where('is_active', true)
                        ->with(['products' => function ($q) {
                            $q->select('products.id', 'products.name', 'products.slug', 'products.price', 'products.old_price', 'products.rating', 'products.review_count', 'products.badge');
                        }, 'products.images' => function ($q) {
                            $q->select('product_images.id', 'product_images.product_id', 'product_images.url', 'product_images.alt')
                              ->orderBy('product_images.display_order');
                        }, 'products.variants' => function ($q) {
                            // Pickers need attributes/price/stock/images — not name/sku bloat
                            $q->select('id', 'product_id', 'attributes', 'price', 'quantity', 'images');
                        }])
                        ->orderBy('display_order')
                        ->orderBy('created_at', 'desc')
                        ->select('id', 'title', 'description', 'video_url', 'image_url', 'link_url', 'display_order')
                        ->get()
                        ->map(fn ($reel) => [
                            'id' => $reel->id,
                            'title' => $reel->title,
                            'description' => $reel->description,
                            'videoUrl' => $reel->video_url,
                            'imageUrl' => $reel->image_url,
                            'linkUrl' => $reel->link_url,
                            'displayOrder' => $reel->display_order,
                            'products' => $reel->products->map(fn ($p) => [
                                'id' => $p->id,
                                'name' => $p->name,
                                'slug' => $p->slug,
                                'price' => $p->price,
                                'old_price' => $p->old_price,
                                'rating' => $p->rating,
                                'review_count' => $p->review_count,
                                'badge' => $p->badge,
                                'image_url' => $p->images->first()?->url ?? null,
                                'variants' => $p->variants->map(fn ($v) => [
                                    'id' => $v->id,
                                    'product_id' => $v->product_id,
                                    'attributes' => is_string($v->attributes) ? json_decode($v->attributes, true) : $v->attributes,
                                    'price' => $v->price,
                                    'quantity' => $v->quantity,
                                    'images' => $v->images,
                                ])->values()->toArray(),
                                'colors' => collect($p->variants)
                                    ->map(fn ($v) => $v->attributes['color'] ?? null)
                                    ->filter()
                                    ->unique()
                                    ->values()
                                    ->toArray(),
                                'sizes' => collect($p->variants)
                                    ->map(fn ($v) => $v->attributes['size'] ?? null)
                                    ->filter()
                                    ->unique()
                                    ->values()
                                    ->toArray(),
                            ]),
                        ])
                        ->toArray();
                }
            } catch (\Exception $e) {
                logger()->warning('Homepage: reels fetch failed', ['error' => $e->getMessage()]);
            }

            // ── 9. Curated Looks ──
            $curatedLooks = [];
            try {
                $curatedLooksEnabled = $settings['curatedLooksEnabled'] ?? 'true';
                if ($curatedLooksEnabled !== 'false' && $curatedLooksEnabled !== '0') {
                    $curatedLooks = CuratedLook::where('is_active', true)
                        ->orderBy('display_order')
                        ->orderBy('created_at', 'desc')
                        ->select('id', 'name', 'description', 'image_url', 'display_order')
                        ->get()
                        ->toArray();
                }
            } catch (\Exception $e) {
                logger()->warning('Homepage: curatedLooks fetch failed', ['error' => $e->getMessage()]);
            }

            // ── 10. Promotions (with camelCase mapping for frontend) ──
            $promotions = [];
            try {
                $salesEnabled = $settings['salesEnabled'] ?? 'true';
                if ($salesEnabled !== 'false' && $salesEnabled !== '0') {
                    $promotions = Promotion::where('is_active', true)
                        ->where(function ($q) {
                            $q->whereNull('start_date')->orWhere('start_date', '<=', now());
                        })
                        ->where(function ($q) {
                            $q->whereNull('end_date')->orWhere('end_date', '>=', now());
                        })
                        ->orderBy('created_at', 'desc')
                        ->get()
                        ->toArray();
                    $promotions = array_map(fn($p) => $this->promotionToCamelCase($p), $promotions);
                }
            } catch (\Exception $e) {
                logger()->warning('Homepage: promotions fetch failed', ['error' => $e->getMessage()]);
            }

            // ── 11. SEO global data (from settings, already loaded) ──
            $seo = [
                'title' => $settings['seo_title'] ?? '',
                'description' => $settings['seo_description'] ?? '',
                'keywords' => $settings['seo_keywords'] ?? '',
            ];

            // ── 12. Maintenance mode status ──
            $maintenance = [
                'enabled' => ($settings['maintenance_mode'] ?? '0') === '1',
                'message' => $settings['maintenance_message'] ?? 'Site is under maintenance',
            ];

            // NOTE: 'settings' is intentionally NOT returned here — the full
            // settings map (including sensitive values like SMTP/API secrets) is
            // already delivered once via /app-init, and this endpoint's copy was
            // never consumed by the frontend. Only the small 'seo' and
            // 'maintenance' projections are included below.
            return compact(
                'bannersByType', 'featured', 'newArrivals', 'bestSellers',
                'categories', 'reviews', 'reels', 'curatedLooks', 'promotions',
                'seo', 'maintenance'
            );
        });

        return response()->json([
            'success' => true,
            'data' => $data,
        ])->setCache(['public' => true, 'max_age' => 60]);
    }
}
