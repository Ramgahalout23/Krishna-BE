<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SupportTicket;
use App\Models\TicketMessage;
use App\Exceptions\AppError;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChatController extends Controller
{
    public function init(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate(['subject' => 'nullable|string|max:255', 'message' => 'nullable|string']);

            $ticket = SupportTicket::create([
                'ticket_number' => 'CHAT-' . now()->format('Ymd') . '-' . strtoupper(\Illuminate\Support\Str::random(8)),
                'user_id' => $request->user()->id,
                'subject' => $validated['subject'] ?? 'Chat Support',
                'description' => $validated['message'] ?? 'Chat initiated by user',
                'category' => 'OTHER',
                'status' => 'OPEN',
                'priority' => 'NORMAL',
            ]);

            if (!empty($validated['message'])) {
                TicketMessage::create([
                    'ticket_id' => $ticket->id,
                    'sender_id' => $request->user()->id,
                    'content' => $validated['message'],
                    'is_from_admin' => false,
                ]);
            }

            return response()->json(['success' => true, 'data' => $ticket->load('messages')]);
        } catch (AppError $e) { return $e->render(); }
        catch (\Exception $e) { return response()->json(['success' => false, 'message' => 'Failed to create chat: ' . $e->getMessage()], 500); }
    }

    public function sendMessage(Request $request, string $ticketId): JsonResponse
    {
        try {
            $validated = $request->validate(['content' => 'required|string']);
            $ticket = SupportTicket::where('id', $ticketId)->where('user_id', $request->user()->id)->firstOrFail();
            $msg = TicketMessage::create([
                'ticket_id' => $ticket->id,
                'sender_id' => $request->user()->id,
                'content' => $validated['content'],
                'is_from_admin' => false,
            ]);
            return response()->json(['success' => true, 'data' => $msg]);
        } catch (\Exception $e) { return response()->json(['success' => false, 'message' => 'Ticket not found'], 404); }
    }

    public function sendTyping(Request $request, string $ticketId): JsonResponse
    {
        return response()->json(['success' => true, 'message' => 'Typing indicator sent']);
    }

    public function getMessages(Request $request, string $ticketId): JsonResponse
    {
        try {
            $ticket = SupportTicket::where('id', $ticketId)->where('user_id', $request->user()->id)->firstOrFail();
            return response()->json(['success' => true, 'data' => $ticket->messages()->orderBy('created_at')->get()]);
        } catch (\Exception $e) { return response()->json(['success' => false, 'message' => 'Ticket not found'], 404); }
    }

    public function getAdminConversations(Request $request): JsonResponse
    {
        $tickets = SupportTicket::with(['user', 'messages'])->whereIn('status', ['OPEN', 'IN_PROGRESS'])->latest()->get();
        return response()->json(['success' => true, 'data' => $tickets]);
    }

    public function updateStatus(Request $request, string $ticketId): JsonResponse
    {
        try {
            $validated = $request->validate(['status' => 'required|in:OPEN,IN_PROGRESS,RESOLVED,CLOSED']);
            $ticket = SupportTicket::findOrFail($ticketId);
            $ticket->update(['status' => $validated['status']]);
            return response()->json(['success' => true, 'data' => $ticket]);
        } catch (AppError $e) { return $e->render(); }
    }

    public function getStats(Request $request): JsonResponse
    {
        $stats = [
            'total_conversations' => SupportTicket::count(),
            'open' => SupportTicket::where('status', 'OPEN')->count(),
            'in_progress' => SupportTicket::where('status', 'IN_PROGRESS')->count(),
            'resolved' => SupportTicket::where('status', 'RESOLVED')->count(),
            'closed' => SupportTicket::where('status', 'CLOSED')->count(),
        ];
        return response()->json(['success' => true, 'data' => $stats]);
    }
}
