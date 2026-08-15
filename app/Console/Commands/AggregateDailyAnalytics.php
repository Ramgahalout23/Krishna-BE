<?php

namespace App\Console\Commands;

use App\Services\AnalyticsSummaryService;
use Illuminate\Console\Command;

class AggregateDailyAnalytics extends Command
{
    protected $signature = 'analytics:aggregate-daily
                            {--days= : Number of past days to aggregate (default: 1)}
                            {--from= : Start date (Y-m-d)}
                            {--to= : End date (Y-m-d)}
                            {--force : Re-aggregate even if already exists}';

    protected $description = 'Aggregate daily analytics into the summary table for fast dashboard queries';

    public function handle(AnalyticsSummaryService $summaryService): int
    {
        // Range mode
        $from = $this->option('from');
        $to   = $this->option('to');

        if ($from && $to) {
            $this->info("Aggregating analytics from {$from} to {$to}...");
            $count = $summaryService->aggregateRange($from, $to);
            $this->info("   ✓ Aggregated {$count} day(s)");
            return self::SUCCESS;
        }

        // Last N days mode
        $days = (int) ($this->option('days') ?? 1);
        if ($days > 365) {
            $this->warn('Maximum 365 days allowed. Limiting to 365.');
            $days = 365;
        }

        $this->info("Aggregating analytics for the last {$days} day(s)...");
        $start = microtime(true);

        $count = $summaryService->aggregateLastDays($days);

        $elapsed = round(microtime(true) - $start, 2);
        $this->info("   ✓ Aggregated {$count} day(s) in {$elapsed}s");

        return self::SUCCESS;
    }
}
