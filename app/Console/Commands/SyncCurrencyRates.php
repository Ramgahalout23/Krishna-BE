<?php

namespace App\Console\Commands;

use App\Services\CurrencyService;
use Illuminate\Console\Command;

class SyncCurrencyRates extends Command
{
    protected $signature = 'currencies:sync
                           {--notify : Send a notification when sync completes}';
    protected $description = 'Fetch live exchange rates from Frankfurter API (ECB data) and update all currencies';

    public function handle(CurrencyService $currencyService): int
    {
        $this->info('🌐 Fetching live exchange rates from Frankfurter API (ECB data)...');

        $result = $currencyService->syncRates();

        $updated = $result['updated'] ?? 0;
        $skipped = $result['skipped'] ?? 0;
        $errors = $result['errors'] ?? [];

        $this->line('');
        $this->info("   ✓ {$updated} rate(s) updated");
        $this->info("   ✓ {$skipped} rate(s) unchanged");

        if (!empty($errors)) {
            $this->newLine();
            $this->warn('   ⚠️  ' . count($errors) . ' error(s):');
            foreach ($errors as $error) {
                $this->warn("       • {$error}");
            }
            $this->newLine();
            return self::FAILURE;
        }

        $this->newLine();
        $this->info('✅ All currency rates are up to date.');

        return self::SUCCESS;
    }
}
