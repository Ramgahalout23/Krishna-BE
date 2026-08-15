<?php

namespace App\Services;

use App\Repositories\TicketRepository;
use App\Exceptions\AppError;
use App\Models\SupportTicket;
use App\Models\Order;

class TicketService
{
    public function __construct(
        protected TicketRepository $ticketRepository
    ) {}

    /**
     * Validate pagination parameters.
     */
    private function validatePagination(int $page, int $limit): array
    {
        return [
            'page' => max(1, $page),
            'limit' => max(1, min(100, $limit)),
        ];
    }

    /**
     * Get all tickets with filters (Admin).
     */
    public function getAll(array $filters = [], int $page = 1, int $limit = 20): array
    {
        $p = $this->validatePagination($page, $limit);
        return $this->ticketRepository->findAll($filters, $p['page'], $p['limit']);
    }

    /**
     * Get user's tickets with pagination.
     */
    public function getUserTickets(string $userId, int $page = 1, int $limit = 20): array
    {
        if (empty($userId)) throw AppError::validation('User ID is required');
        $p = $this->validatePagination($page, $limit);
        return $this->ticketRepository->findByUserId($userId, $p['page'], $p['limit']);
    }

    /**
     * Get ticket by ID with optional authorization check.
     */
    public function getById(string $id, ?string $userId = null): array
    {
        $ticket = $this->ticketRepository->getWithDetails($id);
        if (!$ticket) throw AppError::notFound('Ticket not found');

        // Non-admin: verify ownership
        if ($userId && $ticket->user_id !== $userId) {
            throw AppError::forbidden('Unauthorized to view this ticket');
        }

        return $ticket->toArray();
    }

    /**
     * Create new ticket with validation.
     */
    public function create(string $userId, array $data): array
    {
        if (empty($data['subject'])) throw AppError::validation('Subject is required');
        if (empty($data['description'] ?? $data['message'] ?? '')) throw AppError::validation('Description is required');
        if (empty($data['category'])) throw AppError::validation('Category is required');

        if (strlen($data['subject']) > 200) throw AppError::validation('Subject must not exceed 200 characters');
        $desc = $data['description'] ?? $data['message'] ?? '';
        if (strlen($desc) > 5000) throw AppError::validation('Description must not exceed 5000 characters');

        // Validate category
        $validCategories = ['ORDER', 'PAYMENT', 'SHIPPING', 'PRODUCT', 'REFUND', 'ACCOUNT', 'TECHNICAL', 'OTHER'];
        if (!in_array(strtoupper($data['category']), $validCategories)) {
            throw AppError::validation('Invalid category');
        }

        // If orderId provided, verify it exists and belongs to the user
        if (!empty($data['order_id'])) {
            $order = Order::find($data['order_id']);
            if (!$order) throw AppError::notFound('Order not found');
            if ($order->user_id !== $userId) throw AppError::validation('Order does not belong to this user');
        }

        $ticket = $this->ticketRepository->create([
            'ticket_number' => 'TKT-' . now()->timestamp . '-' . strtoupper(substr(uniqid(), -6)),
            'user_id' => $userId,
            'order_id' => $data['order_id'] ?? null,
            'subject' => $data['subject'],
            'description' => $desc,
            'category' => strtoupper($data['category']),
            'priority' => $data['priority'] ?? 'MEDIUM',
            'status' => 'OPEN',
        ]);

        // Create initial message if provided
        if (!empty($data['message'])) {
            $this->ticketRepository->addMessage($ticket->id, $data['message'], $userId);
        }

        return $ticket->fresh()->load('user:id,first_name,last_name,email')->toArray();
    }

    /**
     * Add message to ticket with auto status transitions.
     */
    public function addMessage(string $ticketId, string $message, string $userId): array
    {
        $ticket = $this->ticketRepository->getWithDetails($ticketId);
        if (!$ticket) throw AppError::notFound('Ticket not found');

        if (empty($message)) throw AppError::validation('Message content is required');
        if (strlen($message) > 5000) throw AppError::validation('Message must not exceed 5000 characters');

        // Check if sender is admin
        $user = \App\Models\User::find($userId);
        $isAdmin = $user && in_array($user->role, ['ADMIN', 'SUPER_ADMIN']);

        if (!$isAdmin && $ticket->user_id !== $userId) {
            throw AppError::forbidden('Unauthorized to reply to this ticket');
        }

        $msg = $this->ticketRepository->addMessage($ticketId, $message, $userId, $isAdmin || false);

        // Auto status transitions
        if (!$isAdmin && $ticket->status === 'RESOLVED') {
            $this->ticketRepository->updateStatus($ticketId, 'OPEN');
        }
        if ($isAdmin && $ticket->status === 'WAITING_CUSTOMER') {
            $this->ticketRepository->updateStatus($ticketId, 'IN_PROGRESS');
        }

        return $msg->load('sender:id,first_name,last_name')->toArray();
    }

    /**
     * Update ticket status with transition validation.
     */
    public function updateStatus(string $id, string $status): array
    {
        $ticket = $this->ticketRepository->findByIdOrFail($id);

        $validStatuses = ['OPEN', 'IN_PROGRESS', 'WAITING_CUSTOMER', 'RESOLVED', 'CLOSED'];
        $status = strtoupper($status);
        if (!in_array($status, $validStatuses)) {
            throw AppError::validation('Invalid status');
        }

        // Validate status transitions
        $allowedTransitions = [
            'OPEN' => ['IN_PROGRESS', 'RESOLVED', 'CLOSED'],
            'IN_PROGRESS' => ['RESOLVED', 'WAITING_CUSTOMER', 'CLOSED'],
            'WAITING_CUSTOMER' => ['IN_PROGRESS', 'RESOLVED', 'CLOSED'],
            'RESOLVED' => ['CLOSED', 'IN_PROGRESS'],
            'CLOSED' => ['OPEN'],
        ];

        $allowed = $allowedTransitions[$ticket->status] ?? null;
        if ($allowed && !in_array($status, $allowed)) {
            throw AppError::validation(
                "Cannot transition from {$ticket->status} to {$status}"
            );
        }

        $updated = $this->ticketRepository->updateStatus($id, $status);
        return $updated->fresh()->load('user')->toArray();
    }

    /**
     * Admin update ticket.
     */
    public function adminUpdate(string $id, array $data): array
    {
        $this->ticketRepository->findByIdOrFail($id);

        // Validate category if provided
        if (!empty($data['category'])) {
            $validCategories = ['ORDER', 'PAYMENT', 'SHIPPING', 'PRODUCT', 'REFUND', 'ACCOUNT', 'TECHNICAL', 'OTHER'];
            if (!in_array(strtoupper($data['category']), $validCategories)) {
                throw AppError::validation('Invalid category');
            }
        }

        // Validate priority if provided
        if (!empty($data['priority'])) {
            $validPriorities = ['LOW', 'MEDIUM', 'HIGH', 'URGENT'];
            if (!in_array(strtoupper($data['priority']), $validPriorities)) {
                throw AppError::validation('Invalid priority');
            }
        }

        return $this->ticketRepository->update($id, $data)->fresh()->load('user')->toArray();
    }

    /**
     * Admin delete ticket.
     */
    public function adminDelete(string $id): void
    {
        $this->ticketRepository->findByIdOrFail($id);
        $this->ticketRepository->delete($id);
    }

    /**
     * Get ticket statistics.
     */
    public function getStats(): array
    {
        return $this->ticketRepository->getStats();
    }
}
