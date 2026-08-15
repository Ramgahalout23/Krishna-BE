<?php

namespace App\Repositories;

use App\Models\Banner;
use Illuminate\Support\Facades\DB;

class BannerRepository extends BaseRepository
{
    protected function modelClass(): string
    {
        return Banner::class;
    }

    public function getActive(): \Illuminate\Database\Eloquent\Collection
    {
        return Banner::select('id', 'title', 'description', 'image_url', 'link_url', 'type', 'position', 'is_active', 'display_mode', 'start_date', 'end_date', 'show_on_mobile', 'show_on_desktop', 'background_color', 'text_color', 'button_text', 'button_link')
            ->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('start_date')->orWhere('start_date', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('end_date')->orWhere('end_date', '>=', now());
            })
            ->orderBy('position')
            ->get();
    }

    /**
     * Get active banners optionally filtered by type.
     */
    public function getActiveByType(?string $type = null): \Illuminate\Database\Eloquent\Collection
    {
        $query = Banner::select('id', 'title', 'description', 'image_url', 'link_url', 'type', 'position', 'is_active', 'display_mode', 'start_date', 'end_date', 'show_on_mobile', 'show_on_desktop', 'background_color', 'text_color', 'button_text', 'button_link')
            ->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('start_date')->orWhere('start_date', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('end_date')->orWhere('end_date', '>=', now());
            });

        if ($type) {
            $query->where('type', strtoupper($type));
        }

        return $query->orderBy('position')->get();
    }

    /**
     * Get banners by type and device visibility.
     */
    public function getByTypeAndDevice(string $type, string $device = 'desktop'): \Illuminate\Database\Eloquent\Collection
    {
        $query = Banner::select('id', 'title', 'description', 'image_url', 'link_url', 'type', 'position', 'is_active', 'display_mode', 'start_date', 'end_date', 'show_on_mobile', 'show_on_desktop', 'background_color', 'text_color', 'button_text', 'button_link')
            ->where('is_active', true)
            ->where('type', strtoupper($type))
            ->where(function ($q) {
                $q->whereNull('start_date')->orWhere('start_date', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('end_date')->orWhere('end_date', '>=', now());
            });

        if ($device === 'mobile') {
            $query->where('show_on_mobile', true);
        } else {
            $query->where('show_on_desktop', true);
        }

        return $query->orderBy('position')->get();
    }

    /**
     * Toggle banner active status.
     */
    public function toggleActive(int|string $id): Banner
    {
        $banner = $this->findByIdOrFail($id);
        $banner->update(['is_active' => !$banner->is_active]);
        return $banner->fresh();
    }

    /**
     * Reorder banners by array of IDs.
     */
    public function reorder(array $bannerIds): void
    {
        DB::transaction(function () use ($bannerIds) {
            foreach ($bannerIds as $index => $id) {
                Banner::where('id', $id)->update(['position' => $index]);
            }
        });
    }

    /**
     * Find with pagination, filtering, and search.
     */
    public function findMany(array $params = []): array
    {
        $query = Banner::query();

        if (!empty($params['type'])) {
            $query->where('type', strtoupper($params['type']));
        }

        if (!empty($params['search'])) {
            $search = $params['search'];
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $perPage = $params['per_page'] ?? $params['limit'] ?? 20;
        $page = $params['page'] ?? 1;

        $paginator = $query->orderBy('position')->paginate($perPage, ['*'], 'page', $page);

        // Map snake_case DB fields to camelCase expected by frontend
        // Use array_merge instead of dynamic properties because Eloquent toArray() doesn't serialize dynamic props
        $banners = collect($paginator->items())->map(function ($banner) {
            return array_merge($banner->toArray(), [
                'isActive'    => $banner->is_active ?? true,
                'displayMode' => $banner->display_mode ?? 'DEFAULT',
                'imageUrl'    => $banner->image_url,
                'linkUrl'     => $banner->link_url,
            ]);
        })->toArray();

        return [
            'banners' => $banners,
            'page' => $paginator->currentPage(),
            'limit' => $paginator->perPage(),
            'total' => $paginator->total(),
            'total_pages' => $paginator->lastPage(),
        ];
    }
}
