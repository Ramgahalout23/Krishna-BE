<?php

namespace App\Console\Commands;

use App\Models\AdCampaign;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class AdCampaignScheduler extends Command
{
    protected $signature = 'ads:process-scheduled';
    protected $description = 'Process scheduled ad campaigns — activate, pause, or complete based on dates';

    public function handle(): int
    {
        $this->info('Processing scheduled ad campaigns...');

        // Activate campaigns that should start today
        $activated = AdCampaign::where('status', 'DRAFT')
            ->where('start_date', '<=', now())
            ->update(['status' => 'ACTIVE']);
        $this->info("Activated {$activated} campaigns");

        // Pause campaigns past their end date
        $paused = AdCampaign::where('status', 'ACTIVE')
            ->where('end_date', '<=', now())
            ->update(['status' => 'COMPLETED']);
        $this->info("Completed {$paused} campaigns");

        Log::info("[AdCampaignScheduler] Processed: {$activated} activated, {$paused} completed");
        return Command::SUCCESS;
    }
}
