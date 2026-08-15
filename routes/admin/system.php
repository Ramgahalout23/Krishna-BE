<?php

// ── Admin System Routes (Logs, Backups, Queue, Scheduler, AI, Misc, Tracking, etc.) ──
// Included from routes/api.php within Route::middleware(['auth:sanctum', 'admin'])->prefix('admin')

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\UtilityController;
use App\Http\Controllers\Api\LogController;
use App\Http\Controllers\Api\BackupController;
use App\Http\Controllers\Api\QueueController;
use App\Http\Controllers\Api\ExportController;
use App\Http\Controllers\Api\TrackingController;
use App\Http\Controllers\Api\AIController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\RefundController;
use App\Http\Controllers\Api\ReturnController;
use App\Http\Controllers\Api\SMSController;
use App\Http\Controllers\Api\ChatController;
use App\Http\Controllers\Api\TaxController;
use App\Http\Controllers\Api\WalletController;
use App\Http\Controllers\Api\LoyaltyController;
use App\Http\Controllers\Api\EmailTemplateController;
use App\Http\Controllers\Api\NotificationTemplateController;
use App\Http\Controllers\Api\SchedulerController;

// ── Misc Admin ──
Route::get('/email/preview', [UtilityController::class, 'emailPreview']);
Route::post('/email/test', [UtilityController::class, 'emailTest']);
Route::post('/upload', [UtilityController::class, 'uploadFile']);
Route::post('/upload/multiple', [UtilityController::class, 'uploadMultiple']);
Route::post('/cache/clear', [UtilityController::class, 'cacheClear']);
Route::post('/database/seed', [UtilityController::class, 'databaseSeed']);

// ── Logs ──
Route::get('/logs', [LogController::class, 'logs']);
Route::delete('/logs/{filename}', [LogController::class, 'deleteLog']);
Route::post('/logs/{filename}/truncate', [LogController::class, 'truncateLog']);
Route::post('/logs/{filename}/archive', [LogController::class, 'archiveLog']);
Route::get('/logs/{filename}/download', [LogController::class, 'downloadLog']);
Route::get('/logs/tail', [LogController::class, 'tailLog']);
Route::get('/logs/archived', [LogController::class, 'archivedLogs']);
Route::get('/logs/archived/{filename}', [LogController::class, 'viewArchivedLog']);
Route::get('/logs/archived/{filename}/download', [LogController::class, 'downloadArchivedLog']);
Route::delete('/logs/archived/{filename}', [LogController::class, 'deleteArchivedLog']);

// ── Backups ──
Route::get('/backup-settings', [BackupController::class, 'backupSettings']);
Route::patch('/backup-settings', [BackupController::class, 'backupSettingsUpdate']);
Route::post('/backup', [BackupController::class, 'backupCreate']);
Route::get('/backups', [BackupController::class, 'backupsList']);
Route::get('/backups/{backupId}/status', [BackupController::class, 'backupStatus']);
Route::get('/backups/{filename}', [BackupController::class, 'backupDownload']);
Route::delete('/backups/{filename}', [BackupController::class, 'backupDelete']);

// ── Tracking (specific routes BEFORE generic routes) ──
Route::get('/tracking/pageviews/stats', [TrackingController::class, 'pageViewStats']);
Route::get('/tracking/pageviews', [TrackingController::class, 'adminPageViews']);
Route::get('/tracking/events/stats', [TrackingController::class, 'getEventStats']);
Route::get('/tracking/events', [TrackingController::class, 'getEvents']);
Route::get('/tracking/sessions/active', [TrackingController::class, 'activeSessions']);
Route::get('/tracking/sessions/stats', [TrackingController::class, 'sessionStats']);
Route::get('/tracking/dashboard', [TrackingController::class, 'dashboard']);
Route::get('/tracking/journey/{userId}', [TrackingController::class, 'getUserJourney']);
Route::get('/tracking/traffic-sources', [TrackingController::class, 'trafficSources']);

// ── Payments (Admin) ──
Route::get('/payments/all', [PaymentController::class, 'getAllPayments']);
Route::get('/payments/stats', [PaymentController::class, 'getPaymentStats']);
Route::get('/payments/{id}', [PaymentController::class, 'getPaymentDetails']);
Route::post('/refunds/{refundId}/approve', [PaymentController::class, 'approveRefund']);
Route::post('/refunds/{refundId}/reject', [PaymentController::class, 'rejectRefund']);

// ── Chat (Admin) ──
Route::get('/chat/conversations', [ChatController::class, 'getAdminConversations']);
Route::patch('/chat/{ticketId}/status', [ChatController::class, 'updateStatus']);
Route::get('/chat/stats', [ChatController::class, 'getStats']);

// ── Tax Rates (Admin) ──
Route::get('/tax-rates', [TaxController::class, 'getTaxRates']);
Route::get('/tax-rates/{id}', [TaxController::class, 'getTaxRate']);
Route::post('/tax-rates', [TaxController::class, 'createTaxRate']);
Route::put('/tax-rates/{id}', [TaxController::class, 'updateTaxRate']);
Route::delete('/tax-rates/{id}', [TaxController::class, 'deleteTaxRate']);

