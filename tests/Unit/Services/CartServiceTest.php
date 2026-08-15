<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Services\CartService;
use App\Repositories\CartRepository;
use App\Exceptions\AppError;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Mockery;

class CartServiceTest extends TestCase
{
    protected CartRepository|\Mockery\MockInterface $cartRepository;
    protected CartService $cartService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cartRepository = Mockery::mock(CartRepository::class);
        $this->cartService = new CartService($this->cartRepository);
    }

    protected function tearDown(): void
    {
        // Verify but don't close — preserves alias mocks across tests
        if ($container = Mockery::getContainer()) {
            $container->mockery_verify();
        }
        parent::tearDown();
    }

    protected function createMockProduct(array $overrides = []): object
    {
        $product = Mockery::mock();
        $product->id = $overrides['id'] ?? 'prod-1';
        $product->name = $overrides['name'] ?? 'Test Product';
        $product->price = $overrides['price'] ?? 29.99;
        $product->quantity = $overrides['quantity'] ?? 10;
        $product->status = $overrides['status'] ?? 'PUBLISHED';
        return $product;
    }

    protected function createMockCartItem(array $overrides = []): object
    {
        $item = Mockery::mock();
        $item->id = $overrides['id'] ?? 'ci-1';
        $item->user_id = $overrides['user_id'] ?? 'user-1';
        $item->product_id = $overrides['product_id'] ?? 'prod-1';
        $item->quantity = $overrides['quantity'] ?? 2;
        $item->price = $overrides['price'] ?? 29.99;
        $item->variant_id = $overrides['variant_id'] ?? null;
        $item->product = $overrides['product'] ?? $this->createMockProduct();
        return $item;
    }

    protected function createMockVariant(array $overrides = []): object
    {
        $variant = Mockery::mock();
        $variant->id = $overrides['id'] ?? 'var-1';
        $variant->product_id = $overrides['product_id'] ?? 'prod-1';
        $variant->quantity = $overrides['quantity'] ?? 5;
        $variant->attributes = $overrides['attributes'] ?? ['size' => 'M', 'color' => 'Black'];
        return $variant;
    }

    /** @test */
    public function getCart_returns_formatted_cart_for_user()
    {
        $userId = 'user-1';
        $cartItems = new EloquentCollection([
            $this->createMockCartItem(['quantity' => 2]),
            $this->createMockCartItem([
                'id' => 'ci-2',
                'product_id' => 'prod-2',
                'quantity' => 1,
                'price' => 49.99,
                'product' => $this->createMockProduct([
                    'id' => 'prod-2',
                    'name' => 'Product 2',
                    'price' => 49.99,
                ]),
            ]),
        ]);

        $this->cartRepository->shouldReceive('getUserCart')
            ->once()
            ->with($userId)
            ->andReturn($cartItems);

        $result = $this->cartService->getCart($userId);

        $this->assertCount(2, $result['items']);
        $this->assertEquals(3, $result['count']);
        $this->assertEquals(109.97, $result['total']);  // (29.99*2) + (49.99*1)
        $this->assertEquals('Test Product', $result['items'][0]['product_name']);
    }

    /** @test */
    public function getCart_returns_empty_cart_for_new_user()
    {
        $userId = 'new-user';
        $this->cartRepository->shouldReceive('getUserCart')
            ->once()
            ->with($userId)
            ->andReturn(new EloquentCollection([]));

        $result = $this->cartService->getCart($userId);

        $this->assertEmpty($result['items']);
        $this->assertEquals(0, $result['count']);
        $this->assertEquals(0, $result['total']);
    }

    /** @test */
    public function getCart_supports_guest_session()
    {
        $sessionId = 'session-abc';

        $this->cartRepository->shouldReceive('getCartBySession')
            ->once()
            ->with($sessionId)
            ->andReturn(new EloquentCollection([$this->createMockCartItem()]));

        $result = $this->cartService->getCart(null, $sessionId);

        $this->assertCount(1, $result['items']);
        $this->assertEquals(2, $result['count']);
    }

    /** @test */
    public function addItem_adds_new_item_successfully()
    {
        $product = $this->createMockProduct(['price' => 29.99]);

        \App\Models\Product::shouldReceive('find')
            ->once()
            ->with('prod-1')
            ->andReturn($product);

        $this->cartRepository->shouldReceive('addOrUpdateItem')
            ->once()
            ->with('user-1', 'prod-1', 2, null)
            ->andReturn($this->createMockCartItem(['price' => 29.99]));

        $result = $this->cartService->addItem('prod-1', 2, 'user-1');

        $this->assertEquals('ci-1', $result['id']);
        $this->assertEquals(2, $result['quantity']);
        $this->assertEquals(59.98, $result['total']);
    }

    /** @test */
    public function addItem_throws_when_quantity_less_than_1()
    {
        $this->expectException(AppError::class);
        $this->expectExceptionMessage('Quantity must be at least 1');

        $this->cartService->addItem('prod-1', 0, 'user-1');
    }

    /** @test */
    public function addItem_throws_when_product_not_found()
    {
        \App\Models\Product::shouldReceive('find')
            ->once()
            ->with('nonexistent')
            ->andReturn(null);

        $this->expectException(AppError::class);
        $this->expectExceptionMessage('Product not found');

        $this->cartService->addItem('nonexistent', 1, 'user-1');
    }

    /** @test */
    public function addItem_throws_when_stock_insufficient()
    {
        $product = $this->createMockProduct(['quantity' => 1]);

        \App\Models\Product::shouldReceive('find')
            ->once()
            ->with('prod-1')
            ->andReturn($product);

        $this->expectException(AppError::class);
        $this->expectExceptionMessage('Not enough stock available');

        $this->cartService->addItem('prod-1', 5, 'user-1');
    }

    /** @test */
    public function addItem_supports_guest_session()
    {
        $sessionId = 'session-abc';
        $product = $this->createMockProduct();

        \App\Models\Product::shouldReceive('find')
            ->once()
            ->with('prod-1')
            ->andReturn($product);

        $this->cartRepository->shouldReceive('addOrUpdateItem')
            ->once()
            ->with(null, 'prod-1', 1, $sessionId)
            ->andReturn($this->createMockCartItem());

        $result = $this->cartService->addItem('prod-1', 1, null, $sessionId);

        $this->assertEquals('ci-1', $result['id']);
    }

    /** @test */
    public function updateItem_updates_quantity()
    {
        $itemId = 'ci-1';
        $mockItem = $this->createMockCartItem(['price' => 29.99]);
        $updatedItem = $this->createMockCartItem(['quantity' => 5, 'price' => 29.99]);

        $this->cartRepository->shouldReceive('findItemById')
            ->once()
            ->with($itemId)
            ->andReturn($mockItem);

        $this->cartRepository->shouldReceive('updateItemQuantity')
            ->once()
            ->with($itemId, 5)
            ->andReturn($updatedItem);

        $result = $this->cartService->updateItem($itemId, 5);

        $this->assertEquals(5, $result['quantity']);
        $this->assertEquals(149.95, $result['total']);
    }

    /** @test */
    public function updateItem_throws_when_quantity_zero()
    {
        $this->expectException(AppError::class);
        $this->expectExceptionMessage('Quantity must be greater than 0');

        $this->cartService->updateItem('ci-1', 0);
    }

    /** @test */
    public function updateItem_throws_when_item_not_found()
    {
        $this->cartRepository->shouldReceive('findItemById')
            ->once()
            ->with('nonexistent')
            ->andReturn(null);

        $this->expectException(AppError::class);
        $this->expectExceptionMessage('Cart item not found');

        $this->cartService->updateItem('nonexistent', 3);
    }

    /** @test */
    public function removeItem_removes_item()
    {
        $itemId = 'ci-1';
        $mockItem = $this->createMockCartItem();

        $this->cartRepository->shouldReceive('findItemById')
            ->once()
            ->with($itemId)
            ->andReturn($mockItem);

        $this->cartRepository->shouldReceive('removeItemById')
            ->once()
            ->with($itemId)
            ->andReturn(true);

        $result = $this->cartService->removeItem($itemId);

        $this->assertEquals('Item removed from cart', $result['message']);
    }

    /** @test */
    public function removeItem_throws_when_item_not_found()
    {
        $this->cartRepository->shouldReceive('findItemById')
            ->once()
            ->with('nonexistent')
            ->andReturn(null);

        $this->expectException(AppError::class);
        $this->expectExceptionMessage('Cart item not found');

        $this->cartService->removeItem('nonexistent');
    }

    /** @test */
    public function clearCart_clears_all_items()
    {
        $userId = 'user-1';

        $this->cartRepository->shouldReceive('clearCart')
            ->once()
            ->with($userId)
            ->andReturn(true);

        $result = $this->cartService->clearCart($userId);

        $this->assertEquals('Cart cleared', $result['message']);
    }

    /** @test */
    public function validateCart_returns_valid_when_all_good()
    {
        $userId = 'user-1';
        $cartItems = new EloquentCollection([
            $this->createMockCartItem([
                'product' => $this->createMockProduct(['quantity' => 10]),
            ]),
        ]);

        $this->cartRepository->shouldReceive('getUserCart')
            ->once()
            ->with($userId)
            ->andReturn($cartItems);

        $this->cartRepository->shouldReceive('getCartCount')
            ->once()
            ->with($userId)
            ->andReturn(2);

        $result = $this->cartService->validateCart($userId);

        $this->assertTrue($result['valid']);
        $this->assertEmpty($result['errors']);
        $this->assertEquals(2, $result['count']);
    }

    /** @test */
    public function validateCart_returns_invalid_when_product_unpublished()
    {
        $userId = 'user-1';
        $cartItems = new EloquentCollection([
            $this->createMockCartItem([
                'product' => $this->createMockProduct(['status' => 'ARCHIVED']),
            ]),
        ]);

        $this->cartRepository->shouldReceive('getUserCart')
            ->once()
            ->with($userId)
            ->andReturn($cartItems);

        $result = $this->cartService->validateCart($userId);

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('no longer available', $result['errors'][0]);
    }

    /** @test */
    public function validateCart_returns_invalid_when_stock_insufficient()
    {
        $userId = 'user-1';
        $cartItems = new EloquentCollection([
            $this->createMockCartItem([
                'quantity' => 10,
                'product' => $this->createMockProduct(['quantity' => 3]),
            ]),
        ]);

        $this->cartRepository->shouldReceive('getUserCart')
            ->once()
            ->with($userId)
            ->andReturn($cartItems);

        $result = $this->cartService->validateCart($userId);

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('only 3 in stock', $result['errors'][0]);
    }

    /** @test */
    public function validateCart_returns_invalid_when_product_missing()
    {
        $userId = 'user-1';
        $cartItems = new EloquentCollection([
            $this->createMockCartItem([
                'product' => null,  // deleted product
            ]),
        ]);

        $this->cartRepository->shouldReceive('getUserCart')
            ->once()
            ->with($userId)
            ->andReturn($cartItems);

        $result = $this->cartService->validateCart($userId);

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('no longer available', $result['errors'][0]);
    }

    /** @test */
    public function removeItemByProduct_removes_item()
    {
        $userId = 'user-1';

        $this->cartRepository->shouldReceive('removeItem')
            ->once()
            ->with($userId, 'prod-1')
            ->andReturn(true);

        $result = $this->cartService->removeItemByProduct($userId, 'prod-1');

        $this->assertEquals('Item removed from cart', $result['message']);
    }

    /** @test */
    public function removeItemByProduct_throws_when_item_not_found()
    {
        $this->cartRepository->shouldReceive('removeItem')
            ->once()
            ->with('user-1', 'nonexistent')
            ->andReturn(false);

        $this->expectException(AppError::class);
        $this->expectExceptionMessage('Cart item not found');

        $this->cartService->removeItemByProduct('user-1', 'nonexistent');
    }

    /** @test */
    public function mergeCart_merges_and_returns_updated_cart()
    {
        $userId = 'user-1';
        $sessionId = 'session-abc';

        $this->cartRepository->shouldReceive('mergeGuestCart')
            ->once()
            ->with($userId, $sessionId)
            ->andReturn(true);

        $this->cartRepository->shouldReceive('getUserCart')
            ->once()
            ->with($userId)
            ->andReturn(new EloquentCollection([$this->createMockCartItem()]));

        $result = $this->cartService->mergeCart($userId, $sessionId);

        $this->assertCount(1, $result['items']);
    }

    // ── Variant-level stock validation for updateItem ──

    /** @test */
    public function updateItem_rejects_when_variant_stock_insufficient()
    {
        $itemId = 'ci-1';
        $variant = $this->createMockVariant(['quantity' => 3]);
        $mockItem = $this->createMockCartItem([
            'variant_id' => 'var-1',
            'product' => $this->createMockProduct(['quantity' => 10]),
        ]);

        $this->cartRepository->shouldReceive('findItemById')
            ->once()
            ->with($itemId)
            ->andReturn($mockItem);

        $variantMock = Mockery::mock('alias:' . \App\Models\ProductVariant::class);
        $variantMock->shouldReceive('find')
            ->once()
            ->with('var-1')
            ->andReturn($variant);

        $this->expectException(AppError::class);
        $this->expectExceptionMessage('Only 3 units available for this variant');

        $this->cartService->updateItem($itemId, 10);
    }

    /** @test */
    public function updateItem_accepts_when_variant_stock_sufficient()
    {
        $itemId = 'ci-1';
        $variant = $this->createMockVariant(['quantity' => 15]);
        $mockItem = $this->createMockCartItem([
            'variant_id' => 'var-1',
            'product' => $this->createMockProduct(['quantity' => 10]),
        ]);
        $updatedItem = $this->createMockCartItem([
            'variant_id' => 'var-1',
            'quantity' => 8,
            'price' => 29.99,
        ]);

        $this->cartRepository->shouldReceive('findItemById')
            ->once()
            ->with($itemId)
            ->andReturn($mockItem);

        $variantMock = Mockery::mock('alias:' . \App\Models\ProductVariant::class);
        $variantMock->shouldReceive('find')
            ->once()
            ->with('var-1')
            ->andReturn($variant);

        $this->cartRepository->shouldReceive('updateItemQuantity')
            ->once()
            ->with($itemId, 8)
            ->andReturn($updatedItem);

        $result = $this->cartService->updateItem($itemId, 8);

        $this->assertEquals(8, $result['quantity']);
        $this->assertEquals(239.92, $result['total']);
    }

    /** @test */
    public function updateItem_rejects_when_product_stock_insufficient_without_variant()
    {
        $itemId = 'ci-1';
        $mockItem = $this->createMockCartItem([
            'variant_id' => null,
            'product' => $this->createMockProduct(['quantity' => 2, 'name' => 'Limited Tee']),
        ]);

        $this->cartRepository->shouldReceive('findItemById')
            ->once()
            ->with($itemId)
            ->andReturn($mockItem);

        $this->expectException(AppError::class);
        $this->expectExceptionMessage('Only 2 units available for Limited Tee');

        $this->cartService->updateItem($itemId, 5);
    }

    // ── updateItemByProduct tests ──

    /** @test */
    public function updateItemByProduct_throws_when_quantity_zero()
    {
        $this->expectException(AppError::class);
        $this->expectExceptionMessage('Quantity must be greater than 0');

        $this->cartService->updateItemByProduct('user-1', 'prod-1', 0);
    }

    /** @test */
    public function updateItemByProduct_throws_when_item_not_found()
    {
        $userId = 'user-1';
        $productId = 'nonexistent';

        $query = Mockery::mock();
        $query->shouldReceive('where')
            ->with('user_id', $userId)
            ->andReturnSelf();
        $query->shouldReceive('where')
            ->with('product_id', $productId)
            ->andReturnSelf();
        $query->shouldReceive('first')
            ->once()
            ->andReturn(null);

        $cartItemMock = Mockery::mock('alias:' . \App\Models\CartItem::class);
        $cartItemMock->shouldReceive('where')
            ->once()
            ->andReturn($query);

        $this->expectException(AppError::class);
        $this->expectExceptionMessage('Cart item not found');

        $this->cartService->updateItemByProduct($userId, $productId, 3);
    }

    /** @test */
    public function updateItemByProduct_rejects_when_variant_stock_insufficient()
    {
        $userId = 'user-1';
        $productId = 'prod-1';
        $variantId = 'var-1';
        $variant = $this->createMockVariant(['id' => $variantId, 'quantity' => 3]);
        $mockProduct = $this->createMockProduct(['id' => $productId, 'quantity' => 10, 'name' => 'Variant Product']);

        $mockItem = Mockery::mock();
        $mockItem->id = 'ci-1';
        $mockItem->variant_id = $variantId;
        $mockItem->product_id = $productId;
        $mockItem->quantity = 1;

        $query = Mockery::mock();
        $query->shouldReceive('where')
            ->with('user_id', $userId)
            ->andReturnSelf();
        $query->shouldReceive('where')
            ->with('product_id', $productId)
            ->andReturnSelf();
        $query->shouldReceive('first')
            ->once()
            ->andReturn($mockItem);

        $cartItemMock = Mockery::mock('alias:' . \App\Models\CartItem::class);
        $cartItemMock->shouldReceive('where')
            ->once()
            ->andReturn($query);

        \App\Models\Product::shouldReceive('find')
            ->once()
            ->with($productId)
            ->andReturn($mockProduct);

        $variantMock = Mockery::mock('alias:' . \App\Models\ProductVariant::class);
        $variantMock->shouldReceive('find')
            ->once()
            ->with($variantId)
            ->andReturn($variant);

        $this->expectException(AppError::class);
        $this->expectExceptionMessage('Only 3 units available for this variant');

        $this->cartService->updateItemByProduct($userId, $productId, 10);
    }

    /** @test */
    public function updateItemByProduct_accepts_when_variant_stock_sufficient()
    {
        $userId = 'user-1';
        $productId = 'prod-1';
        $variantId = 'var-1';
        $variant = $this->createMockVariant(['id' => $variantId, 'quantity' => 10]);
        $mockProduct = $this->createMockProduct(['id' => $productId, 'quantity' => 10, 'name' => 'Variant Product']);

        $mockItem = Mockery::mock();
        $mockItem->id = 'ci-1';
        $mockItem->variant_id = $variantId;
        $mockItem->product_id = $productId;
        $mockItem->quantity = 1;
        $mockItem->price = 29.99;
        $mockItem->shouldReceive('update')
            ->once()
            ->with(['quantity' => 4])
            ->andReturn(true);
        $mockItem->shouldReceive('fresh')
            ->once()
            ->andReturnUsing(function () use ($mockItem) {
                $fresh = Mockery::mock();
                $fresh->id = $mockItem->id;
                $fresh->quantity = 4;
                $fresh->price = $mockItem->price;
                return $fresh;
            });

        $query = Mockery::mock();
        $query->shouldReceive('where')
            ->with('user_id', $userId)
            ->andReturnSelf();
        $query->shouldReceive('where')
            ->with('product_id', $productId)
            ->andReturnSelf();
        $query->shouldReceive('first')
            ->once()
            ->andReturn($mockItem);

        $cartItemMock = Mockery::mock('alias:' . \App\Models\CartItem::class);
        $cartItemMock->shouldReceive('where')
            ->once()
            ->andReturn($query);

        \App\Models\Product::shouldReceive('find')
            ->once()
            ->with($productId)
            ->andReturn($mockProduct);

        $variantMock = Mockery::mock('alias:' . \App\Models\ProductVariant::class);
        $variantMock->shouldReceive('find')
            ->once()
            ->with($variantId)
            ->andReturn($variant);

        $result = $this->cartService->updateItemByProduct($userId, $productId, 4);

        $this->assertEquals(4, $result['quantity']);
        $this->assertEquals(119.96, $result['total']);
    }

    /** @test */
    public function updateItemByProduct_rejects_when_product_stock_insufficient_without_variant()
    {
        $userId = 'user-1';
        $productId = 'prod-1';
        $mockProduct = $this->createMockProduct(['id' => $productId, 'quantity' => 2, 'name' => 'Limited Tee']);

        $mockItem = Mockery::mock();
        $mockItem->id = 'ci-1';
        $mockItem->variant_id = null;
        $mockItem->product_id = $productId;
        $mockItem->quantity = 1;

        $query = Mockery::mock();
        $query->shouldReceive('where')
            ->with('user_id', $userId)
            ->andReturnSelf();
        $query->shouldReceive('where')
            ->with('product_id', $productId)
            ->andReturnSelf();
        $query->shouldReceive('first')
            ->once()
            ->andReturn($mockItem);

        $cartItemMock = Mockery::mock('alias:' . \App\Models\CartItem::class);
        $cartItemMock->shouldReceive('where')
            ->once()
            ->andReturn($query);

        \App\Models\Product::shouldReceive('find')
            ->once()
            ->with($productId)
            ->andReturn($mockProduct);

        $this->expectException(AppError::class);
        $this->expectExceptionMessage('Only 2 units available for Limited Tee');

        $this->cartService->updateItemByProduct($userId, $productId, 10);
    }

    /** @test */
    public function updateItemByProduct_accepts_when_product_stock_sufficient_without_variant()
    {
        $userId = 'user-1';
        $productId = 'prod-1';
        $mockProduct = $this->createMockProduct(['id' => $productId, 'quantity' => 20, 'name' => 'Well Stocked']);

        $mockItem = Mockery::mock();
        $mockItem->id = 'ci-1';
        $mockItem->variant_id = null;
        $mockItem->product_id = $productId;
        $mockItem->quantity = 1;
        $mockItem->price = 29.99;
        $mockItem->shouldReceive('update')
            ->once()
            ->with(['quantity' => 6])
            ->andReturn(true);
        $mockItem->shouldReceive('fresh')
            ->once()
            ->andReturnUsing(function () use ($mockItem) {
                $fresh = Mockery::mock();
                $fresh->id = $mockItem->id;
                $fresh->quantity = 6;
                $fresh->price = $mockItem->price;
                return $fresh;
            });

        $query = Mockery::mock();
        $query->shouldReceive('where')
            ->with('user_id', $userId)
            ->andReturnSelf();
        $query->shouldReceive('where')
            ->with('product_id', $productId)
            ->andReturnSelf();
        $query->shouldReceive('first')
            ->once()
            ->andReturn($mockItem);

        $cartItemMock = Mockery::mock('alias:' . \App\Models\CartItem::class);
        $cartItemMock->shouldReceive('where')
            ->once()
            ->andReturn($query);

        \App\Models\Product::shouldReceive('find')
            ->once()
            ->with($productId)
            ->andReturn($mockProduct);

        $result = $this->cartService->updateItemByProduct($userId, $productId, 6);

        $this->assertEquals(6, $result['quantity']);
        $this->assertEquals(179.94, $result['total']);
    }
}
