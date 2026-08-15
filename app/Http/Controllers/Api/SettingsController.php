<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\SettingsService;
use App\Exceptions\AppError;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public function __construct(protected SettingsService $settingsService) {}

    /**
     * Flush caches affected by setting changes so they are reflected immediately
     * without waiting for their TTLs to expire.
     *
     * Flushes:
     * - 'app_init' — the app initialization data (layout, nav, etc.)
     * - 'setting_*' — feature toggle caches (curated looks, reels, reviews, best sellers)
     */
    private function flushAppInitCache(): void
    {
        \Illuminate\Support\Facades\Cache::forget('app_init');
        \Illuminate\Support\Facades\Cache::forget('homepage_all');
        \Illuminate\Support\Facades\Cache::forget('promotions_active');
        \Illuminate\Support\Facades\Cache::forget('settings_all');
        \Illuminate\Support\Facades\Cache::forget('setting_curatedLooksEnabled');
        \Illuminate\Support\Facades\Cache::forget('setting_reelsEnabled');
        \Illuminate\Support\Facades\Cache::forget('setting_reviewsEnabled');
        \Illuminate\Support\Facades\Cache::forget('setting_bestSellersEnabled');
        \Illuminate\Support\Facades\Cache::forget('email_enabled');
        \Illuminate\Support\Facades\Cache::forget('smtp_settings');
        \Illuminate\Support\Facades\Cache::forget('store_name');
        \Illuminate\Support\Facades\Cache::forget('sms_enabled');
    }

    public function index(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->settingsService->getAll()]);
    }

    public function show(string $key): JsonResponse
    {
        return response()->json(['success' => true, 'data' => ['key' => $key, 'value' => $this->settingsService->get($key)]]);
    }

    public function update(Request $request): JsonResponse
    {
        $settings = $this->settingsService->updateMultiple($request->all());
        $this->flushAppInitCache();
        return response()->json(['success' => true, 'message' => 'Settings updated', 'data' => $settings]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate(['key' => 'required|string', 'value' => 'required']);
        $this->flushAppInitCache();
        return response()->json(['success' => true, 'data' => $this->settingsService->set($validated['key'], $validated['value'])]);
    }

    // ── Maintenance Mode ──

    public function toggleMaintenance(Request $request): JsonResponse
    {
        $validated = $request->validate(['enabled' => 'required|boolean', 'message' => 'nullable|string']);
        $data = $this->settingsService->set('maintenance_mode', $validated['enabled'] ? '1' : '0');
        if (!empty($validated['message'])) {
            $this->settingsService->set('maintenance_message', $validated['message']);
        }
        $this->flushAppInitCache();
        return response()->json(['success' => true, 'message' => $validated['enabled'] ? 'Maintenance mode enabled' : 'Maintenance mode disabled', 'data' => $data]);
    }

    public function getMaintenanceStatus(): JsonResponse
    {
        $enabled = $this->settingsService->get('maintenance_mode', '0') === '1';
        $message = $this->settingsService->get('maintenance_message', 'Site is under maintenance');
        return response()->json(['success' => true, 'data' => ['enabled' => $enabled, 'message' => $message]]);
    }

    // ── Custom 404 ──

    public function updateCustom404(Request $request): JsonResponse
    {
        $validated = $request->validate(['title' => 'nullable|string', 'message' => 'nullable|string', 'redirect_url' => 'nullable|string']);
        foreach ($validated as $key => $value) {
            if (!is_null($value)) {
                $this->settingsService->set("custom_404_{$key}", $value);
            }
        }
        $this->flushAppInitCache();
        return response()->json(['success' => true, 'message' => 'Custom 404 settings updated']);
    }

    public function getCustom404(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => [
            'title' => $this->settingsService->get('custom_404_title', 'Page Not Found'),
            'message' => $this->settingsService->get('custom_404_message', 'The page you are looking for does not exist.'),
            'redirect_url' => $this->settingsService->get('custom_404_redirect_url', '/'),
        ]]);
    }

    // ── Maintenance Schedules ──

    public function getSchedules(): JsonResponse
    {
        $schedules = json_decode($this->settingsService->get('maintenance_schedules', '[]'), true) ?? [];
        return response()->json(['success' => true, 'data' => $schedules]);
    }

    public function createSchedule(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'message' => 'nullable|string',
        ]);
        $schedules = json_decode($this->settingsService->get('maintenance_schedules', '[]'), true) ?? [];
        $validated['id'] = (string) \Illuminate\Support\Str::uuid();
        $validated['created_at'] = now()->toDateTimeString();
        $schedules[] = $validated;
        $this->settingsService->set('maintenance_schedules', json_encode($schedules));
        return response()->json(['success' => true, 'message' => 'Schedule created', 'data' => $validated], 201);
    }

    public function updateSchedule(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'start_date' => 'sometimes|date',
            'end_date' => 'sometimes|date|after:start_date',
            'message' => 'nullable|string',
        ]);
        $schedules = json_decode($this->settingsService->get('maintenance_schedules', '[]'), true) ?? [];
        $found = false;
        foreach ($schedules as &$schedule) {
            if (($schedule['id'] ?? '') === $id) {
                $schedule = array_merge($schedule, $validated);
                $schedule['updated_at'] = now()->toDateTimeString();
                $found = true;
                break;
            }
        }
        if (!$found) {
            return response()->json(['success' => false, 'message' => 'Schedule not found'], 404);
        }
        $this->settingsService->set('maintenance_schedules', json_encode($schedules));
        return response()->json(['success' => true, 'message' => 'Schedule updated']);
    }

    public function deleteSchedule(string $id): JsonResponse
    {
        $schedules = json_decode($this->settingsService->get('maintenance_schedules', '[]'), true) ?? [];
        $filtered = array_values(array_filter($schedules, fn($s) => ($s['id'] ?? '') !== $id));
        if (count($filtered) === count($schedules)) {
            return response()->json(['success' => false, 'message' => 'Schedule not found'], 404);
        }
        $this->settingsService->set('maintenance_schedules', json_encode($filtered));
        return response()->json(['success' => true, 'message' => 'Schedule deleted']);
    }

    // ── Single Key Update (Admin) ──

    public function updateByKey(Request $request, string $key): JsonResponse
    {
        $validated = $request->validate(['value' => 'required']);
        $this->flushAppInitCache();
        return response()->json(['success' => true, 'data' => $this->settingsService->set($key, $validated['value'])]);
    }
}
