<?php

namespace App\Console\Commands;

use App\Models\Campaign;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CampaignScheduler extends Command
{
    protected $signature = 'campaigns:process-scheduled';
    protected $description = 'Process scheduled marketing campaigns — send, activate, or complete based on schedule';

    public function handle(): int
    {
        $this->info('Processing scheduled marketing campaigns...');

        try {
            // Activate scheduled campaigns
            $activated = Campaign::where('status', 'SCHEDULED')
                ->where('scheduled_at', '<=', now())
                ->update(['status' => 'ACTIVE', 'started_at' => now()]);
            $this->info("Activated {$activated} campaigns");

            // Complete campaigns past their end date
            $completed = Campaign::where('status', 'ACTIVE')
                ->where('end_date', '<=', now())
                ->update(['status' => 'COMPLETED', 'completed_at' => now()]);
            $this->info("Completed {$completed} campaigns");

            Log::info("[CampaignScheduler] Processed: {$activated} activated, {$completed} completed");
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Failed to process campaigns: {$e->getMessage()}");
            Log::error("[CampaignScheduler] Error: {$e->getMessage()}");
            return Command::FAILURE;
        }
    }
}
