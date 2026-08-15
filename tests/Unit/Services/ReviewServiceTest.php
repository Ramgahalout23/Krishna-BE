<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Services\ReviewService;
use App\Repositories\ReviewRepository;
use App\Exceptions\AppError;
use Mockery;

class ReviewServiceTest extends TestCase
{
    protected ReviewRepository|\Mockery\MockInterface $reviewRepository;
    protected ReviewService $reviewService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->reviewRepository = Mockery::mock(ReviewRepository::class);
        $this->reviewService = new ReviewService($this->reviewRepository);
    }

    protected function tearDown(): void
    {
        // Verify but don't close
        if ($container = Mockery::getContainer()) {
            $container->mockery_verify();
        }
        parent::tearDown();
    }

    protected function mockReview(array $overrides = []): object
    {
        $review = Mockery::mock();
        $review->shouldReceive('load')->andReturnSelf();
        $review->shouldReceive('toArray')->andReturn([
            'id' => $overrides['id'] ?? 'rev-1',
            'user_id' => $overrides['user_id'] ?? 'user-1',
            'product_id' => $overrides['product_id'] ?? 'prod-1',
            'rating' => $overrides['rating'] ?? 5,
            'title' => $overrides['title'] ?? 'Great product',
            'comment' => $overrides['comment'] ?? 'Really loved it',
            'status' => $overrides['status'] ?? 'APPROVED',
            'helpful' => $overrides['helpful'] ?? 0,
            'unhelpful' => $overrides['unhelpful'] ?? 0,
        ]);

        $review->id = $overrides['id'] ?? 'rev-1';
        $review->user_id = $overrides['user_id'] ?? 'user-1';
        $review->product_id = $overrides['product_id'] ?? 'prod-1';
        $review->status = $overrides['status'] ?? 'APPROVED';

        return $review;
    }

    /** @test */
    public function create_creates_review_and_updates_rating()
    {
        $userId = 'user-1';
        $data = [
            'product_id' => 'prod-1',
            'rating' => 5,
            'title' => 'Great!',
            'comment' => 'Excellent product',
        ];

        $this->reviewRepository->shouldReceive('create')
            ->once()
            ->with(Mockery::on(function ($arg) use ($userId) {
                return $arg['user_id'] === $userId && $arg['rating'] === 5;
            }))
            ->andReturn($this->mockReview());

        $this->reviewRepository->shouldReceive('updateProductRating')
            ->once()
            ->with('prod-1')
            ->andReturn();

        $result = $this->reviewService->create($userId, $data);

        $this->assertEquals('rev-1', $result['id']);
        $this->assertEquals(5, $result['rating']);
    }

    /** @test */
    public function create_throws_when_rating_out_of_range()
    {
        $this->expectException(AppError::class);
        $this->expectExceptionMessage('Rating must be between 1 and 5');

        $this->reviewService->create('user-1', [
            'product_id' => 'prod-1',
            'rating' => 6,
        ]);
    }

    /** @test */
    public function create_throws_when_rating_below_1()
    {
        $this->expectException(AppError::class);
        $this->expectExceptionMessage('Rating must be between 1 and 5');

        $this->reviewService->create('user-1', [
            'product_id' => 'prod-1',
            'rating' => 0,
        ]);
    }

    /** @test */
    public function getProductReviews_returns_paginated_reviews()
    {
        $productId = 'prod-1';

        $mockPaginator = new \Illuminate\Pagination\LengthAwarePaginator(
            [['id' => 'rev-1', 'rating' => 5]], 1, 10, 1
        );

        $this->reviewRepository->shouldReceive('getProductReviews')
            ->once()
            ->with($productId, 10)
            ->andReturn($mockPaginator);

        $result = $this->reviewService->getProductReviews($productId);

        $this->assertCount(1, $result['data']);
    }

    /** @test */
    public function getStats_returns_rating_stats()
    {
        $productId = 'prod-1';

        $mockStats = [
            'average' => 4.5,
            'total' => 10,
            'distribution' => [1 => 0, 2 => 0, 3 => 1, 4 => 3, 5 => 6],
        ];

        $this->reviewRepository->shouldReceive('getStats')
            ->once()
            ->with($productId)
            ->andReturn($mockStats);

        $result = $this->reviewService->getStats($productId);

        $this->assertEquals(4.5, $result['average']);
        $this->assertEquals(10, $result['total']);
    }

    /** @test */
    public function getUserReviews_returns_user_reviews()
    {
        $userId = 'user-1';

        $mockCollection = collect([
            ['id' => 'rev-1', 'rating' => 4, 'product_id' => 'prod-1'],
        ]);

        $this->reviewRepository->shouldReceive('getUserReviews')
            ->once()
            ->with($userId)
            ->andReturn($mockCollection);

        $result = $this->reviewService->getUserReviews($userId);

        $this->assertCount(1, $result);
    }

    /** @test */
    public function update_updates_own_review()
    {
        $reviewId = 'rev-1';
        $userId = 'user-1';
        $data = ['rating' => 4, 'comment' => 'Updated review'];

        $review = $this->mockReview();

        $this->reviewRepository->shouldReceive('findById')
            ->once()
            ->with($reviewId)
            ->andReturn($review);

        $updatedReview = $this->mockReview(['rating' => 4, 'comment' => 'Updated review']);
        $this->reviewRepository->shouldReceive('update')
            ->once()
            ->with($reviewId, $data)
            ->andReturn($updatedReview);

        $this->reviewRepository->shouldReceive('updateProductRating')
            ->once()
            ->with('prod-1')
            ->andReturn();

        $result = $this->reviewService->update($reviewId, $userId, $data);

        $this->assertEquals(4, $result['rating']);
    }

    /** @test */
    public function update_throws_when_not_owner()
    {
        $review = $this->mockReview(['user_id' => 'other-user']);

        $this->reviewRepository->shouldReceive('findById')
            ->once()
            ->with('rev-1')
            ->andReturn($review);

        $this->expectException(AppError::class);
        $this->expectExceptionMessage('Review not found');

        $this->reviewService->update('rev-1', 'user-1', ['rating' => 3]);
    }

    /** @test */
    public function update_throws_when_review_not_found()
    {
        $this->reviewRepository->shouldReceive('findById')
            ->once()
            ->with('nonexistent')
            ->andReturn(null);

        $this->expectException(AppError::class);
        $this->expectExceptionMessage('Review not found');

        $this->reviewService->update('nonexistent', 'user-1', ['rating' => 3]);
    }

    /** @test */
    public function delete_deletes_own_review_and_updates_rating()
    {
        $reviewId = 'rev-1';
        $userId = 'user-1';

        $review = $this->mockReview();

        $this->reviewRepository->shouldReceive('findById')
            ->once()
            ->with($reviewId)
            ->andReturn($review);

        $this->reviewRepository->shouldReceive('delete')
            ->once()
            ->with($reviewId)
            ->andReturn(true);

        $this->reviewRepository->shouldReceive('updateProductRating')
            ->once()
            ->with('prod-1')
            ->andReturn();

        $this->reviewService->delete($reviewId, $userId);

        $this->assertTrue(true); // No exception means success
    }

    /** @test */
    public function delete_throws_when_not_owner()
    {
        $review = $this->mockReview(['user_id' => 'other-user']);

        $this->reviewRepository->shouldReceive('findById')
            ->once()
            ->with('rev-1')
            ->andReturn($review);

        $this->expectException(AppError::class);
        $this->expectExceptionMessage('Review not found');

        $this->reviewService->delete('rev-1', 'user-1');
    }

    /** @test */
    public function delete_throws_when_review_not_found()
    {
        $this->reviewRepository->shouldReceive('findById')
            ->once()
            ->with('nonexistent')
            ->andReturn(null);

        $this->expectException(AppError::class);
        $this->expectExceptionMessage('Review not found');

        $this->reviewService->delete('nonexistent', 'user-1');
    }

    /** @test */
    public function moderate_updates_review_status()
    {
        $reviewId = 'rev-1';
        $review = $this->mockReview();
        $moderatedReview = $this->mockReview(['status' => 'APPROVED']);

        $this->reviewRepository->shouldReceive('findByIdOrFail')
            ->once()
            ->with($reviewId)
            ->andReturn($review);

        $this->reviewRepository->shouldReceive('update')
            ->once()
            ->with($reviewId, ['status' => 'APPROVED'])
            ->andReturn($moderatedReview);

        $result = $this->reviewService->moderate($reviewId, 'APPROVED');

        $this->assertEquals('APPROVED', $result['status']);
    }
}
