<?php

namespace Tests\Feature\E2E;

use Tests\TestCase;
use App\Models\User;
use App\Models\Coupon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

class CouponE2ETest extends TestCase
{
    use RefreshDatabase;

    protected string $apiPrefix = '/api/v1';
    protected User $user;
    protected User $adminUser;
    protected string $token;
    protected string $adminToken;
    protected Coupon $coupon;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['role' => 'CUSTOMER', 'is_active' => true]);
        $this->token = $this->user->createToken('auth-token')->plainTextToken;

        $this->adminUser = User::factory()->create(['role' => 'ADMIN', 'is_active' => true]);
        $this->adminToken = $this->adminUser->createToken('admin-token')->plainTextToken;

        // Create an active coupon
        $this->coupon = Coupon::create([
            'code' => 'TEST10',
            'discount_type' => 'PERCENTAGE',
            'discount_value' => 10,
            'type' => 'PERCENTAGE',
            'is_active' => true,
            'max_uses' => 100,
            'usage_count' => 0,
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
    public function test_public_coupon_listing()
    {
        $response = $this->getJson("{$this->apiPrefix}/coupons");

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $coupons = $response->json('data.data') ?? $response->json('data');
        $this->assertNotEmpty($coupons);
    }

    /** @test */
    public function test_validate_coupon_by_code()
    {
        $response = $this->getJson("{$this->apiPrefix}/coupons/TEST10");

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonPath('data.code', 'TEST10');
    }

    /** @test */
    public function test_validate_coupon()
    {
        $response = $this->getJson("{$this->apiPrefix}/coupons/TEST10");

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonStructure(['success', 'data' => ['id', 'code', 'discount_value']]);
    }

    /** @test */
    public function test_coupon_apply_flow()
    {
        // The /coupons/apply route has a duplicate (public + auth-protected).
        // Using auth headers ensures the route resolves correctly.
        $response = $this->withHeaders($this->authHeaders())
            ->postJson("{$this->apiPrefix}/coupons/apply", [
            'code' => 'TEST10',
        ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonPath('data.code', 'TEST10');
    }

    /** @test */
    public function test_get_best_coupon()
    {
        $response = $this->postJson("{$this->apiPrefix}/coupons/best", [
            'cart_total' => 100.00,
        ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true]);
    }

    /** @test */
    public function test_invalid_coupon_validation()
    {
        $response = $this->getJson("{$this->apiPrefix}/coupons/INVALID-CODE-12345");

        $response->assertStatus(422);
    }

    /** @test */
    public function test_validate_post_endpoint()
    {
        $response = $this->postJson("{$this->apiPrefix}/coupons/validate", [
            'code' => 'TEST10',
        ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonStructure(['success', 'data' => ['id', 'code', 'discount_value']]);
    }

    /** @test */
    public function test_admin_coupon_crud()
    {
        // ── Admin: Create coupon ──
        $createResponse = $this->withHeaders($this->adminHeaders())
            ->postJson("{$this->apiPrefix}/admin/coupons", [
                'code' => 'NEWCOUPON',
                'discount_type' => 'FLAT',
                'discount_value' => 25.00,
                'type' => 'FIXED',
            ]);

        $createResponse->assertStatus(201)
            ->assertJson(['success' => true, 'message' => 'Coupon created']);
        $couponId = $createResponse->json('data.id');

        // ── Admin: List coupons ──
        $this->withHeaders($this->adminHeaders())
            ->getJson("{$this->apiPrefix}/admin/coupons")
            ->assertStatus(200)
            ->assertJson(['success' => true]);

        // ── Admin: View coupon details ──
        $this->withHeaders($this->adminHeaders())
            ->getJson("{$this->apiPrefix}/admin/coupons/{$couponId}")
            ->assertStatus(200)
            ->assertJson(['success' => true]);

        // ── Admin: Update coupon ──
        $this->withHeaders($this->adminHeaders())
            ->putJson("{$this->apiPrefix}/admin/coupons/{$couponId}", [
                'discount_value' => 30.00,
            ])
            ->assertStatus(200)
            ->assertJson(['success' => true]);

        // ── Admin: Delete coupon ──
        $this->withHeaders($this->adminHeaders())
            ->deleteJson("{$this->apiPrefix}/admin/coupons/{$couponId}")
            ->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertDatabaseMissing('coupons', ['id' => $couponId]);
    }

    /** @test */
    public function test_admin_bulk_generate_coupons()
    {
        $response = $this->withHeaders($this->adminHeaders())
            ->postJson("{$this->apiPrefix}/admin/coupons/bulk-generate", [
                'count' => 3,
                'discount_value' => 15.00,
                'type' => 'FIXED',
                'discount_type' => 'FLAT',
                'max_uses' => 5,
            ]);

        $response->assertStatus(201)
            ->assertJson(['success' => true, 'message' => '3 coupons generated'])
            ->assertJsonStructure(['success', 'message', 'data']);
    }

    /** @test */
    public function test_coupon_analytics()
    {
        $response = $this->withHeaders($this->adminHeaders())
            ->getJson("{$this->apiPrefix}/admin/coupons/{$this->coupon->id}/analytics");

        $response->assertStatus(200)
            ->assertJson(['success' => true]);
    }
}
