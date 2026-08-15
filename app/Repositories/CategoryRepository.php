<?php

namespace App\Repositories;

use App\Models\Category;
use App\Traits\CacheKeyRegistry;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;

class CategoryRepository extends BaseRepository
{
    use CacheKeyRegistry;
    protected function modelClass(): string
    {
        return Category::class;
    }

    /**
     * Override all() to only return columns the frontend uses (id, name, slug, image)
     * instead of SELECT *. The public API caches this for 1 hour.
     */
    public function all(array $relations = []): Collection
    {
        return $this->model->select(['id', 'name', 'slug', 'image'])->with($relations)->get();
    }

    public function findBySlug(string $slug): ?Category
    {
        return Category::withCount('products')->where('slug', $slug)->first();
    }

    public function getHierarchy(): Collection
    {
        return $this->cacheWithTracking('categories_hierarchy', 3600, function () {
            return Category::with('children')
                ->whereNull('parent_id')
                ->orderBy('name')
                ->get();
        });
    }

    public function getActive(): Collection
    {
        return $this->cacheWithTracking('categories_active', 3600, function () {
            return Category::where('is_active', true)
                ->withCount('products')
                ->orderBy('name')
                ->get();
        });
    }

    public function getSubcategories(string $categoryId): Collection
    {
        return Category::where('parent_id', $categoryId)
            ->withCount('products')
            ->orderBy('name')
            ->get();
    }

    public function getCategoryProductCount(string $categoryId): int
    {
        return Category::where('id', $categoryId)
            ->withCount('products')
            ->first()
            ?->products_count ?? 0;
    }

    public function getTree(): Collection
    {
        return $this->cacheWithTracking('categories_tree', 3600, function () {
            return Category::withCount('products')
                ->orderBy('name')
                ->get();
        });
    }

    /**
     * Clear all cached category data.
     */
    public function clearCache(): void
    {
        $this->clearTrackedCache();
    }
}
