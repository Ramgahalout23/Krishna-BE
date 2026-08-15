<?php

// ── Admin Marketing Routes (Coupons, Banners, Promotions, Marketing, Campaigns, Ads) ──
// Included from routes/api.php within Route::middleware(['auth:sanctum', 'admin'])->prefix('admin')

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\CouponController;
use App\Http\Controllers\Api\BannerController;
use App\Http\Controllers\Api\PromotionController;
use App\Http\Controllers\Api\MarketingController;
use App\Http\Controllers\Api\CampaignTemplateController;
use App\Http\Controllers\Api\AdCampaignController;

// ── Coupons ──
Route::get('/coupons', [CouponController::class, 'adminIndex']);
Route::get('/coupons/{id}', [CouponController::class, 'show']);
Route::post('/coupons', [CouponController::class, 'store']);
Route::post('/coupons/bulk-generate', [CouponController::class, 'bulkGenerate']);
Route::patch('/coupons/{id}', [CouponController::class, 'update']);
Route::delete('/coupons/{id}', [CouponController::class, 'destroy']);
Route::patch('/coupons/{id}/toggle', [CouponController::class, 'toggle']);
Route::get('/coupons/{id}/analytics', [CouponController::class, 'analytics']);
Route::get('/coupons/{id}/usage-history', [CouponController::class, 'usageHistory']);

// ── Banners ──
Route::get('/banners', [BannerController::class, 'adminIndex']);
Route::post('/banners', [BannerController::class, 'store']);
Route::put('/banners/{id}', [BannerController::class, 'update']);
Route::patch('/banners/{id}/toggle', [BannerController::class, 'toggleStatus']);
Route::patch('/banners/reorder', [BannerController::class, 'reorder']);
Route::delete('/banners/{id}', [BannerController::class, 'destroy']);

// ── Promotions ──
Route::get('/promotions', [PromotionController::class, 'adminIndex']);
Route::post('/promotions', [PromotionController::class, 'store']);
Route::put('/promotions/{id}', [PromotionController::class, 'update']);
Route::delete('/promotions/{id}', [PromotionController::class, 'destroy']);

// ── Marketing (Admin) ──
Route::get('/marketing/dashboard', [MarketingController::class, 'getDashboard']);
Route::get('/marketing/subscribers/stats', [MarketingController::class, 'getSubscriberStats']);
Route::get('/marketing/subscribers', [MarketingController::class, 'getSubscribers']);
Route::get('/marketing/subscribers/export', [MarketingController::class, 'exportSubscribersCSV']);
Route::post('/marketing/subscribers/import/preview', [MarketingController::class, 'previewImportSubscribersCSV']);
Route::post('/marketing/subscribers/import', [MarketingController::class, 'importSubscribersCSV']);
Route::get('/marketing/subscribers/import/{importId}/status', [MarketingController::class, 'subscriberImportStatus']);
Route::get('/marketing/subscribers/{id}', [MarketingController::class, 'getSubscriberById']);
Route::post('/marketing/subscribers', [MarketingController::class, 'createSubscriber']);
Route::put('/marketing/subscribers/{id}', [MarketingController::class, 'updateSubscriber']);
Route::delete('/marketing/subscribers/{id}', [MarketingController::class, 'deleteSubscriber']);
Route::get('/marketing/campaigns', [MarketingController::class, 'getCampaigns']);
Route::get('/marketing/campaigns/{id}', [MarketingController::class, 'getCampaignById']);
Route::post('/marketing/campaigns', [MarketingController::class, 'createCampaign']);
Route::put('/marketing/campaigns/{id}', [MarketingController::class, 'updateCampaign']);
Route::delete('/marketing/campaigns/{id}', [MarketingController::class, 'deleteCampaign']);
Route::post('/marketing/campaigns/{id}/clone', [MarketingController::class, 'cloneCampaign']);
Route::post('/marketing/campaigns/{id}/send', [MarketingController::class, 'sendCampaign']);
Route::get('/marketing/campaigns/{id}/stats', [MarketingController::class, 'getCampaignStats']);
Route::get('/marketing/campaigns/{id}/recipients', [MarketingController::class, 'getCampaignRecipients']);
Route::get('/marketing/campaigns/{id}/recipients/export', [MarketingController::class, 'exportCampaignRecipientsCSV']);
Route::post('/marketing/campaigns/from-template', [MarketingController::class, 'createCampaignFromTemplate']);

