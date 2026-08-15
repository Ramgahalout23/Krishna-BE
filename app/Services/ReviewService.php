<?php

namespace App\Services;

use App\Repositories\ReviewRepository;
use App\Exceptions\AppError;
use App\Jobs\SendNotificationJob;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class ReviewService
{
    public function __construct(
        protected ReviewRepository $reviewRepository
    ) {}

    /**
     * Create a new review with validation.
     */
    public function create(string $userId, array $data): array
    {
        // Validate required fields
        if (empty($data['product_id'])) {
            throw AppError::validation('Product ID is required');
        }

        if (empty($data['rating']) || $data['rating'] < 1 || $data['rating'] > 5) {
            throw AppError::validation('Rating must be between 1 and 5');
        }

        if (empty($data['title'])) {
            throw AppError::validation('Review title is required');
        }

        if (empty($data['comment'])) {
            throw AppError::validation('Review comment is required');
        }

        // Handle images array — pass raw array, Eloquent's `json` cast handles encoding
        if (isset($data['images']) && is_array($data['images'])) {
            $data['images'] = array_values(array_filter($data['images'], fn($v) => !empty($v)));
        }

        // Check if product exists
        $product = Product::find($data['product_id']);
        if (!$product) {
            throw AppError::notFound('Product not found');
        }

        // Check if user already reviewed this product
        $existing = $this->reviewRepository->hasUserReviewedProduct($userId, $data['product_id']);
        if ($existing) {
            throw AppError::conflict('You have already reviewed this product. You can edit your review instead.');
        }

        $data['user_id'] = $userId;
        $data['is_moderated'] = false;

        $review = $this->reviewRepository->create($data);

        // Update product rating
        $this->reviewRepository->updateProductRating($data['product_id']);
        $this->reviewRepository->clearProductReviewCache($data['product_id']);

        // ── Notify admins about the new review ──
        $this->notifyAdminsOfNewReview($review, $product);

        return $review->load('user:id,first_name,last_name,email,avatar')->toArray();
    }

    /**
     * Create a store review (no product association).
     */
    public function createStoreReview(array $data): array
    {
        if (empty($data['rating']) || $data['rating'] < 1 || $data['rating'] > 5) {
            throw AppError::validation('Rating must be between 1 and 5');
        }

        if (empty($data['comment'])) {
            throw AppError::validation('Review comment is required');
        }

        if (empty($data['name'])) {
            throw AppError::validation('Name is required');
        }

        if (empty($data['email'])) {
            throw AppError::validation('Email is required');
        }

        // Handle images array
        if (isset($data['images']) && is_array($data['images'])) {
            $data['images'] = array_values(array_filter($data['images'], fn($v) => !empty($v)));
        }

        $data['type'] = 'store';
        $data['is_moderated'] = false;
        $data['user_id'] = null;

        $review = $this->reviewRepository->create($data);

        // ── Notify admins about the new store review ──
        $this->notifyAdminsOfNewReview($review, null);

        return $review->toArray();
    }

    /**
     * Get reviews for a product with pagination.
     */
    public function getProductReviews(string $productId, int $page = 1, int $perPage = 10): array
    {
        if ($page < 1) throw AppError::validation('Page number must be at least 1');
        if ($perPage < 1 || $perPage > 50) throw AppError::validation('Limit must be between 1 and 50');

        $product = Product::find($productId);
        if (!$product) throw AppError::notFound('Product not found');

        return $this->reviewRepository->getProductReviews($productId, $page, $perPage, true);
    }

    /**
     * Get user's reviews with pagination.
     */
    public function getUserReviews(string $userId, int $page = 1, int $perPage = 10): array
    {
        if ($page < 1) throw AppError::validation('Page number must be at least 1');
        if ($perPage < 1 || $perPage > 50) throw AppError::validation('Limit must be between 1 and 50');

        return $this->reviewRepository->getUserReviews($userId, $page, $perPage);
    }

    /**
     * Update a review with authorization check.
     */
    public function update(string $id, string $userId, array $data): array
    {
        $review = $this->reviewRepository->getReviewById($id);
        if (!$review) throw AppError::notFound('Review not found');
        if ($review->user_id !== $userId) throw AppError::forbidden('You can only edit your own reviews');

        if (isset($data['rating']) && ($data['rating'] < 1 || $data['rating'] > 5)) {
            throw AppError::validation('Rating must be between 1 and 5');
        }

        // Reset moderation when updated
        $data['is_moderated'] = false;

        $updated = $this->reviewRepository->update($id, $data);

        // Recalculate product rating
        $this->reviewRepository->updateProductRating($review->product_id);
        $this->reviewRepository->clearProductReviewCache($review->product_id);

        return $updated->fresh()->load('user:id,first_name,last_name,email,avatar')->toArray();
    }

    /**
     * Delete a review with authorization check.
     * Admins can delete any review; regular users can only delete their own.
     */
    public function delete(string $id, string $userId): void
    {
        $review = $this->reviewRepository->getReviewById($id);
        if (!$review) throw AppError::notFound('Review not found');
        if ($review->user_id !== $userId) {
            // Check if the requesting user is admin
            $user = \App\Models\User::find($userId);
            if (!$user || !$user->isAdmin()) {
                throw AppError::forbidden('You can only delete your own reviews');
            }
        }

        $productId = $review->product_id;
        $this->reviewRepository->delete($id);
        $this->reviewRepository->updateProductRating($productId);
        $this->reviewRepository->clearProductReviewCache($productId);
    }

    /**
     * Moderate a review (admin).
     * status: 'APPROVED' sets is_moderated=true, 'REJECTED' sets is_flagged=true, 'PENDING' sets both false.
     */
    public function moderate(string $id, string $status): array
    {
        $review = $this->reviewRepository->findByIdOrFail($id);

        $data = match ($status) {
            'APPROVED' => ['is_moderated' => true, 'is_flagged' => false],
            'REJECTED' => ['is_moderated' => false, 'is_flagged' => true],
            default => ['is_moderated' => false, 'is_flagged' => false],
        };

        $review = $this->reviewRepository->update($id, $data);
        $this->reviewRepository->updateProductRating($review->product_id);
        $this->reviewRepository->clearProductReviewCache($review->product_id);

        // ── Notify the user when their review is approved ──
        if ($status === 'APPROVED' && $review->user_id) {
            $this->notifyReviewApproved($review);
        }

        return $review->fresh()->load('user', 'product:id,name,slug')->toArray();
    }

    /**
     * Mark review as helpful.
     */
    public function markHelpful(string $reviewId): array
    {
        if (empty($reviewId)) throw AppError::validation('Review ID is required');

        $review = $this->reviewRepository->getReviewById($reviewId);
        if (!$review) throw AppError::notFound('Review not found');

        return $this->reviewRepository->markHelpful($reviewId)->toArray();
    }

    /**
     * Mark review as unhelpful.
     */
    public function markUnhelpful(string $reviewId): array
    {
        if (empty($reviewId)) throw AppError::validation('Review ID is required');

        $review = $this->reviewRepository->getReviewById($reviewId);
        if (!$review) throw AppError::notFound('Review not found');

        return $this->reviewRepository->markUnhelpful($reviewId)->toArray();
    }

    /**
     * Get product review statistics.
     */
    public function getProductStats(string $productId): array
    {
        if (empty($productId)) throw AppError::validation('Product ID is required');

        $product = Product::find($productId);
        if (!$product) throw AppError::notFound('Product not found');

        return $this->reviewRepository->getStats($productId);
    }

    /**
     * Get pending reviews for moderation (admin).
     */
    public function getPendingReviews(int $page = 1, int $limit = 20, ?string $search = null, ?string $type = null): array
    {
        if ($page < 1) throw AppError::validation('Page number must be at least 1');
        if ($limit < 1 || $limit > 50) throw AppError::validation('Limit must be between 1 and 50');

        return $this->reviewRepository->getPendingModeration($page, $limit, $search, $type);
    }

    /**
     * Send in-app notifications to all admin users about a new pending review.
     */
    private function notifyAdminsOfNewReview($review, ?Product $product): void
    {
        try {
            // Find all admin users
            $admins = User::whereIn('role', ['ADMIN', 'SUPER_ADMIN'])->get(['id', 'first_name']);
            if ($admins->isEmpty()) return;

            $reviewerName = '';
            if ($review->user_id && $review->user) {
                $reviewerName = trim($review->user->first_name . ' ' . ($review->user->last_name ?? ''));
            } elseif ($review->name) {
                $reviewerName = $review->name;
            }

            $productName = $product ? $product->name : null;
            $productSlug = $product ? $product->slug : null;

            $title = $productName
                ? "New Review: {$productName}"
                : "New Store Review 💬";

            $message = $reviewerName
                ? "{$reviewerName} left a new review" . ($productName ? " on {$productName}" : '') . "."
                : "A new review has been submitted" . ($productName ? " for {$productName}" : '') . ".";

            $notificationData = [
                'reviewId' => $review->id,
                'userId' => $review->user_id,
                'productId' => $review->product_id,
                'productName' => $productName,
                'productSlug' => $productSlug,
            ];

            foreach ($admins as $admin) {
                SendNotificationJob::dispatch(
                    $admin->id,
                    'REVIEW',
                    $title,
                    $message,
                    $notificationData
                );
            }

            Log::info('[ReviewService] Notified admins of new review', [
                'review_id' => $review->id,
                'admin_count' => $admins->count(),
                'product_name' => $productName,
            ]);
        } catch (\Exception $e) {
            Log::error('[ReviewService] Failed to notify admins of new review', [
                'review_id' => $review->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Send an in-app notification to the user when their review is approved.
     */
    private function notifyReviewApproved($review): void
    {
        try {
            $product = Product::find($review->product_id);
            if (!$product) return;

            $productName = $product->name ?? 'Product';
            $productSlug = $product->slug;

            SendNotificationJob::dispatch(
                $review->user_id,
                'REVIEW',
                "Review Approved ✅",
                "Your review on \"{$productName}\" has been approved and is now live!",
                ['productId' => $review->product_id, 'productSlug' => $productSlug, 'productName' => $productName]
            );

            Log::info('[ReviewService] Notified user of approved review', [
                'review_id' => $review->id,
                'user_id' => $review->user_id,
                'product_id' => $review->product_id,
            ]);
        } catch (\Exception $e) {
            Log::error('[ReviewService] Failed to notify user of approved review', [
                'review_id' => $review->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Approve a review (admin).
     */
    public function approveReview(string $reviewId): array
    {
        if (empty($reviewId)) throw AppError::validation('Review ID is required');

        $review = $this->reviewRepository->getReviewById($reviewId);
        if (!$review) throw AppError::notFound('Review not found');

        $approved = $this->reviewRepository->approveReview($reviewId);
        $this->reviewRepository->updateProductRating($review->product_id);
        $this->reviewRepository->clearProductReviewCache($review->product_id);

        // Notify the user when their review is approved
        if ($review->user_id) {
            $this->notifyReviewApproved($review);
        }

        return $approved->toArray();
    }

    /**
     * Reject a review (admin).
     */
    public function rejectReview(string $reviewId): array
    {
        if (empty($reviewId)) throw AppError::validation('Review ID is required');

        $review = $this->reviewRepository->getReviewById($reviewId);
        if (!$review) throw AppError::notFound('Review not found');

        $rejected = $this->reviewRepository->rejectReview($reviewId);
        $this->reviewRepository->clearProductReviewCache($review->product_id);

        return $rejected->toArray();
    }

    /**
     * Get verified purchase reviews.
     */
    public function getVerifiedReviews(string $productId, int $page = 1, int $limit = 10): array
    {
        if ($page < 1) throw AppError::validation('Page number must be at least 1');
        if ($limit < 1 || $limit > 50) throw AppError::validation('Limit must be between 1 and 50');

        $product = Product::find($productId);
        if (!$product) throw AppError::notFound('Product not found');

        return $this->reviewRepository->getVerifiedReviews($productId, $page, $limit);
    }
}
