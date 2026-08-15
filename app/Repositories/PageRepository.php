<?php

namespace App\Repositories;

use App\Models\Page;
use App\Traits\CacheKeyRegistry;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;

class PageRepository extends BaseRepository
{
    use CacheKeyRegistry;
    protected function modelClass(): string
    {
        return Page::class;
    }

    public function findBySlug(string $slug): ?Page
    {
        return Page::where('slug', $slug)->where('is_published', true)->first();
    }

    /**
     * Get published pages ordered by title.
     * Cached for 60 seconds to avoid repeated DB hits on every page load.
     */
    public function getPublished(): Collection
    {
        return $this->cacheWithTracking('pages_published', 60, function () {
            return Page::where('is_published', true)->orderBy('title')->get();
        });
    }

    /**
     * Get all pages (including unpublished) — admin use.
     */
    public function getAllPaginated(int $perPage = 15): LengthAwarePaginator
    {
        return Page::orderBy('updated_at', 'desc')->paginate($perPage);
    }

    /**
     * Clear the published pages cache.
     * Called automatically when pages are created, updated, or deleted.
     */
    public function clearPublishedCache(): void
    {
        $this->clearTrackedCache();
    }
}
