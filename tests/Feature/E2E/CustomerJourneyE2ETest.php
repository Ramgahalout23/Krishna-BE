<?php

namespace Tests\Feature\E2E;

use Tests\TestCase;
use App\Models\User;
use App\Models\Product;
use App\Models\Category;
use App\Models\Brand;
use App\Models\Address;
use App\Models\CartItem;
use App\Models\WishlistItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Review;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

class CustomerJourneyE2ETest extends TestCase
{
    use RefreshDatabase;

    protected string $apiPrefix = '/api/v1';
    protected User $user;
    protected string $token;
    protected Product $product;
    protected Product $product2;
    protected Category $category;
    protected Brand $brand;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test user
        $this->user = User::factory()->create([
            'role' => 'CUSTOMER',
            'is_active' => true,
        ]);
        $this->token = $this->user->createToken('auth-token')->plainTextToken;

        // Create test category and brand
        $this->category = Category::create([
            'name' => 'Test Category',
            'slug' => 'test-category',
            'is_active' => true,
        ]);

        $this->brand = Brand::create([
            'name' => 'Test Brand',
            'slug' => 'test-brand',
        ]);

        // Create test products
        $this->product = Product::create([
            'name' => 'Test Product 1',
            'slug' => 'test-product-1',
            'description' => 'A test product for cart testing',
            'short_description' => 'Test product',
            'price' => 49.99,
            'quantity' => 100,
            'sku' => 'TEST-SKU-001',
            'category_id' => $this->category->id,
            'brand_id' => $this->brand->id,
            'status' => 'PUBLISHED',
            'is_featured' => true,
        ]);

