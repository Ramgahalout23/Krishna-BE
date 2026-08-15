<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\Traits\MapsCamelCaseFields;
use App\Services\TicketService;
use App\Exceptions\AppError;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SupportTicketController extends Controller
{
    use MapsCamelCaseFields;
    public function __construct(protected TicketService $ticketService) {}

    public function index(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->ticketService->getUserTickets(Auth::id())]);
    }

    public function show(string $id): JsonResponse
    {
        try {
            return response()->json(['success' => true, 'data' => $this->ticketService->getById($id)]);
        } catch (AppError $e) { return $e->render(); }
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $input = $this->mapCamelCase($request->all(), [
                'orderId' => 'order_id',
            ]);
            $request->replace($input);

            $validated = $request->validate(['subject' => 'required|string|max:255', 'message' => 'nullable|string', 'order_id' => 'nullable|string', 'priority' => 'nullable|string|in:LOW,MEDIUM,HIGH,URGENT']);
            $ticket = $this->ticketService->create(Auth::id(), $validated);
            return response()->json(['success' => true, 'message' => 'Ticket created', 'data' => $ticket], 201);
        } catch (AppError $e) { return $e->render(); }
    }

    public function addMessage(Request $request, string $id): JsonResponse
    {
        try {
            $validated = $request->validate(['message' => 'required|string']);
            $msg = $this->ticketService->addMessage($id, $validated['message'], Auth::id());
            return response()->json(['success' => true, 'message' => 'Message added', 'data' => $msg], 201);
        } catch (AppError $e) { return $e->render(); }
    }

    public function adminIndex(Request $request): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->ticketService->getAll($request->all())]);
    }

    public function updateStatus(Request $request, string $id): JsonResponse
    {
        try {
            $validated = $request->validate(['status' => 'required|string|in:OPEN,IN_PROGRESS,RESOLVED,CLOSED']);
            return response()->json(['success' => true, 'message' => 'Status updated', 'data' => $this->ticketService->updateStatus($id, $validated['status'])]);
        } catch (AppError $e) { return $e->render(); }
    }

    /**
     * Admin adds a message to a ticket.
     * POST /api/v1/admin/tickets/{id}/messages
     * The TicketService already identifies admin users by their role and flags the message accordingly.
     */
    public function adminAddMessage(Request $request, string $id): JsonResponse
    {
        try {
            $validated = $request->validate(['message' => 'required|string']);
            $msg = $this->ticketService->addMessage($id, $validated['message'], Auth::id());
            return response()->json(['success' => true, 'message' => 'Reply added', 'data' => $msg], 201);
        } catch (AppError $e) { return $e->render(); }
    }

    // ── Admin CRUD ──

    /**
     * Get ticket stats (Admin).
     * GET /api/v1/admin/tickets/stats
     */
    public function adminStats(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->ticketService->getStats()]);
    }

    /**
     * Update a ticket (Admin).
     * PUT /api/v1/admin/tickets/{id}
     */
    public function adminUpdate(Request $request, string $id): JsonResponse
    {
        try {
            $validated = $request->validate([
                'subject' => 'sometimes|string|max:255',
                'priority' => 'sometimes|string|in:LOW,MEDIUM,HIGH,URGENT',
                'assigned_to' => 'nullable|string',
                'status' => 'sometimes|string|in:OPEN,IN_PROGRESS,WAITING_CUSTOMER,RESOLVED,CLOSED',
            ]);
            return response()->json(['success' => true, 'message' => 'Ticket updated', 'data' => $this->ticketService->adminUpdate($id, $validated)]);
        } catch (AppError $e) { return $e->render(); }
    }

    /**
     * Delete a ticket (Admin).
     * DELETE /api/v1/admin/tickets/{id}
     */
    public function adminDestroy(string $id): JsonResponse
    {
        try {
            $this->ticketService->adminDelete($id);
            return response()->json(['success' => true, 'message' => 'Ticket deleted']);
        } catch (AppError $e) { return $e->render(); }
    }
}

