<?php

namespace App\Services;

use App\Repositories\PromotionRepository;

class FlashSaleService
{
    private const CAMEL_CASE_MAP = [
        'image_url'       => 'imageUrl',
        'link_url'        => 'linkUrl',
        'start_date'      => 'startDate',
        'end_date'        => 'endDate',
        'is_active'       => 'isActive',
        'min_purchase'    => 'minPurchase',
        'max_discount'    => 'maxDiscount',
        'coupon_code'     => 'couponCode',
    ];

    public function __construct(
        protected PromotionRepository $promotionRepository
    ) {}

    /**
     * Get applicable flash sale and store-wide discounts for a set of cart items.
     *
     * Also handles store-wide offers (promotions with offer_badge/offer_highlight/offer_tagline
     * but NO linked products or categories). These apply to every item in the cart.
     *
     * Each item should have: product_id, quantity, price, and optionally category_id.
     * Returns structured data with per-item breakdown and total discount.
     *
     * @param array $items  Array of items with at least ['product_id' => string, 'quantity' => int, 'price' => float]
     * @return array{items_discount: array, total_discount: float, flash_sales: array}
     */
    public function getApplicableDiscounts(array $items): array
    {
        $promotions = $this->promotionRepository->getActive();

        if ($promotions->isEmpty() || empty($items)) {
            return [
                'items_discount' => [],
                'total_discount' => 0.0,
                'flash_sales' => [],
            ];
        }

        // Build lookup maps from the eager-loaded relationships
        $productPromotions = [];  // productId => [promotion, ...]
        $categoryPromotions = []; // categoryId => [promotion, ...]
        $storeWidePromotions = []; // Promotions with no product/category links (apply to all)

        foreach ($promotions as $promotion) {
            // Check if this promotion has a discount that can be applied
            $discountValue = (float) ($promotion->discount ?? 0);
            if ($discountValue <= 0 || !$promotion->is_active) {
                continue;
            }

            $promoData = [
                'id' => $promotion->id,
                'title' => $promotion->title,
                'type' => $promotion->type,
                'discount' => $discountValue,
                'maxDiscount' => (float) ($promotion->max_discount ?? 0),
                'minPurchase' => (float) ($promotion->min_purchase ?? 0),
                'startDate' => $promotion->start_date?->toIso8601String(),
                'endDate' => $promotion->end_date?->toIso8601String(),
                'offerBadge' => $promotion->offer_badge,
                'offerHighlight' => $promotion->offer_highlight,
                'offerTagline' => $promotion->offer_tagline,
            ];

            $hasProducts = $promotion->products->isNotEmpty();
            $hasCategories = $promotion->categories->isNotEmpty();

            if (!$hasProducts && !$hasCategories) {
                // Store-wide auto-apply offer — no specific product/category links.
                // Only applies if admin has enabled the auto_apply toggle.
                if (!empty($promotion->auto_apply)) {
                    $storeWidePromotions[] = $promoData;
                }
                continue;
            }

            // Map product associations (for flash sales with linked products)
            foreach ($promotion->products as $product) {
                $productPromotions[$product->id][] = $promoData;
            }

            // Map category associations
            foreach ($promotion->categories as $category) {
                $categoryPromotions[$category->id][] = $promoData;
            }
        }

        $itemsDiscount = [];
        $totalDiscount = 0.0;
        $appliedFlashSales = [];

        foreach ($items as $item) {
            $productId = $item['product_id'] ?? $item['productId'] ?? null;
            $categoryId = $item['category_id'] ?? $item['categoryId'] ?? null;
            $quantity = (int) ($item['quantity'] ?? 1);
            $price = (float) ($item['price'] ?? 0);
            $itemSubtotal = $price * $quantity;

            if (!$productId || $price <= 0) {
                continue;
            }

            // Collect all matching promotions for this item
            $matchingPromos = [];

            // Direct product match
            if (isset($productPromotions[$productId])) {
                foreach ($productPromotions[$productId] as $promo) {
                    if (!isset($matchingPromos[$promo['id']])) {
                        $matchingPromos[$promo['id']] = $promo + ['matchType' => 'product'];
                    }
                }
            }

            // Category match
            if ($categoryId && isset($categoryPromotions[$categoryId])) {
                foreach ($categoryPromotions[$categoryId] as $promo) {
                    if (!isset($matchingPromos[$promo['id']])) {
                        $matchingPromos[$promo['id']] = $promo + ['matchType' => 'category'];
                    }
                }
            }

            // Store-wide offers apply to ALL items
            foreach ($storeWidePromotions as $promo) {
                if (!isset($matchingPromos[$promo['id']])) {
                    $matchingPromos[$promo['id']] = $promo + ['matchType' => 'store-wide'];
                }
            }

            if (empty($matchingPromos)) {
                continue;
            }

            // Use the best (highest discount) promotion for this item
            $bestPromo = null;
            $bestDiscount = 0;

            foreach ($matchingPromos as $promo) {
                $discount = $this->calculateItemDiscount($itemSubtotal, $promo);
                if ($discount > $bestDiscount) {
                    $bestDiscount = $discount;
                    $bestPromo = $promo;
                }
            }

            if ($bestDiscount > 0 && $bestPromo) {
                $itemsDiscount[] = [
                    'productId' => $productId,
                    'productName' => $item['product_name'] ?? $item['name'] ?? 'Product',
                    'quantity' => $quantity,
                    'price' => $price,
                    'subtotal' => $itemSubtotal,
                    'discount' => $bestDiscount,
                    'discountedSubtotal' => max(0, $itemSubtotal - $bestDiscount),
                    'promotionId' => $bestPromo['id'],
                    'promotionTitle' => $bestPromo['title'],
                    'promotionType' => $bestPromo['type'],
                    'discountLabel' => $this->formatDiscountLabel($bestPromo),
                    'matchType' => $bestPromo['matchType'],
                ];

                $totalDiscount += $bestDiscount;

                // Track which promotions were used
                if (!isset($appliedFlashSales[$bestPromo['id']])) {
                    $appliedFlashSales[$bestPromo['id']] = [
                        'id' => $bestPromo['id'],
                        'title' => $bestPromo['title'],
                        'discountLabel' => $this->formatDiscountLabel($bestPromo),
                        'offerBadge' => $bestPromo['offerBadge'] ?? null,
                        'offerHighlight' => $bestPromo['offerHighlight'] ?? null,
                        'offerTagline' => $bestPromo['offerTagline'] ?? null,
                    ];
                }
            }
        }

        return [
            'items_discount' => $itemsDiscount,
            'total_discount' => round($totalDiscount, 2),
            'flash_sales' => array_values($appliedFlashSales),
        ];
    }

