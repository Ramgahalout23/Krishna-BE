<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Log;

class OAuthSettingsService
{
    /**
     * Resolve Google OAuth credentials.
     * Priority: env vars > DB settings.
     */
    public function getGoogleCredentials(): array
    {
        $db = $this->getCachedOAuthSettings();

        return [
            'client_id' => config('services.google.client_id') ?: ($db['googleClientId'] ?? null),
            'client_secret' => config('services.google.client_secret') ?: ($db['googleClientSecret'] ?? null),
            'callback_url' => config('services.google.redirect') ?: url('/api/v1/auth/google/callback'),
        ];
    }

    /**
     * Resolve Facebook OAuth credentials.
     * Priority: env vars > DB settings.
     */
    public function getFacebookCredentials(): array
    {
        $db = $this->getCachedOAuthSettings();

        return [
            'client_id' => config('services.facebook.client_id') ?: ($db['facebookAppId'] ?? null),
            'client_secret' => config('services.facebook.client_secret') ?: ($db['facebookAppSecret'] ?? null),
            'callback_url' => config('services.facebook.redirect') ?: url('/api/v1/auth/facebook/callback'),
        ];
    }

    /**
     * Get detailed OAuth provider status.
     */
    public function getOAuthProviderStatus(): array
    {
        $google = $this->getGoogleCredentials();
        $facebook = $this->getFacebookCredentials();

        return [
            'google' => [
                'configured' => !empty($google['client_id']) && !empty($google['client_secret']),
                'source' => !empty(config('services.google.client_id')) ? 'env' : (!empty($google['client_id']) ? 'database' : null),
                'has_client_id' => !empty($google['client_id']),
                'has_client_secret' => !empty($google['client_secret']),
            ],
            'facebook' => [
                'configured' => !empty($facebook['client_id']) && !empty($facebook['client_secret']),
                'source' => !empty(config('services.facebook.client_id')) ? 'env' : (!empty($facebook['client_id']) ? 'database' : null),
                'has_client_id' => !empty($facebook['client_id']),
                'has_client_secret' => !empty($facebook['client_secret']),
            ],
        ];
    }

    /**
     * Get cached OAuth settings from database.
     */
    private function getCachedOAuthSettings(): array
    {
        try {
            $keys = ['googleClientId', 'googleClientSecret', 'facebookAppId', 'facebookAppSecret'];
            return Setting::whereIn('key', $keys)->pluck('value', 'key')->toArray();
        } catch (\Exception $e) {
            Log::warning('[OAuthSettings] Failed to fetch DB settings: ' . $e->getMessage());
            return [];
        }
    }
}
