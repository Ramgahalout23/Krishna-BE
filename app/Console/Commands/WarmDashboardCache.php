<?php

namespace App\Console\Commands;

use App\Repositories\AdminRepository;
use Illuminate\Console\Command;

class WarmDashboardCache extends Command
{
    protected $signature = 'dashboard:warm-cache {--force : Clear existing cache before warming}';
    protected $description = 'Pre-warm all dashboard and analytics caches so the first admin visit is fast';

    public function handle(AdminRepository $adminRepository): int
    {
        if ($this->option('force')) {
            $this->info('🗑️  Clearing stale dashboard cache...');
            $adminRepository->clearDashboardCache();
        }

        $this->info('🔥 Warming dashboard cache...');
        $start = microtime(true);

        $adminRepository->warmDashboardCache();

        $elapsed = round(microtime(true) - $start, 2);
        $this->info("   ✓ All dashboard caches warmed in {$elapsed}s");

        return self::SUCCESS;
    }
}
