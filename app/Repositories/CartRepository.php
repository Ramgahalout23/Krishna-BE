<?php

namespace App\Repositories;

use App\Models\CartItem;
use App\Models\Product;
use App\Models\RecentlyViewedProduct;
use App\Repositories\ProductRepository;
use App\Models\CartRecommendation;
use Illuminate\Database\Eloquent\Collection;

class CartRepository extends BaseRepository
{
    protected function modelClass(): string
    {
        return CartItem::class;
    }

    public function getUserCart(string $userId): Collection
    {
        return CartItem::with([
            'product:id,name,slug,price,quantity,old_price,status',
            'product.images:id,product_id,url',
            'variant:id,product_id,name,price,quantity',
        ])
            ->where('user_id', $userId)
            ->where('saved_for_later', false)
            ->get();
    }

    /**
     * Get cart by session ID (guest cart support).
     */
    public function getCartBySession(string $sessionId): Collection
    {
        return CartItem::with([
            'product:id,name,slug,price,quantity,old_price,status',
            'product.images:id,product_id,url',
            'variant:id,product_id,name,price,quantity',
        ])
            ->where('session_id', $sessionId)
            ->whereNull('user_id')
            ->get();
    }

    public function getUserCartItem(string $userId, string $productId): ?CartItem
    {
        return CartItem::where('user_id', $userId)
            ->where('product_id', $productId)
            ->first();
    }

    public function addOrUpdateItem(?string $userId, string $productId, int $quantity, ?string $sessionId = null, ?string $size = null, ?string $color = null, ?string $variantId = null): CartItem
    {
        $query = CartItem::where('product_id', $productId);

        // Match by size and color so same product with different variants = different cart items
        if ($size) {
            $query->where('size', $size);
        } else {
            $query->whereNull('size');
        }
        if ($color) {
            $query->where('color', $color);
        } else {
            $query->whereNull('color');
        }

        if ($userId) {
            $query->where('user_id', $userId);
        } elseif ($sessionId) {
            $query->where('session_id', $sessionId)->whereNull('user_id');
        }

        $item = $query->first();
        $product = Product::find($productId);

        if ($item) {
            $item->update(['quantity' => $quantity]);
            return $item->fresh();
        }

        $data = [
            'product_id' => $productId,
            'quantity' => $quantity,
            'price' => $product?->price ?? 0,
        ];

        if ($size) $data['size'] = $size;
        if ($color) $data['color'] = $color;
        if ($variantId) $data['variant_id'] = $variantId;

        if ($userId) {
            $data['user_id'] = $userId;
        } elseif ($sessionId) {
            $data['session_id'] = $sessionId;
        }

        return CartItem::create($data);
    }

    public function findItemById(string $itemId): ?CartItem
    {
        return CartItem::with([
            'product:id,name,slug,price,quantity,old_price,status',
            'product.images:id,product_id,url',
            'variant:id,product_id,name,price,quantity',
        ])->find($itemId);
    }

    public function findByUserAndProduct(string $userId, string $productId): ?CartItem
    {
        return CartItem::with([
            'product:id,name,slug,price,quantity,old_price,status',
            'variant:id,product_id,name,price,quantity',
        ])
            ->where('user_id', $userId)
            ->where('product_id', $productId)
            ->first();
    }

    public function updateItemQuantity(string $itemId, int $quantity): CartItem
    {
        $item = CartItem::findOrFail($itemId);
        $item->update(['quantity' => $quantity]);
        return $item->fresh();
    }

    public function removeItemById(string $itemId): bool
    {
        return CartItem::where('id', $itemId)->delete() > 0;
    }

    public function removeItem(string $userId, string $productId): bool
    {
        return CartItem::where('user_id', $userId)
            ->where('product_id', $productId)
            ->delete() > 0;
    }

    public function clearCart(string $userId): void
    {
        CartItem::where('user_id', $userId)->delete();
    }

