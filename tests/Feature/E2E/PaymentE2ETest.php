<?php

namespace Tests\Feature\E2E;

use Tests\TestCase;
use App\Models\User;
use App\Models\Product;
use App\Models\Category;
use App\Models\Brand;
use App\Models\Address;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Refund;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

class PaymentE2ETest extends TestCase
{
    use RefreshDatabase;

    protected string $apiPrefix = '/api/v1';
    protected User $user;
    protected string $token;
    protected Order $order;
    protected Address $address;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['role' => 'CUSTOMER', 'is_active' => true]);
        $this->token = $this->user->createToken('auth-token')->plainTextToken;

        $category = Category::create(['name' => 'Payment Cat', 'slug' => 'payment-cat', 'is_active' => true]);
        $brand = Brand::create(['name' => 'Payment Brand', 'slug' => 'payment-brand']);
        $product = Product::create([
            'name' => 'Payment Product', 'slug' => 'payment-product',
            'description' => 'Test', 'short_description' => 'Test',
            'price' => 100.00, 'quantity' => 10, 'sku' => 'PAY-SKU-001',
            'category_id' => $category->id, 'brand_id' => $brand->id, 'status' => 'PUBLISHED',
        ]);

        $this->address = Address::create([
            'user_id' => $this->user->id, 'type' => 'HOME',
            'first_name' => 'John', 'last_name' => 'Doe', 'phone_number' => '+1234567890',
            'address_line1' => '123 Main St', 'city' => 'New York', 'state' => 'NY',
            'zip_code' => '10001', 'country' => 'US', 'is_default' => true,
        ]);

        // Create an order for payment tests
        $this->order = Order::create([
            'order_number' => 'ORD-PAY-' . strtoupper(Str::random(8)),
            'user_id' => $this->user->id,
            'shipping_address_id' => $this->address->id,
            'subtotal' => 100.00, 'total' => 110.00, 'shipping_cost' => 10.00,
            'status' => 'PENDING',
        ]);
    }

    protected function authHeaders(): array
    {
        return ['Authorization' => "Bearer {$this->token}"];
    }

    /** @test */
    public function test_get_payment_methods()
    {
        $response = $this->getJson("{$this->apiPrefix}/payments/methods");

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'success', 'data' => [['id', 'name', 'active']],
            ]);

        $methods = $response->json('data');
        $this->assertGreaterThanOrEqual(3, count($methods));
    }

    /** @test */
    public function test_create_and_confirm_payment()
    {
        // ── Step 1: Create payment intent ──
        $createResponse = $this->withHeaders($this->authHeaders())
            ->postJson("{$this->apiPrefix}/payments/create-payment-intent", [
                'order_id' => $this->order->id,
                'method' => 'STRIPE',
                'amount' => 110.00,
            ]);

        $createResponse->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'success', 'data' => ['id', 'order_id', 'method', 'amount', 'status'],
            ]);

        $paymentId = $createResponse->json('data.id');
        $this->assertEquals('PENDING', $createResponse->json('data.status'));

        // ── Step 2: Confirm payment ──
        $confirmResponse = $this->withHeaders($this->authHeaders())
            ->postJson("{$this->apiPrefix}/payments/confirm", [
                'payment_id' => $paymentId,
                'transaction_id' => 'TXN-' . strtoupper(Str::random(12)),
            ]);

        $confirmResponse->assertStatus(200)
            ->assertJson(['success' => true, 'message' => 'Payment confirmed'])
            ->assertJsonPath('data.status', 'COMPLETED');
    }

    /** @test */
    public function test_get_user_payments()
    {
        // Create a payment record
        $payment = Payment::create([
            'order_id' => $this->order->id,                                            'method' => 'STRIPE',
            'amount' => 110.00,
            'status' => 'COMPLETED',
            'transaction_id' => 'TXN-TEST-001',
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->getJson("{$this->apiPrefix}/payments");

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $payments = $response->json('data');
        $this->assertGreaterThanOrEqual(1, count($payments));
    }

    /** @test */
    public function test_get_payment_details()
    {
        $payment = Payment::create([
            'order_id' => $this->order->id,                'method' => 'STRIPE',
            'amount' => 110.00,
            'status' => 'COMPLETED',
            'transaction_id' => 'TXN-DETAILS-001',
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->getJson("{$this->apiPrefix}/payments/{$payment->id}");

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonPath('data.amount', '110.00');
    }

    /** @test */
    public function test_forbidden_payment_access()
    {
        $otherUser = User::factory()->create(['role' => 'CUSTOMER']);
        $otherOrder = Order::create([
            'order_number' => 'ORD-OTHER-PAY',
            'user_id' => $otherUser->id,
            'shipping_address_id' => $this->address->id,
            'subtotal' => 50.00, 'total' => 55.00, 'shipping_cost' => 5.00,
            'status' => 'PENDING',
        ]);

        $otherPayment = Payment::create([
            'order_id' => $otherOrder->id,                'method' => 'STRIPE',
            'amount' => 55.00,
            'status' => 'PENDING',
        ]);

        // Current user should not see another user's payment
        $response = $this->withHeaders($this->authHeaders())
            ->getJson("{$this->apiPrefix}/payments/{$otherPayment->id}");

        $this->assertTrue(
            $response->status() === 403 || $response->status() === 404,
            'Should return 403 or 404 for another user\'s payment'
        );
    }

    /** @test */
    public function test_refund_flow()
    {
        $payment = Payment::create([
            'order_id' => $this->order->id,                                            'method' => 'STRIPE',
            'amount' => 110.00,
            'status' => 'COMPLETED',
            'transaction_id' => 'TXN-REFUND-001',
        ]);

        // Request a refund
        $refundResponse = $this->withHeaders($this->authHeaders())
            ->postJson("{$this->apiPrefix}/payments/{$payment->id}/refund", [
                'amount' => 50.00,
                'reason' => 'Partial refund requested',
            ]);

        $refundResponse->assertStatus(201)
            ->assertJson(['success' => true]);

        // Check user refunds list
        $refundsResponse = $this->withHeaders($this->authHeaders())
            ->getJson("{$this->apiPrefix}/payments/refunds/list");

        $refundsResponse->assertStatus(200)
            ->assertJson(['success' => true]);
    }
}
