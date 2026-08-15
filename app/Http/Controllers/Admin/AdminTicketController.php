<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AdminService;
use Illuminate\Http\Request;

/**
 * @deprecated Blade admin views are no longer used (admin is React SPA).
 *             This controller is kept for reference only.
 */
class AdminTicketController extends Controller
{
    public function __construct(
        protected AdminService $adminService
    ) {
        $this->middleware('auth');
        $this->middleware('admin');
    }

    // ── Tickets ──
    public function ticketsIndex(Request $request)
    {
        $tickets = $this->adminService->getTickets($request->only(['status', 'priority']));
        return view('admin.tickets.index', compact('tickets'));
    }

    public function ticketsShow(string $id)
    {
        $ticket = $this->adminService->getTicketModel($id);
        return view('admin.tickets.show', compact('ticket'));
    }

    public function ticketsUpdateStatus(Request $request, string $id)
    {
        $request->validate(['status' => 'required|string']);
        $this->adminService->updateTicketStatus($id, $request->status);
        return redirect()->route('admin.tickets.show', $id)
            ->with('success', 'Ticket status updated');
    }
}
