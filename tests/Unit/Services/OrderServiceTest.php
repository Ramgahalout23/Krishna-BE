<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Services\OrderService;
use App\Repositories\OrderRepository;
use App\Repositories\CartRepository;
use App\Exceptions\AppError;
use Mockery;

class OrderServiceTest extends TestCase
{
    protected OrderRepository|\Mockery\MockInterface $orderRepository;
    protected CartRepository|\Mockery\MockInterface $cartRepository;
    protected OrderService $orderService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->orderRepository = Mockery::mock(OrderRepository::class);
        $this->cartRepository = Mockery::mock(CartRepository::class);
        $this->orderService = new OrderService($this->orderRepository, $this->cartRepository);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    protected function createMockOrder(array $overrides = []): object
    {
        $order = Mockery::mock();
        $order->shouldReceive('fresh')->andReturnSelf();
        $order->shouldReceive('load')->andReturnSelf();
        $order->shouldReceive('toArray')->andReturnUsing(function () use ($order) {
            return [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'user_id' => $order->user_id,
                'status' => $order->status,
                'subtotal' => $order->subtotal ?? 109.97,
                'total' => $order->total ?? 125.96,
                'shipping_cost' => $order->shipping_cost ?? 10.00,
                'discount' => $order->discount ?? 0,
                'notes' => $order->notes ?? null,
                'shipping_address_id' => $order->shipping_address_id ?? 'addr-1',
                'billing_address_id' => $order->billing_address_id ?? null,
                'created_at' => now()->toISOString(),
                'updated_at' => now()->toISOString(),
            ];
        });

        $order->id = $overrides['id'] ?? 'order-1';
        $order->order_number = $overrides['order_number'] ?? 'ORD-12345678';
        $order->user_id = $overrides['user_id'] ?? 'user-1';
        $order->status = $overrides['status'] ?? 'PENDING';
        $order->subtotal = $overrides['subtotal'] ?? 109.97;
        $order->total = $overrides['total'] ?? 125.96;
        $order->shipping_cost = $overrides['shipping_cost'] ?? 10.00;
        $order->shipping_address_id = $overrides['shipping_address_id'] ?? 'addr-1';
        $order->billing_address_id = $overrides['billing_address_id'] ?? null;
        $order->items = collect([]);
        $order->shipping = null;
        $order->payment = null;

        return $order;
    }

    /** @test */
    public function createOrder_creates_order_successfully()
    {
        $userId = 'user-1';
        $data = [
            'shipping_address_id' => 'addr-1',
            'items' => [
                ['product_id' => 'prod-1', 'quantity' => 2, 'price' => 29.99],
                ['product_id' => 'prod-2', 'quantity' => 1, 'price' => 49.99],
            ],
            'shipping_cost' => 10.00,
            'notes' => 'Handle with care',
        ];

        $mockOrder = $this->createMockOrder(['total' => 119.97]);

        $this->orderRepository->shouldReceive('create')
            ->once()
            ->with(Mockery::on(function ($arg) {
                return isset($arg['user_id']) && isset($arg['order_number'])
                    && $arg['status'] === 'PENDING';
            }))
            ->andReturn($mockOrder);

        $this->orderRepository->shouldReceive('createOrderItems')
            ->once()
            ->with(Mockery::on(function ($items) {
                return count($items) === 2;
            }))
            ->andReturn(true);

        $this->cartRepository->shouldReceive('clearCart')
            ->once()
            ->with($userId)
            ->andReturn(true);

        $result = $this->orderService->createOrder($userId, $data);

        $this->assertEquals('PENDING', $result['status']);
    }

    /** @test */
    public function createOrder_throws_when_items_empty()
    {
        $this->expectException(AppError::class);
        $this->expectExceptionMessage('Order must contain at least one item');

        $this->orderService->createOrder('user-1', [
            'shipping_address_id' => 'addr-1',
            'items' => [],
        ]);
    }

    /** @test */
    public function createOrder_throws_when_total_is_zero()
    {
        $this->expectException(AppError::class);
        $this->expectExceptionMessage('Invalid order total');

        $this->orderService->createOrder('user-1', [
            'shipping_address_id' => 'addr-1',
            'items' => [['product_id' => 'prod-1', 'quantity' => 0, 'price' => 0]],
        ]);
    }

    /** @test */
    public function createOrder_resolves_default_address()
    {
        $userId = 'user-1';

        $mockAddress = new \stdClass();
        $mockAddress->id = 'default-addr-1';

        $this->orderRepository->shouldReceive('getUserDefaultAddress')
            ->once()
            ->with($userId)
            ->andReturn($mockAddress);

        $this->orderRepository->shouldReceive('create')
            ->once()
            ->with(Mockery::on(function ($arg) {
                return $arg['shipping_address_id'] === 'default-addr-1';
            }))
            ->andReturn($this->createMockOrder());

        $this->orderRepository->shouldReceive('createOrderItems')
            ->once()
            ->andReturn(true);

        $this->cartRepository->shouldReceive('clearCart')
            ->once()
            ->andReturn(true);

        $this->orderService->createOrder($userId, [
            'shipping_address_id' => 'default',
            'items' => [['product_id' => 'prod-1', 'quantity' => 1, 'price' => 29.99]],
        ]);

        $this->assertTrue(true);
    }

    /** @test */
    public function createOrder_throws_when_no_default_address()
    {
        $this->orderRepository->shouldReceive('getUserDefaultAddress')
            ->once()
            ->with('user-1')
            ->andReturn(null);

        $this->expectException(AppError::class);
        $this->expectExceptionMessage('No default shipping address found');

        $this->orderService->createOrder('user-1', [
            'shipping_address_id' => 'default',
            'items' => [['product_id' => 'prod-1', 'quantity' => 1, 'price' => 29.99]],
        ]);
    }

