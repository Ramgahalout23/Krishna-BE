<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Repositories\CartRepository;
use App\Exceptions\AppError;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RecentlyViewedController extends Controller
{
    public function __construct(protected CartRepository $cartRepository) {}

    /**
     * Get recently viewed products for the authenticated user.
     * GET /api/v1/recently-viewed
     */
    public function index(Request $request): JsonResponse
    {
        $limit = min((int) ($request->limit ?? 10), 50);
        $items = $this->cartRepository->getRecentlyViewed(Auth::id(), $limit);

        $products = $items->map(fn($item) => [
            'id' => $item->product_id,
            'name' => $item->product?->name,
            'slug' => $item->product?->slug,
            'price' => $item->product?->price,
            'oldPrice' => $item->product?->old_price,
            'imageUrl' => $item->product?->images?->first()?->url ?? null,
            'category' => $item->product?->category?->name,
            'rating' => $item->product?->rating,
            'viewed_at' => $item->viewed_at,
        ]);

        return response()->json(['success' => true, 'data' => $products]);
    }

    /**
     * Track a product view.
     * POST /api/v1/recently-viewed
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'product_id' => 'required|string|exists:products,id',
            ]);

            $this->cartRepository->addRecentlyViewed(Auth::id(), $validated['product_id']);

            return response()->json(['success' => true, 'message' => 'Product view recorded']);
        } catch (AppError $e) { return $e->render(); }
    }
}
