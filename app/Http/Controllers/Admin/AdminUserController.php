<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AdminService;
use Illuminate\Http\Request;

/**
 * @deprecated Blade admin views are no longer used (admin is React SPA).
 *             This controller is kept for reference only.
 */
class AdminUserController extends Controller
{
    public function __construct(
        protected AdminService $adminService
    ) {
        $this->middleware('auth');
        $this->middleware('admin');
    }

    // ── Users ──
    public function usersIndex(Request $request)
    {
        $users = $this->adminService->getUsers($request->only(['search', 'role']));
        return view('admin.users.index', compact('users'));
    }

    public function usersShow(string $id)
    {
        $user = $this->adminService->getUserModel($id);
        return view('admin.users.show', compact('user'));
    }

    public function usersManage(Request $request, string $id)
    {
        $this->adminService->manageUser($id, $request->only(['role', 'is_active', 'is_blocked']));
        return redirect()->route('admin.users.show', $id)
            ->with('success', 'User updated successfully');
    }

    // ── Reviews ──
    public function reviewsIndex(Request $request)
    {
        $reviews = $this->adminService->getReviews($request->only(['is_moderated', 'rating']));
        return view('admin.reviews.index', compact('reviews'));
    }

    public function reviewsModerate(Request $request, string $id)
    {
        $request->validate(['action' => 'required|in:approve,reject']);
        $this->adminService->moderateReview($id, $request->action === 'approve');
        return redirect()->route('admin.reviews.index')
            ->with('success', 'Review moderated successfully');
    }
}
