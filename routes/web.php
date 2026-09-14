<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Admin is now fully served via React frontend.
| Blade admin views & routes have been removed.
| All admin API endpoints are in routes/api.php under 'admin' prefix.
|
*/

// ── IndexNow Verification File ──
// IndexNow requires a verification file at /{key}.txt to prove domain ownership.
// The file is served either from storage/app/public/indexnow/ or generated on the fly
// using the md5(host) key (consistent with AdvancedSeoService::pushToIndexNow).
Route::get('/{filename}.txt', function (string $filename) {
    // Only match IndexNow-style keys: 8-128 alphanumeric + dashes
    if (!preg_match('/^[a-zA-Z0-9\-]{8,128}$/', $filename)) {
        abort(404);
    }

    // First check if a physical file exists (uploaded by admin)
    $storagePath = storage_path("app/public/indexnow/{$filename}.txt");
    if (file_exists($storagePath)) {
        return response()->file($storagePath, ['Content-Type' => 'text/plain']);
    }

    // Fallback: generate dynamically using md5(host) — consistent with AdvancedSeoService
    $host = request()->getHost();
    $expectedKey = md5($host);

    if ($filename === $expectedKey) {
        return response($expectedKey, 200)
            ->header('Content-Type', 'text/plain');
    }

    abort(404);
})->where('filename', '[a-zA-Z0-9\-]{8,128}');

// ── Direct Storage File Serving (Fallback when symlink is absent on shared hosting) ──
Route::get('/storage/{path}', function (string $path) {
    $fullPath = storage_path("app/public/{$path}");
    if (!file_exists($fullPath) || is_dir($fullPath)) {
        $fullPath = storage_path("app/{$path}");
    }
    if (!file_exists($fullPath) || is_dir($fullPath)) {
        abort(404);
    }
    return response()->file($fullPath, [
        'Access-Control-Allow-Origin' => '*',
        'Access-Control-Allow-Methods' => 'GET, OPTIONS',
        'Cache-Control' => 'public, max-age=31536000',
    ]);
})->where('path', '.*');

Route::get('/uploads/{path}', function (string $path) {
    $fullPath = storage_path("app/public/uploads/{$path}");
    if (!file_exists($fullPath) || is_dir($fullPath)) {
        $fullPath = storage_path("app/uploads/{$path}");
    }
    if (!file_exists($fullPath) || is_dir($fullPath)) {
        abort(404);
    }
    return response()->file($fullPath, [
        'Access-Control-Allow-Origin' => '*',
        'Access-Control-Allow-Methods' => 'GET, OPTIONS',
        'Cache-Control' => 'public, max-age=31536000',
    ]);
})->where('path', '.*');

// ── React SPA Entry Point ──
// Serve the React app's index.html for all SPA routes so client-side
// routing (react-router) handles navigation properly.
// API routes are prefixed with /api and handled separately.
// Horizon dashboard routes (/horizon*) are registered by HorizonServiceProvider
// (registered before RouteServiceProvider) so they take priority over this catch-all.
Route::get('/{any?}', function () {
    return response()->file(public_path('index.html'));
})->where('any', '.*');
