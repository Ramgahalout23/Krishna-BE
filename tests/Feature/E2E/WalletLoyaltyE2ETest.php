<?php

namespace Tests\Feature\E2E;

use Tests\TestCase;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Models\LoyaltyPoint;
use App\Models\LoyaltyTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;

class WalletLoyaltyE2ETest extends TestCase
{
    use RefreshDatabase;

    protected string $apiPrefix = '/api/v1';
    protected User $user;
    protected User $adminUser;
    protected string $token;
    protected string $adminToken;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['role' => 'CUSTOMER', 'is_active' => true]);
        $this->token = $this->user->createToken('auth-token')->plainTextToken;

        $this->adminUser = User::factory()->create(['role' => 'ADMIN', 'is_active' => true]);
        $this->adminToken = $this->adminUser->createToken('admin-token')->plainTextToken;
    }

    protected function authHeaders(): array
    {
        return ['Authorization' => "Bearer {$this->token}"];
    }

    protected function adminHeaders(): array
    {
        return ['Authorization' => "Bearer {$this->adminToken}"];
    }

    // ── Wallet Tests ──

    /** @test */
    public function test_wallet_balance()
    {
        // Ensure wallet exists
        Wallet::create(['user_id' => $this->user->id, 'balance' => 100.00]);

        $response = $this->withHeaders($this->authHeaders())
            ->getJson("{$this->apiPrefix}/wallet/balance");

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonStructure(['success', 'data' => ['balance']]);

        $this->assertEquals(100.00, (float) $response->json('data.balance'));
    }

    /** @test */
    public function test_wallet_recharge()
    {
        $response = $this->withHeaders($this->authHeaders())
            ->postJson("{$this->apiPrefix}/wallet/recharge", [
                'amount' => 50.00,
                'reason' => 'Adding funds',
            ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true, 'message' => 'Wallet recharged successfully'])
            ->assertJsonStructure(['success', 'message', 'data' => ['id', 'balance']]);
    }

    /** @test */
    public function test_wallet_transactions()
    {
        // Create wallet and a transaction
        $wallet = Wallet::create(['user_id' => $this->user->id, 'balance' => 200.00]);
        WalletTransaction::create([
            'wallet_id' => $wallet->id,
            'type' => 'CREDIT',
            'amount' => 200.00,
            'reason' => 'Initial deposit',
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->getJson("{$this->apiPrefix}/wallet/transactions");

        $response->assertStatus(200)
            ->assertJson(['success' => true]);
    }

    /** @test */
    public function test_wallet_show()
    {
        $wallet = Wallet::create(['user_id' => $this->user->id, 'balance' => 150.00]);
        WalletTransaction::create([
            'wallet_id' => $wallet->id,
            'type' => 'CREDIT',
            'amount' => 150.00,
            'reason' => 'Deposit',
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->getJson("{$this->apiPrefix}/wallet");

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonStructure(['success', 'data' => ['id', 'balance', 'transactions']]);
    }

    /** @test */
    public function test_wallet_auto_creation_on_recharge()
    {
        // No wallet exists yet - recharge should auto-create one
        $response = $this->withHeaders($this->authHeaders())
            ->postJson("{$this->apiPrefix}/wallet/recharge", [
                'amount' => 25.00,
            ]);

        $response->assertStatus(200);

        // Verify wallet was auto-created
        $this->assertDatabaseHas('wallets', [
            'user_id' => $this->user->id,
        ]);
    }

    /** @test */
    public function test_admin_wallet_adjustment()
    {
        // Ensure wallet exists
        Wallet::create(['user_id' => $this->user->id, 'balance' => 100.00]);

        $response = $this->withHeaders($this->adminHeaders())
            ->postJson("{$this->apiPrefix}/admin/wallets/adjust", [
                'user_id' => $this->user->id,
                'amount' => 50.00,
                'reason' => 'Promotional credit',
            ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true, 'message' => 'Wallet adjusted successfully']);
    }

    /** @test */
    public function test_admin_list_wallets()
    {
        Wallet::create(['user_id' => $this->user->id, 'balance' => 75.00]);

        $response = $this->withHeaders($this->adminHeaders())
            ->getJson("{$this->apiPrefix}/admin/wallets");

        $response->assertStatus(200)
            ->assertJson(['success' => true]);
    }

    /** @test */
    public function test_admin_wallet_show()
    {
        Wallet::create(['user_id' => $this->user->id, 'balance' => 300.00]);

        $response = $this->withHeaders($this->adminHeaders())
            ->getJson("{$this->apiPrefix}/admin/wallets/{$this->user->id}");

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonStructure(['success', 'data' => ['id', 'balance']]);
    }

    // ── Loyalty Tests ──

    /** @test */
    public function test_loyalty_points_show()
    {
        LoyaltyPoint::create(['user_id' => $this->user->id, 'points' => 500]);

        $response = $this->withHeaders($this->authHeaders())
            ->getJson("{$this->apiPrefix}/loyalty");

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonStructure(['success', 'data' => ['points', 'transactions']]);

        $this->assertEquals(500, $response->json('data.points'));
    }

    /** @test */
    public function test_loyalty_balance()
    {
        LoyaltyPoint::create(['user_id' => $this->user->id, 'points' => 1000]);

        $response = $this->withHeaders($this->authHeaders())
            ->getJson("{$this->apiPrefix}/loyalty/balance");

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonStructure(['success', 'data' => ['points', 'tier', 'money_value']]);
    }

    /** @test */
    public function test_loyalty_transaction_history()
    {
        $loyalty = LoyaltyPoint::create(['user_id' => $this->user->id, 'points' => 250]);
        LoyaltyTransaction::create([
            'loyalty_id' => $loyalty->id,
            'type' => 'EARNED',
            'points' => 250,
            'reason' => 'Welcome bonus',
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->getJson("{$this->apiPrefix}/loyalty/history");

        $response->assertStatus(200)
            ->assertJson(['success' => true]);
    }

    /** @test */
    public function test_loyalty_tier_info()
    {
        LoyaltyPoint::create(['user_id' => $this->user->id, 'points' => 2500]);

        $response = $this->withHeaders($this->authHeaders())
            ->getJson("{$this->apiPrefix}/loyalty/info");

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'success', 'data' => ['current_points', 'tier', 'conversion_rate', 'points_to_money', 'next_tier_points'],
            ]);

        $this->assertEquals('GOLD', $response->json('data.tier'));
        $this->assertEquals(2500, $response->json('data.current_points'));
    }

    /** @test */
    public function test_loyalty_zero_points()
    {
        // No loyalty record exists for this user
        $response = $this->withHeaders($this->authHeaders())
            ->getJson("{$this->apiPrefix}/loyalty/balance");

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonPath('data.points', 0)
            ->assertJsonPath('data.tier', 'BRONZE');
    }

    /** @test */
    public function test_admin_loyalty_adjustment()
    {
        LoyaltyPoint::create(['user_id' => $this->user->id, 'points' => 500]);

        $response = $this->withHeaders($this->adminHeaders())
            ->postJson("{$this->apiPrefix}/admin/loyalty/adjust", [
                'user_id' => $this->user->id,
                'points' => 200,
                'reason' => 'Bonus points for referral',
            ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true, 'message' => 'Loyalty points adjusted successfully']);
    }

    /** @test */
    public function test_admin_list_loyalty_all()
    {
        LoyaltyPoint::create(['user_id' => $this->user->id, 'points' => 100]);

        $response = $this->withHeaders($this->adminHeaders())
            ->getJson("{$this->apiPrefix}/admin/loyalty/all");

        $response->assertStatus(200)
            ->assertJson(['success' => true]);
    }

    /** @test */
    public function test_admin_loyalty_show()
    {
        LoyaltyPoint::create(['user_id' => $this->user->id, 'points' => 750]);

        $response = $this->withHeaders($this->adminHeaders())
            ->getJson("{$this->apiPrefix}/admin/loyalty/{$this->user->id}");

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonPath('data.points', 750);
    }
}
