<?php

namespace App\Observers;

use App\Models\Review;
use App\Repositories\ProductRepository;
use Illuminate\Support\Facades\Cache;

class ReviewObserver
{
    /**
     * Handle the Review "created" event.
     */
    public function created(Review $review): void
    {
        $this->syncProductRating($review);
    }

    /**
     * Handle the Review "updated" event.
     */
    public function updated(Review $review): void
    {
        $this->syncProductRating($review);
    }

    /**
     * Handle the Review "deleted" event.
     */
    public function deleted(Review $review): void
    {
        $this->syncProductRating($review);
    }

    /**
     * Handle the Review "restored" event.
     */
    public function restored(Review $review): void
    {
        $this->syncProductRating($review);
    }

    /**
     * Recalculate the product's rating and review_count, then clear caches.
     */
    private function syncProductRating(Review $review): void
    {
        if (!$review->product_id) {
            return;
        }

        try {
            app(ProductRepository::class)->updateProductRating($review->product_id);

            // Invalidate product detail cache so frontend shows fresh data
            Cache::increment('products_cache_version');
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning('[ReviewObserver] Failed to sync product rating', [
                'product_id' => $review->product_id,
                'review_id' => $review->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
