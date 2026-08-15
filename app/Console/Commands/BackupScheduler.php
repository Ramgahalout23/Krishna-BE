<?php

namespace App\Console\Commands;

use App\Models\Setting;
use App\Services\BackupService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class BackupScheduler extends Command
{
    protected $signature = 'backup:run {--force : Run backup even if not scheduled}';
    protected $description = 'Run scheduled database backup if conditions match';

    public function handle(BackupService $backupService): int
    {
        $force = $this->option('force');

        if (!$force) {
            $settings = $backupService->getBackupSettings();
            $frequency = $settings['backup_frequency'] ?? 'manual';

            if ($frequency === 'manual') {
                $this->info('Backup frequency is set to manual — skipping. Use --force to run anyway.');
                return Command::SUCCESS;
            }

            if ($frequency === 'daily') {
                $backupTime = $settings['backup_time'] ?? '02:00';
                if (now()->format('H:i') !== $backupTime) {
                    $this->info("Scheduled backup time is {$backupTime}, current time is " . now()->format('H:i') . ' — skipping.');
                    return Command::SUCCESS;
                }
            }

            if ($frequency === 'weekly') {
                $dayOfWeek = $settings['backup_day_of_week'] ?? 'Monday';
                $backupTime = $settings['backup_time'] ?? '02:00';
                if (now()->format('l') !== $dayOfWeek || now()->format('H:i') !== $backupTime) {
                    $this->info("Scheduled for {$dayOfWeek} at {$backupTime} — skipping.");
                    return Command::SUCCESS;
                }
            }
        }

        $this->info('Starting database backup...');

        try {
            $backup = $backupService->createBackup();
            $this->info("Backup completed: {$backup['filename']} ({$backup['size_formatted']})");
            Log::info("[BackupScheduler] Backup completed: {$backup['filename']}");
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Backup failed: {$e->getMessage()}");
            Log::error("[BackupScheduler] Backup failed: {$e->getMessage()}");
            return Command::FAILURE;
        }
    }
}
