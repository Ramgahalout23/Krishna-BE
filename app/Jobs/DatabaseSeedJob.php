<?php

namespace App\Jobs;

use App\Repositories\AdminRepository;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

class DatabaseSeedJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1; // Seeding is idempotent — no retry needed
    public int $timeout = 300; // 5 minutes — large seeders take time

    public function handle(AdminRepository $adminRepository): void
    {
        Log::info('[DatabaseSeedJob] Starting database seed');

        try {
            Artisan::call('db:seed', ['--force' => true]);

            // Clear dashboard cache so admin sees fresh data
            $adminRepository->clearDashboardCache();

            Log::info('[DatabaseSeedJob] Database seed completed');
        } catch (\Exception $e) {
            Log::error("[DatabaseSeedJob] Database seed failed: {$e->getMessage()}");
            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("[DatabaseSeedJob] Permanently failed: {$exception->getMessage()}");
    }
}