    /** @test */
    public function getOrder_returns_order()
    {
        $mockOrder = $this->createMockOrder();

        $this->orderRepository->shouldReceive('findWithDetails')
            ->once()
            ->with('order-1')
            ->andReturn($mockOrder);

        $result = $this->orderService->getOrder('order-1');

        $this->assertEquals('order-1', $result['id']);
    }

    /** @test */
    public function getOrder_throws_when_not_found()
    {
        $this->orderRepository->shouldReceive('findWithDetails')
            ->once()
            ->with('nonexistent')
            ->andReturn(null);

        $this->expectException(AppError::class);
        $this->expectExceptionMessage('Order not found');

        $this->orderService->getOrder('nonexistent');
    }

    /** @test */
    public function cancelOrder_cancels_pending_order()
    {
        $mockOrder = $this->createMockOrder(['status' => 'PENDING']);

        $this->orderRepository->shouldReceive('findByIdOrFail')
            ->once()
            ->with('order-1')
            ->andReturn($mockOrder);

        $this->orderRepository->shouldReceive('update')
            ->once()
            ->with('order-1', Mockery::on(function ($arg) {
                return $arg['status'] === 'CANCELLED';
            }))
            ->andReturn($mockOrder);

        $result = $this->orderService->cancelOrder('order-1', 'Changed mind');

        $this->assertEquals('CANCELLED', $result['status']);
    }

    /** @test */
    public function cancelOrder_throws_for_delivered_order()
    {
        $mockOrder = $this->createMockOrder(['status' => 'DELIVERED']);

        $this->orderRepository->shouldReceive('findByIdOrFail')
            ->once()
            ->with('order-1')
            ->andReturn($mockOrder);

        $this->expectException(AppError::class);
        $this->expectExceptionMessage('Only pending or confirmed orders can be cancelled');

        $this->orderService->cancelOrder('order-1');
    }

    /** @test */
    public function updateStatus_validates_transition()
    {
        $mockOrder = $this->createMockOrder(['status' => 'PENDING']);

        $this->orderRepository->shouldReceive('findByIdOrFail')
            ->once()
            ->with('order-1')
            ->andReturn($mockOrder);

        $updatedOrder = $this->createMockOrder(['status' => 'CONFIRMED']);
        $this->orderRepository->shouldReceive('updateStatus')
            ->once()
            ->with('order-1', 'CONFIRMED')
            ->andReturn($updatedOrder);

        $result = $this->orderService->updateStatus('order-1', 'CONFIRMED');

        $this->assertEquals('CONFIRMED', $result['status']);
    }

    /** @test */
    public function updateStatus_rejects_invalid_transition()
    {
        $mockOrder = $this->createMockOrder(['status' => 'DELIVERED']);

        $this->orderRepository->shouldReceive('findByIdOrFail')
            ->once()
            ->with('order-1')
            ->andReturn($mockOrder);

        $this->expectException(AppError::class);
        $this->expectExceptionMessage('Cannot transition from DELIVERED to PENDING');

        $this->orderService->updateStatus('order-1', 'PENDING');
    }

    /** @test */
    public function getByOrderNumber_returns_order()
    {
        $mockOrder = $this->createMockOrder(['order_number' => 'ORD-001']);

        $this->orderRepository->shouldReceive('findByOrderNumber')
            ->once()
            ->with('ORD-001')
            ->andReturn($mockOrder);

        $result = $this->orderService->getByOrderNumber('ORD-001');

        $this->assertEquals('ORD-001', $result['order_number']);
    }

    /** @test */
    public function getByOrderNumber_throws_when_not_found()
    {
        $this->orderRepository->shouldReceive('findByOrderNumber')
            ->once()
            ->with('INVALID')
            ->andReturn(null);

        $this->expectException(AppError::class);
        $this->expectExceptionMessage('Order not found');

        $this->orderService->getByOrderNumber('INVALID');
    }

    /** @test */
    public function getUserOrders_returns_paginated_orders()
    {
        $userId = 'user-1';

        $mockPaginator = new \Illuminate\Pagination\LengthAwarePaginator(
            [['id' => 'order-1']], 1, 15, 1
        );

        $this->orderRepository->shouldReceive('getUserOrders')
            ->once()
            ->with($userId, [])
            ->andReturn($mockPaginator);

        $result = $this->orderService->getUserOrders($userId, []);

        $this->assertCount(1, $result['data']);
    }

    /** @test */
    public function getOrderTracking_returns_tracking_with_timeline()
    {
        $userId = 'user-1';
        $mockOrder = $this->createMockOrder([
            'shipping_address_id' => 'addr-1',
        ]);
        $shipping = new \stdClass();
        $shipping->estimated_delivery = '2024-12-01';
        $shipping->tracking_number = 'TRACK-001';
        $shipping->carrier = 'UPS';
        $mockOrder->shipping = $shipping;

        $this->orderRepository->shouldReceive('findWithDetails')
            ->once()
            ->with('order-1')
            ->andReturn($mockOrder);

        $result = $this->orderService->getOrderTracking('order-1', $userId);

        $this->assertEquals('order-1', $result['order_id']);
        $this->assertNotEmpty($result['timeline']);
        $this->assertEquals('TRACK-001', $result['tracking_number']);
    }

    /** @test */
    public function getOrderTracking_throws_for_other_user()
    {
        $mockOrder = $this->createMockOrder(['user_id' => 'other-user']);

        $this->orderRepository->shouldReceive('findWithDetails')
            ->once()
            ->with('order-1')
            ->andReturn($mockOrder);

        $this->expectException(AppError::class);
        $this->expectExceptionMessage('You do not have permission');

        $this->orderService->getOrderTracking('order-1', 'user-1');
    }
}
