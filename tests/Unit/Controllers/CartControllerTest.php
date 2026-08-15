<?php

namespace Tests\Unit\Controllers;

use Tests\TestCase;
use App\Http\Controllers\Api\CartController;
use App\Services\CartService;
use App\Exceptions\AppError;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Mockery;

class CartControllerTest extends TestCase
{
    protected CartService|\Mockery\MockInterface $cartService;
    protected CartController $controller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cartService = Mockery::mock(CartService::class);
        $this->controller = new CartController($this->cartService);
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
    public function index_returns_cart_data()
    {
        $userId = 'user-1';
        Auth::shouldReceive('id')->once()->andReturn($userId);

        $mockCart = [
            'items' => [
                ['id' => 'ci-1', 'product_id' => 'prod-1', 'quantity' => 2, 'price' => 29.99, 'total' => 59.98],
            ],
            'total' => 59.98,
            'count' => 2,
        ];

        $this->cartService->shouldReceive('getCart')
            ->once()
            ->with($userId)
            ->andReturn($mockCart);

        $response = $this->controller->index();

        $this->assertEquals(200, $response->getStatusCode());
        $data = $response->getData(true);
        $this->assertTrue($data['success']);
        $this->assertEquals($mockCart, $data['data']);
    }

    /** @test */
    public function addItem_returns_201_and_created_item()
    {
        $userId = 'user-1';
        Auth::shouldReceive('id')->once()->andReturn($userId);

        $request = Request::create('/cart', 'POST', [
            'product_id' => 'prod-1',
            'quantity' => 2,
        ]);

        $mockItem = [
            'id' => 'ci-1',
            'product_id' => 'prod-1',
            'quantity' => 2,
            'price' => 29.99,
            'total' => 59.98,
        ];

        $this->cartService->shouldReceive('addItem')
            ->once()
            ->with('prod-1', 2, $userId)
            ->andReturn($mockItem);

        $response = $this->controller->addItem($request);

        $this->assertEquals(201, $response->getStatusCode());
        $data = $response->getData(true);
        $this->assertTrue($data['success']);
        $this->assertEquals('Added to cart', $data['message']);
        $this->assertEquals($mockItem, $data['data']);
    }

    /** @test */
    public function addItem_returns_422_on_validation_error()
    {
        // No Auth::shouldReceive('id') — validation fails before Auth is called

        $request = Request::create('/cart', 'POST', [
            'product_id' => '',  // empty
            'quantity' => 0,      // invalid
        ]);

        $response = $this->controller->addItem($request);

        $this->assertEquals(422, $response->getStatusCode());
    }

    /** @test */
    public function addItem_returns_404_when_product_not_found()
    {
        $userId = 'user-1';
        Auth::shouldReceive('id')->once()->andReturn($userId);

        $request = Request::create('/cart', 'POST', [
            'product_id' => 'nonexistent',
            'quantity' => 1,
        ]);

        $this->cartService->shouldReceive('addItem')
            ->once()
            ->with('nonexistent', 1, $userId)
            ->andThrow(AppError::notFound('Product not found'));

        $response = $this->controller->addItem($request);

        $this->assertEquals(404, $response->getStatusCode());
        $data = $response->getData(true);
        $this->assertFalse($data['success']);
    }

    /** @test */
    public function addItem_returns_422_on_insufficient_stock()
    {
        $userId = 'user-1';
        Auth::shouldReceive('id')->once()->andReturn($userId);

        $request = Request::create('/cart', 'POST', [
            'product_id' => 'prod-1',
            'quantity' => 999,
        ]);

        $this->cartService->shouldReceive('addItem')
            ->once()
            ->with('prod-1', 999, $userId)
            ->andThrow(AppError::validation('Not enough stock available'));

        $response = $this->controller->addItem($request);

        $this->assertEquals(422, $response->getStatusCode());
        $data = $response->getData(true);
        $this->assertFalse($data['success']);
    }

    /** @test */
    public function updateItem_returns_200_and_updated_item()
    {
        $request = Request::create('/cart/update', 'PATCH', [
            'product_id' => 'prod-1',
            'quantity' => 5,
        ]);

        $userId = 'user-1';
        Auth::shouldReceive('id')->once()->andReturn($userId);

        $mockResult = ['id' => 'ci-1', 'quantity' => 5, 'total' => 149.95];

        $this->cartService->shouldReceive('updateItemByProduct')
            ->once()
            ->with($userId, 'prod-1', 5)
            ->andReturn($mockResult);

        $response = $this->controller->updateItem($request);

        $this->assertEquals(200, $response->getStatusCode());
        $data = $response->getData(true);
        $this->assertTrue($data['success']);
        $this->assertEquals('Cart updated', $data['message']);
        $this->assertEquals($mockResult, $data['data']);
    }

