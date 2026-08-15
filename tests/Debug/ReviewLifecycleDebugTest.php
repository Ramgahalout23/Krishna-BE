<?php

namespace Tests\Debug;

use Tests\TestCase;
use App\Models\User;
use App\Models\Product;
use App\Models\Category;
use App\Models\Brand;
use App\Models\Address;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Review;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

class ReviewLifecycleDebugTest extends TestCase
{
    use RefreshDatabase;

    protected string $apiPrefix = '/api/v1';
    protected User $user;
    protected string $token;
    protected Product $product;
    protected Category $category;
    protected Brand $brand;

    protected function setUp(): void
    {
        parent::setUp();
        echo "\n=== SETUP ===\n";

        $this->user = User::factory()->create([
            'role' => 'CUSTOMER',
            'is_active' => true,
        ]);
        echo "Created user: {$this->user->id}\n";

        $this->token = $this->user->createToken('auth-token')->plainTextToken;
        echo "Token created: " . substr($this->token, 0, 20) . "...\n";

        $this->category = Category::create([
            'name' => 'Debug Category',
            'slug' => 'debug-category',
            'is_active' => true,
        ]);

        $this->brand = Brand::create([
            'name' => 'Debug Brand',
            'slug' => 'debug-brand',
        ]);

        $this->product = Product::create([
            'name' => 'Debug Product',
            'slug' => 'debug-product',
            'description' => 'A debug product for testing reviews',
            'short_description' => 'Debug product',
            'price' => 99.99,
            'quantity' => 100,
            'sku' => 'DBG-SKU-001',
            'category_id' => $this->category->id,
            'brand_id' => $this->brand->id,
            'status' => 'PUBLISHED',
            'is_featured' => true,
        ]);
        echo "Created product: {$this->product->id}\n";
    }

    protected function authHeaders(): array
    {
        return ['Authorization' => "Bearer {$this->token}"];
    }

