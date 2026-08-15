<?php

namespace App\Repositories;

use App\Models\Promotion;
use App\Models\Setting;
use App\Traits\CacheKeyRegistry;
use Illuminate\Support\Facades\Cache;

class PromotionRepository extends BaseRepository
{
    use CacheKeyRegistry;
    protected function modelClass(): string
    {
        return Promotion::class;
    }

    /**
     * Get active promotions (no end_date OR end_date in the future).
     * Cached for 5 minutes — promotions change infrequently.
     *
     * Uses two separate index-friendly queries + merge instead of a single query
     * with an OR condition on end_date. MySQL's B-tree index can't efficiently
     * scan an OR of (IS NULL) and (>= range), so splitting lets each subquery
     * use a clean range scan on the (is_active, end_date, priority) index.
     *
     * Each subquery can also satisfy ORDER BY priority directly from the index
     * without a filesort.
     */
    public function getActive(): \Illuminate\Database\Eloquent\Collection
    {
        // Check global sales toggle — if disabled, return empty Eloquent Collection
        $salesEnabled = Setting::where('key', 'salesEnabled')->value('value');
        if ($salesEnabled === 'false' || $salesEnabled === '0') {
            return new \Illuminate\Database\Eloquent\Collection();
        }

        return $this->cacheWithTracking('promotions_active', 300, function () {
            $with = ['products:id,name,slug,price', 'categories:id,name,slug'];

            // 1) Promotions with no end date (permanent/evergreen)
            $noEndDate = Promotion::select('id', 'title', 'description', 'image_url', 'discount', 'type', 'status', 'start_date', 'end_date', 'priority', 'is_active', 'coupon_code', 'offer_badge', 'offer_highlight', 'offer_tagline', 'offer_theme', 'auto_apply')
                ->with($with)
                ->where('is_active', true)
                ->whereNull('end_date')
                ->orderBy('priority', 'desc')
                ->get();

            // 2) Promotions with a future end date
            $activeDateRange = Promotion::select('id', 'title', 'description', 'image_url', 'discount', 'type', 'status', 'start_date', 'end_date', 'priority', 'is_active', 'coupon_code', 'offer_badge', 'offer_highlight', 'offer_tagline', 'offer_theme', 'auto_apply')
                ->with($with)
                ->where('is_active', true)
                ->where('end_date', '>=', now())
                ->orderBy('priority', 'desc')
                ->get();

            // Merge and re-sort globally by priority to match the original single-query ordering.
            return $noEndDate->merge($activeDateRange)
                ->sortByDesc('priority')
                ->values();
        });
    }

    /**
     * Get all promotions with filters and pagination.
     */
    public function findAll(array $filters = [], int $page = 1, int $limit = 20): array
    {
        $query = Promotion::with(['products:id,name,slug,price', 'categories:id,name,slug']);

        if (!empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['is_active'])) {
            $query->where('is_active', $filters['is_active']);
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhere('coupon_code', 'like', "%{$search}%");
            });
        }

        $paginator = $query->orderBy('priority', 'desc')
            ->paginate($limit, ['*'], 'page', $page);

        // Map snake_case DB fields to camelCase expected by frontend
        $items = collect($paginator->items())->map(function ($promotion) {
            $products = $promotion->products->map(fn($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'slug' => $p->slug,
                'price' => $p->price,
            ]);
            $categories = $promotion->categories->map(fn($c) => [
                'id' => $c->id,
                'name' => $c->name,
                'slug' => $c->slug,
            ]);
            return array_merge($promotion->toArray(), [
                'startDate' => $promotion->start_date,
                'endDate'   => $promotion->end_date,
                'isActive'  => $promotion->is_active ?? false,
                'imageUrl'  => $promotion->image_url,
                'productIds' => $products->pluck('id')->toArray(),
                'categoryIds' => $categories->pluck('id')->toArray(),
                'products' => $products->toArray(),
                'categories' => $categories->toArray(),
            ]);
        })->toArray();

        return [
            'items' => $items,
            'page' => $paginator->currentPage(),
            'limit' => $paginator->perPage(),
            'total' => $paginator->total(),
            'total_pages' => $paginator->lastPage(),
        ];
    }

    /**
     * Find promotion by coupon code.
     */
    public function findByCouponCode(string $code): ?Promotion
    {
        return Promotion::with(['products:id,name,slug,price', 'categories:id,name,slug'])
            ->where('coupon_code', $code)->first();
    }

    /**
     * Find promotions by type.
     */
    public function findByType(string $type): \Illuminate\Database\Eloquent\Collection
    {
        return Promotion::with(['products:id,name,slug,price', 'categories:id,name,slug'])
            ->where('type', $type)
            ->where('is_active', true)
            ->orderBy('priority', 'desc')
            ->get();
    }

    /**
     * Update promotion status.
     */
    public function updateStatus(string $id, string $status): Promotion
    {
        $promotion = $this->findByIdOrFail($id);
        $promotion->update(['status' => $status]);
        $this->clearActiveCache();
        return $promotion->fresh();
    }

    /**
     * Clear cached active promotions.
     */
    private function clearActiveCache(): void
    {
        $this->clearTrackedCache();
    }

    /**
     * Create a promotion with optional product/category pivot sync.
     */
    public function createWithRelations(array $data, array $productIds = [], array $categoryIds = []): Promotion
    {
        $promotion = Promotion::create($data);

        if (!empty($productIds)) {
            $promotion->products()->sync($productIds);
        }
        if (!empty($categoryIds)) {
            $promotion->categories()->sync($categoryIds);
        }

        $this->clearActiveCache();

        return $promotion->fresh()->load(['products:id,name,slug,price', 'categories:id,name,slug']);
    }

    /**
     * Update a promotion with optional product/category pivot sync.
     * Pass null for productIds/categoryIds to leave associations unchanged.
     * Pass an empty array to clear all associations.
     */
    public function updateWithRelations(string $id, array $data, ?array $productIds = null, ?array $categoryIds = null): Promotion
    {
        $promotion = $this->findByIdOrFail($id);
        $promotion->update($data);

        if ($productIds !== null) {
            $promotion->products()->sync($productIds);
        }
        if ($categoryIds !== null) {
            $promotion->categories()->sync($categoryIds);
        }

        $this->clearActiveCache();

        return $promotion->fresh()->load(['products:id,name,slug,price', 'categories:id,name,slug']);
    }

    /**
     * Find a promotion by ID with relations loaded.
     */
    public function findByIdWithRelations(string $id): ?Promotion
    {
        return Promotion::with(['products:id,name,slug,price', 'categories:id,name,slug'])->find($id);
    }

    /**
     * Override delete to clear cache.
     */
    public function delete(string $id): bool
    {
        $result = parent::delete($id);
        $this->clearActiveCache();
        return $result;
    }
}
