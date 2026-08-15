<?php

namespace Tests\Feature\E2E;

use Tests\TestCase;
use App\Models\User;
use App\Models\Product;
use App\Models\Category;
use App\Models\Brand;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Address;
use App\Models\Review;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

class AdminE2ETest extends TestCase
{
    use RefreshDatabase;

    protected string $apiPrefix = '/api/v1/admin';
    protected User $admin;
    protected string $adminToken;
    protected User $customer;
    protected Category $category;
    protected Brand $brand;

    protected function setUp(): void
    {
        parent::setUp();

        // Create admin user
        $this->admin = User::factory()->create([
            'first_name' => 'Admin',
            'last_name' => 'User',
            'email' => 'admin@example.com',
            'role' => 'SUPER_ADMIN',
            'is_active' => true,
        ]);
        $this->adminToken = $this->admin->createToken('auth-token')->plainTextToken;

        // Create regular customer
        $this->customer = User::factory()->create([
            'first_name' => 'Regular',
            'last_name' => 'Customer',
            'email' => 'customer@example.com',
            'role' => 'CUSTOMER',
            'is_active' => true,
        ]);

        // Create test data
        $this->category = Category::create([
            'name' => 'Admin Test Category',
            'slug' => 'admin-test-category',
            'is_active' => true,
        ]);

        $this->brand = Brand::create([
            'name' => 'Admin Test Brand',
            'slug' => 'admin-test-brand',
        ]);
    }

    protected function adminHeaders(): array
    {
        return ['Authorization' => "Bearer {$this->adminToken}"];
    }

    /** @test */
    public function test_admin_dashboard_and_analytics()
    {
        // ── Step 1: Dashboard metrics ──
        $this->withHeaders($this->adminHeaders())
            ->getJson("{$this->apiPrefix}/dashboard/metrics")
            ->assertStatus(200)
            ->assertJson(['success' => true]);

        // ── Step 2: Dashboard summary ──
        $this->withHeaders($this->adminHeaders())
            ->getJson("{$this->apiPrefix}/dashboard/summary")
            ->assertStatus(200)
            ->assertJson(['success' => true]);

        // ── Step 3: System health ──
        $this->withHeaders($this->adminHeaders())
            ->getJson("{$this->apiPrefix}/dashboard/health")
            ->assertStatus(200)
            ->assertJson(['success' => true]);
    }

