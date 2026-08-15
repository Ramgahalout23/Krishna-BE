<?php

namespace App\Services;

use App\Repositories\ProductVariantRepository;
use App\Exceptions\AppError;
use App\Traits\CacheKeyRegistry;
use Illuminate\Support\Facades\Cache;

class ProductVariantService
{
    use CacheKeyRegistry;

    public function __construct(
        protected ProductVariantRepository $variantRepository
    ) {}

    public function getByProduct(string $productId): array
    {
        return $this->cacheWithTracking("product_variants_{$productId}", 3600, function () use ($productId) {
            return $this->variantRepository->findByProduct($productId)->toArray();
        });
    }

    public function getById(string $id): array
    {
        $variant = $this->variantRepository->findById($id);
        if (!$variant) throw AppError::notFound('Variant not found');
        return $variant->toArray();
    }

    public function create(string $productId, array $data): array
    {
        $data['product_id'] = $productId;
        $variant = $this->variantRepository->create($data);
        $this->clearTrackedCache();
        return $variant->toArray();
    }

    public function update(string $id, array $data): array
    {
        $variant = $this->variantRepository->findByIdOrFail($id);
        $result = $this->variantRepository->update($id, $data)->toArray();
        $this->clearTrackedCache();
        return $result;
    }

    public function delete(string $id): void
    {
        $variant = $this->variantRepository->findByIdOrFail($id);
        $this->variantRepository->delete($id);
        $this->clearTrackedCache();
    }

    public function getLowStock(int $threshold = 5): array
    {
        return $this->variantRepository->getLowStock($threshold)->toArray();
    }
}
