<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * @deprecated Blade admin views are no longer used (admin is React SPA).
 *             This controller is kept for reference only.
 */
class AdminAuthController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth')->except(['login', 'loginPost']);
        $this->middleware('admin')->except(['login', 'loginPost']);
    }

    // ── Login / Logout ──
    public function login()
    {
        return view('admin.login');
    }

    public function loginPost(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        if (Auth::attempt($credentials)) {
            $user = Auth::user();
            if ($user->isAdmin()) {
                return redirect()->route('admin.dashboard');
            }
            Auth::logout();
            return back()->with('error', 'Unauthorized access. Admin privileges required.');
        }

        return back()->with('error', 'Invalid credentials');
    }

    public function logout()
    {
        Auth::logout();
        return redirect()->route('admin.login');
    }
}
