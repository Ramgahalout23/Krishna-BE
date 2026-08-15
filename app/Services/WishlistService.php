<?php

namespace App\Services;

use App\Repositories\WishlistRepository;
use App\Services\CartService;
use App\Exceptions\AppError;
use App\Models\WishlistItem;
use App\Models\SharedWishlist;
use App\Models\Product;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class WishlistService
{
    public function __construct(
        protected WishlistRepository $wishlistRepository
    ) {}

    /**
     * Clear all cached wishlist data for a user.
     * Uses version-based invalidation (same pattern as ProductService)
     * so all paginated list caches are invalidated atomically.
     */
    private function clearUserCache(string $userId): void
    {
        // Increment version to invalidate all list caches for this user
        $version = Cache::get("wishlist_cache_version:{$userId}", 0);
        Cache::forever("wishlist_cache_version:{$userId}", $version + 1);

        // Also forget count cache immediately (it's a simple key, not versioned)
        Cache::forget("wishlist:{$userId}:count");
    }

    /**
     * Get versioned cache key for wishlist paginated list.
     */
    private function versionedWishlistKey(string $userId, int $page, int $limit): string
    {
        $version = Cache::get("wishlist_cache_version:{$userId}", 0);
        return "wishlist:v{$version}:{$userId}:{$page}:{$limit}";
    }

    /**
     * Get user's wishlist with pagination.
     */
    public function getUserWishlist(string $userId, int $page = 1, int $limit = 20): array
    {
        if ($page < 1) throw AppError::validation('Page number must be at least 1');
        if ($limit < 1 || $limit > 100) throw AppError::validation('Limit must be between 1 and 100');

        $cacheKey = $this->versionedWishlistKey($userId, $page, $limit);
        return Cache::remember($cacheKey, 300, function () use ($userId, $page, $limit) {
            return $this->wishlistRepository->getUserWishlist($userId, $page, $limit);
        });
    }

    public function toggle(string $userId, string $productId): array
    {
        $result = $this->wishlistRepository->toggleItem($userId, $productId);
        $this->clearUserCache($userId);
        return $result;
    }

    /**
     * Add product to wishlist with product existence check.
     */
    public function add(string $userId, string $productId): array
    {
        if (empty($productId)) throw AppError::validation('Product ID is required');

        // Check if product exists
        $product = Product::find($productId);
        if (!$product) throw AppError::notFound('Product not found');

        // Check for duplicate
        $existing = $this->wishlistRepository->findByUserAndProduct($userId, $productId);
        if ($existing) throw AppError::conflict('Product already in wishlist');

        $result = $this->wishlistRepository->addItem($userId, $productId);
        $this->clearUserCache($userId);
        return $result;
    }

    public function remove(string $userId, string $productId): void
    {
        $item = $this->wishlistRepository->findByUserAndProduct($userId, $productId);
        if (!$item) throw AppError::notFound('Product not in wishlist');
        $this->wishlistRepository->removeByUserAndProduct($userId, $productId);
        $this->clearUserCache($userId);
    }

    public function check(string $userId, string $productId): array
    {
        if (empty($productId)) throw AppError::validation('Product ID is required');
        return Cache::remember("wishlist:{$userId}:check:{$productId}", 300, function () use ($userId, $productId) {
            return ['wishlisted' => $this->wishlistRepository->checkProduct($userId, $productId)];
        });
    }

    public function getCount(string $userId): array
    {
        return Cache::remember("wishlist:{$userId}:count", 300, function () use ($userId) {
            return ['count' => $this->wishlistRepository->getCount($userId)];
        });
    }

    public function clearAll(string $userId): array
    {
        $count = $this->wishlistRepository->getCount($userId);
        $this->wishlistRepository->clearWishlist($userId);
        $this->clearUserCache($userId);
        return ['message' => 'Wishlist cleared', 'count' => $count];
    }

    public function moveToCart(string $userId, string $productId, ?string $size = null, ?string $color = null, ?string $variantId = null): array
    {
        if (empty($productId)) throw AppError::validation('Product ID is required');

        $item = $this->wishlistRepository->findByUserAndProduct($userId, $productId);
        if (!$item) throw AppError::notFound('Product not in wishlist');

        // Add to cart with variant info
        $cartService = app(CartService::class);
        $cartService->addItem($productId, 1, $userId, null, $size, $color, $variantId);

        // Remove from wishlist
        $this->wishlistRepository->removeByUserAndProduct($userId, $productId);

        return ['message' => 'Product moved to cart'];
    }

    /**
     * Add multiple products to wishlist (bulk).
     */
    public function addMultiple(array $productIds, string $userId): array
    {
        if (empty($productIds)) {
            throw AppError::validation('Product IDs array cannot be empty');
        }

        $success = [];
        $failed = [];

        foreach ($productIds as $productId) {
            try {
                $this->add($userId, $productId);
                $success[] = $productId;
            } catch (\Exception $e) {
                $failed[] = $productId;
            }
        }

        return ['success' => $success, 'failed' => $failed];
    }

    // ──────────────────────────────────────────────
    //  Wishlist Sharing
    // ──────────────────────────────────────────────

    /**
     * Generate or retrieve an existing share link for the user's wishlist.
     * Returns the share URL and token.
     */
    public function share(string $userId): array
    {
        $existing = SharedWishlist::where('user_id', $userId)
            ->where('is_active', true)
            ->first();

        if ($existing) {
            return [
                'token' => $existing->token,
                'url' => url("/shared-wishlist/{$existing->token}"),
                'created_at' => $existing->created_at,
            ];
        }

        $token = Str::random(32);

        $shared = SharedWishlist::create([
            'user_id' => $userId,
            'token' => $token,
            'is_active' => true,
        ]);

        return [
            'token' => $shared->token,
            'url' => url("/shared-wishlist/{$shared->token}"),
            'created_at' => $shared->created_at,
        ];
    }

    /**
     * Revoke the user's active wishlist share link.
     */
    public function unshare(string $userId): void
    {
        SharedWishlist::where('user_id', $userId)
            ->where('is_active', true)
            ->update(['is_active' => false]);
    }

    /**
     * Get the current share status for the user's wishlist.
     * Returns null if no active share link exists.
     */
    public function getShareStatus(string $userId): ?array
    {
        $shared = SharedWishlist::where('user_id', $userId)
            ->where('is_active', true)
            ->first();

        if (!$shared) {
            return null;
        }

        return [
            'token' => $shared->token,
            'url' => url("/shared-wishlist/{$shared->token}"),
            'created_at' => $shared->created_at,
        ];
    }

    /**
     * Get a user's wishlist items by share token (public access).
     * Also returns the user's display name.
     */
    public function getSharedWishlist(string $token): array
    {
        $shared = SharedWishlist::where('token', $token)
            ->where('is_active', true)
            ->with('user')
            ->first();

        if (!$shared) {
            throw AppError::notFound('Wishlist not found or share link has expired');
        }

        $items = $this->wishlistRepository->getUserWishlist($shared->user_id, 1, 100);

        return [
            'user_name' => $shared->user->name ?? 'Anonymous',
            'user_id' => $shared->user_id,
            'items' => $items['items'],
            'total' => $items['total'],
        ];
    }
}