// ── Campaign Templates (Admin) ──
Route::get('/campaign-templates', [CampaignTemplateController::class, 'getTemplates']);
Route::get('/campaign-templates/{id}', [CampaignTemplateController::class, 'getTemplateById']);
Route::post('/campaign-templates', [CampaignTemplateController::class, 'createTemplate']);
Route::put('/campaign-templates/{id}', [CampaignTemplateController::class, 'updateTemplate']);
Route::delete('/campaign-templates/{id}', [CampaignTemplateController::class, 'deleteTemplate']);
Route::post('/campaign-templates/seed-defaults', [CampaignTemplateController::class, 'getDefaultTemplates']);
Route::post('/campaign-templates/render', [CampaignTemplateController::class, 'renderTemplate']);

// ── Ad Campaigns (Admin) ──
Route::get('/ads/stats', [AdCampaignController::class, 'getStats']);
Route::get('/ads/analytics/performance', [AdCampaignController::class, 'getPerformanceReport']);
Route::get('/ads/analytics/brand-presets', [AdCampaignController::class, 'getBrandPresetPerformance']);
Route::get('/ads/analytics/budget-optimization', [AdCampaignController::class, 'getBudgetOptimization']);
Route::get('/ads/analytics/templates', [AdCampaignController::class, 'getAdTemplates']);
Route::get('/ads', [AdCampaignController::class, 'getCampaigns']);
Route::get('/ads/{id}', [AdCampaignController::class, 'getCampaignById']);
Route::post('/ads', [AdCampaignController::class, 'createCampaign']);
Route::put('/ads/{id}', [AdCampaignController::class, 'updateCampaign']);
Route::delete('/ads/{id}', [AdCampaignController::class, 'deleteCampaign']);
Route::post('/ads/compare/{campaignId1}/{campaignId2}', [AdCampaignController::class, 'compareCampaigns']);
Route::post('/ads/ai/generate-copy', [AdCampaignController::class, 'generateAdCopy']);
Route::post('/ads/ai/generate-variants', [AdCampaignController::class, 'generateAdVariants']);
Route::post('/ads/ai/generate-strategy', [AdCampaignController::class, 'generateFullStrategy']);
Route::post('/ads/ai/suggest-audience', [AdCampaignController::class, 'suggestAudience']);
Route::post('/ads/ai/generate-banner', [AdCampaignController::class, 'generateBannerDesign']);
Route::get('/ads/{id}/products', [AdCampaignController::class, 'getCampaignProducts']);
Route::post('/ads/{id}/products', [AdCampaignController::class, 'linkProduct']);
Route::put('/ads/{id}/products/{productId}', [AdCampaignController::class, 'updateProductLink']);
Route::delete('/ads/{id}/products/{productId}', [AdCampaignController::class, 'unlinkProduct']);
Route::post('/ads/{id}/products/bulk', [AdCampaignController::class, 'bulkLinkProducts']);
Route::post('/ads/{id}/products/{productId}/generate-creative', [AdCampaignController::class, 'generateCreativeFromProduct']);
Route::post('/ads/test-meta-connection', [AdCampaignController::class, 'testMetaConnection']);
Route::post('/ads/test-google-connection', [AdCampaignController::class, 'testGoogleConnection']);
Route::get('/ads/whatsapp-recipients', [AdCampaignController::class, 'getWhatsAppRecipients']);
Route::post('/ads/{id}/push-meta', [AdCampaignController::class, 'pushToMeta']);
Route::post('/ads/{id}/sync-stats', [AdCampaignController::class, 'syncMetaStats']);
Route::post('/ads/{id}/push-google', [AdCampaignController::class, 'pushToGoogle']);
Route::post('/ads/{id}/sync-google-stats', [AdCampaignController::class, 'syncGoogleStats']);
Route::post('/ads/{id}/push-whatsapp', [AdCampaignController::class, 'pushToWhatsApp']);
