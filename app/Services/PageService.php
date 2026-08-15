<?php

namespace App\Services;

use App\Repositories\PageRepository;
use App\Exceptions\AppError;
use App\Models\Page;
use App\Services\SeoService;
use App\Traits\CacheKeyRegistry;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class PageService
{
    use CacheKeyRegistry;

    public function __construct(
        protected PageRepository $pageRepository,
        protected SeoService $seoService
    ) {}

    public function getAll(): array
    {
        return $this->pageRepository->getPublished()->toArray();
    }

    public function getById(string $id): array
    {
        $page = $this->pageRepository->findById($id);
        if (!$page) throw AppError::notFound('Page not found');
        return $page->toArray();
    }

    public function getBySlug(string $slug): array
    {
        return $this->cacheWithTracking("page_by_slug_{$slug}", 3600, function () use ($slug) {
            $page = $this->pageRepository->findBySlug($slug);
            if (!$page) throw AppError::notFound('Page not found');
            return $page->toArray();
        });
    }

    // ── Admin Methods ──

    public function getAllPages(int $perPage = 15): array
    {
        $paginated = $this->pageRepository->getAllPaginated($perPage)->toArray();

        // Map snake_case DB fields to camelCase expected by frontend
        if (isset($paginated['data']) && is_array($paginated['data'])) {
            $paginated['data'] = array_map(function ($page) {
                $page['isPublished'] = (bool) ($page['is_published'] ?? false);
                $page['status'] = $page['isPublished'] ? 'PUBLISHED' : 'DRAFT';
                $page['createdAt'] = $page['created_at'] ?? null;
                $page['updatedAt'] = $page['updated_at'] ?? null;
                return $page;
            }, $paginated['data']);
        }

        return $paginated;
    }

    public function createPage(array $data): array
    {
        if (empty($data['slug'])) {
            $data['slug'] = Str::slug($data['title']);
        }
        $data['is_published'] = $data['is_active'] ?? ($data['status'] ?? 'DRAFT') === 'PUBLISHED';
        $page = $this->pageRepository->create($data);

        // Clear published pages cache so new page appears in nav
        $this->clearTrackedCache();
        $this->pageRepository->clearPublishedCache();

        // Auto-generate SEO (matches TypeScript SEOService.autoGenerateSEO)
        try {
            $this->seoService->autoGenerateSEO('page', $page->id, $page->title, $page->content ?? '');
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning('AutoSEO failed for page', ['id' => $page->id, 'error' => $e->getMessage()]);
        }

        // Regenerate sitemap (matches TypeScript CmsRoutes sitemap regeneration)
        try {
            $this->seoService->generateAndPersistSitemap();
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning('Sitemap regeneration failed for created page', ['id' => $page->id, 'error' => $e->getMessage()]);
        }

        return $page->toArray();
    }

    public function updatePage(string $id, array $data): array
    {
        $page = $this->pageRepository->findById($id);
        if (!$page) throw AppError::notFound('Page not found');
        $oldSlug = $page->slug;

        if (isset($data['status'])) {
            $data['is_published'] = $data['status'] === 'PUBLISHED';
            unset($data['status']);
        }
        if (!empty($data['title']) && empty($data['slug'])) {
            $data['slug'] = Str::slug($data['title']);
        }
        $page = $this->pageRepository->update($id, $data);

        // Clear cache so nav reflects updated page
        $this->clearTrackedCache();
        $this->pageRepository->clearPublishedCache();

        // Regenerate sitemap (matches TypeScript CmsRoutes sitemap regeneration on update)
        try {
            $this->seoService->generateAndPersistSitemap();
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning('Sitemap regeneration failed for updated page', ['id' => $id, 'error' => $e->getMessage()]);
        }

        return $page->fresh()->toArray();
    }

    public function deletePage(string $id): void
    {
        $page = $this->pageRepository->findById($id);
        if (!$page) throw AppError::notFound('Page not found');
        $this->pageRepository->delete($id);

        // Clear cache so nav no longer shows deleted page
        $this->clearTrackedCache();
        $this->pageRepository->clearPublishedCache();

        // Regenerate sitemap (matches TypeScript CmsRoutes sitemap regeneration on delete)
        try {
            $this->seoService->generateAndPersistSitemap();
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning('Sitemap regeneration failed after page deletion', ['id' => $id, 'error' => $e->getMessage()]);
        }
    }
}
