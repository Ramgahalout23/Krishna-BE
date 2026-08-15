<?php

namespace App\Console\Commands;

use App\Models\MaintenanceSchedule;
use App\Models\Setting;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class MaintenanceScheduler extends Command
{
    protected $signature = 'maintenance:check-schedule';
    protected $description = 'Check and apply scheduled maintenance windows';

    public function handle(): int
    {
        $this->info('Checking scheduled maintenance windows...');

        try {
            // Activate maintenance if a scheduled window is due
            $dueSchedules = MaintenanceSchedule::where('status', 'SCHEDULED')
                ->where('start_time', '<=', now())
                ->where('end_time', '>', now())
                ->get();

            foreach ($dueSchedules as $schedule) {
                $schedule->update(['status' => 'ACTIVE']);
                Setting::updateOrCreate(
                    ['key' => 'maintenance_mode', 'module' => 'SYSTEM'],
                    ['value' => 'true']
                );
                Log::info("[MaintenanceScheduler] Maintenance activated: {$schedule->title}");
            }

            // Deactivate maintenance if window has passed
            $expiredSchedules = MaintenanceSchedule::where('status', 'ACTIVE')
                ->where('end_time', '<=', now())
                ->get();

            foreach ($expiredSchedules as $schedule) {
                $schedule->update(['status' => 'COMPLETED']);
                $remaining = MaintenanceSchedule::where('status', 'ACTIVE')
                    ->where('end_time', '>', now())
                    ->count();
                if ($remaining === 0) {
                    Setting::updateOrCreate(
                        ['key' => 'maintenance_mode', 'module' => 'SYSTEM'],
                        ['value' => 'false']
                    );
                }
                Log::info("[MaintenanceScheduler] Maintenance completed: {$schedule->title}");
            }

            $this->info("Processed: {$dueSchedules->count()} activated, {$expiredSchedules->count()} completed");
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Failed to check maintenance: {$e->getMessage()}");
            Log::error("[MaintenanceScheduler] Error: {$e->getMessage()}");
            return Command::FAILURE;
        }
    }
}
