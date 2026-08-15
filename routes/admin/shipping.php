<?php

// ── Admin Shipping Routes ──
// These are loaded as a separate Route::middleware(['auth:sanctum', 'admin'])->prefix('admin') group in api.php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ShippingController;

Route::post('/shipping/zones', [ShippingController::class, 'createZone']);
Route::get('/shipping/zones/list', [ShippingController::class, 'zonesList']);
Route::put('/shipping/zones/{id}', [ShippingController::class, 'updateZone']);
Route::delete('/shipping/zones/{id}', [ShippingController::class, 'deleteZone']);
Route::post('/shipping/rates', [ShippingController::class, 'createRate']);
Route::put('/shipping/rates/{id}', [ShippingController::class, 'updateRate']);
Route::delete('/shipping/rates/{id}', [ShippingController::class, 'deleteRate']);
Route::post('/shipping', [ShippingController::class, 'createShipping']);
Route::put('/shipping/{id}', [ShippingController::class, 'updateShipping']);
Route::get('/shipping/all', [ShippingController::class, 'getAllShippings']);
Route::get('/shipping/by-status', [ShippingController::class, 'getShipmentsByStatus']);
Route::get('/shipping/stats', [ShippingController::class, 'getShippingStats']);
