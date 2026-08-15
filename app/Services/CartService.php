<?php

namespace App\Services;

use App\Repositories\CartRepository;
use App\Exceptions\AppError;
use App\Models\CartItem;
use App\Models\Product;
use App\Repositories\ProductRepository;
use App\Models\ProductVariant;

class CartService
{
    public function __construct(
        protected CartRepository $cartRepository
    ) {}

    /**
     * Get cart — supports both authenticated users and guest sessions.
     */
    public function getCart(?string $userId = null, ?string $sessionId = null): array
    {
        $items = [];
        if ($userId) {
            $items = $this->cartRepository->getUserCart($userId);
        } elseif ($sessionId) {
            $items = $this->cartRepository->getCartBySession($sessionId);
        }

        $total = 0;
        $count = 0;
        $formattedItems = [];
        foreach ($items as $item) {
            $price = $item->product?->price ?? 0;
            $qty = $item->quantity ?? 1;
            $total += $price * $qty;
            $count += $qty;
            $formattedItems[] = [
                'id' => $item->id,
                'product_id' => $item->product_id,
                'product_name' => $item->product?->name,
                'name' => $item->product?->name,
                'quantity' => $qty,
                'size' => $item->size,
                'color' => $item->color,
                'variant_id' => $item->variant_id,
                'variantId' => $item->variant_id,
                'variantStock' => $item->variant?->quantity !== null ? (int) $item->variant->quantity : null,
                'price' => (float) $price,
                'total' => (float) $price * $qty,
                'imageUrl' => $item->product?->images?->first()?->url ?? null,
                'slug' => $item->product?->slug ?? null,
                'oldPrice' => $item->product?->old_price ? (float) $item->product->old_price : null,
            ];
        }

        return [
            'items' => $formattedItems,
            'total' => $total,
            'count' => $count,
        ];
    }

    /**
     * Add item — supports both authenticated users and guest sessions.
     */
    public function addItem(string $productId, int $quantity, ?string $userId = null, ?string $sessionId = null, ?string $size = null, ?string $color = null, ?string $variantId = null): array
    {
        if ($quantity < 1) throw AppError::validation('Quantity must be at least 1');

        // Validate product exists
        $product = Product::find($productId);
        if (!$product) throw AppError::notFound('Product not found');

        // Check stock: when a specific variant is selected, check its stock individually.
        // Otherwise, sum variant stock or fall back to base product stock.
        if ($variantId) {
            $variant = ProductVariant::find($variantId);
            if (!$variant) throw AppError::notFound('Variant not found');
            if ((int) $variant->quantity < $quantity) {
                throw AppError::validation('Not enough stock available for this variant');
            }
        } else {
            $totalVariantStock = (int) ProductVariant::where('product_id', $productId)->sum('quantity');
            if ($totalVariantStock > 0) {
                if ($totalVariantStock < $quantity) {
                    throw AppError::validation('Not enough stock available across variants');
                }
            } elseif ($product->quantity < $quantity) {
                throw AppError::validation('Not enough stock available');
            }
        }

        $item = $this->cartRepository->addOrUpdateItem($userId, $productId, $quantity, $sessionId, $size, $color, $variantId);

        return [
            'id' => $item->id,
            'product_id' => $item->product_id,
            'quantity' => $item->quantity,
            'size' => $item->size,
            'color' => $item->color,
            'variant_id' => $item->variant_id,
            'price' => (float) ($item->price ?? $product->price),
            'total' => (float) ($item->price ?? $product->price) * $item->quantity,
        ];
    }

    public function updateItem(string $itemId, int $quantity): array
    {
        if ($quantity <= 0) throw AppError::validation('Quantity must be greater than 0');

        $item = $this->cartRepository->findItemById($itemId);
        if (!$item) throw AppError::notFound('Cart item not found');

        // Check stock availability before updating quantity
        // Eager load variant if variant_id is set to avoid a separate query
        if ($item->variant_id && !$item->relationLoaded('variant')) {
            $item->load('variant');
        }
        $product = $item->product;
        if ($product) {
            if ($item->variant_id) {
                $variant = $item->variant ?? ProductVariant::find($item->variant_id);
                if ($variant && $variant->quantity < $quantity) {
                    throw AppError::validation(
                        "Only {$variant->quantity} units available for this variant"
                    );
                }
            } elseif ($product->quantity < $quantity) {
                throw AppError::validation(
                    "Only {$product->quantity} units available for {$product->name}"
                );
            }
        }

        $updated = $this->cartRepository->updateItemQuantity($itemId, $quantity);

        return [
            'id' => $updated->id,
            'quantity' => $updated->quantity,
            'total' => (float) ($updated->price ?? 0) * $updated->quantity,
        ];
    }

