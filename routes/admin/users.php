<?php

// ── Admin User, Staff, Reviews, Tickets, Notifications Routes ──
// Included from routes/api.php within Route::middleware(['auth:sanctum', 'admin'])->prefix('admin')

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\UserManagementController;
use App\Http\Controllers\Api\ReviewController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\SupportTicketController;

// ── Users ──
Route::get('/users', [UserManagementController::class, 'users']);
Route::get('/users/export', [UserManagementController::class, 'exportUsers']);
Route::get('/users/{id}', [UserManagementController::class, 'userDetail']);
Route::post('/users/{id}/manage', [UserManagementController::class, 'manageUser']);
Route::patch('/users/{id}/role', [UserManagementController::class, 'updateUserRole']);

// ── Staff ──
Route::get('/staff', [UserManagementController::class, 'staff']);
Route::post('/staff', [UserManagementController::class, 'staffCreate']);
Route::patch('/staff/{id}', [UserManagementController::class, 'staffUpdate']);

// ── Reviews ──
Route::get('/reviews', [ReviewController::class, 'adminIndex']);
Route::get('/reviews/pending', [ReviewController::class, 'pendingReviews']);
Route::get('/reviews/{id}', [ReviewController::class, 'adminShow']);
Route::post('/reviews/{id}/moderate', [ReviewController::class, 'moderate']);
Route::post('/reviews/{id}/approve', [ReviewController::class, 'approve']);
Route::post('/reviews/{id}/reject', [ReviewController::class, 'reject']);
Route::delete('/reviews/{id}', [ReviewController::class, 'destroy']);

// ── Notifications (Admin) ──
Route::get('/notifications/all', [NotificationController::class, 'adminGetAll']);
Route::delete('/notifications/{id}', [NotificationController::class, 'adminDelete']);
Route::post('/notifications/system', [NotificationController::class, 'store']);
Route::post('/notifications/bulk', [NotificationController::class, 'sendBulkNotification']);

// ── Tickets ──
Route::get('/tickets/stats', [SupportTicketController::class, 'adminStats']);
Route::get('/tickets', [SupportTicketController::class, 'adminIndex']);
Route::patch('/tickets/{id}/status', [SupportTicketController::class, 'updateStatus']);
Route::put('/tickets/{id}', [SupportTicketController::class, 'adminUpdate']);
Route::post('/tickets/{id}/messages', [SupportTicketController::class, 'adminAddMessage']);
Route::delete('/tickets/{id}', [SupportTicketController::class, 'adminDestroy']);
