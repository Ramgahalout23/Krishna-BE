<?php

namespace Tests\Feature\E2E;

use Tests\TestCase;
use App\Models\User;
use App\Models\Product;
use App\Models\Category;
use App\Models\Brand;
use App\Models\Address;
use App\Models\Order;
use App\Models\SupportTicket;
use App\Models\TicketMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

class SupportTicketE2ETest extends TestCase
{
    use RefreshDatabase;

    protected string $apiPrefix = '/api/v1';
    protected User $user;
    protected User $adminUser;
    protected string $token;
    protected string $adminToken;
    protected Order $order;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['role' => 'CUSTOMER', 'is_active' => true]);
        $this->token = $this->user->createToken('auth-token')->plainTextToken;

        $this->adminUser = User::factory()->create(['role' => 'ADMIN', 'is_active' => true]);
        $this->adminToken = $this->adminUser->createToken('admin-token')->plainTextToken;

        $category = Category::create(['name' => 'Ticket Cat', 'slug' => 'ticket-cat', 'is_active' => true]);
        $brand = Brand::create(['name' => 'Ticket Brand', 'slug' => 'ticket-brand']);
        $product = Product::create([
            'name' => 'Ticket Product', 'slug' => 'ticket-product',
            'description' => 'Test', 'short_description' => 'Test',
            'price' => 50.00, 'quantity' => 10, 'sku' => 'TCK-SKU-001',
            'category_id' => $category->id, 'brand_id' => $brand->id, 'status' => 'PUBLISHED',
        ]);

        $address = Address::create([
            'user_id' => $this->user->id, 'type' => 'HOME',
            'first_name' => 'Support', 'last_name' => 'User', 'phone_number' => '+1234567890',
            'address_line1' => '123 Ticket St', 'city' => 'Support City', 'state' => 'SC',
            'zip_code' => '12345', 'country' => 'US',
        ]);

        $this->order = Order::create([
            'order_number' => 'ORD-TCK-' . strtoupper(Str::random(8)),
            'user_id' => $this->user->id,
            'shipping_address_id' => $address->id,
            'subtotal' => 50.00, 'total' => 55.00, 'shipping_cost' => 5.00,
            'status' => 'PENDING',
        ]);
    }

    protected function authHeaders(): array
    {
        return ['Authorization' => "Bearer {$this->token}"];
    }

    protected function adminHeaders(): array
    {
        return ['Authorization' => "Bearer {$this->adminToken}"];
    }

    /** @test */
    public function test_create_ticket()
    {
        $response = $this->withHeaders($this->authHeaders())
            ->postJson("{$this->apiPrefix}/tickets", [
                'subject' => 'Issue with my order',
                'message' => 'My order has not arrived yet.',
                'order_id' => $this->order->id,
                'priority' => 'HIGH',
            ]);

        $response->assertStatus(201)
            ->assertJson(['success' => true, 'message' => 'Ticket created'])
            ->assertJsonStructure([
                'success', 'message', 'data' => ['id', 'subject', 'status', 'priority'],
            ]);

        $this->assertEquals('OPEN', $response->json('data.status'));
    }

    /** @test */
    public function test_list_user_tickets()
    {
        // Create a ticket first
        SupportTicket::create([
            'ticket_number' => 'TKT-' . strtoupper(Str::random(8)),
            'user_id' => $this->user->id,
            'subject' => 'Test ticket',
            'description' => 'Test description',
            'category' => 'OTHER',
            'priority' => 'MEDIUM',
            'status' => 'OPEN',
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->getJson("{$this->apiPrefix}/tickets");

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $tickets = $response->json('data');
        $ticketList = $tickets['data'] ?? $tickets;
        $this->assertGreaterThanOrEqual(1, count($ticketList));
    }

    /** @test */
    public function test_view_ticket_details()
    {
        $ticket = SupportTicket::create([
            'ticket_number' => 'TKT-DETAIL-' . strtoupper(Str::random(6)),
            'user_id' => $this->user->id,
            'subject' => 'Detail ticket',
            'description' => 'Detail description',
            'category' => 'ORDER',
            'priority' => 'LOW',
            'status' => 'OPEN',
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->getJson("{$this->apiPrefix}/tickets/{$ticket->id}");

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonPath('data.subject', 'Detail ticket');
    }

    /** @test */
    public function test_add_message_to_ticket()
    {
        $ticket = SupportTicket::create([
            'ticket_number' => 'TKT-MSG-' . strtoupper(Str::random(6)),
            'user_id' => $this->user->id,
            'subject' => 'Messaging ticket',
            'description' => 'Need help with order',
            'category' => 'ORDER',
            'priority' => 'HIGH',
            'status' => 'OPEN',
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->postJson("{$this->apiPrefix}/tickets/{$ticket->id}/messages", [
                'message' => 'I need help with this issue.',
            ]);

        $response->assertStatus(201)
            ->assertJson(['success' => true, 'message' => 'Message added']);
    }

    /** @test */
    public function test_admin_update_ticket_status()
    {
        $ticket = SupportTicket::create([
            'ticket_number' => 'TKT-ADMIN-' . strtoupper(Str::random(6)),
            'user_id' => $this->user->id,
            'subject' => 'Admin update ticket',
            'description' => 'Urgent issue description',
            'category' => 'PAYMENT',
            'priority' => 'URGENT',
            'status' => 'OPEN',
        ]);

        $response = $this->withHeaders($this->adminHeaders())
            ->patchJson("{$this->apiPrefix}/admin/tickets/{$ticket->id}/status", [
                'status' => 'IN_PROGRESS',
            ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true, 'message' => 'Status updated'])
            ->assertJsonPath('data.status', 'IN_PROGRESS');
    }

    /** @test */
    public function test_admin_ticket_stats()
    {
        $response = $this->withHeaders($this->adminHeaders())
            ->getJson("{$this->apiPrefix}/admin/tickets/stats");

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'success', 'data' => ['total', 'open', 'in_progress', 'resolved', 'closed'],
            ]);
    }

    /** @test */
    public function test_admin_update_ticket()
    {
        $ticket = SupportTicket::create([
            'ticket_number' => 'TKT-UPD-' . strtoupper(Str::random(6)),
            'user_id' => $this->user->id,
            'subject' => 'Update me',
            'description' => 'Update description',
            'category' => 'TECHNICAL',
            'priority' => 'MEDIUM',
            'status' => 'OPEN',
        ]);

        $response = $this->withHeaders($this->adminHeaders())
            ->putJson("{$this->apiPrefix}/admin/tickets/{$ticket->id}", [
                'priority' => 'HIGH',
                'assigned_to' => $this->adminUser->id,
            ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true, 'message' => 'Ticket updated']);
    }

    /** @test */
    public function test_forbidden_ticket_access()
    {
        $otherUser = User::factory()->create(['role' => 'CUSTOMER']);
        $otherTicket = SupportTicket::create([
            'ticket_number' => 'TKT-OTHER-' . strtoupper(Str::random(6)),
            'user_id' => $otherUser->id,
            'subject' => 'Not my ticket',
            'description' => 'Other user description',
            'category' => 'ACCOUNT',
            'priority' => 'LOW',
            'status' => 'OPEN',
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->postJson("{$this->apiPrefix}/tickets/{$otherTicket->id}/messages", [
                'message' => 'Trying to message on another ticket',
            ]);

        $response->assertStatus(403);
    }
}
