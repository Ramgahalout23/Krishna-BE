<?php

namespace App\Services;

use App\Repositories\CartRepository;
use App\Repositories\OrderRepository;
use App\Repositories\CouponRepository;
use App\Exceptions\AppError;
use Illuminate\Support\Str;

class CheckoutService
{
    public function __construct(
        protected CartRepository $cartRepository,
        protected OrderRepository $orderRepository,
        protected CouponRepository $couponRepository,
        protected SettingsService $settingsService,
        protected FlashSaleService $flashSaleService
    ) {}

    protected function getTaxRate(): float
    {
        return (float) ($this->settingsService->get('taxRate', '10.0')) / 100;
    }

    protected function getFreeShippingThreshold(): float
    {
        return (float) ($this->settingsService->get('freeShippingThreshold', '100'));
    }

    protected function getStandardShippingCost(): float
    {
        return (float) ($this->settingsService->get('shippingFlatRate', '10'));
    }

    public function getSummary(string $userId): array
    {
        $items = $this->cartRepository->getUserCart($userId);
        $subtotal = $items->sum(fn($item) => ($item->product?->price ?? 0) * $item->quantity);
        $taxRate = $this->getTaxRate();
        $tax = $subtotal * $taxRate;
        $freeShippingThreshold = $this->getFreeShippingThreshold();
        $standardShippingCost = $this->getStandardShippingCost();
        $shipping = $subtotal >= $freeShippingThreshold ? 0 : $standardShippingCost;

        $itemsArray = $items->load('product.images')->toArray();

        // Map flat imageUrl onto each item for consistency with order endpoints
        foreach ($itemsArray as &$item) {
            $item['imageUrl'] = $item['product']['images'][0]['url'] ?? $item['image_url'] ?? null;
        }
        unset($item);

        // Build plain items array for flash sale matching
        $plainItems = $itemsArray;
        foreach ($plainItems as &$plainItem) {
            $plainItem['product_id'] = $plainItem['product']['id'] ?? $plainItem['product_id'] ?? null;
            $plainItem['category_id'] = $plainItem['product']['category_id'] ?? null;
            $plainItem['price'] = $plainItem['product']['price'] ?? $plainItem['price'] ?? 0;
        }
        unset($plainItem);

        $flashSaleDiscounts = $this->flashSaleService->getApplicableDiscounts($plainItems);

        return [
            'items' => $itemsArray,
            'subtotal' => $subtotal,
            'tax' => $tax,
            'shipping' => $shipping,
            'flashSaleDiscount' => $flashSaleDiscounts['total_discount'],
            'flashSaleDiscountItems' => $flashSaleDiscounts['items_discount'],
            'flashSalePromotions' => $flashSaleDiscounts['flash_sales'],
            'total' => $subtotal + $tax + $shipping - $flashSaleDiscounts['total_discount'],
            'item_count' => $items->sum('quantity'),
        ];
    }

    public function calculateShipping(string $userId): array
    {
        $items = $this->cartRepository->getUserCart($userId);
        $subtotal = $items->sum(fn($item) => ($item->product?->price ?? 0) * $item->quantity);
        $freeShippingThreshold = $this->getFreeShippingThreshold();
        $standardShippingCost = $this->getStandardShippingCost();
        $standardCost = $subtotal >= $freeShippingThreshold ? 0 : $standardShippingCost;
        $expressCost = $subtotal >= $freeShippingThreshold ? 0 : $standardShippingCost + 5;

        return [
            'standard' => ['cost' => $standardCost, 'estimated_days' => '5-7'],
            'express' => ['cost' => $expressCost, 'estimated_days' => '2-3'],
            'free_threshold' => $freeShippingThreshold,
            'current_subtotal' => $subtotal,
            'free_shipping_remaining' => max(0, $freeShippingThreshold - $subtotal),
        ];
    }

    public function applyCoupon(string $code, float $subtotal): array
    {
        $coupon = $this->couponRepository->findActiveByCode($code);
        if (!$coupon) throw AppError::validation('Invalid or expired coupon');

        $discount = 0;
        if ($coupon->type === 'PERCENTAGE') {
            $discount = $subtotal * ($coupon->discount_value / 100);
            if ($coupon->max_discount) {
                $discount = min($discount, $coupon->max_discount);
            }
        } elseif ($coupon->type === 'FIXED') {
            $discount = $coupon->discount_value;
        }

        return [
            'coupon' => $coupon->toArray(),
            'discount' => $discount,
            'new_total' => $subtotal - $discount,
        ];
    }

    /**
     * Initiate a checkout session with cart items.
     */
    public function initiateCheckout(string $userId): array
    {
        $items = $this->cartRepository->getUserCart($userId);
        if ($items->isEmpty()) {
            throw AppError::validation('Cart is empty');
        }

        $subtotal = $items->sum(fn($item) => ($item->product?->price ?? 0) * $item->quantity);

        $itemsArray = $items->load('product.images')->toArray();

        // Map flat imageUrl onto each item for consistency
        foreach ($itemsArray as &$item) {
            $item['imageUrl'] = $item['product']['images'][0]['url'] ?? $item['image_url'] ?? null;
        }
        unset($item);

        return [
            'session_id' => (string) Str::uuid(),
            'items' => $itemsArray,
            'subtotal' => $subtotal,
            'status' => 'INITIATED',
        ];
    }

    /**
     * Calculate total from subtotal, tax, shipping, and discount.
     */
    public function calculateTotal(float $subtotal, float $tax = 0, float $shipping = 0, float $discount = 0): float
    {
        return max(0, $subtotal + $tax + $shipping - $discount);
    }

    /**
     * Process the checkout (prepare for payment).
     */
    public function processCheckout(array $sessionData): array
    {
        $total = $this->calculateTotal(
            $sessionData['subtotal'] ?? 0,
            $sessionData['tax'] ?? 0,
            $sessionData['shipping_cost'] ?? 0,
            $sessionData['discount'] ?? 0
        );

        return array_merge($sessionData, [
            'total' => $total,
            'status' => 'READY_FOR_PAYMENT',
        ]);
    }

    public function removeCoupon(): array
    {
        return ['message' => 'Coupon removed'];
    }
}
