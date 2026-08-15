<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Services\TicketService;
use App\Repositories\TicketRepository;
use App\Exceptions\AppError;
use Mockery;

class TicketServiceTest extends TestCase
{
    protected TicketRepository|\Mockery\MockInterface $ticketRepository;
    protected TicketService $ticketService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ticketRepository = Mockery::mock(TicketRepository::class);
        $this->ticketService = new TicketService($this->ticketRepository);
    }

    protected function tearDown(): void
    {
        // Verify but don't close — preserves alias mocks across tests
        if ($container = Mockery::getContainer()) {
            $container->mockery_verify();
        }
        parent::tearDown();
    }

    protected function mockTicket(array $overrides = []): object
    {
        $ticket = Mockery::mock();
        $ticket->shouldReceive('fresh')->andReturnSelf();
        $ticket->shouldReceive('load')->andReturnSelf();
        $ticket->shouldReceive('toArray')->andReturn([
            'id' => $overrides['id'] ?? 'tkt-1',
            'ticket_number' => $overrides['ticket_number'] ?? 'TKT-001',
            'user_id' => $overrides['user_id'] ?? 'user-1',
            'subject' => $overrides['subject'] ?? 'Help needed',
            'description' => $overrides['description'] ?? 'Description',
            'category' => $overrides['category'] ?? 'ORDER',
            'priority' => $overrides['priority'] ?? 'MEDIUM',
            'status' => $overrides['status'] ?? 'OPEN',
            'order_id' => $overrides['order_id'] ?? null,
            'assigned_to' => $overrides['assigned_to'] ?? null,
        ]);

        $ticket->id = $overrides['id'] ?? 'tkt-1';
        $ticket->user_id = $overrides['user_id'] ?? 'user-1';
        $ticket->status = $overrides['status'] ?? 'OPEN';

        return $ticket;
    }

    /** @test */
    public function create_creates_ticket_without_message()
    {
        $userId = 'user-1';
        $data = ['subject' => 'Help needed', 'priority' => 'HIGH'];

        $this->ticketRepository->shouldReceive('create')
            ->once()
            ->with(Mockery::on(function ($arg) use ($userId) {
                return $arg['user_id'] === $userId
                    && $arg['subject'] === 'Help needed'
                    && $arg['status'] === 'OPEN';
            }))
            ->andReturn($this->mockTicket(['subject' => 'Help needed']));

        $result = $this->ticketService->create($userId, $data);

        $this->assertEquals('Help needed', $result['subject']);
        $this->assertEquals('OPEN', $result['status']);
    }

    /** @test */
    public function create_creates_ticket_with_initial_message()
    {
        $userId = 'user-1';
        $data = [
            'subject' => 'Order issue',
            'message' => 'My order has not arrived',
            'order_id' => 'order-1',
            'priority' => 'URGENT',
        ];

        $ticket = $this->mockTicket([
            'id' => 'tkt-1',
            'subject' => 'Order issue',
            'priority' => 'URGENT',
            'order_id' => 'order-1',
        ]);

        $this->ticketRepository->shouldReceive('create')
            ->once()
            ->with(Mockery::on(function ($arg) {
                return $arg['subject'] === 'Order issue' && $arg['order_id'] === 'order-1';
            }))
            ->andReturn($ticket);

        $this->ticketRepository->shouldReceive('addMessage')
            ->once()
            ->with('tkt-1', 'My order has not arrived', $userId)
            ->andReturn(true);

        $result = $this->ticketService->create($userId, $data);

        $this->assertEquals('Order issue', $result['subject']);
        $this->assertEquals('URGENT', $result['priority']);
    }

    /** @test */
    public function getUserTickets_returns_tickets()
    {
        $userId = 'user-1';

        $this->ticketRepository->shouldReceive('getUserTickets')
            ->once()
            ->with($userId)
            ->andReturn(collect([$this->mockTicket()]));

        $result = $this->ticketService->getUserTickets($userId);

        $this->assertCount(1, $result);
    }

    /** @test */
    public function getUserTickets_returns_empty_when_none()
    {
        $userId = 'user-1';

        $this->ticketRepository->shouldReceive('getUserTickets')
            ->once()
            ->with($userId)
            ->andReturn(collect([]));

        $result = $this->ticketService->getUserTickets($userId);

        $this->assertEmpty($result);
    }

    /** @test */
    public function getById_returns_ticket()
    {
        $ticketId = 'tkt-1';

        $this->ticketRepository->shouldReceive('getWithDetails')
            ->once()
            ->with($ticketId)
            ->andReturn($this->mockTicket());

        $result = $this->ticketService->getById($ticketId);

        $this->assertEquals('TKT-001', $result['ticket_number']);
    }

    /** @test */
    public function getById_throws_when_not_found()
    {
        $this->ticketRepository->shouldReceive('getWithDetails')
            ->once()
            ->with('nonexistent')
            ->andReturn(null);

        $this->expectException(AppError::class);
        $this->expectExceptionMessage('Ticket not found');

        $this->ticketService->getById('nonexistent');
    }

    /** @test */
    public function addMessage_adds_to_own_ticket()
    {
        $ticketId = 'tkt-1';
        $userId = 'user-1';
        $message = 'I need more help';

        $ticket = $this->mockTicket();

        $this->ticketRepository->shouldReceive('findById')
            ->once()
            ->with($ticketId)
            ->andReturn($ticket);

        $msg = Mockery::mock();
        $msg->shouldReceive('toArray')->andReturn([
            'id' => 'msg-1',
            'content' => $message,
        ]);

        $this->ticketRepository->shouldReceive('addMessage')
            ->once()
            ->with($ticketId, $message, $userId)
            ->andReturn($msg);

        $result = $this->ticketService->addMessage($ticketId, $message, $userId);

        $this->assertEquals('msg-1', $result['id']);
    }

    /** @test */
    public function addMessage_throws_for_other_users_ticket()
    {
        $ticketId = 'tkt-other';
        $userId = 'user-1';

        $ticket = $this->mockTicket(['user_id' => 'other-user']);

        $this->ticketRepository->shouldReceive('findById')
            ->once()
            ->with($ticketId)
            ->andReturn($ticket);

        $this->expectException(AppError::class);
        $this->expectExceptionMessage('Not your ticket');

        $this->ticketService->addMessage($ticketId, 'Message', $userId);
    }

    /** @test */
    public function addMessage_throws_when_ticket_not_found()
    {
        $this->ticketRepository->shouldReceive('findById')
            ->once()
            ->with('nonexistent')
            ->andReturn(null);

        $this->expectException(AppError::class);
        $this->expectExceptionMessage('Ticket not found');

        $this->ticketService->addMessage('nonexistent', 'Message', 'user-1');
    }

    /** @test */
    public function updateStatus_updates_ticket_status()
    {
        $ticketId = 'tkt-1';
        $newStatus = 'IN_PROGRESS';

        $this->ticketRepository->shouldReceive('findByIdOrFail')
            ->once()
            ->with($ticketId)
            ->andReturn(true);

        $updated = $this->mockTicket(['status' => 'IN_PROGRESS']);
        $this->ticketRepository->shouldReceive('update')
            ->once()
            ->with($ticketId, ['status' => 'IN_PROGRESS'])
            ->andReturn($updated);

        $result = $this->ticketService->updateStatus($ticketId, $newStatus);

        $this->assertEquals('IN_PROGRESS', $result['status']);
    }

    /** @test */
    public function getAll_returns_all_tickets_for_admin()
    {
        $filters = ['status' => 'OPEN'];

        $this->ticketRepository->shouldReceive('getAll')
            ->once()
            ->with($filters)
            ->andReturn(new \Illuminate\Pagination\LengthAwarePaginator(
                [$this->mockTicket()], 1, 15, 1
            ));

        $result = $this->ticketService->getAll($filters);

        $this->assertCount(1, $result['data']);
    }

    /** @test */
    public function getStats_returns_correct_counts()
    {
        // TicketService::getStats uses SupportTicket model directly
        \App\Models\SupportTicket::shouldReceive('count')
            ->once()
            ->andReturn(10);

        \App\Models\SupportTicket::shouldReceive('where')
            ->times(4)
            ->andReturnSelf();
        \App\Models\SupportTicket::shouldReceive('count')
            ->times(4)
            ->andReturnValues([5, 2, 2, 1]);

        $result = $this->ticketService->getStats();

        $this->assertEquals(10, $result['total']);
        $this->assertEquals(5, $result['open']);
        $this->assertEquals(2, $result['in_progress']);
        $this->assertEquals(2, $result['resolved']);
        $this->assertEquals(1, $result['closed']);
    }

    /** @test */
    public function adminUpdate_updates_ticket()
    {
        $ticketId = 'tkt-1';
        $data = ['priority' => 'HIGH', 'assigned_to' => 'admin-1'];

        $this->ticketRepository->shouldReceive('findByIdOrFail')
            ->once()
            ->with($ticketId)
            ->andReturn(true);

        $updated = $this->mockTicket(['priority' => 'HIGH', 'assigned_to' => 'admin-1']);
        $updated->fresh = function () use ($updated) { return $updated; };
        $updated->load = function () use ($updated) { return $updated; };

        $this->ticketRepository->shouldReceive('update')
            ->once()
            ->with($ticketId, $data)
            ->andReturn($updated);

        $result = $this->ticketService->adminUpdate($ticketId, $data);

        $this->assertEquals('HIGH', $result['priority']);
    }

    /** @test */
    public function adminDelete_deletes_ticket()
    {
        $ticketId = 'tkt-1';

        $this->ticketRepository->shouldReceive('findByIdOrFail')
            ->once()
            ->with($ticketId)
            ->andReturn(true);

        $this->ticketRepository->shouldReceive('delete')
            ->once()
            ->with($ticketId)
            ->andReturn(true);

        $this->ticketService->adminDelete($ticketId);

        $this->assertTrue(true);  // No exception = success
    }
}
