<?php

namespace App\Console\Commands;

use App\Models\ExportJob;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class CleanupExportFiles extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'exports:cleanup
                            {--days=30 : Delete export files older than this many days}
                            {--dry-run : List files that would be deleted without actually deleting}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete old export CSV files from storage/app/exports/';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $days = (int) $this->option('days');
        $dryRun = (bool) $this->option('dry-run');
        $cutoff = now()->subDays($days);

        $this->info("Scanning for export files older than {$days} days (before {$cutoff->toDateTimeString()})...");

        // ── 1. Clean up physical files in storage/app/exports/ ──
        $disk = Storage::disk('local');
        $exportFiles = $disk->files('exports');
        $deletedCount = 0;
        $skippedCount = 0;

        foreach ($exportFiles as $file) {
            $lastModified = $disk->lastModified($file);
            $fileDate = now()->createFromTimestamp($lastModified);

            if ($fileDate->lt($cutoff)) {
                if ($dryRun) {
                    $this->line("  [DRY RUN] Would delete: {$file} (last modified {$fileDate->toDateTimeString()})");
                } else {
                    $disk->delete($file);
                    $this->line("  Deleted: {$file}");
                }
                $deletedCount++;
            } else {
                $skippedCount++;
            }
        }

        // ── 2. Update ExportJob records ──
        // Mark orphaned jobs whose files have been cleaned up
        $affected = ExportJob::where('status', 'completed')
            ->where('created_at', '<', $cutoff)
            ->whereNull('cleaned_up_at')
            ->update(['cleaned_up_at' => now()]);

        if ($dryRun) {
            $this->line("  [DRY RUN] Would mark {$affected} completed export jobs as cleaned up.");
        } else {
            if ($affected > 0) {
                $this->info("Marked {$affected} old export jobs as cleaned up.");
            }
        }

        // ── 3. Failed exports older than 7 days — also mark cleaned ──
        $failedCutoff = now()->subDays(7);
        $failedAffected = ExportJob::whereIn('status', ['failed', 'pending'])
            ->where('created_at', '<', $failedCutoff)
            ->whereNull('cleaned_up_at')
            ->update(['cleaned_up_at' => now()]);

        if ($dryRun) {
            $this->line("  [DRY RUN] Would mark {$failedAffected} old failed/pending jobs as cleaned up.");
        } else {
            if ($failedAffected > 0) {
                $this->info("Marked {$failedAffected} old failed/pending jobs as cleaned up.");
            }
        }

        $this->newLine();
        $this->info("Done. {$deletedCount} file(s) " . ($dryRun ? 'would be deleted' : 'deleted') . ", {$skippedCount} file(s) kept.");
    }
}
