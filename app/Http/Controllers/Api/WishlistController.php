<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\WishlistService;
use App\Exceptions\AppError;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class WishlistController extends Controller
{
    public function __construct(protected WishlistService $wishlistService) {}

    public function index(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->wishlistService->getUserWishlist(Auth::id())]);
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate(['product_id' => 'required|string']);
            $result = $this->wishlistService->add(Auth::id(), $validated['product_id']);
            return response()->json(['success' => true, 'message' => 'Added to wishlist', 'data' => $result]);
        } catch (AppError $e) { return $e->render(); }
    }

    public function destroy(string $productId): JsonResponse
    {
        try {
            $this->wishlistService->remove(Auth::id(), $productId);
            return response()->json(['success' => true, 'message' => 'Removed from wishlist']);
        } catch (AppError $e) { return $e->render(); }
    }

    public function check(string $productId): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->wishlistService->check(Auth::id(), $productId)]);
    }

    public function count(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->wishlistService->getCount(Auth::id())]);
    }

    /**
     * Clear entire wishlist.
     * DELETE /api/v1/wishlist
     */
    public function clearWishlist(): JsonResponse
    {
        try {
            $result = $this->wishlistService->clearAll(Auth::id());
            return response()->json(['success' => true, 'message' => $result['message'], 'data' => ['items_removed' => $result['count']]]);
        } catch (AppError $e) { return $e->render(); }
    }

    /**
     * Add multiple products to wishlist.
     * POST /api/v1/wishlist/bulk
     */
    public function bulkAdd(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'product_ids' => 'required|array|min:1',
                'product_ids.*' => 'required|string',
            ]);

            $added = [];
            $failed = [];
            foreach ($validated['product_ids'] as $pid) {
                try {
                    $this->wishlistService->add(Auth::id(), $pid);
                    $added[] = $pid;
                } catch (\Exception $e) {
                    $failed[] = $pid;
                }
            }

            return response()->json([
                'success' => true,
                'data' => ['added' => $added, 'failed' => $failed],
            ]);
        } catch (AppError $e) { return $e->render(); }
    }

    /**
     * Move product from wishlist to cart.
     * POST /api/v1/wishlist/{productId}/move-to-cart
     */
    public function moveToCart(Request $request, string $productId): JsonResponse
    {
        try {
            $validated = $request->validate([
                'size' => 'nullable|string',
                'color' => 'nullable|string',
                'variantId' => 'nullable|string',
            ]);

            $result = $this->wishlistService->moveToCart(
                Auth::id(),
                $productId,
                $validated['size'] ?? null,
                $validated['color'] ?? null,
                $validated['variantId'] ?? null
            );
            return response()->json(['success' => true, 'message' => $result['message']]);
        } catch (AppError $e) { return $e->render(); }
    }

    // ──────────────────────────────────────────────
    //  Wishlist Sharing
    // ──────────────────────────────────────────────

    /**
     * Generate or get existing share link for wishlist.
     * POST /api/v1/wishlist/share
     */
    public function share(): JsonResponse
    {
        try {
            $result = $this->wishlistService->share(Auth::id());
            return response()->json(['success' => true, 'data' => $result]);
        } catch (AppError $e) { return $e->render(); }
    }

    /**
     * Revoke the wishlist share link.
     * DELETE /api/v1/wishlist/share
     */
    public function unshare(): JsonResponse
    {
        try {
            $this->wishlistService->unshare(Auth::id());
            return response()->json(['success' => true, 'message' => 'Wishlist share link revoked']);
        } catch (AppError $e) { return $e->render(); }
    }

    /**
     * Get current share status.
     * GET /api/v1/wishlist/share
     */
    public function shareStatus(): JsonResponse
    {
        try {
            $result = $this->wishlistService->getShareStatus(Auth::id());
            return response()->json(['success' => true, 'data' => $result]);
        } catch (AppError $e) { return $e->render(); }
    }

    /**
     * Public: view a shared wishlist by token.
     * GET /api/v1/shared-wishlist/{token}
     */
    public function viewShared(string $token): JsonResponse
    {
        try {
            $result = $this->wishlistService->getSharedWishlist($token);
            return response()->json(['success' => true, 'data' => $result]);
        } catch (AppError $e) { return $e->render(); }
    }
}
