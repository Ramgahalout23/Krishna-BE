<?php

namespace App\Services;

use App\Repositories\PromotionRepository;
use App\Exceptions\AppError;

class PromotionService
{
    // Snake_case DB fields that the frontend expects in camelCase
    private const CAMEL_CASE_MAP = [
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
        'offer_badge'     => 'offerBadge',
        'offer_highlight' => 'offerHighlight',
        'offer_tagline'   => 'offerTagline',
        'offer_theme'     => 'offerTheme',
        'auto_apply'      => 'autoApply',
        'created_by'      => 'createdBy',
    ];

    public function __construct(
        protected PromotionRepository $promotionRepository
    ) {}

    /**
     * Convert a single promotion array from snake_case to camelCase for the frontend.
     * Preserves all original keys and adds camelCase variants.
     */
    private function toCamelCase(array $promotion): array
    {
        $mapped = $promotion;
        foreach (self::CAMEL_CASE_MAP as $snake => $camel) {
            if (array_key_exists($snake, $promotion)) {
                $mapped[$camel] = $promotion[$snake];
            }
        }
        return $mapped;
    }

    /**
     * Convert a collection/model toArray result (array of promotion arrays) to camelCase.
     */
    private function collectionToCamelCase(array $promotions): array
    {
        return array_map(fn(array $p) => $this->toCamelCase($p), $promotions);
    }

    public function getActive(): array
    {
        return $this->collectionToCamelCase($this->promotionRepository->getActive()->toArray());
    }

    public function getAll(): array
    {
        return $this->collectionToCamelCase($this->promotionRepository->all()->toArray());
    }

    public function getById(string $id): array
    {
        $promotion = $this->promotionRepository->findById($id);
        if (!$promotion) throw AppError::notFound('Promotion not found');
        return $this->toCamelCase($promotion->toArray());
    }

    public function create(array $data): array
    {
        return $this->toCamelCase($this->promotionRepository->create($data)->toArray());
    }

    public function createWithRelations(array $data, array $productIds = [], array $categoryIds = []): array
    {
        $promotion = $this->promotionRepository->createWithRelations($data, $productIds, $categoryIds);
        return $this->toCamelCase($promotion->toArray());
    }

    public function updateWithRelations(string $id, array $data, ?array $productIds = null, ?array $categoryIds = null): array
    {
        $promotion = $this->promotionRepository->updateWithRelations($id, $data, $productIds, $categoryIds);
        return $this->toCamelCase($promotion->toArray());
    }

    public function update(string $id, array $data): array
    {
        $this->promotionRepository->findByIdOrFail($id);
        return $this->toCamelCase($this->promotionRepository->update($id, $data)->toArray());
    }

    public function delete(string $id): void
    {
        $this->promotionRepository->findByIdOrFail($id);
        $this->promotionRepository->delete($id);
    }

    /**
     * Get all promotions with filters and pagination.
     */
    public function getAllPromotions(array $filters = [], int $page = 1, int $limit = 20): array
    {
        return $this->promotionRepository->findAll($filters, $page, $limit);
    }

    /**
     * Find promotion by coupon code.
     */
    public function findByCouponCode(string $code): array
    {
        $promotion = $this->promotionRepository->findByCouponCode($code);
        if (!$promotion) throw AppError::notFound('Promotion not found for this coupon code');
        return $this->toCamelCase($promotion->toArray());
    }

    /**
     * Find promotions by type.
     */
    public function findByType(string $type): array
    {
        return $this->collectionToCamelCase($this->promotionRepository->findByType($type)->toArray());
    }

    /**
     * Update promotion status.
     */
    public function updateStatus(string $id, string $status): array
    {
        $this->promotionRepository->findByIdOrFail($id);
        $promotion = $this->promotionRepository->updateStatus($id, strtoupper($status));
        return $this->toCamelCase($promotion->toArray());
    }
}
