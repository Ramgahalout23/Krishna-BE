<?php

namespace App\Console\Commands;

use App\Services\LogStreamService;
use App\Services\RealtimeService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class StreamLogs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'logs:stream
                            {--file=laravel.log : The log file to tail}
                            {--interval=2 : Polling interval in seconds}
                            {--levels=ERROR,CRITICAL,EMERGENCY,WARNING : Comma-separated levels to emit (empty=all)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Tail log files and emit new entries via WebSocket in real-time';

    /**
     * Execute the console command.
     */
    public function handle(LogStreamService $logStream, RealtimeService $realtime): int
    {
        $filename = $this->option('file');
        $interval = max(1, (int) $this->option('interval'));
        $levelsFilter = $this->option('levels');
        $allowedLevels = $levelsFilter ? array_map('trim', explode(',', strtoupper($levelsFilter))) : [];

        $this->info("═══════════════════════════════════════════");
        $this->info("  Log Stream — Real-time Log Tailing");
        $this->info("═══════════════════════════════════════════");
        $this->line("  File:     storage/logs/{$filename}");
        $this->line("  Interval: {$interval}s");
        $this->line("  Levels:   " . ($allowedLevels ? implode(', ', $allowedLevels) : 'ALL'));
        $this->line("");

        // Start from the current end of the file (skip existing content)
        $offset = $logStream->getPosition($filename);
        $logPath = storage_path('logs/' . basename($filename));

        if (file_exists($logPath)) {
            $offset = filesize($logPath);
            $logStream->savePosition($filename, $offset);
            $this->line("  Starting from offset: {$offset} bytes");
        } else {
            $this->warn("  File not found yet: storage/logs/{$filename}");
            $this->line("  Will wait for file to appear...");
        }

        $this->newLine();
        $this->info("  Streaming... Press Ctrl+C to stop.");
        $this->line("");

        $entryCount = 0;
        $loopCount = 0;

        while (true) {
            $loopCount++;

            try {
                $result = $logStream->tail($filename, $offset);

                if ($result['file_exists'] && !empty($result['entries'])) {
                    $newEntries = $result['entries'];

                    // Filter by level if configured
                    if (!empty($allowedLevels)) {
                        $newEntries = array_values(array_filter(
                            $newEntries,
                            fn($e) => $e['level'] !== null && in_array($e['level'], $allowedLevels)
                        ));
                    }

                    if (!empty($newEntries)) {
                        foreach ($newEntries as $entry) {
                            $entry['file'] = $filename;
                            $realtime->emitLogEvent('log:newEntry', $entry);
                            $entryCount++;
                        }

                        $this->line(sprintf(
                            "  [%s] Emitted %d log entry/entries (total: %d)",
                            now()->format('H:i:s'),
                            count($newEntries),
                            $entryCount
                        ));
                    }
                }

                // Update offset for next iteration
                $offset = $result['new_offset'];
                $logStream->savePosition($filename, $offset);

            } catch (\Throwable $e) {
                $this->error("  Error: {$e->getMessage()}");
                Log::error("[logs:stream] Error: {$e->getMessage()}");
            }

            // Sleep for the polling interval
            sleep($interval);
        }

        return Command::SUCCESS;
    }
}
