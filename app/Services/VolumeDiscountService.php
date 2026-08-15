<?php

namespace App\Services;

/**
 * VolumeDiscountService — Calculates tiered volume/bundle discounts
 * based on item quantities.
 *
 * Tier structure (matching the frontend BundleOffer component):
 *   Buy 1   → 0% off
 *   Buy 2   → 5% off (each)
 *   Buy 3   → 10% off (each)
 *   Buy 4+  → 15% off (each)
 *
 * Only the highest applicable tier is used per item.
 * Discounts are per-item subtotal (price × quantity × tier%).
 */
class VolumeDiscountService
{
    /**
     * Default tier definitions.
     */
    public const TIERS = [
        ['minQty' => 1, 'label' => 'Buy 1',  'discount' => 0],
        ['minQty' => 2, 'label' => 'Buy 2',  'discount' => 5],
        ['minQty' => 3, 'label' => 'Buy 3',  'discount' => 10],
        ['minQty' => 4, 'label' => 'Buy 4+', 'discount' => 15],
    ];

    /**
     * Calculate volume discounts for a set of cart/checkout items.
     *
     * Each item should have: product_id (or productId), quantity, price.
     *
     * @param array $items  Array of items with at least ['product_id' => string, 'quantity' => int, 'price' => float]
     * @return array{total_discount: float, items_discount: array, applied_tiers: array}
     */
    public function calculateDiscount(array $items): array
    {
        if (empty($items)) {
            return [
                'total_discount'   => 0.0,
                'items_discount'   => [],
                'applied_tiers'    => [],
            ];
        }

        $itemsDiscount = [];
        $totalDiscount = 0.0;
        $appliedTiers  = [];

        foreach ($items as $item) {
            $productId = $item['product_id'] ?? $item['productId'] ?? null;
            $quantity  = max(1, (int) ($item['quantity'] ?? 1));
            $price     = (float) ($item['price'] ?? 0);

            if (!$productId || $price <= 0) {
                continue;
            }

            $itemSubtotal = $price * $quantity;
            $tier = $this->getBestTier($quantity);

            if ($tier && $tier['discount'] > 0) {
                $discountAmount = $itemSubtotal * ($tier['discount'] / 100);
                $discountAmount = round($discountAmount, 2);

                $itemsDiscount[] = [
                    'productId'         => $productId,
                    'productName'       => $item['product_name'] ?? $item['name'] ?? 'Product',
                    'quantity'          => $quantity,
                    'price'             => $price,
                    'subtotal'          => $itemSubtotal,
                    'tierMinQty'        => $tier['minQty'],
                    'tierDiscountPct'   => $tier['discount'],
                    'discount'          => $discountAmount,
                    'discountedPrice'   => round($price * (1 - $tier['discount'] / 100), 2),
                    'discountedSubtotal'=> round($itemSubtotal - $discountAmount, 2),
                ];

                $totalDiscount += $discountAmount;

                $tierKey = $tier['minQty'];
                if (!isset($appliedTiers[$tierKey])) {
                    $appliedTiers[$tierKey] = [
                        'minQty'   => $tier['minQty'],
                        'label'    => $tier['label'],
                        'discount' => $tier['discount'],
                    ];
                }
            }
        }

        return [
            'total_discount' => round($totalDiscount, 2),
            'items_discount' => $itemsDiscount,
            'applied_tiers'  => array_values($appliedTiers),
        ];
    }

    /**
     * Find the best (highest) tier that applies to a given quantity.
     * Tiers are ordered by minQty ascending — the last applicable tier wins.
     */
    public function getBestTier(int $quantity): ?array
    {
        $best = null;
        foreach (self::TIERS as $tier) {
            if ($quantity >= $tier['minQty']) {
                $best = $tier;
            }
        }
        return $best;
    }
}
