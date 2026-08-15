<?php

// ── Admin Content Routes (Settings, Pages, SEO, Translations, Webhooks, Looks, Reels, Currency) ──
// Included from routes/api.php within Route::middleware(['auth:sanctum', 'admin'])->prefix('admin')

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\SettingsController;
use App\Http\Controllers\Api\PageController;
use App\Http\Controllers\Api\SeoController;
use App\Http\Controllers\Api\TranslationController;
use App\Http\Controllers\Api\WebhookController;
use App\Http\Controllers\Api\CuratedLookController;
use App\Http\Controllers\Api\ReelController;
use App\Http\Controllers\Api\CurrencyController;

// ── Settings ──
Route::post('/settings', [SettingsController::class, 'store']);
Route::post('/settings/update-multiple', [SettingsController::class, 'update']);
Route::post('/settings/maintenance', [SettingsController::class, 'toggleMaintenance']);
Route::put('/settings/404', [SettingsController::class, 'updateCustom404']);
Route::get('/settings/maintenance/schedules', [SettingsController::class, 'getSchedules']);
Route::post('/settings/maintenance/schedules', [SettingsController::class, 'createSchedule']);
Route::put('/settings/maintenance/schedules/{id}', [SettingsController::class, 'updateSchedule']);
Route::delete('/settings/maintenance/schedules/{id}', [SettingsController::class, 'deleteSchedule']);
Route::put('/settings/{key}', [SettingsController::class, 'updateByKey']);

// ── CMS Pages (Admin) ──
Route::get('/pages', [PageController::class, 'adminIndex']);
Route::post('/pages', [PageController::class, 'store']);
Route::post('/pages/seed-defaults', [PageController::class, 'seedDefaults']);
Route::put('/pages/{id}', [PageController::class, 'adminUpdate']);
Route::delete('/pages/{id}', [PageController::class, 'adminDestroy']);

// ── SEO ──
Route::get('/seo/list/{entityType}', [SeoController::class, 'listSEO']);
Route::put('/seo/global', [SeoController::class, 'updateGlobalSEO']);
Route::put('/seo/robots', [SeoController::class, 'updateRobotsTxt']);
Route::post('/seo/sitemap/refresh', [SeoController::class, 'refreshSitemap']);
Route::get('/seo/sitemap/db', [SeoController::class, 'getSitemapFromDB']);
Route::put('/seo/{entityType}/{entityId}', [SeoController::class, 'updateEntitySEO']);
Route::get('/seo/{entityType}/{entityId}/full', [SeoController::class, 'getFullEntitySEO']);
Route::delete('/seo/{id}', [SeoController::class, 'destroySEO']);
Route::post('/seo/pages/{slug}', [SeoController::class, 'update']);

// ── SEO Dashboard ──
Route::get('/seo/dashboard', [SeoController::class, 'dashboard']);

// ── Advanced SEO ──
Route::get('/seo/advanced/settings', [SeoController::class, 'advancedSettings']);
Route::put('/seo/advanced/settings', [SeoController::class, 'updateAdvancedSettings']);
Route::get('/seo/advanced/schema/product/{entityId}', [SeoController::class, 'generateProductSchema']);
Route::get('/seo/advanced/schema/organization', [SeoController::class, 'generateOrganizationSchema']);
Route::get('/seo/advanced/schema/website', [SeoController::class, 'generateWebsiteSchema']);
Route::post('/seo/advanced/schema/breadcrumb', [SeoController::class, 'generateBreadcrumbSchema']);
Route::post('/seo/advanced/schema/faq', [SeoController::class, 'generateFAQSchema']);
Route::post('/seo/advanced/schema/auto/{entityType}/{entityId}', [SeoController::class, 'autoGenerateSchemas']);
Route::get('/seo/advanced/audit/{entityType}/{entityId}', [SeoController::class, 'auditEntitySEO']);
Route::post('/seo/advanced/audit/bulk', [SeoController::class, 'bulkAuditSEO']);
Route::get('/seo/advanced/breadcrumbs/{entityType}/{entityId}', [SeoController::class, 'generateBreadcrumbs']);
Route::post('/seo/advanced/indexnow', [SeoController::class, 'pushIndexNow']);

// ── Reels (Admin) ──
Route::get('/reels', [ReelController::class, 'adminIndex']);
Route::get('/reels/{id}', [ReelController::class, 'show']);
Route::post('/reels', [ReelController::class, 'store']);
Route::put('/reels/{id}', [ReelController::class, 'update']);
Route::patch('/reels/{id}/toggle', [ReelController::class, 'toggleStatus']);
Route::patch('/reels/reorder', [ReelController::class, 'reorder']);
Route::delete('/reels/{id}', [ReelController::class, 'destroy']);
Route::get('/reels/{id}/likes', [ReelController::class, 'adminLikes']);

// ── Curated Looks (Admin) ──
Route::get('/curated-looks', [CuratedLookController::class, 'adminIndex']);
Route::get('/curated-looks/{id}', [CuratedLookController::class, 'show']);
Route::post('/curated-looks', [CuratedLookController::class, 'store']);
Route::put('/curated-looks/{id}', [CuratedLookController::class, 'update']);
Route::delete('/curated-looks/{id}', [CuratedLookController::class, 'destroy']);
Route::patch('/curated-looks/reorder', [CuratedLookController::class, 'reorder']);
Route::post('/curated-looks/{id}/products', [CuratedLookController::class, 'syncProducts']);

// ── Currency Admin Routes ──
Route::get('/currencies', [CurrencyController::class, 'adminIndex']);
Route::post('/currencies', [CurrencyController::class, 'store']);
Route::post('/currencies/sync', [CurrencyController::class, 'sync']);
Route::delete('/currencies/{id}', [CurrencyController::class, 'destroy']);

// ── Translation / Language Admin Routes ──
Route::get('/languages', [TranslationController::class, 'adminLanguages']);
Route::post('/languages', [TranslationController::class, 'storeLanguage']);
Route::delete('/languages/{id}', [TranslationController::class, 'destroyLanguage']);
Route::post('/translations/bulk', [TranslationController::class, 'bulkUpdate']);
Route::get('/translations/{lang}/{group}', function (string $lang, string $group) {
    return app(\App\Http\Controllers\Api\TranslationController::class)->index(request()->merge(compact('lang', 'group')));
});

// ── Webhook Admin Routes ──
Route::get('/webhooks', [WebhookController::class, 'index']);
Route::post('/webhooks', [WebhookController::class, 'store']);
Route::put('/webhooks/{id}', [WebhookController::class, 'update']);
Route::delete('/webhooks/{id}', [WebhookController::class, 'destroy']);
Route::get('/webhooks/{id}/logs', [WebhookController::class, 'logs']);
Route::post('/webhooks/{id}/test', [WebhookController::class, 'test']);
