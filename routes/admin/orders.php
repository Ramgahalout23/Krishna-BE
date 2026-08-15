<?php

// ── Admin Orders, Abandoned Carts, Export Routes ──
// Included from routes/api.php within Route::middleware(['auth:sanctum', 'admin'])->prefix('admin')

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\AbandonedCartController;
use App\Http\Controllers\Api\ExportController;

// ── Orders ──
Route::get('/orders/revenue-stats', [OrderController::class, 'getRevenueStats']);
Route::get('/orders', [OrderController::class, 'allOrders']);
Route::get('/orders/export', [ExportController::class, 'exportOrders']);
Route::get('/orders/{id}', [OrderController::class, 'show']);
Route::patch('/orders/{id}/status', [OrderController::class, 'updateStatus']);
Route::put('/orders/{id}/edit', [OrderController::class, 'editOrder']);

// ── Abandoned Carts (specific before generic {id}) ──
Route::get('/abandoned-carts/stats', [AbandonedCartController::class, 'stats']);
Route::get('/abandoned-carts', [AbandonedCartController::class, 'all']);
Route::get('/abandoned-carts/{id}', [AbandonedCartController::class, 'show']);
Route::post('/abandoned-carts/{id}/remind', [AbandonedCartController::class, 'sendReminder']);
Route::delete('/abandoned-carts/{id}', [AbandonedCartController::class, 'destroy']);
