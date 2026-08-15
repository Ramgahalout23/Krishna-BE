<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        // ── Backup Scheduler ──
        $schedule->command('backup:run')->everyMinute();

        // ── Ad Campaign Scheduler ──
        $schedule->command('ads:process-scheduled')->hourly();

        // ── Marketing Campaign Scheduler ──
        $schedule->command('campaigns:process-scheduled')->everyFiveMinutes();

        // ── Maintenance Schedule Check ──
        $schedule->command('maintenance:check-schedule')->everyMinute();

        // ── Daily Analytics Aggregation ──
        // Aggregate today's metrics into the summary table every night at 23:55
        $schedule->command('analytics:aggregate-daily --days=1')->dailyAt('23:55');

        // ── Guest User Cleanup (delete/anonymize placeholder accounts) ──
        $schedule->command('guest-users:cleanup --days=30')->dailyAt('03:00');

        // ── Export File Cleanup (delete export CSVs older than 30 days) ──
        $schedule->command('exports:cleanup --days=30')->dailyAt('03:30');

        // ── Abandoned Cart Reminders ──
        // Send email + DB notification reminders for carts abandoned >2 hours
        $schedule->command('abandoned-carts:process --hours=2')->everyFifteenMinutes();
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
