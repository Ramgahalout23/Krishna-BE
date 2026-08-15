<?php

namespace App\Services;

use App\Models\Brand;
use App\Repositories\BrandRepository;
use App\Exceptions\AppError;
use App\Traits\CacheKeyRegistry;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class BrandService
{
    use CacheKeyRegistry;

    public function __construct(
        protected BrandRepository $brandRepository
    ) {}

    public function getAll(): array
    {
        return $this->cacheWithTracking('brands_all', 3600, function () {
            return Brand::select('id', 'name', 'slug', 'image_url')
                ->orderBy('name')
                ->get()
                ->toArray();
        });
    }

    public function getById(string $id): array
    {
        $cacheKey = 'brand_' . $id;
        return $this->cacheWithTracking($cacheKey, 3600, function () use ($id) {
            $brand = $this->brandRepository->findById($id);
            if (!$brand) throw AppError::notFound('Brand not found');
            return $brand->toArray();
        });
    }

    public function create(array $data): array
    {
        $data['slug'] = $data['slug'] ?? Str::slug($data['name']);
        $result = $this->brandRepository->create($data)->toArray();
        $this->clearTrackedCache();
        return $result;
    }

    public function update(string $id, array $data): array
    {
        $this->brandRepository->findByIdOrFail($id);
        $result = $this->brandRepository->update($id, $data)->toArray();
        $this->clearTrackedCache();
        return $result;
    }

    public function delete(string $id): void
    {
        $this->brandRepository->findByIdOrFail($id);
        $this->brandRepository->delete($id);
        $this->clearTrackedCache();
    }
}
