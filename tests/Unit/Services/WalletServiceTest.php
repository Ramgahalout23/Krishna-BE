<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Services\WalletService;
use App\Repositories\WalletRepository;
use App\Exceptions\AppError;
use Mockery;

class WalletServiceTest extends TestCase
{
    protected WalletRepository|\Mockery\MockInterface $walletRepository;
    protected WalletService $walletService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->walletRepository = Mockery::mock(WalletRepository::class);
        $this->walletService = new WalletService($this->walletRepository);
    }

    protected function tearDown(): void
    {
        // Verify but don't close
        if ($container = Mockery::getContainer()) {
            $container->mockery_verify();
        }
        parent::tearDown();
    }

    protected function mockWallet(float $balance = 100.00, array $overrides = []): object
    {
        $wallet = Mockery::mock();
        $wallet->shouldReceive('toArray')->andReturn([
            'id' => $overrides['id'] ?? 'wallet-1',
            'user_id' => $overrides['user_id'] ?? 'user-1',
            'balance' => $balance,
        ]);

        $wallet->id = $overrides['id'] ?? 'wallet-1';
        $wallet->user_id = $overrides['user_id'] ?? 'user-1';
        $wallet->balance = $balance;

        return $wallet;
    }

    /** @test */
    public function getWallet_returns_wallet()
    {
        $userId = 'user-1';
        $mockWallet = $this->mockWallet(250.00);

        $this->walletRepository->shouldReceive('findByUserOrFail')
            ->once()
            ->with($userId)
            ->andReturn($mockWallet);

        $result = $this->walletService->getWallet($userId);

        $this->assertEquals('wallet-1', $result['id']);
        $this->assertEquals(250.00, $result['balance']);
    }

    /** @test */
    public function getBalance_returns_balance()
    {
        $userId = 'user-1';
        $mockWallet = $this->mockWallet(100.00);

        $this->walletRepository->shouldReceive('findByUser')
            ->once()
            ->with($userId)
            ->andReturn($mockWallet);

        $result = $this->walletService->getBalance($userId);

        $this->assertEquals(100.00, $result);
    }

    /** @test */
    public function getBalance_returns_zero_when_no_wallet()
    {
        $userId = 'new-user';

        $this->walletRepository->shouldReceive('findByUser')
            ->once()
            ->with($userId)
            ->andReturn(null);

        $result = $this->walletService->getBalance($userId);

        $this->assertEquals(0.0, $result);
    }

    /** @test */
    public function recharge_adds_funds()
    {
        $userId = 'user-1';
        $amount = 50.00;

        $this->walletRepository->shouldReceive('addBalance')
            ->once()
            ->with($userId, $amount, 'Manual recharge', null)
            ->andReturn(true);

        $this->walletRepository->shouldReceive('findByUserOrFail')
            ->once()
            ->with($userId)
            ->andReturn($this->mockWallet(150.00));

        $result = $this->walletService->recharge($userId, $amount);

        $this->assertEquals(150.00, $result['balance']);
    }

    /** @test */
    public function recharge_throws_when_amount_zero()
    {
        $this->expectException(AppError::class);
        $this->expectExceptionMessage('Recharge amount must be positive');

        $this->walletService->recharge('user-1', 0);
    }

    /** @test */
    public function recharge_throws_when_amount_negative()
    {
        $this->expectException(AppError::class);
        $this->expectExceptionMessage('Recharge amount must be positive');

        $this->walletService->recharge('user-1', -10);
    }

    /** @test */
    public function recharge_accepts_custom_reason()
    {
        $userId = 'user-1';
        $amount = 25.00;

        $this->walletRepository->shouldReceive('addBalance')
            ->once()
            ->with($userId, $amount, 'Birthday bonus', null)
            ->andReturn(true);

        $this->walletRepository->shouldReceive('findByUserOrFail')
            ->once()
            ->with($userId)
            ->andReturn($this->mockWallet(125.00));

        $result = $this->walletService->recharge($userId, $amount, 'Birthday bonus');

        $this->assertEquals(125.00, $result['balance']);
    }

    /** @test */
    public function deduct_removes_funds()
    {
        $userId = 'user-1';
        $amount = 30.00;

        $this->walletRepository->shouldReceive('deductBalance')
            ->once()
            ->with($userId, $amount, 'Purchase', null)
            ->andReturn(true);

        $this->walletRepository->shouldReceive('findByUserOrFail')
            ->once()
            ->with($userId)
            ->andReturn($this->mockWallet(70.00));

        $result = $this->walletService->deduct($userId, $amount);

        $this->assertEquals(70.00, $result['balance']);
    }

    /** @test */
    public function deduct_throws_when_amount_zero()
    {
        $this->expectException(AppError::class);
        $this->expectExceptionMessage('Deduction amount must be positive');

        $this->walletService->deduct('user-1', 0);
    }

    /** @test */
    public function adjustWallet_adds_funds_for_admin()
    {
        $userId = 'user-1';
        $adminId = 'admin-1';
        $amount = 100.00;

        $this->walletRepository->shouldReceive('adjustBalance')
            ->once()
            ->with($userId, $amount, 'Promotional credit', $adminId)
            ->andReturn(true);

        $this->walletRepository->shouldReceive('findByUserOrFail')
            ->once()
            ->with($userId)
            ->andReturn($this->mockWallet(200.00));

        $result = $this->walletService->adjustWallet($userId, $amount, 'Promotional credit', $adminId);

        $this->assertEquals(200.00, $result['balance']);
    }

    /** @test */
    public function adjustWallet_deducts_funds_for_admin()
    {
        $userId = 'user-1';
        $adminId = 'admin-1';
        $amount = -50.00;

        $this->walletRepository->shouldReceive('adjustBalance')
            ->once()
            ->with($userId, $amount, 'Penalty', $adminId)
            ->andReturn(true);

        $this->walletRepository->shouldReceive('findByUserOrFail')
            ->once()
            ->with($userId)
            ->andReturn($this->mockWallet(50.00));

        $result = $this->walletService->adjustWallet($userId, $amount, 'Penalty', $adminId);

        $this->assertEquals(50.00, $result['balance']);
    }

    /** @test */
    public function getTransactions_returns_paginated_transactions()
    {
        $userId = 'user-1';

        $mockTransactions = [
            'data' => [
                ['id' => 'txn-1', 'type' => 'CREDIT', 'amount' => 100.00, 'reason' => 'Deposit'],
                ['id' => 'txn-2', 'type' => 'DEBIT', 'amount' => 25.00, 'reason' => 'Purchase'],
            ],
            'total' => 2,
            'per_page' => 20,
            'current_page' => 1,
        ];

        $this->walletRepository->shouldReceive('getTransactions')
            ->once()
            ->with($userId, 20)
            ->andReturn($mockTransactions);

        $result = $this->walletService->getTransactions($userId, 20);

        $this->assertCount(2, $result['data']);
    }

    /** @test */
    public function getAllWallets_returns_paginated_wallets()
    {
        $filters = ['min_balance' => 50];
        $mockWallets = ['data' => [['id' => 'wallet-1', 'balance' => 100]], 'total' => 1];

        $this->walletRepository->shouldReceive('getAll')
            ->once()
            ->with($filters)
            ->andReturn($mockWallets);

        $result = $this->walletService->getAllWallets($filters);

        $this->assertCount(1, $result['data']);
    }
}
