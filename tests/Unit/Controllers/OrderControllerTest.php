<?php

namespace Tests\Unit\Controllers;

use Tests\TestCase;
use App\Http\Controllers\Api\OrderController;
use App\Services\OrderService;
use App\Exceptions\AppError;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Mockery;

class OrderControllerTest extends TestCase
{
    protected OrderService|\Mockery\MockInterface $orderService;
    protected OrderController $controller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->orderService = Mockery::mock(OrderService::class);
        $this->controller = new OrderController($this->orderService);
    }

    protected function tearDown(): void
    {
        // Verify but don't close
        if ($container = Mockery::getContainer()) {
            $container->mockery_verify();
        }
        parent::tearDown();
    }

    /** @test */
    public function store_creates_order_and_returns_201()
    {
        $userId = 'user-1';
        Auth::shouldReceive('id')->once()->andReturn($userId);

        $request = Request::create('/orders', 'POST', [
            'shipping_address_id' => 'addr-1',
            'items' => [
                ['product_id' => 'prod-1', 'quantity' => 2, 'price' => 29.99],
            ],
            'shipping_cost' => 10.00,
        ]);

        $mockOrder = [
            'id' => 'order-1',
            'order_number' => 'ORD-12345678',
            'total' => 69.98,
            'status' => 'PENDING',
            'items' => [],
        ];

        $this->orderService->shouldReceive('createOrder')
            ->once()
            ->with($userId, Mockery::on(function ($arg) {
                return is_array($arg) && isset($arg['shipping_address_id']);
            }))
            ->andReturn($mockOrder);

        $response = $this->controller->store($request);

        $this->assertEquals(201, $response->getStatusCode());
        $data = $response->getData(true);
        $this->assertTrue($data['success']);
        $this->assertEquals('Order created', $data['message']);
        $this->assertEquals($mockOrder, $data['data']);
    }

    /** @test */
    public function store_returns_422_on_validation_error()
    {
        $request = Request::create('/orders', 'POST', [
            'items' => 'not-an-array',  // invalid
        ]);

        $response = $this->controller->store($request);

        $this->assertEquals(422, $response->getStatusCode());
    }

    /** @test */
    public function store_returns_422_when_items_empty()
    {
        $userId = 'user-1';
        Auth::shouldReceive('id')->once()->andReturn($userId);

        $request = Request::create('/orders', 'POST', [
            'shipping_address_id' => 'addr-1',
            'items' => [],
        ]);

        $this->orderService->shouldReceive('createOrder')
            ->once()
            ->andThrow(AppError::validation('Order must contain at least one item'));

        $response = $this->controller->store($request);

        $this->assertEquals(422, $response->getStatusCode());
        $data = $response->getData(true);
        $this->assertFalse($data['success']);
    }

    /** @test */
    public function index_returns_user_orders()
    {
        $userId = 'user-1';
        Auth::shouldReceive('id')->once()->andReturn($userId);

        request()->merge(['page' => 1, 'limit' => 15]);

        $paginatedOrders = [
            'data' => [
                ['id' => 'order-1', 'order_number' => 'ORD-001', 'status' => 'PENDING', 'total' => 100.00],
            ],
            'current_page' => 1,
            'per_page' => 15,
            'total' => 1,
            'last_page' => 1,
        ];

        $this->orderService->shouldReceive('getUserOrders')
            ->once()
            ->with($userId, ['page' => 1, 'limit' => 15])
            ->andReturn($paginatedOrders);

        $response = $this->controller->index();

        $this->assertEquals(200, $response->getStatusCode());
        $data = $response->getData(true);
        $this->assertTrue($data['success']);
    }

    /** @test */
    public function index_uses_default_pagination()
    {
        $userId = 'user-1';
        Auth::shouldReceive('id')->once()->andReturn($userId);

        $this->orderService->shouldReceive('getUserOrders')
            ->once()
            ->with($userId, [])
            ->andReturn(['data' => [], 'total' => 0]);

        $this->controller->index();
        // Assert passes if no exception thrown
        $this->assertTrue(true);
    }

    /** @test */
    public function show_returns_order_for_owner()
    {
        $userId = 'user-1';
        $user = new \stdClass();
        $user->id = $userId;
        $user->role = 'CUSTOMER';
        Auth::shouldReceive('id')->once()->andReturn($userId);
        Auth::shouldReceive('user')->once()->andReturn($user);

        $mockOrder = [
            'id' => 'order-1',
            'order_number' => 'ORD-001',
            'status' => 'PENDING',
            'user_id' => $userId,
        ];

        $this->orderService->shouldReceive('getOrder')
            ->once()
            ->with('order-1')
            ->andReturn($mockOrder);

        $response = $this->controller->show('order-1');

        $this->assertEquals(200, $response->getStatusCode());
        $data = $response->getData(true);
        $this->assertTrue($data['success']);
    }

    /** @test */
    public function show_returns_403_for_non_owner()
    {
        $userId = 'user-1';
        $user = new \stdClass();
        $user->id = $userId;
        $user->role = 'CUSTOMER';
        Auth::shouldReceive('id')->once()->andReturn($userId);
        Auth::shouldReceive('user')->once()->andReturn($user);

        $this->orderService->shouldReceive('getOrder')
            ->once()
            ->with('order-other')
            ->andReturn(['user_id' => 'other-user', 'status' => 'PENDING']);

        $response = $this->controller->show('order-other');

        $this->assertEquals(403, $response->getStatusCode());
    }

    /** @test */
    public function show_returns_404_when_order_not_found()
    {
        Auth::shouldReceive('id')->once()->andReturn('user-1');
        // When getOrder throws, Auth::user() is never reached

        $this->orderService->shouldReceive('getOrder')
            ->once()
            ->with('nonexistent')
            ->andThrow(AppError::notFound('Order not found'));

        $response = $this->controller->show('nonexistent');

        $this->assertEquals(404, $response->getStatusCode());
    }

    /** @test */
    public function cancel_returns_200_for_owner()
    {
        $userId = 'user-1';
        Auth::shouldReceive('id')->once()->andReturn($userId);

        $request = Request::create('/orders/order-1/cancel', 'PATCH', [
            'reason' => 'Changed my mind',
        ]);

        $this->orderService->shouldReceive('cancelOrder')
            ->once()
            ->with('order-1', 'Changed my mind')
            ->andReturn(['status' => 'CANCELLED', 'message' => 'Order cancelled']);

        $response = $this->controller->cancel('order-1', $request);

        $this->assertEquals(200, $response->getStatusCode());
        $data = $response->getData(true);
        $this->assertTrue($data['success']);
    }

    /** @test */
    public function cancel_returns_422_when_not_cancellable()
    {
        $userId = 'user-1';
        Auth::shouldReceive('id')->once()->andReturn($userId);

        $request = Request::create('/orders/delivered-1/cancel', 'PATCH', []);

        $this->orderService->shouldReceive('cancelOrder')
            ->once()
            ->andThrow(AppError::validation('Only pending or confirmed orders can be cancelled'));

        $response = $this->controller->cancel('delivered-1', $request);

        $this->assertEquals(422, $response->getStatusCode());
        $data = $response->getData(true);
        $this->assertFalse($data['success']);
    }

    /** @test */
    public function tracking_returns_tracking_info()
    {
        $orderNumber = 'ORD-001';

        $mockTracking = [
            'order_id' => 'order-1',
            'order_number' => $orderNumber,
            'status' => 'PENDING',
            'timeline' => [['status' => 'ORDER_PLACED', 'description' => 'Order placed']],
        ];

        $this->orderService->shouldReceive('getByOrderNumber')
            ->once()
            ->with($orderNumber)
            ->andReturn($mockTracking);

        $response = $this->controller->tracking($orderNumber);

        $this->assertEquals(200, $response->getStatusCode());
        $data = $response->getData(true);
        $this->assertTrue($data['success']);
        $this->assertEquals($orderNumber, $data['data']['order_number']);
    }

    /** @test */
    public function tracking_returns_404_for_invalid_order()
    {
        $this->orderService->shouldReceive('getByOrderNumber')
            ->once()
            ->with('INVALID')
            ->andThrow(AppError::notFound('Order not found'));

        $response = $this->controller->tracking('INVALID');

        $this->assertEquals(404, $response->getStatusCode());
    }
}