    /**
     * Calculate the discount amount for a single item against a promotion.
     *
     * Supports PERCENTAGE, FLASH_SALE, SEASONAL (all treated as percentage-off),
     * and FIXED discount types.
     */
    private function calculateItemDiscount(float $itemSubtotal, array $promotion): float
    {
        $type = $promotion['type'] ?? 'PERCENTAGE';
        $discountValue = (float) ($promotion['discount'] ?? 0);
        $maxDiscount = (float) ($promotion['maxDiscount'] ?? 0);
        $minPurchase = (float) ($promotion['minPurchase'] ?? 0);

        // Skip if item subtotal doesn't meet minimum purchase requirement
        if ($minPurchase > 0 && $itemSubtotal < $minPurchase) {
            return 0;
        }

        $discount = 0;

        // SEASONAL, PERCENTAGE, and FLASH_SALE are all treated as percentage-off
        if (in_array(strtoupper($type), ['PERCENTAGE', 'FLASH_SALE', 'SEASONAL', 'PRODUCT_LAUNCH', 'NEWSLETTER', 'LOYALTY_REWARD'], true)) {
            // Percentage-off discount
            $discount = $itemSubtotal * ($discountValue / 100);
        } elseif (strtoupper($type) === 'FIXED') {
            // Fixed discount
            $discount = $discountValue;
        } else {
            // Unknown type — cannot calculate discount
            return 0;
        }

        // Apply maximum discount cap
        if ($maxDiscount > 0 && $discount > $maxDiscount) {
            $discount = $maxDiscount;
        }

        // Never exceed the item subtotal
        return min($discount, $itemSubtotal);
    }

    /**
     * Format a human-readable discount label.
     */
    private function formatDiscountLabel(array $promotion): string
    {
        $type = $promotion['type'] ?? 'PERCENTAGE';
        $value = (float) ($promotion['discount'] ?? 0);

        if (strtoupper($type) === 'PERCENTAGE' || strtoupper($type) === 'FLASH_SALE') {
            return "{$value}% OFF";
        }

        return '$' . number_format($value, 2) . ' OFF';
    }
}
