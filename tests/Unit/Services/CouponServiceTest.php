<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Services\CouponService;
use App\Repositories\CouponRepository;
use App\Exceptions\AppError;
use Mockery;

class CouponServiceTest extends TestCase
{
    protected CouponRepository|\Mockery\MockInterface $couponRepository;
    protected CouponService $couponService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->couponRepository = Mockery::mock(CouponRepository::class);
        $this->couponService = new CouponService($this->couponRepository);
    }

    protected function tearDown(): void
    {
        // Verify but don't close — preserves alias mocks across tests
        if ($container = Mockery::getContainer()) {
            $container->mockery_verify();
        }
        parent::tearDown();
    }

    protected function mockCoupon(array $overrides = []): object
    {
        $coupon = Mockery::mock();
        $coupon->shouldReceive('toArray')->andReturn([
            'id' => $overrides['id'] ?? 'coup-1',
            'code' => $overrides['code'] ?? 'SAVE10',
            'discount_type' => $overrides['discount_type'] ?? 'PERCENTAGE',
            'type' => $overrides['type'] ?? 'PERCENTAGE',
            'discount_value' => $overrides['discount_value'] ?? 10,
            'is_active' => $overrides['is_active'] ?? true,
            'max_discount' => $overrides['max_discount'] ?? null,
            'min_order_value' => $overrides['min_order_value'] ?? null,
            'usage_limit' => $overrides['usage_limit'] ?? null,
            'usage_per_user' => $overrides['usage_per_user'] ?? null,
            'usage_count' => $overrides['usage_count'] ?? 0,
            'start_date' => $overrides['start_date'] ?? null,
            'expiry_date' => $overrides['expiry_date'] ?? null,
        ]);

        $coupon->id = $overrides['id'] ?? 'coup-1';
        $coupon->code = $overrides['code'] ?? 'SAVE10';
        $coupon->discount_type = $overrides['discount_type'] ?? 'PERCENTAGE';
        $coupon->is_active = $overrides['is_active'] ?? true;
        $coupon->discount_value = $overrides['discount_value'] ?? 10;
        $coupon->max_discount = $overrides['max_discount'] ?? null;
        $coupon->min_order_value = $overrides['min_order_value'] ?? null;
        $coupon->usage_limit = $overrides['usage_limit'] ?? null;
        $coupon->usage_per_user = $overrides['usage_per_user'] ?? null;
        $coupon->usage_count = $overrides['usage_count'] ?? 0;
        $coupon->start_date = $overrides['start_date'] ?? null;
        $coupon->expiry_date = $overrides['expiry_date'] ?? null;

        return $coupon;
    }

    /** @test */
    public function create_creates_coupon()
    {
        $data = [
            'code' => 'NEW20',
            'discount_type' => 'PERCENTAGE',
            'discount_value' => 20,
        ];

        $this->couponRepository->shouldReceive('create')
            ->once()
            ->with($data)
            ->andReturn($this->mockCoupon([
                'code' => 'NEW20',
                'discount_value' => 20,
            ]));

        $result = $this->couponService->create($data);

        $this->assertEquals('NEW20', $result['code']);
        $this->assertEquals(20, $result['discount_value']);
    }

    /** @test */
    public function create_throws_when_code_empty()
    {
        $this->expectException(AppError::class);
        $this->expectExceptionMessage('Coupon code is required');

        $this->couponService->create(['code' => '']);
    }

    /** @test */
    public function create_throws_when_discount_type_missing()
    {
        $this->expectException(AppError::class);
        $this->expectExceptionMessage('Discount type is required');

        $this->couponService->create(['code' => 'CODE1', 'discount_value' => 10]);
    }

    /** @test */
    public function create_throws_when_discount_value_zero()
    {
        $this->expectException(AppError::class);
        $this->expectExceptionMessage('Discount value must be positive');

        $this->couponService->create([
            'code' => 'CODE1',
            'discount_type' => 'FLAT',
            'discount_value' => 0,
        ]);
    }

    /** @test */
    public function create_validates_date_order()
    {
        $this->expectException(AppError::class);
        $this->expectExceptionMessage('Expiry date must be after start date');

        $this->couponService->create([
            'code' => 'BAD',
            'discount_type' => 'FLAT',
            'discount_value' => 10,
            'start_date' => '2025-12-31',
            'expiry_date' => '2025-01-01',
        ]);
    }

    /** @test */
    public function validateCoupon_returns_valid_coupon()
    {
        $coupon = $this->mockCoupon();

        $this->couponRepository->shouldReceive('findActiveByCode')
            ->once()
            ->with('SAVE10')
            ->andReturn($coupon);

        $result = $this->couponService->validateCoupon('SAVE10');

        $this->assertEquals('SAVE10', $result['code']);
    }

    /** @test */
    public function validateCoupon_throws_for_inactive_coupon()
    {
        $this->couponRepository->shouldReceive('findActiveByCode')
            ->once()
            ->with('EXPIRED')
            ->andReturn(null);

        $this->expectException(AppError::class);
        $this->expectExceptionMessage('Invalid or expired coupon code');

        $this->couponService->validateCoupon('EXPIRED');
    }

    /** @test */
    public function getById_returns_coupon()
    {
        $coupon = $this->mockCoupon();

        $this->couponRepository->shouldReceive('findById')
            ->once()
            ->with('coup-1')
            ->andReturn($coupon);

        $result = $this->couponService->getById('coup-1');

        $this->assertEquals('SAVE10', $result['code']);
    }

    /** @test */
    public function getById_throws_when_not_found()
    {
        $this->couponRepository->shouldReceive('findById')
            ->once()
            ->with('nonexistent')
            ->andReturn(null);

        $this->expectException(AppError::class);
        $this->expectExceptionMessage('Coupon not found');

        $this->couponService->getById('nonexistent');
    }

    /** @test */
    public function update_updates_coupon()
    {
        $couponId = 'coup-1';
        $data = ['discount_value' => 25];

        $this->couponRepository->shouldReceive('findByIdOrFail')
            ->once()
            ->with($couponId)
            ->andReturn(true);

        $updated = $this->mockCoupon(['discount_value' => 25]);
        $this->couponRepository->shouldReceive('update')
            ->once()
            ->with($couponId, $data)
            ->andReturn($updated);

        $result = $this->couponService->update($couponId, $data);

        $this->assertEquals(25, $result['discount_value']);
    }

    /** @test */
    public function update_validates_date_order()
    {
        $couponId = 'coup-1';

        $this->expectException(AppError::class);
        $this->expectExceptionMessage('Expiry date must be after start date');

        $this->couponService->update($couponId, [
            'start_date' => '2025-12-31',
            'expiry_date' => '2025-01-01',
        ]);
    }

    /** @test */
    public function delete_deletes_coupon()
    {
        $couponId = 'coup-1';

        $this->couponRepository->shouldReceive('findByIdOrFail')
            ->once()
            ->with($couponId)
            ->andReturn(true);

        $this->couponRepository->shouldReceive('delete')
            ->once()
            ->with($couponId)
            ->andReturn(true);

        $this->couponService->delete($couponId);

        $this->assertTrue(true);  // No exception = success
    }

    /** @test */
    public function getAll_returns_paginated_coupons()
    {
        // CouponService::getAll uses Coupon model directly, no filters = no where() call
        \App\Models\Coupon::shouldReceive('query')
            ->once()
            ->andReturnSelf();
        \App\Models\Coupon::shouldReceive('count')
            ->once()
            ->andReturn(1);
        \App\Models\Coupon::shouldReceive('latest')
            ->once()
            ->andReturnSelf();
        \App\Models\Coupon::shouldReceive('skip')
            ->once()
            ->andReturnSelf();
        \App\Models\Coupon::shouldReceive('take')
            ->once()
            ->andReturnSelf();
        \App\Models\Coupon::shouldReceive('get')
            ->once()
            ->andReturn(new \Illuminate\Database\Eloquent\Collection([$this->mockCoupon()]));

        $result = $this->couponService->getAll();

        $this->assertCount(1, $result['data']);
    }

    /** @test */
    public function getAnalytics_returns_coupon_with_analytics()
    {
        $couponId = 'coup-1';

        $this->couponRepository->shouldReceive('findById')
            ->once()
            ->with($couponId)
            ->andReturn($this->mockCoupon());

        $this->couponRepository->shouldReceive('getAnalytics')
            ->once()
            ->with($couponId)
            ->andReturn(['total_usage' => 5, 'total_revenue' => 500]);

        $this->couponRepository->shouldReceive('getUsageHistory')
            ->once()
            ->with($couponId)
            ->andReturn(collect([]));

        $result = $this->couponService->getAnalytics($couponId);

        $this->assertEquals('SAVE10', $result['coupon']['code']);
        $this->assertEquals(5, $result['analytics']['total_usage']);
    }
}
