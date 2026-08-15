<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * LogStreamService — Tails log files and extracts new entries for real-time streaming.
 *
 * Tracks read position per file using a simple JSON cache in storage.
 * Each polling cycle returns only the new lines appended since the last check.
 */
class LogStreamService
{
    /**
     * Path to the position tracking cache file.
     */
    protected string $cachePath;

    /**
     * Regex to match the start of a new log entry.
     * Laravel log format: [YYYY-MM-DD HH:MM:SS] channel.LEVEL: message
     */
    protected const ENTRY_START_REGEX = '/^\[\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}:\d{2}/';

    public function __construct()
    {
        $this->cachePath = storage_path('logs/.stream_positions.json');
    }

    /**
     * Get the cache file path for position tracking.
     */
    public function getCachePath(): string
    {
        return $this->cachePath;
    }

    /**
     * Read new lines from a log file since the last tracked position.
     * Returns parsed log entries and the new offset.
     *
     * @param string $filename Log filename (e.g. laravel.log)
     * @param int|null $offset Starting byte position (null = from end)
     * @return array{entries: array, new_offset: int, file_exists: bool}
     */
    public function tail(string $filename, ?int $offset = null): array
    {
        $safeName = basename($filename);
        $logPath = storage_path('logs/' . $safeName);

        if (!file_exists($logPath)) {
            return [
                'entries' => [],
                'new_offset' => 0,
                'file_exists' => false,
            ];
        }

        $currentSize = filesize($logPath);

        // If offset is null, start from the end (skip existing content)
        if ($offset === null || $offset > $currentSize) {
            return [
                'entries' => [],
                'new_offset' => $currentSize,
                'file_exists' => true,
            ];
        }

        // No new data
        if ($offset >= $currentSize) {
            return [
                'entries' => [],
                'new_offset' => $currentSize,
                'file_exists' => true,
            ];
        }

        // Read new content from offset to end
        $handle = fopen($logPath, 'rb');
        if (!$handle) {
            return [
                'entries' => [],
                'new_offset' => $offset,
                'file_exists' => true,
            ];
        }

        fseek($handle, $offset);
        $newContent = stream_get_contents($handle);
        fclose($handle);

        if ($newContent === false || trim($newContent) === '') {
            return [
                'entries' => [],
                'new_offset' => $currentSize,
                'file_exists' => true,
            ];
        }

        // Parse the new content into lines, then into entries
        $lines = explode("\n", $newContent);
        $lines = array_values(array_filter($lines, fn($l) => trim($l) !== ''));

        $entries = [];
        foreach ($lines as $rawLine) {
            $entries[] = [
                'raw' => $rawLine,
                'level' => $this->parseLogLevel($rawLine),
                'timestamp' => $this->parseLogTimestamp($rawLine),
                'has_stack_trace' => str_contains($rawLine, '#') || str_contains($rawLine, 'Stack trace:'),
            ];
        }

        return [
            'entries' => $entries,
            'new_offset' => $currentSize,
            'file_exists' => true,
        ];
    }

    /**
     * Get or initialize the tracked position for a log file.
     */
    public function getPosition(string $filename): int
    {
        $positions = $this->loadPositions();
        return $positions[$filename] ?? 0;
    }

    /**
     * Save the tracked position for a log file.
     */
    public function savePosition(string $filename, int $offset): void
    {
        $positions = $this->loadPositions();
        $positions[$filename] = $offset;

        // Keep only last 20 files to prevent cache bloat
        if (count($positions) > 20) {
            $positions = array_slice($positions, -20, null, true);
        }

        $this->writePositions($positions);
    }

    /**
     * Reset the tracked position for a file (start from end on next read).
     */
    public function resetPosition(string $filename): void
    {
        $positions = $this->loadPositions();
        unset($positions[$filename]);
        $this->writePositions($positions);
    }

    /**
     * Load all tracked positions from cache file.
     */
    protected function loadPositions(): array
    {
        if (!file_exists($this->cachePath)) {
            return [];
        }

        $content = @file_get_contents($this->cachePath);
        if ($content === false) {
            return [];
        }

        $data = json_decode($content, true);
        return is_array($data) ? $data : [];
    }

    /**
     * Write tracked positions to cache file.
     */
    protected function writePositions(array $positions): void
    {
        $dir = dirname($this->cachePath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        @file_put_contents($this->cachePath, json_encode($positions, JSON_PRETTY_PRINT));
    }

    /**
     * Parse the log level from a log line.
     */
    protected function parseLogLevel(string $line): ?string
    {
        if (preg_match('/\.(EMERGENCY|ALERT|CRITICAL|ERROR|WARNING|NOTICE|INFO|DEBUG):/', $line, $matches)) {
            return $matches[1];
        }
        return null;
    }

    /**
     * Parse the timestamp from a log line.
     */
    protected function parseLogTimestamp(string $line): ?string
    {
        if (preg_match('/^\[(\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}:\d{2})/', $line, $matches)) {
            return $matches[1];
        }
        return null;
    }
}