    /** @test */
    public function test_review_lifecycle_end_to_end()
    {
        // ── Step 1: Create a delivered order ──
        echo "\n--- Step 1: Create Delivered Order ---\n";
        $address = Address::create([
            'user_id' => $this->user->id,
            'type' => 'HOME',
            'first_name' => 'Debug',
            'last_name' => 'User',
            'phone_number' => '+1234567890',
            'address_line1' => '123 Debug St',
            'city' => 'Debug City',
            'state' => 'DC',
            'zip_code' => '12345',
            'country' => 'US',
        ]);
        echo "Created address: {$address->id}\n";

        $order = Order::create([
            'order_number' => 'ORD-DEBUG-' . strtoupper(Str::random(6)),
            'user_id' => $this->user->id,
            'shipping_address_id' => $address->id,
            'subtotal' => 99.99,
            'total' => 109.99,
            'shipping_cost' => 10.00,
            'status' => 'DELIVERED',
        ]);
        echo "Created order: {$order->id} ({$order->order_number})\n";

        $orderItem = OrderItem::create([
            'order_id' => $order->id,
            'user_id' => $this->user->id,
            'product_id' => $this->product->id,
            'quantity' => 1,
            'price' => $this->product->price,
            'total' => $this->product->price,
        ]);
        echo "Created order_item: {$orderItem->id}\n";

        // ── Step 2: Create a review ──
        echo "\n--- Step 2: Create Review ---\n";
        $createResponse = $this->withHeaders($this->authHeaders())
            ->postJson("{$this->apiPrefix}/reviews", [
                'product_id' => $this->product->id,
                'order_id' => $order->id,
                'rating' => 5,
                'title' => 'Excellent debug product!',
                'comment' => 'Really loved this product. Highly recommended!',
            ]);

        echo "POST /reviews -> Status: {$createResponse->status()}\n";
        echo "Response body: " . json_encode($createResponse->json(), JSON_PRETTY_PRINT) . "\n";

        $createResponse->assertStatus(201);
        $reviewId = $createResponse->json('data.id');
        echo "Review created with ID: {$reviewId}\n";

        // ── Step 3: Get review stats (public) ──
        echo "\n--- Step 3: Get Review Stats ---\n";
        $statsResponse = $this->getJson("{$this->apiPrefix}/reviews/stats/{$this->product->id}");
        echo "GET /reviews/stats/{$this->product->id} -> Status: {$statsResponse->status()}\n";
        echo "Response: " . json_encode($statsResponse->json(), JSON_PRETTY_PRINT) . "\n";
        $statsResponse->assertStatus(200)->assertJson(['success' => true]);

        // ── Step 4: Get product reviews (public) ──
        echo "\n--- Step 4: Get Product Reviews ---\n";
        $productReviewsResponse = $this->getJson("{$this->apiPrefix}/reviews/product/{$this->product->id}");
        echo "GET /reviews/product/{$this->product->id} -> Status: {$productReviewsResponse->status()}\n";
        echo "Response: " . json_encode($productReviewsResponse->json(), JSON_PRETTY_PRINT) . "\n";
        $productReviewsResponse->assertStatus(200)->assertJson(['success' => true]);

        // ── Step 5: Get single review by ID (public) ──
        echo "\n--- Step 5: Get Single Review ---\n";
        $showResponse = $this->getJson("{$this->apiPrefix}/reviews/{$reviewId}");
        echo "GET /reviews/{$reviewId} -> Status: {$showResponse->status()}\n";
        echo "Response: " . json_encode($showResponse->json(), JSON_PRETTY_PRINT) . "\n";
        $showResponse->assertStatus(200)->assertJson(['success' => true]);

        // ── Step 6: Get user's reviews (authenticated) ──
        echo "\n--- Step 6: Get User's Reviews ---\n";
        $userReviewsResponse = $this->withHeaders($this->authHeaders())
            ->getJson("{$this->apiPrefix}/reviews/user");
        echo "GET /reviews/user -> Status: {$userReviewsResponse->status()}\n";
        echo "Response: " . json_encode($userReviewsResponse->json(), JSON_PRETTY_PRINT) . "\n";
        $userReviewsResponse->assertStatus(200)->assertJson(['success' => true]);

        // Verify review count
        $reviews = $userReviewsResponse->json('data');
        echo "User has " . count($reviews) . " reviews\n";
        $this->assertCount(1, $reviews);

        // ── Step 7: Update the review ──
        echo "\n--- Step 7: Update Review ---\n";
        $updateResponse = $this->withHeaders($this->authHeaders())
            ->putJson("{$this->apiPrefix}/reviews/{$reviewId}", [
                'rating' => 4,
                'comment' => 'Updated: Still a great product.',
            ]);
        echo "PUT /reviews/{$reviewId} -> Status: {$updateResponse->status()}\n";
        echo "Response: " . json_encode($updateResponse->json(), JSON_PRETTY_PRINT) . "\n";
        $updateResponse->assertStatus(200)->assertJson(['success' => true]);

        // ── Step 8: Mark as helpful ──
        echo "\n--- Step 8: Mark as Helpful ---\n";
        $helpfulResponse = $this->withHeaders($this->authHeaders())
            ->postJson("{$this->apiPrefix}/reviews/{$reviewId}/helpful");
        echo "POST /reviews/{$reviewId}/helpful -> Status: {$helpfulResponse->status()}\n";
        echo "Response: " . json_encode($helpfulResponse->json(), JSON_PRETTY_PRINT) . "\n";
        $helpfulResponse->assertStatus(200)->assertJson(['success' => true]);

        // Verify helpful count incremented
        $this->assertEquals(1, Review::find($reviewId)->helpful);

        // ── Step 9: Mark as unhelpful ──
        echo "\n--- Step 9: Mark as Unhelpful ---\n";
        $unhelpfulResponse = $this->withHeaders($this->authHeaders())
            ->postJson("{$this->apiPrefix}/reviews/{$reviewId}/unhelpful");
        echo "POST /reviews/{$reviewId}/unhelpful -> Status: {$unhelpfulResponse->status()}\n";
        echo "Response: " . json_encode($unhelpfulResponse->json(), JSON_PRETTY_PRINT) . "\n";
        $unhelpfulResponse->assertStatus(200)->assertJson(['success' => true]);

        // Verify unhelpful count incremented
        $this->assertEquals(1, Review::find($reviewId)->unhelpful);

        // ── Step 10: Delete the review ──
        echo "\n--- Step 10: Delete Review ---\n";
        $deleteResponse = $this->withHeaders($this->authHeaders())
            ->deleteJson("{$this->apiPrefix}/reviews/{$reviewId}");
        echo "DELETE /reviews/{$reviewId} -> Status: {$deleteResponse->status()}\n";
        echo "Response: " . json_encode($deleteResponse->json(), JSON_PRETTY_PRINT) . "\n";
        $deleteResponse->assertStatus(200)->assertJson(['success' => true]);

        // Verify deletion
        $this->assertDatabaseMissing('reviews', ['id' => $reviewId]);
        echo "\n=== REVIEW LIFECYCLE COMPLETE: ALL STEPS PASSED ===\n";
    }

    /** @test */
    public function test_review_route_resolution_order()
    {
        echo "\n=== ROUTE RESOLUTION TEST ===\n";

        // Test that /reviews/user doesn't match /reviews/{id}
        echo "\n1. Testing unauthenticated /reviews/user (should be 401, not 404)\n";
        $response1 = $this->getJson("{$this->apiPrefix}/reviews/user");
        echo "   Status: {$response1->status()}\n";
        echo "   Body: " . json_encode($response1->json()) . "\n";
        // Without auth, /reviews/user has middleware auth:sanctum, so it should be 401
        $this->assertEquals(401, $response1->status(), "/reviews/user should return 401 without auth, not 404");

        // Test that /reviews/{id} works with a valid UUID
        echo "\n2. Testing /reviews/{non-existent-id} (should be 404 - proper 404)\n";
        $response2 = $this->getJson("{$this->apiPrefix}/reviews/" . Str::uuid());
        echo "   Status: {$response2->status()}\n";
        echo "   Body: " . json_encode($response2->json()) . "\n";
        $this->assertEquals(404, $response2->status(), "/reviews/{id} should return 404 for non-existent review");

        // Test /reviews/product/{id}
        echo "\n3. Testing /reviews/product/{productId} (should be 200)\n";
        $response3 = $this->getJson("{$this->apiPrefix}/reviews/product/{$this->product->id}");
        echo "   Status: {$response3->status()}\n";
        echo "   Body: " . json_encode($response3->json()) . "\n";
        $this->assertEquals(200, $response3->status());

        echo "\n=== ROUTE RESOLUTION TEST COMPLETE ===\n";
    }
}