    public function getCartCount(string $userId): int
    {
        return CartItem::where('user_id', $userId)->sum('quantity');
    }

    public function validateStock(string $productId, int $quantity): bool
    {
        $product = Product::find($productId);
        return $product && $product->quantity >= $quantity;
    }

    /**
     * Save item for later (move from cart to saved-for-later).
     */
    public function saveForLater(string $userId, string $productId): void
    {
        CartItem::where('user_id', $userId)
            ->where('product_id', $productId)
            ->update(['saved_for_later' => true]);
    }

    /**
     * Get recently viewed products for a user.
     */
    public function getRecentlyViewed(string $userId, int $limit = 10): Collection
    {
        return RecentlyViewedProduct::with('product.images')
            ->where('user_id', $userId)
            ->latest('viewed_at')
            ->take($limit)
            ->get();
    }

    /**
     * Add a product view record.
     */
    public function addRecentlyViewed(string $userId, string $productId): void
    {
        RecentlyViewedProduct::updateOrCreate(
            ['user_id' => $userId, 'product_id' => $productId],
            ['viewed_at' => now()]
        );
    }

    /**
     * Get cart recommendations based on similar categories.
     */
    public function getRecommendations(string $userId, array $cartProductIds, array $categoryIds, int $limit = 5): Collection
    {
        if (empty($categoryIds)) {
            return collect([]);
        }

        return Product::whereNotIn('id', $cartProductIds)
            ->whereIn('category_id', $categoryIds)
            ->where('status', 'PUBLISHED')
            ->where('id', '!=', ProductRepository::CUSTOM_TEE_PRODUCT_ID)
            ->take($limit)
            ->get(['id', 'name', 'price', 'slug']);
    }

    /**
     * Merge guest cart (by session) into user cart.
     */
    public function mergeGuestCart(string $userId, string $sessionId): void
    {
        $guestItems = CartItem::where('session_id', $sessionId)->whereNull('user_id')->get();
        if ($guestItems->isEmpty()) return;

        // Fetch all existing user cart items for these product IDs in one query
        $productIds = $guestItems->pluck('product_id')->toArray();
        $existingItems = CartItem::where('user_id', $userId)
            ->whereIn('product_id', $productIds)
            ->get()
            ->keyBy('product_id');

        $toDelete = [];
        $toUpdate = [];
        $toReassign = [];

        foreach ($guestItems as $item) {
            $existing = $existingItems->get($item->product_id);

            if ($existing) {
                $toDelete[] = $item->id;
                $toUpdate[] = [
                    'id' => $existing->id,
                    'quantity' => $existing->quantity + $item->quantity,
                ];
            } else {
                $toReassign[] = $item->id;
            }
        }

        // Bulk update quantities
        foreach ($toUpdate as $update) {
            CartItem::where('id', $update['id'])->update(['quantity' => $update['quantity']]);
        }

        // Bulk delete conflicting guest items
        if (!empty($toDelete)) {
            CartItem::whereIn('id', $toDelete)->delete();
        }

        // Bulk reassign unmatched guest items to user
        if (!empty($toReassign)) {
            CartItem::whereIn('id', $toReassign)->update([
                'user_id' => $userId,
                'session_id' => null,
            ]);
        }
    }

    public function getCartTotal(string $userId): array
    {
        $items = CartItem::with([
            'product:id,name,slug,price,quantity,old_price,status',
            'product.images:id,product_id,url',
        ])
            ->where('user_id', $userId)
            ->get();

        $subtotal = $items->sum(fn($item) => (float) $item->price * $item->quantity);
        $totalItems = $items->sum('quantity');

        return [
            'subtotal' => $subtotal,
            'total_items' => $totalItems,
            'items' => $items->toArray(),
        ];
    }

    public function itemExists(string $cartItemId): bool
    {
        return CartItem::where('id', $cartItemId)->exists();
    }
}
