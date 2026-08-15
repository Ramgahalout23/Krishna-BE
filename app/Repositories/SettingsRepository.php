<?php

namespace App\Repositories;

use App\Models\Setting;
use App\Traits\CacheKeyRegistry;
use Illuminate\Support\Facades\Cache;

class SettingsRepository extends BaseRepository
{
    use CacheKeyRegistry;
    private const CACHE_TTL = 600; // 10 minutes

    protected function modelClass(): string
    {
        return Setting::class;
    }

    /**
     * Get a single setting value by key, with individual cache.
     */
    public function getValue(string $key, mixed $default = null): mixed
    {
        $cacheKey = "setting_{$key}";
        return $this->cacheWithTracking($cacheKey, self::CACHE_TTL, function () use ($key, $default) {
            $setting = Setting::where('key', $key)->first();
            return $setting ? $setting->value : $default;
        });
    }

    /**
     * Set a setting value and clear related caches.
     */
    public function setValue(string $key, mixed $value): Setting
    {
        $this->clearTrackedCache();
        self::$_allSettingsCache = null; // reset same-request cache
        return Setting::updateOrCreate(['key' => $key], ['value' => $value]);
    }

    /**
     * All settings loaded within this request (reduces repeat DB hits
     * when getValue is called for multiple keys in the same request).
     */
    private static ?array $_allSettingsCache = null;

    /**
     * Get all settings as a flat key-value array.
     *
     * Uses Laravel's file-safe Cache::remember() which handles concurrent
     * writes atomically via temp-file + rename. The result is also cached
     * in a static variable to avoid repeat DB queries within the same request.
     */
    public function getAllAsArray(): array
    {
        // Request-level cache: avoids repeat DB + cache deserialization
        // within the same request (e.g. when multiple getValue() calls happen)
        if (self::$_allSettingsCache !== null) {
            return self::$_allSettingsCache;
        }

        self::$_allSettingsCache = $this->cacheWithTracking('settings_all', self::CACHE_TTL, function () {
            return Setting::pluck('value', 'key')->toArray();
        });

        return self::$_allSettingsCache;
    }
}
