<?php

namespace Tests\Feature\E2E;

use Tests\TestCase;
use App\Models\User;
use App\Models\Product;
use App\Models\Category;
use App\Models\Brand;
use App\Models\Inventory;
use App\Models\InventoryHistory;
use Illuminate\Foundation\Testing\RefreshDatabase;

class InventoryE2ETest extends TestCase
{
    use RefreshDatabase;

    protected string $apiPrefix = '/api/v1';
    protected User $adminUser;
    protected string $adminToken;
    protected Product $product;
    protected Inventory $inventory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminUser = User::factory()->create(['role' => 'ADMIN', 'is_active' => true]);
        $this->adminToken = $this->adminUser->createToken('admin-token')->plainTextToken;

        $category = Category::create(['name' => 'Inv Cat', 'slug' => 'inv-cat', 'is_active' => true]);
        $brand = Brand::create(['name' => 'Inv Brand', 'slug' => 'inv-brand']);

        $this->product = Product::create([
            'name' => 'Inventory Product', 'slug' => 'inventory-product',
            'description' => 'Test', 'short_description' => 'Test',
            'price' => 25.00, 'quantity' => 50, 'sku' => 'INV-SKU-001',
            'category_id' => $category->id, 'brand_id' => $brand->id, 'status' => 'PUBLISHED',
        ]);

        $this->inventory = Inventory::create([
            'product_id' => $this->product->id,
            'total_quantity' => 50,
            'available_quantity' => 50,
            'reserved_quantity' => 0,
            'damaged_quantity' => 0,
        ]);
    }

    protected function adminHeaders(): array
    {
        return ['Authorization' => "Bearer {$this->adminToken}"];
    }

    /** @test */
    public function test_check_inventory_availability()
    {
        $response = $this->getJson("{$this->apiPrefix}/inventory/{$this->product->id}/check");

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonPath('data.available', true);
    }

    /** @test */
    public function test_add_stock()
    {
        $response = $this->withHeaders($this->adminHeaders())
            ->postJson("{$this->apiPrefix}/admin/inventory/add", [
                'product_id' => $this->product->id,
                'quantity' => 25,
                'notes' => 'Restock from supplier',
            ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true, 'message' => 'Stock added']);

        // Verify quantity increased
        $this->assertEquals(75, Inventory::find($this->inventory->id)->available_quantity);
    }

    /** @test */
    public function test_reduce_stock()
    {
        $response = $this->withHeaders($this->adminHeaders())
            ->postJson("{$this->apiPrefix}/admin/inventory/reduce", [
                'product_id' => $this->product->id,
                'quantity' => 10,
                'notes' => 'Order fulfillment',
            ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true, 'message' => 'Stock reduced']);

        // Verify quantity decreased
        $this->assertEquals(40, Inventory::find($this->inventory->id)->available_quantity);
    }

    /** @test */
    public function test_get_inventory_for_product()
    {
        $response = $this->withHeaders($this->adminHeaders())
            ->getJson("{$this->apiPrefix}/admin/inventory/{$this->product->id}");

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonStructure(['success', 'data' => ['id', 'product_id', 'available_quantity', 'total_quantity']]);
    }

    /** @test */
    public function test_get_low_stock_items()
    {
        $response = $this->withHeaders($this->adminHeaders())
            ->getJson("{$this->apiPrefix}/admin/inventory/low-stock");

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $data = $response->json('data');
        $items = $data['data'] ?? $data;
        $this->assertIsArray($items);
    }

    /** @test */
    public function test_inventory_movement_history()
    {
        // Create some movement history
        InventoryHistory::create([
            'inventory_id' => $this->inventory->id,
            'type' => 'INBOUND',
            'quantity' => 100,
            'notes' => 'Initial stock',
        ]);

        InventoryHistory::create([
            'inventory_id' => $this->inventory->id,
            'type' => 'OUTBOUND',
            'quantity' => 20,
            'notes' => 'Order #1234',
        ]);

        $response = $this->withHeaders($this->adminHeaders())
            ->getJson("{$this->apiPrefix}/admin/inventory/{$this->product->id}/movement");

        $response->assertStatus(200)
            ->assertJson(['success' => true]);
    }

    /** @test */
    public function test_inventory_stats()
    {
        $response = $this->withHeaders($this->adminHeaders())
            ->getJson("{$this->apiPrefix}/admin/inventory/stats");

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'success', 'data' => ['total_products', 'total_available', 'low_stock', 'out_of_stock'],
            ]);
    }

    /** @test */
    public function test_batch_update_inventory()
    {
        $response = $this->withHeaders($this->adminHeaders())
            ->postJson("{$this->apiPrefix}/admin/inventory/batch-update", [
                'updates' => [
                    ['product_id' => $this->product->id, 'quantity' => 10, 'type' => 'add'],
                    ['product_id' => $this->product->id, 'quantity' => 5, 'type' => 'reduce'],
                ],
            ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonStructure(['success', 'data' => ['updated_count', 'results']]);

        $this->assertEquals(55, Inventory::find($this->inventory->id)->available_quantity);
    }
}
