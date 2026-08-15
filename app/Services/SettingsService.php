<?php

namespace App\Services;

use App\Repositories\SettingsRepository;
use App\Exceptions\AppError;
use App\Models\Setting;

class SettingsService
{
    public function __construct(
        protected SettingsRepository $settingsRepository
    ) {}

    public function getAll(): array
    {
        return $this->settingsRepository->getAllAsArray();
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->settingsRepository->getValue($key, $default);
    }

    public function set(string $key, mixed $value): array
    {
        $setting = $this->settingsRepository->setValue($key, $value);
        return $setting->toArray();
    }

    public function updateMultiple(array $data): array
    {
        foreach ($data as $key => $value) {
            if (!is_null($value)) {
                $this->settingsRepository->setValue($key, $value);
            }
        }
        return $this->getAll();
    }

    /**
     * Get a specific setting by module and key (module-scoped query).
     */
    public function getSetting(string $module, string $key): ?array
    {
        $setting = Setting::where('module', $module)->where('key', $key)->first();
        return $setting ? $setting->toArray() : null;
    }

    /**
     * Update a setting by module and key (module-scoped upsert).
     */
    public function updateSetting(string $module, string $key, string $value): array
    {
        $setting = Setting::updateOrCreate(
            ['module' => $module, 'key' => $key],
            ['value' => $value]
        );
        return $setting->toArray();
    }

    /**
     * Get all settings for a specific module.
     * Returns array of setting objects (matching TS behavior: array of {id, module, key, value}).
     */
    public function getAllSettings(string $module): array
    {
        return Setting::where('module', $module)->get()->toArray();
    }
}
