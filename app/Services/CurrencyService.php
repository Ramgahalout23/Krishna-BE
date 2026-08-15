<?php

namespace App\Services;

use App\Models\CurrencyExchangeRate;
use App\Traits\CacheKeyRegistry;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CurrencyService
{
    use CacheKeyRegistry;

    /**
     * Fetch live exchange rates from a free API (no API key required).
     * Tries Frankfurter (ECB data) first, falls back to ExchangeRate-API.
     * Normalizes all rates relative to the store's default currency.
     *
     * @return array{updated: int, skipped: int, errors: string[]}
     */
    public function syncRates(): array
    {
        $result = ['updated' => 0, 'skipped' => 0, 'errors' => []];

        try {
            // 1. Fetch latest rates — try Frankfurter first, fall back to open.er-api.com
            $rates = $this->fetchLiveRates();

            if ($rates === null) {
                throw new \Exception('All exchange rate APIs failed — unable to fetch live rates');
            }

            $eurRates = $rates;

            // 2. Get all currencies from the database
            $currencies = CurrencyExchangeRate::all();

            if ($currencies->isEmpty()) {
                $result['errors'][] = 'No currencies found in database to update';
                return $result;
            }

            // 3. Determine the default currency to normalize rates
            $defaultCurrency = $currencies->firstWhere('is_default', true)
                ?? $currencies->first();

            $defaultCode = strtoupper($defaultCurrency->code);

            // 4. Get the API rate for the default currency (relative to EUR)
            //    EUR -> DEFAULT rate: if DEFAULT is EUR, rate = 1; else look it up
            $eurToDefault = ($defaultCode === 'EUR') ? 1.0 : (float) ($eurRates[$defaultCode] ?? 1.0);

            if ($eurToDefault <= 0) {
                $eurToDefault = 1.0;
            }

            // 5. Update each currency's exchange rate
            $now = now();
            foreach ($currencies as $currency) {
                $code = strtoupper($currency->code);

                if ($code === $defaultCode) {
                    // Default currency always has rate 1.0
                    if ((float) $currency->exchange_rate !== 1.0) {
                        $currency->exchange_rate = 1.0;
                        $currency->last_synced_at = $now;
                        $currency->save();
                        $result['updated']++;
                    }
                    continue;
                }

                // Rate from Frankfurter is EUR -> CODE
                // We need DEFAULT -> CODE = (EUR -> CODE) / (EUR -> DEFAULT)
                $eurToCode = ($code === 'EUR') ? 1.0 : (float) ($eurRates[$code] ?? null);

                if ($eurToCode === null || $eurToCode <= 0) {
                    $result['errors'][] = "Rate for {$code} not available from API";
                    $result['skipped']++;
                    continue;
                }

                $normalizedRate = round($eurToCode / $eurToDefault, 6);

                if ((float) $currency->exchange_rate !== $normalizedRate) {
                    $currency->exchange_rate = $normalizedRate;
                    $currency->last_synced_at = $now;
                    $currency->save();
                    $result['updated']++;
                } else {
                    $result['skipped']++;
                }
            }

            // 6. Flush caches        $this->clearTrackedCache();

        } catch (\Exception $e) {
            Log::error('Currency sync failed: ' . $e->getMessage());
            $result['errors'][] = $e->getMessage();
        }

        return $result;
    }

    /**
     * Try to fetch live exchange rates from multiple free APIs.
     * Returns a flat array of {CODE: rate} relative to EUR, or null on total failure.
     */
    protected function fetchLiveRates(): ?array
    {
        $providers = [
            [
                'name' => 'Frankfurter',
                'url' => 'https://api.frankfurter.dev/v1/latest?base=EUR',
                'rates_path' => 'rates',
            ],
            [
                'name' => 'ExchangeRate-API',
                'url' => 'https://open.er-api.com/v6/latest/EUR',
                'rates_path' => 'rates',
            ],
        ];

        foreach ($providers as $provider) {
            try {
                $response = Http::timeout(8)->get($provider['url']);

                if (!$response->successful()) {
                    Log::warning("{$provider['name']} returned status {$response->status()}, trying next...");
                    continue;
                }

                $data = $response->json();
                $rates = $data[$provider['rates_path']] ?? [];

                if (!empty($rates)) {
                    Log::info("Currency rates synced successfully from {$provider['name']}");
                    return $rates;
                }

                Log::warning("{$provider['name']} returned empty rates, trying next...");
            } catch (\Exception $e) {
                Log::warning("{$provider['name']} failed: {$e->getMessage()}, trying next...");
            }
        }

        return null;
    }

    /**
     * Get all active currencies.
     */
    public function getAllActive(): array
    {
        return $this->cacheWithTracking('currencies_active', 3600, function () {
            return CurrencyExchangeRate::where('is_active', true)
                ->select('code', 'name', 'symbol', 'exchange_rate', 'is_default')
                ->get()
                ->toArray();
        });
    }

    /**
     * Get the default currency.
     */
    public function getDefault(): ?CurrencyExchangeRate
    {
        return $this->cacheWithTracking('currency_default', 3600, function () {
            return CurrencyExchangeRate::where('is_default', true)->first()
                ?? CurrencyExchangeRate::first();
        });
    }

    /**
     * Convert amount from default currency to target currency.
     */
    public function convert(float $amount, string $targetCurrency): float
    {
        $rate = CurrencyExchangeRate::where('code', strtoupper($targetCurrency))
            ->where('is_active', true)
            ->first();

        if (!$rate) return $amount;

        return round($amount * (float) $rate->exchange_rate, 2);
    }

    /**
     * Get all currencies (admin) — cached + column-selected.
     */
    public function getAll(): array
    {
        return $this->cacheWithTracking('currencies_admin', 600, function () {
            return CurrencyExchangeRate::latest()
                ->select('id', 'code', 'name', 'symbol', 'exchange_rate', 'last_synced_at', 'is_active', 'is_default')
                ->get()
                ->toArray();
        });
    }

    /**
     * Create or update a currency.
     */
    public function upsert(array $data): CurrencyExchangeRate
    {
        $currency = CurrencyExchangeRate::updateOrCreate(
            ['code' => strtoupper($data['code'])],
            [
                'name' => $data['name'],
                'symbol' => $data['symbol'],
                'exchange_rate' => $data['exchange_rate'] ?? 1.0,
                'is_default' => $data['is_default'] ?? false,
                'is_active' => $data['is_active'] ?? true,
            ]
        );

        if ($currency->is_default) {
            CurrencyExchangeRate::where('id', '!=', $currency->id)
                ->where('is_default', true)
                ->update(['is_default' => false]);
        }

        $this->clearTrackedCache();

        return $currency;
    }

    /**
     * Delete a currency.
     */
    public function delete(string $id): void
    {
        $currency = CurrencyExchangeRate::findOrFail($id);
        $currency->delete();
        $this->clearTrackedCache();
    }
}
