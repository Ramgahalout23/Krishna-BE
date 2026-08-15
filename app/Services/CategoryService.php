<?php

namespace App\Services;

use App\Repositories\CategoryRepository;
use App\Exceptions\AppError;
use App\Models\Category;
use App\Traits\CacheKeyRegistry;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class CategoryService
{
    use CacheKeyRegistry;

    public function __construct(
        protected CategoryRepository $categoryRepository
    ) {}

    public function getAll(): array
    {
        return $this->cacheWithTracking('categories_all', 3600, function () {
            return $this->categoryRepository->all()->toArray();
        });
    }

    /**
     * Clear cached category data when categories are created/updated/deleted.
     */
    private function clearCache(?string $categoryId = null): void
    {
        $this->clearTrackedCache();
        $this->categoryRepository->clearCache();
    }

    public function getHierarchy(): array
    {
        return $this->categoryRepository->getHierarchy()->toArray();
    }

    public function getById(string $id): array
    {
        $category = $this->categoryRepository->findById($id);
        if (!$category) throw AppError::notFound('Category not found');
        return $category->toArray();
    }

    public function getActive(): array
    {
        return $this->categoryRepository->getActive()->toArray();
    }

    public function getTree(): array
    {
        $categories = $this->categoryRepository->getTree()->toArray();
        return $this->buildTree($categories);
    }

    public function getSubcategories(string $categoryId): array
    {
        return $this->cacheWithTracking("category_subcategories_{$categoryId}", 3600, function () use ($categoryId) {
            $category = Category::with(['children' => function ($q) {
                $q->withCount('products');
            }])->withCount('products')->find($categoryId);
            if (!$category) throw AppError::notFound('Category not found');
            return [
                'category' => $category->toArray(),
                'subcategories' => $category->children->toArray(),
            ];
        });
    }

    public function getCategoryStats(string $categoryId): array
    {
        return $this->cacheWithTracking("category_stats_{$categoryId}", 3600, function () use ($categoryId) {
            $category = Category::withCount('products')
                ->with(['children' => function ($q) {
                    $q->withCount('products');
                }])
                ->find($categoryId);
            if (!$category) throw AppError::notFound('Category not found');

            $totalProducts = $category->products_count;
            foreach ($category->children as $child) {
                $totalProducts += $child->products_count;
            }

            return [
                'id' => $category->id,
                'name' => $category->name,
                'products_count' => $category->products_count,
                'total_products_including_subcategories' => $totalProducts,
                'subcategories_count' => $category->children->count(),
            ];
        });
    }

    public function create(array $data): array
    {
        $data['slug'] = $data['slug'] ?? Str::slug($data['name']);
        $category = $this->categoryRepository->create($data);

        $this->clearCache();

        // Auto-generate SEO (matches TypeScript SEOService.autoGenerateSEO)
        try {
            $seoService = app(SeoService::class);
            $seoService->autoGenerateSEO('category', $category->id, $category->name, $category->description ?? '');
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning('AutoSEO failed for category', ['id' => $category->id, 'error' => $e->getMessage()]);
        }

        return $category->toArray();
    }

    public function update(string $id, array $data): array
    {
        $this->categoryRepository->findByIdOrFail($id);
        if (!empty($data['name']) && empty($data['slug'])) {
            $data['slug'] = Str::slug($data['name']);
        }
        $category = $this->categoryRepository->update($id, $data);
        $this->clearCache($id);
        return $category->toArray();
    }

    public function delete(string $id): void
    {
        $category = $this->categoryRepository->findByIdOrFail($id);
        
        // Check for products before deleting (matching TS behavior)
        if ($category->products_count > 0) {
            throw AppError::validation('Cannot delete category that contains products');
        }
        
        $this->categoryRepository->delete($id);
        $this->clearCache($id);
    }

    private function buildTree(array $categories, ?string $parentId = null): array
    {
        $tree = [];
        foreach ($categories as $cat) {
            if ($cat['parent_id'] === $parentId) {
                $children = $this->buildTree($categories, $cat['id']);
                $cat['children'] = $children;
                $tree[] = $cat;
            }
        }
        return $tree;
    }
}
