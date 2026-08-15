<?php

namespace App\Repositories;

use App\Models\SupportTicket;
use App\Models\TicketMessage;
use Illuminate\Database\Eloquent\Collection;

class TicketRepository extends BaseRepository
{
    protected function modelClass(): string
    {
        return SupportTicket::class;
    }

    /**
     * Get all tickets with filters, search, and pagination.
     */
    public function findAll(array $filters = [], int $page = 1, int $limit = 20): array
    {
        $query = SupportTicket::with([
            'user' => fn($q) => $q->select('id', 'first_name', 'last_name', 'email'),
            'order' => fn($q) => $q->select('id', 'order_number'),
            'messages' => fn($q) => $q->orderBy('created_at'),
        ]);

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (!empty($filters['priority'])) {
            $query->where('priority', $filters['priority']);
        }
        if (!empty($filters['category'])) {
            $query->where('category', $filters['category']);
        }
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('subject', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhere('ticket_number', 'like', "%{$search}%");
            });
        }

        $paginator = $query->latest()->paginate($limit, ['*'], 'page', $page);

        // Map each item to include camelCase fields expected by frontend
        $mappedItems = collect($paginator->items())->map(function ($ticket) {
            $data = $ticket->toArray();
            $data['createdAt'] = $data['created_at'] ?? null;
            $data['updatedAt'] = $data['updated_at'] ?? null;
            // Frontend reads t.message but DB column is 'description'
            $data['message'] = $data['description'] ?? '';

            // Build user display name from first_name + last_name
            $userName = '';
            if ($ticket->relationLoaded('user') && $ticket->user) {
                $userName = trim(($ticket->user->first_name ?? '') . ' ' . ($ticket->user->last_name ?? '')) ?: ($ticket->user->email ?? '');
                if (isset($data['user']) && is_array($data['user'])) {
                    $data['user']['name'] = $userName;
                    $data['user']['firstName'] = $data['user']['first_name'] ?? '';
                    $data['user']['lastName'] = $data['user']['last_name'] ?? '';
                }
            }
            $data['userName'] = $userName;

            return $data;
        });

        return [
            'items' => $mappedItems->toArray(),
            'page' => $paginator->currentPage(),
            'limit' => $paginator->perPage(),
            'total' => $paginator->total(),
            'total_pages' => $paginator->lastPage(),
        ];
    }

    /**
     * Get tickets by user ID with pagination and messages.
     */
    public function findByUserId(string $userId, int $page = 1, int $limit = 20): array
    {
        $query = SupportTicket::with(['messages' => fn($q) => $q->orderBy('created_at')])
            ->where('user_id', $userId);

        $paginator = $query->latest()->paginate($limit, ['*'], 'page', $page);

        return [
            'items' => $paginator->items(),
            'page' => $paginator->currentPage(),
            'limit' => $paginator->perPage(),
            'total' => $paginator->total(),
            'total_pages' => $paginator->lastPage(),
        ];
    }

    /**
     * Get ticket by ID with all relations.
     */
    public function getWithDetails(string $id): ?SupportTicket
    {
        return SupportTicket::with([
            'user' => fn($q) => $q->select('id', 'first_name', 'last_name', 'email'),
            'order',
            'messages' => fn($q) => $q->orderBy('created_at'),
            'attachments',
        ])->find($id);
    }

    /**
     * Find ticket by ticket number.
     */
    public function findByTicketNumber(string $ticketNumber): ?SupportTicket
    {
        return SupportTicket::with([
            'user' => fn($q) => $q->select('id', 'first_name', 'last_name', 'email'),
            'order',
            'messages' => fn($q) => $q->orderBy('created_at'),
            'attachments',
        ])->where('ticket_number', $ticketNumber)->first();
    }

    /**
     * Get user's tickets (simple list).
     */
    public function getUserTickets(string $userId): Collection
    {
        return SupportTicket::where('user_id', $userId)->latest()->get();
    }

    /**
     * Get all tickets with basic filters.
     */
    public function getAll(array $filters = []): \Illuminate\Pagination\LengthAwarePaginator
    {
        $query = SupportTicket::with('user');
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (!empty($filters['priority'])) {
            $query->where('priority', $filters['priority']);
        }
        return $query->latest()->paginate($filters['per_page'] ?? 15);
    }

    /**
     * Create a new ticket with auto-generated ticket number.
     */
    public function create(array $data): SupportTicket
    {
        $data['ticket_number'] = $data['ticket_number'] ?? 'TKT-' . now()->timestamp . '-' . strtoupper(substr(uniqid(), -6));
        return SupportTicket::create($data);
    }

    /**
     * Update ticket status.
     */
    public function updateStatus(string $id, string $status): SupportTicket
    {
        $ticket = $this->findByIdOrFail($id);
        $ticket->update(['status' => $status]);
        return $ticket->fresh();
    }

    /**
     * Add message to ticket.
     */
    public function addMessage(string $ticketId, string $content, ?string $userId = null, bool $isFromAdmin = false): TicketMessage
    {
        return TicketMessage::create([
            'ticket_id' => $ticketId,
            'content' => $content,
            'sender_id' => $userId,
            'is_from_admin' => $isFromAdmin,
        ]);
    }

    /**
     * Get ticket statistics.
     */
    public function getStats(): array
    {
        $total = SupportTicket::count();
        $open = SupportTicket::where('status', 'OPEN')->count();
        $inProgress = SupportTicket::where('status', 'IN_PROGRESS')->count();
        $resolved = SupportTicket::where('status', 'RESOLVED')->count();
        $closed = SupportTicket::where('status', 'CLOSED')->count();

        return [
            'total' => $total,
            'open' => $open,
            'in_progress' => $inProgress,
            'resolved' => $resolved,
            'closed' => $closed,
        ];
    }
}