    /** @test */
    public function test_admin_product_crud()
    {
        // ── Step 1: Create a product ──
        $createResponse = $this->withHeaders($this->adminHeaders())
            ->postJson("{$this->apiPrefix}/products", [
                'name' => 'Admin Created Product',
                'description' => 'Created by admin through API',
                'price' => 199.99,
                'sku' => 'ADM-SKU-001',
                'category_id' => $this->category->id,
                'quantity' => 25,
                'status' => 'PUBLISHED',
            ]);

        $createResponse->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Product created',
            ]);

        $productId = $createResponse->json('data.id');
        $this->assertNotNull($productId);

        // ── Step 2: List admin products ──
        $this->withHeaders($this->adminHeaders())
            ->getJson("{$this->apiPrefix}/products")
            ->assertStatus(200)
            ->assertJson(['success' => true]);

        // ── Step 3: Update the product ──
        $this->withHeaders($this->adminHeaders())
            ->putJson("{$this->apiPrefix}/products/{$productId}", [
                'name' => 'Updated Admin Product',
                'price' => 249.99,
            ])
            ->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Product updated',
            ]);

        // ── Step 4: Publish/archive product ──
        $this->withHeaders($this->adminHeaders())
            ->patchJson("{$this->apiPrefix}/products/{$productId}/publish")
            ->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->withHeaders($this->adminHeaders())
            ->patchJson("{$this->apiPrefix}/products/{$productId}/archive")
            ->assertStatus(200)
            ->assertJson(['success' => true]);

        // ── Step 5: Delete product ──
        $this->withHeaders($this->adminHeaders())
            ->deleteJson("{$this->apiPrefix}/products/{$productId}")
            ->assertStatus(200)
            ->assertJson(['success' => true, 'message' => 'Product deleted']);

        $this->assertDatabaseMissing('products', ['id' => $productId]);
    }

    /** @test */
    public function test_admin_category_brand_management()
    {
        // ── Step 1: Create category ──
        $catResponse = $this->withHeaders($this->adminHeaders())
            ->postJson("{$this->apiPrefix}/categories", [
                'name' => 'New Category',
                'slug' => 'new-category',
                'description' => 'A brand new category',
            ]);

        $catResponse->assertStatus(201)
            ->assertJson(['success' => true]);

        $categoryId = $catResponse->json('data.id');

        // ── Step 2: Update category ──
        $this->withHeaders($this->adminHeaders())
            ->putJson("{$this->apiPrefix}/categories/{$categoryId}", [
                'name' => 'Updated Category Name',
            ])
            ->assertStatus(200)
            ->assertJson(['success' => true]);

        // ── Step 3: Delete category ──
        $this->withHeaders($this->adminHeaders())
            ->deleteJson("{$this->apiPrefix}/categories/{$categoryId}")
            ->assertStatus(200)
            ->assertJson(['success' => true]);

        // ── Step 4: Create brand ──
        $brandResponse = $this->withHeaders($this->adminHeaders())
            ->postJson("{$this->apiPrefix}/brands", [
                'name' => 'New Brand',
                'slug' => 'new-brand',
            ]);

        $brandResponse->assertStatus(201)
            ->assertJson(['success' => true]);

        $brandId = $brandResponse->json('data.id');

        // ── Step 5: Update brand ──
        $this->withHeaders($this->adminHeaders())
            ->putJson("{$this->apiPrefix}/brands/{$brandId}", [
                'name' => 'Updated Brand',
            ])
            ->assertStatus(200);

        // ── Step 6: Delete brand ──
        $this->withHeaders($this->adminHeaders())
            ->deleteJson("{$this->apiPrefix}/brands/{$brandId}")
            ->assertStatus(200)
            ->assertJson(['success' => true]);
    }

    /** @test */
    public function test_admin_order_management()
    {
        // ── Setup: Create an order ──
        $address = Address::create([
            'user_id' => $this->customer->id,
            'type' => 'HOME',
            'first_name' => 'Regular',
            'last_name' => 'Customer',
            'phone_number' => '+1234567890',
            'address_line1' => '123 Main St',
            'city' => 'New York',
            'state' => 'NY',
            'zip_code' => '10001',
            'country' => 'US',
        ]);

        $product = Product::create([
            'name' => 'Order Test Product',
            'slug' => 'order-test-product',
            'description' => 'Test product for order',
            'short_description' => 'Test',
            'price' => 75.00,
            'quantity' => 10,
            'sku' => 'ORD-TEST-SKU',
            'category_id' => $this->category->id,
            'brand_id' => $this->brand->id,
            'status' => 'PUBLISHED',
        ]);

        $order = Order::create([
            'order_number' => 'ORD-ADM-TEST-' . strtoupper(Str::random(6)),
            'user_id' => $this->customer->id,
            'shipping_address_id' => $address->id,
            'subtotal' => 150.00,
            'total' => 165.00,
            'shipping_cost' => 15.00,
            'status' => 'PENDING',
        ]);

        $orderId = $order->id;

        // ── Step 1: List all orders (admin) ──
        $this->withHeaders($this->adminHeaders())
            ->getJson("{$this->apiPrefix}/orders")
            ->assertStatus(200)
            ->assertJson(['success' => true]);

        // ── Step 2: View order details ──
        $this->withHeaders($this->adminHeaders())
            ->getJson("{$this->apiPrefix}/orders/{$orderId}")
            ->assertStatus(200)
            ->assertJson(['success' => true]);

        // ── Step 3: Update order status ──
        $this->withHeaders($this->adminHeaders())
            ->patchJson("{$this->apiPrefix}/orders/{$orderId}/status", [
                'status' => 'CONFIRMED',
            ])
            ->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Status updated',
            ]);

        $this->assertDatabaseHas('orders', [
            'id' => $orderId,
            'status' => 'CONFIRMED',
        ]);

        // Step through more statuses
        foreach (['PROCESSING', 'SHIPPED', 'DELIVERED'] as $status) {
            $this->withHeaders($this->adminHeaders())
                ->patchJson("{$this->apiPrefix}/orders/{$orderId}/status", [
                    'status' => $status,
                ])
                ->assertStatus(200);
        }

        // ── Step 4: Revenue stats ──
        $this->withHeaders($this->adminHeaders())
            ->getJson("{$this->apiPrefix}/orders/revenue")
            ->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->withHeaders($this->adminHeaders())
            ->getJson("{$this->apiPrefix}/orders/revenue-stats")
            ->assertStatus(200)
            ->assertJson(['success' => true]);
    }

    /** @test */
    public function test_admin_user_management()
    {
        // ── Step 1: List users ──
        $usersResponse = $this->withHeaders($this->adminHeaders())
            ->getJson("{$this->apiPrefix}/users");

        $usersResponse->assertStatus(200)
            ->assertJson(['success' => true]);

        // ── Step 2: View user details ──
        $this->withHeaders($this->adminHeaders())
            ->getJson("{$this->apiPrefix}/users/{$this->customer->id}")
            ->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $this->customer->id,
                    'email' => 'customer@example.com',
                ],
            ]);

        // ── Step 3: User analytics ──
        $this->withHeaders($this->adminHeaders())
            ->getJson("{$this->apiPrefix}/analytics/users")
            ->assertStatus(200)
            ->assertJson(['success' => true]);
    }

    /** @test */
    public function test_admin_review_management()
    {
        // Create a review
        $product = Product::create([
            'name' => 'Review Test Product',
            'slug' => 'review-test-product',
            'description' => 'Product for review management test',
            'short_description' => 'Review test',
            'price' => 50.00,
            'quantity' => 10,
            'sku' => 'REV-TEST-SKU',
            'category_id' => $this->category->id,
            'brand_id' => $this->brand->id,
            'status' => 'PUBLISHED',
        ]);

        Review::create([
            'product_id' => $product->id,
            'user_id' => $this->customer->id,
            'rating' => 4,
            'title' => 'Good product',
            'comment' => 'Really liked it',
            'is_verified' => false,
            'is_moderated' => false,
        ]);

        // ── Step 1: List all reviews (admin) ──
        $this->withHeaders($this->adminHeaders())
            ->getJson("{$this->apiPrefix}/reviews")
            ->assertStatus(200)
            ->assertJson(['success' => true]);

        // ── Step 2: Pending reviews ──
        $this->withHeaders($this->adminHeaders())
            ->getJson("{$this->apiPrefix}/reviews/pending")
            ->assertStatus(200)
            ->assertJson(['success' => true]);
    }

    /** @test */
    public function test_non_admin_cannot_access_admin_endpoints()
    {
        $customerToken = $this->customer->createToken('auth-token')->plainTextToken;
        $customerHeaders = ['Authorization' => "Bearer {$customerToken}"];

        $adminEndpoints = [
            ['method' => 'GET', 'url' => "{$this->apiPrefix}/dashboard/metrics"],
            ['method' => 'GET', 'url' => "{$this->apiPrefix}/users"],
            ['method' => 'GET', 'url' => "{$this->apiPrefix}/orders"],
            ['method' => 'POST', 'url' => "{$this->apiPrefix}/products", 'data' => [
                'name' => 'Test', 'description' => 'Test', 'price' => 10, 'sku' => 'TEST-SKU',
                'category_id' => $this->category->id,
            ]],
            ['method' => 'GET', 'url' => "{$this->apiPrefix}/reviews"],
            ['method' => 'GET', 'url' => "{$this->apiPrefix}/analytics/sales"],
        ];

        foreach ($adminEndpoints as $endpoint) {
            $response = match ($endpoint['method']) {
                'POST' => $this->withHeaders($customerHeaders)
                    ->postJson($endpoint['url'], $endpoint['data'] ?? []),
                'PUT' => $this->withHeaders($customerHeaders)
                    ->putJson($endpoint['url'], $endpoint['data'] ?? []),
                'DELETE' => $this->withHeaders($customerHeaders)
                    ->deleteJson($endpoint['url']),
                default => $this->withHeaders($customerHeaders)
                    ->getJson($endpoint['url']),
            };

            $response->assertStatus(403);
        }
    }
}