        $this->product2 = Product::create([
            'name' => 'Test Product 2',
            'slug' => 'test-product-2',
            'description' => 'Another test product',
            'short_description' => 'Test product 2',
            'price' => 29.99,
            'quantity' => 50,
            'sku' => 'TEST-SKU-002',
            'category_id' => $this->category->id,
            'brand_id' => $this->brand->id,
            'status' => 'PUBLISHED',
        ]);
    }

    protected function authHeaders(): array
    {
        return ['Authorization' => "Bearer {$this->token}"];
    }

    /** @test */
    public function test_complete_cart_workflow()
    {
        // ── Step 1: Get empty cart ──
        $emptyCartResponse = $this->withHeaders($this->authHeaders())
            ->getJson("{$this->apiPrefix}/cart");

        $emptyCartResponse->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'items' => [],
                    'count' => 0,
                    'total' => 0,
                ],
            ]);

        // ── Step 2: Add item to cart ──
        $addResponse = $this->withHeaders($this->authHeaders())
            ->postJson("{$this->apiPrefix}/cart", [
                'product_id' => $this->product->id,
                'quantity' => 2,
            ]);

        $addResponse->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => ['id', 'product_id', 'quantity', 'price', 'total'],
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Added to cart',
                'data' => [
                    'product_id' => $this->product->id,
                    'quantity' => 2,
                ],
            ]);

        $cartItemId = $addResponse->json('data.id');

        // ── Step 3: Add second product to cart ──
        $this->withHeaders($this->authHeaders())
            ->postJson("{$this->apiPrefix}/cart", [
                'product_id' => $this->product2->id,
                'quantity' => 1,
            ])
            ->assertStatus(201);

        // ── Step 4: Get cart (verify items) ──
        $cartResponse = $this->withHeaders($this->authHeaders())
            ->getJson("{$this->apiPrefix}/cart");

        $cartResponse->assertStatus(200);
        $this->assertCount(2, $cartResponse->json('data.items'));
        $this->assertEquals(3, $cartResponse->json('data.count'));

        // ── Step 5: Update cart item quantity ──
        $updateResponse = $this->withHeaders($this->authHeaders())
            ->patchJson("{$this->apiPrefix}/cart/{$this->product->id}", [
                'product_id' => $this->product->id,
                'quantity' => 5,
            ]);

        $updateResponse->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Cart updated',
            ]);

        // Verify updated quantity
        $cartAfterUpdate = $this->withHeaders($this->authHeaders())
            ->getJson("{$this->apiPrefix}/cart");

        $this->assertEquals(6, $cartAfterUpdate->json('data.count'));

        // ── Step 6: Validate cart ──
        $this->withHeaders($this->authHeaders())
            ->postJson("{$this->apiPrefix}/cart/validate")
            ->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'valid' => true,
                ],
            ]);

        // ── Step 7: Remove item from cart ──
        $this->withHeaders($this->authHeaders())
            ->deleteJson("{$this->apiPrefix}/cart/{$this->product2->id}")
            ->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Item removed from cart',
            ]);

        // Verify only 1 item remains
        $cartAfterRemove = $this->withHeaders($this->authHeaders())
            ->getJson("{$this->apiPrefix}/cart");

        $this->assertCount(1, $cartAfterRemove->json('data.items'));

        // ── Step 8: Clear cart ──
        $this->withHeaders($this->authHeaders())
            ->deleteJson("{$this->apiPrefix}/cart")
            ->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Cart cleared',
            ]);

        // Verify cart is empty
        $emptyResponse = $this->withHeaders($this->authHeaders())
            ->getJson("{$this->apiPrefix}/cart");

        $this->assertEquals(0, $emptyResponse->json('data.count'));
    }

    /** @test */
    public function test_wishlist_workflow()
    {
        // ── Step 1: Get empty wishlist ──
        $this->withHeaders($this->authHeaders())
            ->getJson("{$this->apiPrefix}/wishlist")
            ->assertStatus(200)
            ->assertJson(['success' => true]);

        // Wishlist count should be 0
        $this->withHeaders($this->authHeaders())
            ->getJson("{$this->apiPrefix}/wishlist/count")
            ->assertStatus(200);

        // ── Step 2: Add items to wishlist ──
        $this->withHeaders($this->authHeaders())
            ->postJson("{$this->apiPrefix}/wishlist", [
                'product_id' => $this->product->id,
            ])
            ->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->withHeaders($this->authHeaders())
            ->postJson("{$this->apiPrefix}/wishlist", [
                'product_id' => $this->product2->id,
            ])
            ->assertStatus(200);

        // ── Step 3: Check if product is in wishlist ──
        $this->withHeaders($this->authHeaders())
            ->getJson("{$this->apiPrefix}/wishlist/check/{$this->product->id}")
            ->assertStatus(200);

        // ── Step 4: Get wishlist with items ──
        $wishlistResponse = $this->withHeaders($this->authHeaders())
            ->getJson("{$this->apiPrefix}/wishlist");

        $wishlistResponse->assertStatus(200)
            ->assertJson(['success' => true]);

        // ── Step 5: Remove item from wishlist ──
        $this->withHeaders($this->authHeaders())
            ->deleteJson("{$this->apiPrefix}/wishlist/{$this->product->id}")
            ->assertStatus(200)
            ->assertJson(['success' => true]);

        // ── Step 6: Move item to cart ──
        $this->withHeaders($this->authHeaders())
            ->postJson("{$this->apiPrefix}/wishlist/{$this->product2->id}/move-to-cart")
            ->assertStatus(200);

        // ── Step 7: Bulk add to wishlist ──
        $this->withHeaders($this->authHeaders())
            ->postJson("{$this->apiPrefix}/wishlist/bulk", [
                'product_ids' => [$this->product->id, $this->product2->id],
            ])
            ->assertStatus(200);

        // ── Step 8: Clear entire wishlist ──
        $this->withHeaders($this->authHeaders())
            ->deleteJson("{$this->apiPrefix}/wishlist")
            ->assertStatus(200)
            ->assertJson(['success' => true]);
    }

    /** @test */
    public function test_address_management()
    {
        // ── Step 1: Add a shipping address ──
        $addressResponse = $this->withHeaders($this->authHeaders())
            ->postJson("{$this->apiPrefix}/addresses", [
                'type' => 'HOME',
                'first_name' => 'John',
                'last_name' => 'Doe',
                'phone_number' => '+1234567890',
                'address_line1' => '123 Main St',
                'address_line2' => 'Apt 4B',
                'city' => 'New York',
                'state' => 'NY',
                'zip_code' => '10001',
                'country' => 'US',
                'is_default' => true,
            ]);

        $addressResponse->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'data' => ['id', 'address_line1', 'city', 'state', 'zip_code', 'country'],
            ]);

        $addressId = $addressResponse->json('data.id');

        // ── Step 2: List addresses ──
        $listResponse = $this->withHeaders($this->authHeaders())
            ->getJson("{$this->apiPrefix}/addresses");

        $listResponse->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertCount(1, $listResponse->json('data'));

        // ── Step 3: Get single address ──
        $this->withHeaders($this->authHeaders())
            ->getJson("{$this->apiPrefix}/addresses/{$addressId}")
            ->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'address_line1' => '123 Main St',
                    'city' => 'New York',
                ],
            ]);

        // ── Step 4: Update address ──
        $this->withHeaders($this->authHeaders())
            ->putJson("{$this->apiPrefix}/addresses/{$addressId}", [
                'address_line1' => '456 Oak Ave',
                'city' => 'Brooklyn',
                'state' => 'NY',
                'zip_code' => '11201',
                'country' => 'US',
            ])
            ->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('addresses', [
            'id' => $addressId,
            'address_line1' => '456 Oak Ave',
            'city' => 'Brooklyn',
        ]);

        // ── Step 5: Delete address ──
        $this->withHeaders($this->authHeaders())
            ->deleteJson("{$this->apiPrefix}/addresses/{$addressId}")
            ->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertDatabaseMissing('addresses', ['id' => $addressId]);
    }

    /** @test */
    public function test_order_workflow()
    {
        // ── Step 1: Create an address for the order ──
        $address = Address::create([
            'user_id' => $this->user->id,
            'type' => 'HOME',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'phone_number' => '+1234567890',
            'address_line1' => '123 Main St',
            'city' => 'New York',
            'state' => 'NY',
            'zip_code' => '10001',
            'country' => 'US',
            'is_default' => true,
        ]);

        // ── Step 2: Create an order ──
        $orderResponse = $this->withHeaders($this->authHeaders())
            ->postJson("{$this->apiPrefix}/orders", [
                'shipping_address_id' => $address->id,
                'items' => [
                    [
                        'product_id' => $this->product->id,
                        'quantity' => 2,
                        'price' => $this->product->price,
                    ],
                    [
                        'product_id' => $this->product2->id,
                        'quantity' => 1,
                        'price' => $this->product2->price,
                    ],
                ],
                'shipping_cost' => 10.00,
                'notes' => 'Please deliver during business hours',
            ]);

        $orderResponse->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id', 'order_number', 'total', 'status', 'items',
                ],
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Order created',
                'data' => [
                    'status' => 'PENDING',
                ],
            ]);

        $orderId = $orderResponse->json('data.id');
        $orderNumber = $orderResponse->json('data.order_number');

        // ── Step 3: List user orders ──
        $ordersListResponse = $this->withHeaders($this->authHeaders())
            ->getJson("{$this->apiPrefix}/orders");

        $ordersListResponse->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertCount(1, $ordersListResponse->json('data.data'));

        // ── Step 4: Get order details ──
        $this->withHeaders($this->authHeaders())
            ->getJson("{$this->apiPrefix}/orders/{$orderId}")
            ->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $orderId,
                    'order_number' => $orderNumber,
                    'status' => 'PENDING',
                ],
            ]);

        // ── Step 5: Track order by order number ──
        $this->getJson("{$this->apiPrefix}/orders/track-by-number/{$orderNumber}")
            ->assertStatus(200)
            ->assertJson(['success' => true]);

        // ── Step 6: Cancel order ──
        $this->withHeaders($this->authHeaders())
            ->patchJson("{$this->apiPrefix}/orders/{$orderId}/cancel", [
                'reason' => 'Changed my mind',
            ])
            ->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Order cancelled',
            ]);

        // Verify order status changed in database
        $this->assertDatabaseHas('orders', [
            'id' => $orderId,
            'status' => 'CANCELLED',
        ]);
    }

    /** @test */
    public function test_review_workflow()
    {
        // ── Step 1: Create an order first (to write a verified review) ──
        $address = Address::create([
            'user_id' => $this->user->id,
            'type' => 'HOME',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'phone_number' => '+1234567890',
            'address_line1' => '123 Main St',
            'city' => 'New York',
            'state' => 'NY',
            'zip_code' => '10001',
            'country' => 'US',
        ]);

        $order = Order::create([
            'order_number' => 'ORD-' . strtoupper(Str::random(8)),
            'user_id' => $this->user->id,
            'shipping_address_id' => $address->id,
            'subtotal' => 79.98,
            'total' => 89.98,
            'shipping_cost' => 10.00,
            'status' => 'DELIVERED',
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'user_id' => $this->user->id,
            'product_id' => $this->product->id,
            'quantity' => 2,
            'price' => $this->product->price,
            'total' => 99.98,
        ]);

        // ── Step 2: Write a review for the purchased product ──
        $reviewResponse = $this->withHeaders($this->authHeaders())
            ->postJson("{$this->apiPrefix}/reviews", [
                'product_id' => $this->product->id,
                'order_id' => $order->id,
                'rating' => 5,
                'title' => 'Excellent product!',
                'comment' => 'Really loved this product. Highly recommended!',
            ]);

        $reviewResponse->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'data' => ['id', 'rating', 'title', 'comment', 'product_id'],
            ]);

        $reviewId = $reviewResponse->json('data.id');

        // ── Step 3: Get product reviews ──
        $this->getJson("{$this->apiPrefix}/reviews/product/{$this->product->id}")
            ->assertStatus(200)
            ->assertJson(['success' => true]);

        // ── Step 4: Get review stats ──
        $this->getJson("{$this->apiPrefix}/reviews/stats/{$this->product->id}")
            ->assertStatus(200)
            ->assertJson(['success' => true]);

        // ── Step 5: Get user's reviews ──
        $this->withHeaders($this->authHeaders())
            ->getJson("{$this->apiPrefix}/reviews/user")
            ->assertStatus(200)
            ->assertJson(['success' => true]);

        // ── Step 6: Update review ──
        $this->withHeaders($this->authHeaders())
            ->putJson("{$this->apiPrefix}/reviews/{$reviewId}", [
                'rating' => 4,
                'title' => 'Updated: Great product!',
                'comment' => 'Updated review: Still a great product.',
            ])
            ->assertStatus(200)
            ->assertJson(['success' => true]);

        // ── Step 7: Mark review as helpful ──
        $this->withHeaders($this->authHeaders())
            ->postJson("{$this->apiPrefix}/reviews/{$reviewId}/helpful")
            ->assertStatus(200);

        // ── Step 8: Delete review ──
        $this->withHeaders($this->authHeaders())
            ->deleteJson("{$this->apiPrefix}/reviews/{$reviewId}")
            ->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertDatabaseMissing('reviews', ['id' => $reviewId]);
    }

    /** @test */
    public function test_cart_validation_with_out_of_stock_product()
    {
        // Create product with low stock
        $lowStockProduct = Product::create([
            'name' => 'Low Stock Item',
            'slug' => 'low-stock-item',
            'description' => 'Almost out of stock',
            'short_description' => 'Low stock',
            'price' => 9.99,
            'quantity' => 1,
            'sku' => 'LOW-STK-001',
            'category_id' => $this->category->id,
            'brand_id' => $this->brand->id,
            'status' => 'PUBLISHED',
        ]);

        // Add to cart with quantity exceeding stock
        $this->withHeaders($this->authHeaders())
            ->postJson("{$this->apiPrefix}/cart", [
                'product_id' => $lowStockProduct->id,
                'quantity' => 5,
            ])
            ->assertStatus(422); // Validation error - not enough stock
    }

    /** @test */
    public function test_forbidden_order_access()
    {
        // Create an order belonging to another user
        $otherUser = User::factory()->create(['role' => 'CUSTOMER']);
        $address = Address::create([
            'user_id' => $otherUser->id,
            'type' => 'HOME',
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'phone_number' => '+1234567890',
            'address_line1' => '456 Other St',
            'city' => 'Los Angeles',
            'state' => 'CA',
            'zip_code' => '90001',
            'country' => 'US',
        ]);

        $order = Order::create([
            'order_number' => 'ORD-OTHER-001',
            'user_id' => $otherUser->id,
            'shipping_address_id' => $address->id,
            'subtotal' => 100.00,
            'total' => 110.00,
            'shipping_cost' => 10.00,
            'status' => 'PENDING',
        ]);

        // Current user should not be able to access another user's order
        $this->withHeaders($this->authHeaders())
            ->getJson("{$this->apiPrefix}/orders/{$order->id}")
            ->assertStatus(403);
    }
}