    /** @test */
    public function removeItem_returns_200_and_message()
    {
        $userId = 'user-1';
        Auth::shouldReceive('id')->once()->andReturn($userId);

        $this->cartService->shouldReceive('removeItemByProduct')
            ->once()
            ->with($userId, 'prod-1')
            ->andReturn(['message' => 'Item removed from cart']);

        $response = $this->controller->removeItem('prod-1');

        $this->assertEquals(200, $response->getStatusCode());
        $data = $response->getData(true);
        $this->assertTrue($data['success']);
        $this->assertEquals('Item removed from cart', $data['message']);
    }

    /** @test */
    public function removeItem_returns_404_when_item_not_found()
    {
        $userId = 'user-1';
        Auth::shouldReceive('id')->once()->andReturn($userId);

        $this->cartService->shouldReceive('removeItemByProduct')
            ->once()
            ->with($userId, 'nonexistent')
            ->andThrow(AppError::notFound('Cart item not found'));

        $response = $this->controller->removeItem('nonexistent');

        $this->assertEquals(404, $response->getStatusCode());
    }

    /** @test */
    public function clear_returns_200_and_message()
    {
        $userId = 'user-1';
        Auth::shouldReceive('id')->once()->andReturn($userId);

        $this->cartService->shouldReceive('clearCart')
            ->once()
            ->with($userId)
            ->andReturn(['message' => 'Cart cleared']);

        $response = $this->controller->clear();

        $this->assertEquals(200, $response->getStatusCode());
        $data = $response->getData(true);
        $this->assertTrue($data['success']);
        $this->assertEquals('Cart cleared', $data['message']);
    }

    /** @test */
    public function validateCart_returns_validation_result()
    {
        $userId = 'user-1';
        Auth::shouldReceive('id')->once()->andReturn($userId);

        $mockResult = ['valid' => true, 'errors' => [], 'count' => 3];

        $this->cartService->shouldReceive('validateCart')
            ->once()
            ->with($userId)
            ->andReturn($mockResult);

        $response = $this->controller->validateCart();

        $this->assertEquals(200, $response->getStatusCode());
        $data = $response->getData(true);
        $this->assertTrue($data['success']);
        $this->assertTrue($data['data']['valid']);
        $this->assertEmpty($data['data']['errors']);
    }

    /** @test */
    public function validateCart_returns_errors_when_stock_insufficient()
    {
        $userId = 'user-1';
        Auth::shouldReceive('id')->once()->andReturn($userId);

        $mockResult = [
            'valid' => false,
            'errors' => ['Test Product has only 1 in stock'],
            'count' => 3,
        ];

        $this->cartService->shouldReceive('validateCart')
            ->once()
            ->with($userId)
            ->andReturn($mockResult);

        $response = $this->controller->validateCart();

        $this->assertEquals(200, $response->getStatusCode());
        $data = $response->getData(true);
        $this->assertFalse($data['data']['valid']);
        $this->assertNotEmpty($data['data']['errors']);
    }

    /** @test */
    public function mergeCart_with_session_merges_successfully()
    {
        $userId = 'user-1';
        Auth::shouldReceive('id')->once()->andReturn($userId);

        $request = Request::create('/cart/merge', 'POST', [
            'session_id' => 'session-abc-123',
        ]);

        $mergedCart = ['items' => [], 'total' => 0, 'count' => 0];

        $this->cartService->shouldReceive('mergeCart')
            ->once()
            ->with($userId, 'session-abc-123')
            ->andReturn($mergedCart);

        $response = $this->controller->mergeCart($request);

        $this->assertEquals(200, $response->getStatusCode());
        $data = $response->getData(true);
        $this->assertTrue($data['success']);
        $this->assertEquals('Cart merged successfully', $data['message']);
    }

    /** @test */
    public function mergeCart_with_items_merges_successfully()
    {
        $userId = 'user-1';
        Auth::shouldReceive('id')->times(3)->andReturn($userId);

        $request = Request::create('/cart/merge', 'POST', [
            'items' => [
                ['product_id' => 'prod-1', 'quantity' => 2],
                ['product_id' => 'prod-2', 'quantity' => 1],
            ],
        ]);

        $this->cartService->shouldReceive('addItem')
            ->once()
            ->with('prod-1', 2, $userId)
            ->andReturn(['id' => 'ci-1']);

        $this->cartService->shouldReceive('addItem')
            ->once()
            ->with('prod-2', 1, $userId)
            ->andReturn(['id' => 'ci-2']);

        $this->cartService->shouldReceive('getCart')
            ->once()
            ->with($userId)
            ->andReturn(['items' => [], 'total' => 0, 'count' => 0]);

        $response = $this->controller->mergeCart($request);

        $this->assertEquals(200, $response->getStatusCode());
    }

    /** @test */
    public function mergeCart_returns_422_when_no_data_provided()
    {
        $request = Request::create('/cart/merge', 'POST', []);

        $response = $this->controller->mergeCart($request);

        $this->assertEquals(422, $response->getStatusCode());
        $data = $response->getData(true);
        $this->assertFalse($data['success']);
        $this->assertEquals('Provide session_id or items array', $data['message']);
    }
}
