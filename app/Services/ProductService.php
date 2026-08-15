<?php

namespace App\Services;

use App\Models\ProductVariant;
use App\Repositories\ProductRepository;
use App\Exceptions\AppError;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class ProductService
{
    public function __construct(
        protected ProductRepository $productRepository
    ) {}

    /**
     * Get cache key for product list queries.
     */
    private function listCacheKey(string $method, array $params = []): string
    {
        return 'products:' . $method . ':' . md5(serialize($params));
    }

    /**
     * Clear all cached product lists when products are created/updated/deleted.
     * Uses prefixed keys (compatible with file/Redis/Memcached drivers).
     */
    private function clearListCache(): void
    {
        // Use a version key approach: increment version to invalidate all product caches
        $version = Cache::get('products_cache_version', 0);
        Cache::forever('products_cache_version', $version + 1);
    }



    /**
     * Get versioned cache key for product lists.
     */
    private function versionedCacheKey(string $method, array $params = []): string
    {
        $version = Cache::get('products_cache_version', 0);
        return 'products:v' . $version . ':' . $method . ':' . md5(serialize($params));
    }

    public function getAll(array $filters = []): array
    {
        $cacheKey = $this->versionedCacheKey('getAll', $filters);
        return Cache::remember($cacheKey, 300, function () use ($filters) {
            $paginator = $this->productRepository->findMany($filters);
            $products = $this->decodeVariantAttributesInList(
                collect($paginator->items())->toArray()
            );

            return [
                'data' => $products,
                'meta' => [
                    'current_page' => $paginator->currentPage(),
                    'from' => $paginator->firstItem(),
                    'last_page' => $paginator->lastPage(),
                    'per_page' => $paginator->perPage(),
                    'to' => $paginator->lastItem(),
                    'total' => $paginator->total(),
                ],
            ];
        });
    }

    public function getById(string $id): array
    {
        $version = Cache::get('products_cache_version', 0);
        $versionedKey = 'product:detail:v' . $version . ':' . $id;

        return Cache::remember($versionedKey, 60, function () use ($id) {
            // Support slug, UUID, and numeric ID lookups
            $product = $this->productRepository->findBySlug($id);

            if (!$product) {
                $product = $this->productRepository->findWithDetails($id);
            }

            if (!$product) throw AppError::notFound('Product not found');
            
            return $this->enrichWithVariantFields($product->toArray());
        });
    }

    /**
     * Snake-to-camelCase mapping for product fields.
     * The frontend ProductCard component expects camelCase keys.
     */
    private const PRODUCT_CAMEL_MAP = [
        'old_price'          => 'oldPrice',
        'short_description'  => 'shortDescription',
        'review_count'       => 'reviewCount',
        'sold_count'         => 'soldCount',
        'is_featured'        => 'isFeatured',
        'is_new'             => 'isNew',
        'image_url'          => 'imageUrl',
        'hover_image_url'    => 'hoverImageUrl',
        'category_id'        => 'categoryId',
        'brand_id'           => 'brandId',
        'created_at'         => 'createdAt',
        'updated_at'         => 'updatedAt',
        'display_order'      => 'displayOrder',
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

    /**
     * Decode variant attributes and compute sizes/colors for a list of products.
     * This ensures the frontend receives proper attribute objects (not double-encoded strings)
     * AND computed sizes/colors arrays on every product, matching single-product detail responses.
     */
    private function decodeVariantAttributesInList(array $products): array
    {
        // Filter out non-array items (e.g., stale cached IDs) to prevent TypeError
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

                    // Collect sizes and colors from variant attributes
                    $attrs = $variant['attributes'] ?? [];
                    if (!empty($attrs['size'])) {
                        $sizes[] = $attrs['size'];
                    }
                    if (!empty($attrs['color'])) {
                        $colors[] = $attrs['color'];
                    }
                }
                unset($variant);

                // Attach computed arrays to the product (matching enrichWithVariantFields behavior)
                $product['sizes'] = array_values(array_unique($sizes));
                $product['colors'] = array_values(array_unique($colors));
            }
            return $product;
        }, array_filter($products, 'is_array'));
    }

    /**
     * Enrich product response with computed sizes and colors from variants.
     * Matches TypeScript's attachSizesAndColors() behavior.
     */
    private function enrichWithVariantFields(array $productData): array
    {
        // Map snake_case DB fields to camelCase for the frontend
        $productData = $this->productToCamelCase($productData);

        // Load variants if not already loaded
        if (isset($productData['variants']) && is_array($productData['variants'])) {
            $sizes = [];
            $colors = [];

            foreach ($productData['variants'] as &$variant) {
                $attributes = $variant['attributes'] ?? [];
                if (is_string($attributes)) {
                    $attributes = json_decode($attributes, true) ?? [];
                }
                // Write back decoded attributes so the API returns proper JSON objects
                $variant['attributes'] = $attributes;
                if (!empty($attributes['size'])) {
                    $sizes[] = $attributes['size'];
                }
                if (!empty($attributes['color'])) {
                    $colors[] = $attributes['color'];
                }
            }
            unset($variant);

            $productData['sizes'] = array_values(array_unique($sizes));
            $productData['colors'] = array_values(array_unique($colors));
        }

        return $productData;
    }

    public function getBySlug(string $slug): array
    {
        $product = $this->productRepository->findBySlug($slug);
        if (!$product) throw AppError::notFound('Product not found');
        return $this->enrichWithVariantFields($product->toArray());
    }

    public function create(array $data): array
    {
        if (!empty($data['sku'])) {
            $existing = $this->productRepository->findBySku($data['sku']);
            if ($existing) throw AppError::conflict("Product with SKU {$data['sku']} already exists");
        }

        if (($data['price'] ?? 0) <= 0) throw AppError::validation('Price must be greater than 0');

        $data['slug'] = Str::slug($data['name']) . '-' . Str::random(6);
        $data['status'] = $data['status'] ?? 'DRAFT';

        $product = $this->productRepository->create($data);

        // Clear cached lists so new product appears immediately
        $this->clearListCache();
        Cache::forget('homepage_all');

        // Auto-create default variant (matching TS behavior)
        try {
            ProductVariant::create([
                'product_id' => $product->id,
                'name' => $product->name,
                'sku' => ($data['sku'] ?? $product->sku ?? strtoupper(substr($product->name, 0, 3)) . '-' . substr($product->id, 0, 6)) . '-DEFAULT',
                'attributes' => json_encode([]),
                'price' => $data['price'] ?? 0,
                'quantity' => $data['quantity'] ?? 0,
            ]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning('Default variant creation failed', ['product_id' => $product->id, 'error' => $e->getMessage()]);
        }

        // Auto-generate SEO (matches TypeScript SEOService.autoGenerateSEO)
        try {
            $seoService = app(SeoService::class);
            $seoService->autoGenerateSEO('product', $product->id, $product->name, $product->description ?? '');
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning('AutoSEO failed for product', ['id' => $product->id, 'error' => $e->getMessage()]);
        }

        return $product->toArray();
    }

    public function update(string $id, array $data): array
    {
        $product = $this->productRepository->findByIdOrFail($id);

        if (!empty($data['sku']) && $data['sku'] !== $product->sku) {
            $existing = $this->productRepository->findBySku($data['sku']);
            if ($existing) throw AppError::conflict("SKU {$data['sku']} already in use");
        }

        $product = $this->productRepository->update($id, $data);
        $this->clearListCache();
        Cache::forget('homepage_all');
        return $product->toArray();
    }

    public function delete(string $id): void
    {
        $this->productRepository->findByIdOrFail($id);
        $this->productRepository->delete($id);
        $this->clearListCache();
        Cache::forget('homepage_all');
        
        // Trigger sitemap regeneration and cache invalidation (matching TS behavior)
        try {
            $seoService = app(SeoService::class);
            $seoService->generateAndPersistSitemap();
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning('Sitemap regeneration failed after delete', ['error' => $e->getMessage()]);
        }
    }

    public function getFeatured(int $limit = 8): array
    {
        $cacheKey = $this->versionedCacheKey('featured', ['limit' => $limit]);
        return Cache::remember($cacheKey, 600, function () use ($limit) {
            return $this->decodeVariantAttributesInList($this->productRepository->getFeatured($limit)->toArray());
        });
    }

    public function getNewArrivals(int $limit = 8): array
    {
        $cacheKey = $this->versionedCacheKey('newArrivals', ['limit' => $limit]);
        return Cache::remember($cacheKey, 600, function () use ($limit) {
            return $this->decodeVariantAttributesInList($this->productRepository->getNewArrivals($limit)->toArray());
        });
    }

    public function getBestSellers(int $limit = 8): array
    {
        $cacheKey = $this->versionedCacheKey('bestSellers', ['limit' => $limit]);
        return Cache::remember($cacheKey, 600, function () use ($limit) {
            return $this->decodeVariantAttributesInList($this->productRepository->getBestSellers($limit)->toArray());
        });
    }

    public function search(string $query, int $limit = 10): array
    {
        if (strlen($query) < 2) throw AppError::validation('Search query must be at least 2 characters');
        return $this->decodeVariantAttributesInList($this->productRepository->search($query, $limit)->toArray());
    }

    public function getByCategory(string $categoryId, array $filters = []): array
    {
        $filters['category_id'] = $categoryId;
        $cacheKey = $this->versionedCacheKey('byCategory', $filters);
        return Cache::remember($cacheKey, 300, function () use ($filters) {
            $paginator = $this->productRepository->findMany($filters);
            $products = $this->decodeVariantAttributesInList(
                collect($paginator->items())->toArray()
            );

            return [
                'data' => $products,
                'meta' => [
                    'current_page' => $paginator->currentPage(),
                    'from' => $paginator->firstItem(),
                    'last_page' => $paginator->lastPage(),
                    'per_page' => $paginator->perPage(),
                    'to' => $paginator->lastItem(),
                    'total' => $paginator->total(),
                ],
            ];
        });
    }

    public function checkAvailability(string $productId, int $quantity = 1): bool
    {
        $product = $this->productRepository->findById($productId);
        return $product && $product->quantity >= $quantity && $product->status === 'PUBLISHED';
    }

    public function publish(string $id): array
    {
        $product = $this->productRepository->findByIdOrFail($id);

        // Validate variant stock before publishing (matching TS behavior)
        if ($product->quantity <= 0) {
            throw AppError::validation('Product must have stock available before publishing');
        }

        $updated = $this->productRepository->update($id, ['status' => 'PUBLISHED']);
        $this->clearListCache();
        Cache::forget('homepage_all');
        
        // Trigger sitemap regeneration and cache invalidation (matching TS behavior)
        try {
            $seoService = app(SeoService::class);
            $seoService->generateAndPersistSitemap();
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning('Sitemap regeneration failed after publish', ['error' => $e->getMessage()]);
        }

        return $updated->toArray();
    }

    public function archive(string $id): array
    {
        $product = $this->productRepository->findByIdOrFail($id);

        $updated = $this->productRepository->update($id, ['status' => 'ARCHIVED']);
        $this->clearListCache();
        Cache::forget('homepage_all');
        
        // Trigger sitemap regeneration and cache invalidation (matching TS behavior)
        try {
            $seoService = app(SeoService::class);
            $seoService->generateAndPersistSitemap();
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning('Sitemap regeneration failed after archive', ['error' => $e->getMessage()]);
        }

        return $updated->toArray();
    }

    public function getRelated(string $productId, int $limit = 8): array
    {
        $cacheKey = $this->versionedCacheKey('related', ['id' => $productId, 'limit' => $limit]);
        return Cache::remember($cacheKey, 600, function () use ($productId, $limit) {
            $categoryId = \App\Models\Product::where('id', $productId)->value('category_id');
            if (!$categoryId) {
                return [];
            }

            $products = \App\Models\Product::with([
                'category:id,name,slug,image',
                'images:id,product_id,url,alt,display_order',
                'variants:id,product_id,name,sku,attributes,price,quantity',
            ])
            ->where('category_id', $categoryId)
            ->where('id', '!=', $productId)
            ->where('id', '!=', \App\Repositories\ProductRepository::CUSTOM_TEE_PRODUCT_ID)
            ->where('status', 'PUBLISHED')
            ->inRandomOrder()
            ->take($limit)
            ->get()
            ->toArray();

            return $this->decodeVariantAttributesInList($products);
        });
    }

    public function getLowStock(int $threshold = 5): array
    {
        return $this->decodeVariantAttributesInList($this->productRepository->findLowStock($threshold)->toArray());
    }

    public function importFromCSV(array $rows): array
    {
        $imported = 0;
        $skipped = 0;
        $errors = [];

        foreach ($rows as $index => $row) {
            try {
                $data = [];

                if (empty($row['name'])) {
                    $skipped++;
                    $errors[] = "Row " . ($index + 2) . ": Missing product name";
                    continue;
                }

                $data['name'] = $row['name'];
                $data['description'] = $row['description'] ?? '';
                $data['price'] = $row['price'] ?? 0;
                $data['quantity'] = $row['quantity'] ?? 0;
                $data['sku'] = $row['sku'] ?? null;
                $data['status'] = $row['status'] ?? 'DRAFT';
                $data['category_id'] = $row['category_id'] ?? null;
                $data['is_featured'] = filter_var($row['is_featured'] ?? false, FILTER_VALIDATE_BOOLEAN);

                $this->create($data);
                $imported++;
            } catch (\Exception $e) {
                $skipped++;
                $errors[] = "Row " . ($index + 2) . ": " . $e->getMessage();
            }
        }

        return [
            'imported' => $imported,
            'skipped' => $skipped,
            'errors' => $errors,
            'total' => count($rows),
        ];
    }
}
