<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class AdminMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        if (!$request->user() || !$request->user()->isAdmin()) {
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => 'Unauthorized. Admin access required.'], 403);
            }
            // User is authenticated but not admin — show 403 page instead of redirect loop
            abort(403, 'Admin access required.');
        }
        return $next($request);
    }
}
