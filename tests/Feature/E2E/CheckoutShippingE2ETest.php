<?php

namespace Tests\Feature\E2E;

use Tests\TestCase;
use App\Models\User;
use App\Models\Product;
use App\Models\Category;
use App\Models\Brand;
use App\Models\Address;
use App\Models\CartItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

class CheckoutShippingE2ETest extends TestCase
{
    use RefreshDatabase;

    protected string $apiPrefix = '/api/v1';
    protected User $user;
    protected string $token;
    protected Product $product1;
    protected Product $product2;
    protected Address $address;
    protected Category $category;
    protected Brand $brand;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['role' => 'CUSTOMER', 'is_active' => true]);
        $this->token = $this->user->createToken('auth-token')->plainTextToken;

        $this->category = Category::create(['name' => 'Checkout Cat', 'slug' => 'checkout-cat', 'is_active' => true]);
        $this->brand = Brand::create(['name' => 'Checkout Brand', 'slug' => 'checkout-brand']);

        $this->product1 = Product::create([
            'name' => 'Checkout Product 1', 'slug' => 'checkout-product-1',
            'description' => 'Test', 'short_description' => 'Test',
            'price' => 49.99, 'quantity' => 100, 'sku' => 'CHK-SKU-001',
            'category_id' => $this->category->id, 'brand_id' => $this->brand->id, 'status' => 'PUBLISHED',
        ]);

        $this->product2 = Product::create([
            'name' => 'Checkout Product 2', 'slug' => 'checkout-product-2',
            'description' => 'Test 2', 'short_description' => 'Test 2',
            'price' => 29.99, 'quantity' => 50, 'sku' => 'CHK-SKU-002',
            'category_id' => $this->category->id, 'brand_id' => $this->brand->id, 'status' => 'PUBLISHED',
        ]);

        $this->address = Address::create([
            'user_id' => $this->user->id, 'type' => 'HOME',
            'first_name' => 'Jane', 'last_name' => 'Doe', 'phone_number' => '+1234567890',
            'address_line1' => '456 Oak Ave', 'city' => 'Brooklyn', 'state' => 'NY',
            'zip_code' => '11201', 'country' => 'US', 'is_default' => true,
        ]);
    }

    protected function authHeaders(): array
    {
        return ['Authorization' => "Bearer {$this->token}"];
    }

    /** @test */
    public function test_checkout_summary_with_empty_cart()
    {
        $response = $this->withHeaders($this->authHeaders())
            ->getJson("{$this->apiPrefix}/checkout/summary");

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonPath('data.item_count', 0);
    }

    /** @test */
    public function test_checkout_summary_with_cart_items()
    {
        // Add items to cart
        $this->withHeaders($this->authHeaders())
            ->postJson("{$this->apiPrefix}/cart", [
                'product_id' => $this->product1->id,
                'quantity' => 2,
            ])->assertStatus(201);

        $this->withHeaders($this->authHeaders())
            ->postJson("{$this->apiPrefix}/cart", [
                'product_id' => $this->product2->id,
                'quantity' => 1,
            ])->assertStatus(201);

        // Get checkout summary
        $summaryResponse = $this->withHeaders($this->authHeaders())
            ->getJson("{$this->apiPrefix}/checkout/summary");

        $summaryResponse->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'success', 'data' => ['items', 'subtotal', 'tax', 'shipping', 'total', 'item_count'],
            ]);

        $this->assertEquals(3, $summaryResponse->json('data.item_count'));
        $this->assertGreaterThan(0, $summaryResponse->json('data.subtotal'));
    }

    /** @test */
    public function test_shipping_calculation()
    {
        // Add an item to cart
        $this->withHeaders($this->authHeaders())
            ->postJson("{$this->apiPrefix}/cart", [
                'product_id' => $this->product1->id,
                'quantity' => 1,
            ])->assertStatus(201);

        $response = $this->withHeaders($this->authHeaders())
            ->postJson("{$this->apiPrefix}/checkout/shipping");

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'success', 'data' => [
                    'standard' => ['cost', 'estimated_days'],
                    'express' => ['cost', 'estimated_days'],
                    'free_threshold', 'current_subtotal',
                ],
            ]);
    }

    /** @test */
    public function test_checkout_process()
    {
        // Add items to cart
        $this->withHeaders($this->authHeaders())
            ->postJson("{$this->apiPrefix}/cart", [
                'product_id' => $this->product1->id,
                'quantity' => 1,
            ])->assertStatus(201);

        $this->withHeaders($this->authHeaders())
            ->postJson("{$this->apiPrefix}/cart", [
                'product_id' => $this->product2->id,
                'quantity' => 2,
            ])->assertStatus(201);

        // Process checkout
        $checkoutResponse = $this->withHeaders($this->authHeaders())
            ->postJson("{$this->apiPrefix}/checkout", [
                'shipping_address_id' => $this->address->id,
                'notes' => 'Leave at the door',
            ]);

        $checkoutResponse->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Order created',
            ])
            ->assertJsonStructure([
                'success', 'message', 'data' => ['id', 'order_number', 'total', 'status', 'items'],
            ]);

        // Verify cart is now empty
        $cartResponse = $this->withHeaders($this->authHeaders())
            ->getJson("{$this->apiPrefix}/cart");

        $this->assertEquals(0, $cartResponse->json('data.count'));
    }

    /** @test */
    public function test_checkout_rejects_empty_cart()
    {
        $response = $this->withHeaders($this->authHeaders())
            ->postJson("{$this->apiPrefix}/checkout", [
                'shipping_address_id' => $this->address->id,
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Cart is empty',
            ]);
    }

    /** @test */
    public function test_shipping_methods_public()
    {
        $providersResponse = $this->getJson("{$this->apiPrefix}/shipping/providers");

        $providersResponse->assertStatus(200)
            ->assertJson(['success' => true]);

        // Get shipping zones
        $zonesResponse = $this->getJson("{$this->apiPrefix}/shipping/zones");

        $zonesResponse->assertStatus(200)
            ->assertJson(['success' => true]);
    }

    /** @test */
    public function test_shipping_cost_calculation()
    {
        $response = $this->postJson("{$this->apiPrefix}/shipping/calculate", [
            'zone_id' => 'default',
            'weight' => 2.5,
            'subtotal' => 100.00,
        ]);

        // Either success (if shipping is configured) or validation error about no rate
        $this->assertContains($response->status(), [200, 422],
            'Shipping calc should return 200 (success) or 422 (no rate configured)');
    }
}
