<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\CartService;
use App\Exceptions\AppError;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CartController extends Controller
{
    public function __construct(protected CartService $cartService) {}

    public function index(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->cartService->getCart(Auth::id())]);
    }

    public function addItem(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'product_id' => 'required_without:productId|string',
                'productId' => 'required_without:product_id|string',
                'quantity' => 'required|integer|min:1',
                'size' => 'nullable|string',
                'color' => 'nullable|string',
                'variantId' => 'nullable|string',
                'variant_id' => 'nullable|string',
            ]);
            $productId = $validated['product_id'] ?? $validated['productId'];
            $variantId = $validated['variantId'] ?? $validated['variant_id'] ?? null;
            $item = $this->cartService->addItem(
                $productId,
                (int) $validated['quantity'],
                Auth::id(),
                null,
                $validated['size'] ?? null,
                $validated['color'] ?? null,
                $variantId
            );
            return response()->json(['success' => true, 'message' => 'Added to cart', 'data' => $item], 201);
        } catch (AppError $e) { return $e->render(); }
    }

    public function updateItem(Request $request, string $itemId): JsonResponse
    {
        try {
            $validated = $request->validate(['quantity' => 'required|integer|min:1|max:10']);
            $result = $this->cartService->updateItem($itemId, (int) $validated['quantity']);
            return response()->json(['success' => true, 'message' => 'Cart updated', 'data' => $result]);
        } catch (AppError $e) { return $e->render(); }
    }

    public function removeItem(string $itemId): JsonResponse
    {
        try {
            $result = $this->cartService->removeItem($itemId);
            return response()->json(['success' => true, 'message' => $result['message']]);
        } catch (AppError $e) { return $e->render(); }
    }

    public function clear(): JsonResponse
    {
        $result = $this->cartService->clearCart(Auth::id());
        return response()->json(['success' => true, 'message' => $result['message']]);
    }

    public function validateCart(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->cartService->validateCart(Auth::id())]);
    }

    public function mergeCart(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'session_id' => 'nullable|string',
                'items' => 'nullable|array',
                'items.*.product_id' => 'required_with:items|string',
                'items.*.quantity' => 'required_with:items|integer|min:1',
            ]);

            // Merge by session_id (guest cart from server)
            if (!empty($validated['session_id'])) {
                $cart = $this->cartService->mergeCart(Auth::id(), $validated['session_id']);
            } elseif (!empty($validated['items'])) {
                // Merge by items array (from localStorage)
                // Catch per-item errors so one OOS item doesn't block the entire merge
                $merged = [];
                $failed = [];
                foreach ($validated['items'] as $item) {
                    try {
                        $this->cartService->addItem($item['product_id'], $item['quantity'], Auth::id());
                        $merged[] = $item;
                    } catch (AppError $e) {
                        $failed[] = [
                            'product_id' => $item['product_id'],
                            'reason' => $e->getMessage(),
                        ];
                    }
                }
                $cart = $this->cartService->getCart(Auth::id());
                $cart['merge_summary'] = [
                    'merged' => count($merged),
                    'failed' => count($failed),
                    'failed_items' => $failed,
                ];
            } else {
                return response()->json(['success' => false, 'message' => 'Provide session_id or items array'], 422);
            }

            return response()->json(['success' => true, 'message' => 'Cart merged successfully', 'data' => $cart]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
}