// ── AI Routes (Admin) ──
Route::post('/ai/generate-product-description', [AIController::class, 'generateProductDescription']);
Route::post('/ai/generate-short-description', [AIController::class, 'generateShortDescription']);
Route::post('/ai/generate-seo-meta', [AIController::class, 'generateSeoMeta']);
Route::post('/ai/generate-image', [AIController::class, 'generateImage']);
Route::post('/ai/generate-category-description', [AIController::class, 'generateCategoryDescription']);
Route::post('/ai/generate-variant-description', [AIController::class, 'generateVariantDescription']);
Route::post('/ai/generate-variant-images', [AIController::class, 'generateVariantImages']);
Route::post('/ai/generate-variant-images-stream', [AIController::class, 'generateVariantImagesStream']);
Route::post('/ai/test-connection', [AIController::class, 'testConnection']);
Route::post('/ai/generate-page-content', [AIController::class, 'generatePageContent']);
Route::post('/ai/translate', [AIController::class, 'translateWithAI']);
Route::get('/ai/status/{taskId}', [AIController::class, 'aiStatus']);

// ── Notification Templates (Admin) ──
Route::get('/notification-templates', [NotificationTemplateController::class, 'listTemplates']);
Route::get('/notification-templates/{id}', [NotificationTemplateController::class, 'getTemplate']);
Route::put('/notification-templates/{id}', [NotificationTemplateController::class, 'updateTemplate']);
Route::patch('/notification-templates/{id}/toggle', [NotificationTemplateController::class, 'toggleTemplate']);
Route::post('/notification-templates/{id}/preview', [NotificationTemplateController::class, 'previewTemplate']);

// ── Email Templates (Admin) ──
Route::get('/email-templates', [EmailTemplateController::class, 'listTemplates']);
Route::get('/email-templates/{id}', [EmailTemplateController::class, 'getTemplate']);
Route::put('/email-templates/{id}', [EmailTemplateController::class, 'updateTemplate']);
Route::patch('/email-templates/{id}/toggle', [EmailTemplateController::class, 'toggleTemplate']);
Route::get('/email-templates/{id}/preview', [EmailTemplateController::class, 'previewTemplate']);
Route::post('/email-templates/{id}/test', [EmailTemplateController::class, 'sendTestEmail']);
Route::post('/email-templates/{id}/preview-html', [EmailTemplateController::class, 'previewTemplate']);

// ── Wallet Admin Routes ──
Route::get('/wallets', [WalletController::class, 'adminIndex']);
Route::post('/wallets/adjust', [WalletController::class, 'adjust']);
Route::get('/wallets/{userId}', [WalletController::class, 'adminShow']);

// ── Loyalty Admin Routes ──
Route::get('/loyalty/all', [LoyaltyController::class, 'adminIndex']);
Route::post('/loyalty/adjust', [LoyaltyController::class, 'adjust']);
Route::get('/loyalty/{userId}', [LoyaltyController::class, 'adminShow']);

// ── Refund Admin Routes ──
Route::get('/refund-requests', [RefundController::class, 'adminIndex']);
Route::post('/refund-requests/{id}/approve', [RefundController::class, 'approve']);
Route::post('/refund-requests/{id}/reject', [RefundController::class, 'reject']);

// ── Return Admin Routes ──
Route::get('/return-requests', [ReturnController::class, 'adminIndex']);
Route::get('/return-requests/{id}', [ReturnController::class, 'show']);
Route::post('/return-requests/{id}/approve', [ReturnController::class, 'approve']);
Route::post('/return-requests/{id}/reject', [ReturnController::class, 'reject']);
Route::post('/return-requests/{id}/complete', [ReturnController::class, 'complete']);
Route::get('/refunds/all', [ReturnController::class, 'allRefunds']);

// ── SMS Admin Routes ──
Route::get('/sms/health', [SMSController::class, 'health']);
Route::post('/sms/send', [SMSController::class, 'send']);

// ── Scheduler Admin Routes (controller-based for route:cache compatibility) ──
Route::post('/scheduler/backup', [SchedulerController::class, 'backup']);
Route::post('/scheduler/ads', [SchedulerController::class, 'ads']);
Route::post('/scheduler/campaigns', [SchedulerController::class, 'campaigns']);
Route::post('/scheduler/maintenance', [SchedulerController::class, 'maintenance']);

// ── Async Export Jobs (Admin) ──
Route::post('/exports', [ExportController::class, 'dispatchExport']);
Route::get('/exports', [ExportController::class, 'listExports']);
Route::get('/exports/{id}', [ExportController::class, 'exportStatus']);
Route::get('/exports/{id}/download', [ExportController::class, 'downloadExport']);

// ── Queue Monitoring (Admin) ──
Route::get('/queue/failed-jobs', [QueueController::class, 'failedJobs']);
Route::post('/queue/retry/{uuid}', [QueueController::class, 'retryFailedJob']);
Route::post('/queue/retry-all', [QueueController::class, 'retryAllFailedJobs']);
Route::delete('/queue/flush-failed', [QueueController::class, 'flushFailedJobs']);