    public function updateItemByProduct(string $userId, string $productId, int $quantity): array
    {
        if ($quantity <= 0) throw AppError::validation('Quantity must be greater than 0');

        $item = $this->cartRepository->findByUserAndProduct($userId, $productId);
        if (!$item) throw AppError::notFound('Cart item not found');

        // Check stock availability before updating quantity — preload variant + product together
        $item->loadMissing(['variant', 'product']);
        $product = $item->product;
        if ($product) {
            if ($item->variant_id) {
                $variant = $item->variant;
                if ($variant && $variant->quantity < $quantity) {
                    throw AppError::validation(
                        "Only {$variant->quantity} units available for this variant"
                    );
                }
            } elseif ($product->quantity < $quantity) {
                throw AppError::validation(
                    "Only {$product->quantity} units available for {$product->name}"
                );
            }
        }

        $this->cartRepository->updateItemQuantity($item->id, $quantity);
        $updated = $item->fresh();

        return [
            'id' => $updated->id,
            'quantity' => $updated->quantity,
            'total' => (float) ($updated->price ?? 0) * $updated->quantity,
        ];
    }

    public function removeItem(string $itemId): array
    {
        $item = $this->cartRepository->findItemById($itemId);
        if (!$item) throw AppError::notFound('Cart item not found');

        $this->cartRepository->removeItemById($itemId);
        return ['message' => 'Item removed from cart'];
    }

    public function removeItemByProduct(string $userId, string $productId): array
    {
        $deleted = $this->cartRepository->removeItem($userId, $productId);
        if (!$deleted) throw AppError::notFound('Cart item not found');
        return ['message' => 'Item removed from cart'];
    }

    public function clearCart(string $userId): array
    {
        $this->cartRepository->clearCart($userId);
        return ['message' => 'Cart cleared'];
    }

    public function validateCart(string $userId): array
    {
        $items = $this->cartRepository->getUserCart($userId);
        $errors = [];

        foreach ($items as $item) {
            if (!$item->product || $item->product->status !== 'PUBLISHED') {
                $name = $item->product ? $item->product->name : 'Product';
                $errors[] = "{$name} is no longer available";
                continue;
            }

            // When the cart item has a variant, check variant-level stock
            if ($item->variant_id) {
                $variant = $item->variant ?? ProductVariant::find($item->variant_id);
                if ($variant && $variant->quantity < $item->quantity) {
                    $variantLabel = trim("{$item->size} {$item->color}");
                    $errors[] = "{$item->product->name}" . ($variantLabel ? " ({$variantLabel})" : "") . " has only {$variant->quantity} units available";
                }
            } elseif ($item->product->quantity < $item->quantity) {
                $errors[] = "{$item->product->name} has only {$item->product->quantity} in stock";
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'count' => $this->cartRepository->getCartCount($userId),
        ];
    }

    /**
     * Save an item for later (move to saved list).
     */
    public function saveForLater(string $userId, string $productId): array
    {
        $product = Product::find($productId);
        if (!$product) throw AppError::notFound('Product not found');

        $this->cartRepository->saveForLater($userId, $productId);
        return ['message' => 'Item saved for later', 'product_id' => $productId];
    }

    /**
     * Get abandoned cart recovery info.
     */
    public function getAbandonedCartRecovery(string $userId): array
    {
        $cart = $this->getCart($userId);

        return [
            'cart' => $cart,
            'recovery_message' => 'Your cart was saved. Continue shopping?',
            'expires_in' => '7 days',
        ];
    }

    /**
     * Get recently viewed products.
     */
    public function getRecentlyViewed(string $userId, int $limit = 10): array
    {
        $items = $this->cartRepository->getRecentlyViewed($userId, $limit);

        return $items->map(fn($item) => [
            'id' => $item->product_id,
            'name' => $item->product?->name,
            'price' => $item->product?->price,
            'image' => $item->product?->images?->first()?->url ?? null,
            'viewed_at' => $item->viewed_at,
        ])->toArray();
    }

    /**
     * Record a product view.
     */
    public function recordProductView(string $userId, string $productId): void
    {
        $this->cartRepository->addRecentlyViewed($userId, $productId);
    }

    /**
     * Get cart recommendations (upsell/cross-sell based on same categories).
     */
    public function getCartRecommendations(string $userId): array
    {
        $cart = $this->getCart($userId);
        if (empty($cart['items'])) return [];

        $cartProductIds = array_map(fn($i) => $i['product_id'] ?? $i['id'], $cart['items']);

        // Get categories of cart products
        $cartProducts = Product::whereIn('id', $cartProductIds)->pluck('category_id')->unique()->filter()->values()->toArray();

        if (empty($cartProducts)) return [];

        $products = Product::whereIn('category_id', $cartProducts)
            ->whereNotIn('id', $cartProductIds)
            ->where('id', '!=', ProductRepository::CUSTOM_TEE_PRODUCT_ID)
            ->take(5)
            ->get(['id', 'name', 'price']);

        return $products->map(fn($p) => [
            'product_id' => $p->id,
            'product_name' => $p->name,
            'price' => (float) $p->price,
            'reason' => 'similar',
        ])->toArray();
    }

    /**
     * Merge guest cart (session) with user cart on login.
     */
    public function mergeCart(string $userId, string $sessionId): array
    {
        $this->cartRepository->mergeGuestCart($userId, $sessionId);
        return $this->getCart($userId);
    }
}
