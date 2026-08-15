<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AdminService;
use App\Services\SettingsService;
use Illuminate\Http\Request;

/**
 * @deprecated Blade admin views are no longer used (admin is React SPA).
 *             This controller is kept for reference only.
 */
class AdminDashboardController extends Controller
{
    public function __construct(
        protected AdminService $adminService,
        protected SettingsService $settingsService
    ) {
        $this->middleware('auth');
        $this->middleware('admin');
    }

    // ── Dashboard ──
    public function dashboard()
    {
        $dashboard = $this->adminService->getDashboard();

        return view('admin.dashboard', [
            'totalRevenue' => $dashboard['totalRevenue'] ?? 0,
            'totalOrders' => $dashboard['totalOrders'] ?? 0,
            'totalUsers' => $dashboard['totalUsers'] ?? 0,
            'totalProducts' => $dashboard['totalProducts'] ?? 0,
            'pendingOrders' => $dashboard['pendingOrders'] ?? 0,
            'recentOrders' => $dashboard['recentOrders'] ?? collect(),
        ]);
    }

    // ── Notifications ──
    public function notificationsIndex()
    {
        $notifications = $this->adminService->getNotifications();
        return view('admin.notifications.index', compact('notifications'));
    }

    // ── Settings ──
    public function settings()
    {
        return view('admin.settings.index');
    }

    public function settingsUpdate(Request $request)
    {
        $validated = $request->validate([
            'site_name' => 'nullable|string|max:255',
            'site_email' => 'nullable|email',
            'currency' => 'nullable|string|size:3',
            'tax_rate' => 'nullable|numeric|min:0|max:100',
        ]);

        $this->adminService->updateSettings($validated);
        return redirect()->route('admin.settings')
            ->with('success', 'Settings saved successfully');
    }
}
