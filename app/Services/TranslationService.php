<?php

namespace App\Services;

use App\Models\Language;
use App\Models\Translation;
use App\Traits\CacheKeyRegistry;
use Illuminate\Support\Facades\Cache;

class TranslationService
{
    use CacheKeyRegistry;
    /**
     * Get all translations for a language group.
     */
    public function getTranslations(string $languageCode, string $group = 'frontend'): array
    {
        $cacheKey = "translations_{$languageCode}_{$group}";
        return $this->cacheWithTracking($cacheKey, 3600, function () use ($languageCode, $group) {
            return Translation::where('language_code', $languageCode)
                ->where('group', $group)
                ->pluck('value', 'key')
                ->toArray();
        });
    }

    /**
     * Get all supported languages.
     */
    public function getLanguages(): array
    {
        return $this->cacheWithTracking('languages_active', 3600, function () {
            return Language::where('is_active', true)
                ->select('code', 'name', 'native_name', 'is_default')
                ->get()
                ->toArray();
        });
    }

    /**
     * Get the default language code.
     */
    public function getDefaultLanguage(): string
    {
        $default = $this->cacheWithTracking('language_default', 3600, function () {
            return Language::where('is_default', true)->first();
        });
        return $default ? $default->code : 'en';
    }

    /**
     * Translate a single key.
     */
    public function translate(string $key, string $languageCode, string $group = 'frontend'): string
    {
        $translations = $this->getTranslations($languageCode, $group);
        return $translations[$key] ?? $key;
    }

    /**
     * Create or update a translation.
     */
    public function setTranslation(string $languageCode, string $group, string $key, string $value): Translation
    {
        $translation = Translation::updateOrCreate(
            ['language_code' => $languageCode, 'group' => $group, 'key' => $key],
            ['value' => $value]
        );

        $this->clearTrackedCache();
        return $translation;
    }

    /**
     * Bulk set translations.
     */
    public function bulkSetTranslations(string $languageCode, string $group, array $translations): void
    {
        foreach ($translations as $key => $value) {
            Translation::updateOrCreate(
                ['language_code' => $languageCode, 'group' => $group, 'key' => $key],
                ['value' => $value]
            );
        }
        $this->clearTrackedCache();
    }

    /**
     * Manage languages (admin).
     */
    public function upsertLanguage(array $data): Language
    {
        $lang = Language::updateOrCreate(
            ['code' => strtolower($data['code'])],
            [
                'name' => $data['name'],
                'native_name' => $data['native_name'] ?? null,
                'is_default' => $data['is_default'] ?? false,
                'is_active' => $data['is_active'] ?? true,
            ]
        );

        if ($lang->is_default) {
            Language::where('id', '!=', $lang->id)
                ->where('is_default', true)
                ->update(['is_default' => false]);
        }

        $this->clearTrackedCache();

        return $lang;
    }

    public function deleteLanguage(string $id): void
    {
        $lang = Language::findOrFail($id);
        Translation::where('language_code', $lang->code)->delete();
        $lang->delete();
        $this->clearTrackedCache();
    }

    public function getAllLanguagesAdmin(): array
    {
        return $this->cacheWithTracking('languages_admin', 3600, function () {
            return Language::latest()
                ->select('id', 'code', 'name', 'native_name', 'is_default', 'is_active')
                ->get()
                ->toArray();
        });
    }
}
