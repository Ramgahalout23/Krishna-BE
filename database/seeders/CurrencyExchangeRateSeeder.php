<?php

namespace Database\Seeders;

use App\Models\CurrencyExchangeRate;
use Illuminate\Database\Seeder;

class CurrencyExchangeRateSeeder extends Seeder
{
    /**
     * Seed common currencies with realistic exchange rates (relative to USD).
     * Rates are sourced from approximate market values as of June 2026.
     */
    public function run(): void
    {
        $currencies = [
            ['code' => 'USD', 'name' => 'US Dollar',      'symbol' => '$',     'exchange_rate' => 1.000000, 'is_default' => true,  'is_active' => true],
            ['code' => 'EUR', 'name' => 'Euro',            'symbol' => '€',     'exchange_rate' => 0.930000, 'is_default' => false, 'is_active' => true],
            ['code' => 'GBP', 'name' => 'British Pound',   'symbol' => '£',     'exchange_rate' => 0.790000, 'is_default' => false, 'is_active' => true],
            ['code' => 'INR', 'name' => 'Indian Rupee',    'symbol' => '₹',     'exchange_rate' => 83.500000, 'is_default' => false, 'is_active' => true],
            ['code' => 'JPY', 'name' => 'Japanese Yen',    'symbol' => '¥',     'exchange_rate' => 157.500000, 'is_default' => false, 'is_active' => true],
            ['code' => 'CAD', 'name' => 'Canadian Dollar', 'symbol' => 'CA$',   'exchange_rate' => 1.370000, 'is_default' => false, 'is_active' => true],
            ['code' => 'AUD', 'name' => 'Australian Dollar','symbol' => 'A$',   'exchange_rate' => 1.540000, 'is_default' => false, 'is_active' => true],
            ['code' => 'CNY', 'name' => 'Chinese Yuan',    'symbol' => '¥',     'exchange_rate' => 7.240000, 'is_default' => false, 'is_active' => true],
            ['code' => 'AED', 'name' => 'UAE Dirham',      'symbol' => 'د.إ',   'exchange_rate' => 3.670000, 'is_default' => false, 'is_active' => true],
            ['code' => 'SGD', 'name' => 'Singapore Dollar', 'symbol' => 'S$',   'exchange_rate' => 1.350000, 'is_default' => false, 'is_active' => true],
        ];

        foreach ($currencies as $data) {
            CurrencyExchangeRate::updateOrCreate(
                ['code' => $data['code']],
                $data
            );
        }

        $this->command->info('✅ Currency exchange rates seeded: ' . count($currencies) . ' currencies');
    }
}
