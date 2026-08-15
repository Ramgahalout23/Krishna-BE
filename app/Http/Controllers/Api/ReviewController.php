<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ReviewService;
use App\Exceptions\AppError;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class ReviewController extends Controller
{
    public function __construct(protected ReviewService $reviewService) {}

    public function productReviews(string $productId): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->reviewService->getProductReviews($productId)]);
    }

    public function verifiedReviews(string $productId): JsonResponse
    {
        $reviews = \App\Models\Review::with('user')
            ->where('product_id', $productId)
            ->where('is_verified', true)
            ->latest()
            ->paginate(15);
        return response()->json(['success' => true, 'data' => $reviews]);
    }

    public function stats(string $productId): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->reviewService->getStats($productId)]);
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $type = $request->string('type', 'product');

            $rules = [
                'type' => 'sometimes|string|in:product,store',
                'order_id' => 'nullable|string|exists:orders,id',
                'rating' => 'required|integer|min:1|max:5',
                'title' => 'nullable|string|max:255',
                'comment' => 'nullable|string',
                'images' => 'nullable|array',
                'images.*' => 'nullable|string',
                // Store reviews capture name + email directly (no auth required)
                'name' => 'required_if:type,store|string|max:255',
                'email' => 'required_if:type,store|email|max:255',
            ];

            // Product reviews require product_id; store reviews don't
            if ($type === 'store') {
                $rules['product_id'] = 'nullable|string|exists:products,id';
            } else {
                $rules['product_id'] = 'required|string|exists:products,id';
            }

            $validated = $request->validate($rules);

            if ($type === 'store') {
                $review = $this->reviewService->createStoreReview($validated);
            } else {
                $review = $this->reviewService->create(Auth::id(), $validated);
            }

            Cache::forget('reviews_homepage_data');
            Cache::forget('homepage_all');
            return response()->json(['success' => true, 'message' => 'Review submitted', 'data' => $review], 201);
        } catch (AppError $e) { return $e->render(); }
    }

    public function userReviews(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->reviewService->getUserReviews(Auth::id())]);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        try {
            $validated = $request->validate(['rating' => 'sometimes|integer|min:1|max:5', 'comment' => 'nullable|string']);
            $review = $this->reviewService->update($id, Auth::id(), $validated);
            Cache::forget('reviews_homepage_data');
            Cache::forget('homepage_all');
            return response()->json(['success' => true, 'message' => 'Review updated', 'data' => $review]);
        } catch (AppError $e) { return $e->render(); }
    }

    public function destroy(string $id): JsonResponse
    {
        try {
            $this->reviewService->delete($id, Auth::id());
            Cache::forget('reviews_homepage_data');
            Cache::forget('homepage_all');
            return response()->json(['success' => true, 'message' => 'Review deleted']);
        } catch (AppError $e) { return $e->render(); }
    }

    public function moderate(Request $request, string $id): JsonResponse
    {
        try {
            $validated = $request->validate(['status' => 'required|string|in:APPROVED,REJECTED,PENDING']);
            $review = $this->reviewService->moderate($id, $validated['status']);
            Cache::forget('reviews_homepage_data');
            Cache::forget('homepage_all');
            return response()->json(['success' => true, 'message' => 'Review moderated', 'data' => $review]);
        } catch (AppError $e) { return $e->render(); }
    }

    /**
     * List all reviews for admin with optional status filter + search.
     * GET /api/v1/admin/reviews?page=1&limit=15&status=approved|pending|rejected&search=
     */
    public function adminIndex(Request $request): JsonResponse
    {
        $page = $request->integer('page', 1);
        $limit = $request->integer('limit', 15);
        $status = $request->string('status'); // approved, pending, rejected, or null = all
        $search = $request->string('search');
        $type = $request->string('type'); // product, store, or null = all

        $query = \App\Models\Review::with([
            'user' => fn($q) => $q->select('id', 'first_name', 'last_name', 'email', 'avatar'),
            'product:id,name'
        ]);

        // Status filter
        if ($status === 'approved') {
            $query->where('is_moderated', true)->where('is_flagged', false);
        } elseif ($status === 'pending') {
            $query->where('is_moderated', false)->where('is_flagged', false);
        } elseif ($status === 'rejected') {
            $query->where('is_flagged', true);
        }

        // Type filter (product vs store review)
        if ($type && in_array($type, ['product', 'store'])) {
            $query->where('type', $type);
        }

        // Search filter
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('comment', 'like', "%{$search}%")
                  ->orWhere('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $reviews = $query->latest()->paginate($limit, ['*'], 'page', $page);

        // Map snake_case to camelCase for frontend
        $items = collect($reviews->items())->map(function ($review) {
            $review->createdAt = $review->created_at;
            // For store reviews, use name/email from the review record directly
            if ($review->type === 'store') {
                $review->userName = $review->name ?? 'Customer';
                $review->userEmail = $review->email ?? '';
                $review->productName = 'Store Review';
            } else {
                $review->userName = $review->user?->first_name ? trim($review->user->first_name . ' ' . $review->user->last_name) : 'Customer';
                $review->userEmail = $review->user?->email ?? '';
                $review->productName = $review->product?->name ?? '—';
            }
            return $review;
        })->toArray();

        return response()->json([
            'success' => true,
            'data' => [
                'reviews' => $items,
                'pagination' => [
                    'page' => $reviews->currentPage(),
                    'limit' => $reviews->perPage(),
                    'total' => $reviews->total(),
                    'pages' => $reviews->lastPage(),
                ],
            ],
        ]);
    }


    /**
     * Get single review details for admin.
     * GET /api/v1/admin/reviews/{id}
     */
    public function adminShow(string $id): JsonResponse
    {
        try {
            $review = \App\Models\Review::with([
                'user' => fn($q) => $q->select('id', 'first_name', 'last_name', 'email', 'avatar'),
                'product:id,name,slug'
            ])->findOrFail($id);

            $review->createdAt = $review->created_at;
            if ($review->type === 'store') {
                $review->userName = $review->name ?? 'Customer';
                $review->userEmail = $review->email ?? '';
                $review->productName = 'Store Review';
            } else {
                $review->userName = $review->user?->first_name ? trim($review->user->first_name . ' ' . $review->user->last_name) : 'Customer';
                $review->userEmail = $review->user?->email ?? '';
                $review->productName = $review->product?->name ?? '—';
            }

            return response()->json(['success' => true, 'data' => $review]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Review not found'], 404);
        }
    }

    /**
     * Get approved reviews for the homepage (across all products).
     * GET /api/v1/reviews/homepage
     */
    public function homepage(Request $request): JsonResponse
    {
        // Check master toggle — if reviews are disabled, return empty (cached 5 min)
        $reviewsEnabled = Cache::remember('setting_reviewsEnabled', 300, function () {
            return \App\Models\Setting::where('key', 'reviewsEnabled')->value('value');
        });

        if ($reviewsEnabled === 'false' || $reviewsEnabled === '0') {
            return response()->json([
                'success' => true,
                'data' => ['reviews' => [], 'pagination' => ['page' => 1, 'limit' => 0, 'total' => 0, 'pages' => 0], 'stats' => ['average_rating' => 0, 'total_reviews' => 0]],
            ])->setCache(['public' => true, 'max_age' => 300]);
        }

        // Cache the homepage reviews (page 1, limit 20 only — homepage never paginates beyond first page)
        $data = Cache::remember('reviews_homepage_data', 300, function () {
            $reviews = \App\Models\Review::with(['user' => fn($q) => $q->select('id', 'first_name', 'last_name', 'email', 'avatar')])
                ->with('product:id,name')
                ->where('is_moderated', true)
                ->where('is_flagged', false)
                ->latest()
                ->take(20)
                ->get();

            $statsQuery = \App\Models\Review::where('is_moderated', true)->where('is_flagged', false);
            $totalReviews = (clone $statsQuery)->count();
            $averageRating = round((clone $statsQuery)->avg('rating') ?? 0, 1);

            return [
                'reviews' => $reviews->toArray(),
                'pagination' => [
                    'page' => 1,
                    'limit' => 20,
                    'total' => $totalReviews,
                    'pages' => (int) ceil($totalReviews / 20),
                ],
                'stats' => [
                    'average_rating' => $averageRating,
                    'total_reviews' => $totalReviews,
                ],
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $data,
        ])->setCache(['public' => true, 'max_age' => 300]);
    }

    /**
     * Get single review by ID.
     * GET /api/v1/reviews/{id}
     */
    public function show(string $id): JsonResponse
    {
        try {
            $review = \App\Models\Review::with('user', 'product')->findOrFail($id);
            return response()->json(['success' => true, 'data' => $review]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Review not found'], 404);
        }
    }

    /**
     * Approve review (Admin).
     * POST /api/v1/reviews/{id}/approve
     */
    public function approve(string $id): JsonResponse
    {
        try {
            $review = $this->reviewService->moderate($id, 'APPROVED');
            Cache::forget('reviews_homepage_data');
            Cache::forget('homepage_all');
            return response()->json(['success' => true, 'message' => 'Review approved', 'data' => $review]);
        } catch (AppError $e) { return $e->render(); }
    }

    /**
     * Reject review (Admin).
     * POST /api/v1/reviews/{id}/reject
     */
    public function reject(string $id): JsonResponse
    {
        try {
            $review = $this->reviewService->moderate($id, 'REJECTED');
            Cache::forget('reviews_homepage_data');
            Cache::forget('homepage_all');
            return response()->json(['success' => true, 'message' => 'Review rejected', 'data' => $review]);
        } catch (AppError $e) { return $e->render(); }
    }

    /**
     * Get pending reviews (Admin).
     * GET /api/v1/admin/reviews/pending
     */
    public function pendingReviews(Request $request): JsonResponse
    {
        $perPage = $request->integer('limit', 15);
        $search = $request->string('search');
        $type = $request->string('type'); // product, store, or null = all

        $result = $this->reviewService->getPendingReviews(
            $request->integer('page', 1),
            $perPage,
            $search ?: null,
            $type ?: null
        );

        // Map snake_case to camelCase for frontend
        $items = collect($result['items'])->map(function ($review) {
            $review->createdAt = $review->created_at;
            // For store reviews, use name/email from the review record directly
            if ($review->type === 'store') {
                $review->userName = $review->name ?? 'Customer';
                $review->userEmail = $review->email ?? '';
                $review->productName = 'Store Review';
            } else {
                $review->userName = $review->user?->first_name ? trim($review->user->first_name . ' ' . $review->user->last_name) : 'Customer';
                $review->userEmail = $review->user?->email ?? '';
                $review->productName = $review->product?->name ?? '—';
            }
            return $review;
        })->toArray();

        return response()->json([
            'success' => true,
            'data' => [
                'reviews' => $items,
                'pagination' => [
                    'page' => $result['page'],
                    'limit' => $result['limit'],
                    'total' => $result['total'],
                    'pages' => $result['total_pages'],
                ],
            ],
        ]);
    }

    /**
     * Public endpoint for submitting store reviews (no auth required).
     * POST /api/v1/reviews/store
     */
    public function storeStoreReview(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'required|email|max:255',
                'rating' => 'required|integer|min:1|max:5',
                'comment' => 'required|string',
                'images' => 'nullable|array',
                'images.*' => 'nullable|string',
                'title' => 'nullable|string|max:255',
            ]);

            $validated['type'] = 'store';
            $review = $this->reviewService->createStoreReview($validated);

            Cache::forget('reviews_homepage_data');
            Cache::forget('homepage_all');

            return response()->json(['success' => true, 'message' => 'Store review submitted', 'data' => $review], 201);
        } catch (AppError $e) { return $e->render(); }
    }

    /**
     * Get approved store reviews (reviews about the store, not a specific product).
     * GET /api/v1/reviews/store
     */
    public function storeReviews(Request $request): JsonResponse
    {
        $page = $request->integer('page', 1);
        $limit = $request->integer('limit', 15);

        $reviews = \App\Models\Review::with(['user' => fn($q) => $q->select('id', 'first_name', 'last_name', 'email', 'avatar')])
            ->where('type', 'store')
            ->where('is_moderated', true)
            ->where('is_flagged', false)
            ->latest()
            ->paginate($limit, ['*'], 'page', $page);

        return response()->json([
            'success' => true,
            'data' => [
                'reviews' => $reviews->items(),
                'pagination' => [
                    'page' => $reviews->currentPage(),
                    'limit' => $reviews->perPage(),
                    'total' => $reviews->total(),
                    'pages' => $reviews->lastPage(),
                ],
            ],
        ]);
    }

    /**
     * Upload a review image (authenticated users).
     * POST /api/v1/uploads/review-image
     */
    public function uploadReviewImage(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'file' => 'required|image|mimes:jpeg,png,jpg,webp|max:10240',
            ]);

            $result = app(\App\Services\StorageDriverService::class)->storeFile($request->file('file'), 'uploads/reviews');

            return response()->json([
                'success' => true,
                'data' => ['url' => $result['url'], 'path' => $result['path']],
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    /**
     * Upload multiple review images (authenticated users).
     * POST /api/v1/uploads/review-images
     */
    public function uploadReviewImages(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'files' => 'required|array',
                'files.*' => 'required|image|mimes:jpeg,png,jpg,webp|max:10240',
            ]);

            $results = app(\App\Services\StorageDriverService::class)->storeFiles($request->file('files'), 'uploads/reviews');
            $uploaded = array_map(fn($r) => ['url' => $r['url'], 'path' => $r['path']], $results);

            return response()->json([
                'success' => true,
                'data' => ['files' => $uploaded],
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    /**
     * Mark review as helpful.
     * POST /api/v1/reviews/{id}/helpful
     */
    public function markHelpful(string $id): JsonResponse
    {
        try {
            $review = \App\Models\Review::findOrFail($id);
            $review->increment('helpful');
            return response()->json(['success' => true, 'message' => 'Marked as helpful']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Review not found'], 404);
        }
    }

    /**
     * Mark review as unhelpful.
     * POST /api/v1/reviews/{id}/unhelpful
     */
    public function markUnhelpful(string $id): JsonResponse
    {
        try {
            $review = \App\Models\Review::findOrFail($id);
            $review->increment('unhelpful');
            return response()->json(['success' => true, 'message' => 'Marked as unhelpful']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Review not found'], 404);
        }
    }
}
