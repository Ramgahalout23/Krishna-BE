<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Models\Promotion;
use App\Models\Setting;
use App\Services\CurrencyService;
use App\Services\TranslationService;
use App\Services\SettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class AppInitController extends Controller
{
    public function __construct(
        protected CurrencyService $currencyService,
        protected TranslationService $translationService,
        protected SettingsService $settingsService,
    ) {}

    /**
     * Consolidated app initialization endpoint — returns ALL data needed
     * by the app shell (Navbar, Footer, i18n, currency display, etc.)
     * in a single response.
     *
     * This replaces 8+ individual HTTP requests (each booting Laravel from scratch),
     * dramatically improving initial page load time.
     *
     * GET /api/v1/app-init
     */
    public function __invoke(): JsonResponse
    {
        $data = Cache::remember('app_init', 300, function () {

            // ── 1. Maintenance status (from SettingsService) ──
            $maintenance = [];
            try {
                $enabled = $this->settingsService->get('maintenance_mode', '0') === '1';
                $message = $this->settingsService->get('maintenance_message', 'Site is under maintenance');
                $maintenance = ['enabled' => $enabled, 'message' => $message];
            } catch (\Exception $e) {
                logger()->warning('AppInit: maintenance fetch failed', ['error' => $e->getMessage()]);
            }

            // ── 2. Active currencies (from CurrencyService) ──
            $currencies = [];
            try {
                $currencies = $this->currencyService->getAllActive();
            } catch (\Exception $e) {
                logger()->warning('AppInit: currencies fetch failed', ['error' => $e->getMessage()]);
            }

            // ── 3. Available languages (from TranslationService) ──
            $languages = [];
            try {
                $languages = $this->translationService->getLanguages();
            } catch (\Exception $e) {
                logger()->warning('AppInit: languages fetch failed', ['error' => $e->getMessage()]);
                $languages = ['en'];
            }

            // ── 4. Navigation pages (for navbar links) ──
            // The navbar only renders each page's title + slug as a link, so only
            // those columns are shipped. Full page records (including HTML content)
            // are heavy and never read from app-init.
            $pages = [];
            try {
                $pages = Page::where('is_published', true)
                    ->orderBy('title')
                    ->select('id', 'title', 'slug')
                    ->get()
                    ->toArray();
            } catch (\Exception $e) {
                logger()->warning('AppInit: pages fetch failed', ['error' => $e->getMessage()]);
            }

            // ── 5. Active promotions flag (for the nav "Offers"/LIVE badge) ──
            // The frontend only checks whether any active promotion exists —
            // shipping full promotion objects (image URLs + related products/
            // categories) was wasted bandwidth.
            $hasActivePromotions = false;
            try {
                $salesEnabled = $this->settingsService->get('salesEnabled', 'true');
                if ($salesEnabled !== 'false' && $salesEnabled !== '0') {
                    $hasActivePromotions = Promotion::where('is_active', true)
                        ->where(function ($q) {
                            $q->whereNull('end_date')->orWhere('end_date', '>=', now());
                        })
                        ->exists();
                }
            } catch (\Exception $e) {
                logger()->warning('AppInit: promotions fetch failed', ['error' => $e->getMessage()]);
            }

            // ── 6. Tracking config — REMOVED. The frontend tracker
            // (src/services/tracker.js) manages enable/disable entirely via
            // localStorage; nothing reads this field from app-init.

            // ── 7. ALL settings (replaces the separate GET /settings API call) ──
            $settings = [];
            try {
                $settings = Setting::pluck('value', 'key')->toArray();
            } catch (\Exception $e) {
                logger()->warning('AppInit: settings fetch failed', ['error' => $e->getMessage()]);
            }

            return compact('maintenance', 'currencies', 'languages', 'pages', 'hasActivePromotions', 'settings');
        });

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }
}
