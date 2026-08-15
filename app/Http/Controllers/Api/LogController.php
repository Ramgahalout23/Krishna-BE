<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\LogStreamService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LogController extends Controller
{
    /**
     * Read Laravel server error logs from storage/logs/laravel.log.
     * GET /api/v1/admin/logs
     *
     * Query params:
     *   - search: string (filter log lines containing this string)
     *   - level: string (filter by log level: ERROR, CRITICAL, WARNING, INFO, DEBUG, etc.)
     *   - page: int (1-based page number)
     *   - per_page: int (lines per page, max 500, default 100)
     *   - file: string (specific log filename, e.g. laravel-2025-01-01.log)
     */
    public function logs(Request $request): JsonResponse
    {
        try {
            $search = $request->input('search');
            $level = $request->input('level');
            $page = max(1, (int) $request->input('page', 1));
            $perPage = min(500, max(10, (int) $request->input('per_page', 100)));
            $filename = $request->input('file', 'laravel.log');

            $logPath = storage_path('logs/' . basename($filename));

            if (!file_exists($logPath)) {
                return response()->json([
                    'success' => true,
                    'data' => [
                        'lines' => [],
                        'total' => 0,
                        'page' => 1,
                        'per_page' => $perPage,
                        'total_pages' => 0,
                        'files' => $this->getLogFiles(),
                        'file' => $filename,
                    ],
                ]);
            }

            // Read the file in reverse (newest first)
            $content = file_get_contents($logPath);
            $lines = explode("\n", $content);

            // Filter empty lines and reverse for newest-first
            $lines = array_values(array_filter($lines, fn($l) => trim($l) !== ''));
            $lines = array_reverse($lines);

            // Build line objects with metadata
            $entries = [];
            foreach ($lines as $line) {
                $entry = [
                    'raw' => $line,
                    'level' => $this->parseLogLevel($line),
                    'timestamp' => $this->parseLogTimestamp($line),
                    'has_stack_trace' => str_contains($line, '#') || str_contains($line, 'Stack trace:'),
                ];
                $entries[] = $entry;
            }

            // Apply level filter
            if ($level) {
                $levelUpper = strtoupper($level);
                $entries = array_values(array_filter($entries, fn($e) => $e['level'] === $levelUpper));
            }

            // Apply search filter
            if ($search) {
                $searchLower = strtolower($search);
                $entries = array_values(array_filter($entries, fn($e) => str_contains(strtolower($e['raw']), $searchLower)));
            }

            $total = count($entries);
            $totalPages = max(1, (int) ceil($total / $perPage));
            $offset = ($page - 1) * $perPage;
            $pageLines = array_slice($entries, $offset, $perPage);

            return response()->json([
                'success' => true,
                'data' => [
                    'lines' => $pageLines,
                    'total' => $total,
                    'page' => $page,
                    'per_page' => $perPage,
                    'total_pages' => $totalPages,
                    'files' => $this->getLogFiles(),
                    'file' => $filename,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * List available log files in storage/logs.
     */
    private function getLogFiles(): array
    {
        $logDir = storage_path('logs');
        if (!is_dir($logDir)) {
            return [];
        }
        $files = glob($logDir . '/*.log');
        $result = [];
        foreach ($files as $file) {
            $result[] = [
                'name' => basename($file),
                'size' => filesize($file),
                'size_formatted' => $this->formatBytes(filesize($file)),
                'modified_at' => date('Y-m-d H:i:s', filemtime($file)),
            ];
        }
        // Sort newest first by modified time
        usort($result, fn($a, $b) => strcmp($b['modified_at'], $a['modified_at']));
        return $result;
    }

    /**
     * Parse the log level from a log line (e.g. [2024-01-01 12:00:00] local.ERROR:).
     */
    private function parseLogLevel(string $line): ?string
    {
        if (preg_match('/\.(EMERGENCY|ALERT|CRITICAL|ERROR|WARNING|NOTICE|INFO|DEBUG):/', $line, $matches)) {
            return $matches[1];
        }
        return null;
    }

    /**
     * Parse the timestamp from a log line.
     */
    private function parseLogTimestamp(string $line): ?string
    {
        if (preg_match('/^\[(\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}:\d{2})/', $line, $matches)) {
            return $matches[1];
        }
        return null;
    }

    /**
     * Format bytes into human-readable string.
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        return round($bytes, 1) . ' ' . $units[$i];
    }

    /**
     * Delete a log file from storage/logs.
     * DELETE /api/v1/admin/logs/{filename}
     */
    public function deleteLog(string $filename): JsonResponse
    {
        try {
            $safeName = basename($filename);
            $logPath = storage_path('logs/' . $safeName);

            if (!file_exists($logPath)) {
                return response()->json(['success' => false, 'message' => 'Log file not found'], 404);
            }

            if (!is_writable($logPath)) {
                return response()->json(['success' => false, 'message' => 'Log file is not writable'], 403);
            }

            if (!unlink($logPath)) {
                return response()->json(['success' => false, 'message' => 'Failed to delete log file'], 500);
            }

            return response()->json([
                'success' => true,
                'message' => "Log file {$safeName} deleted",
                'data' => ['files' => $this->getLogFiles()],
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Tail a log file — returns only new entries since the given byte offset.
     * Used by the frontend for fallback polling when WebSocket is unavailable.
     * GET /api/v1/admin/logs/tail?file=laravel.log&offset=0
     */
    public function tailLog(Request $request): JsonResponse
    {
        try {
            $filename = $request->input('file', 'laravel.log');
            $offset = $request->input('offset');

            $logStream = app(LogStreamService::class);
            $result = $logStream->tail($filename, $offset !== null ? (int) $offset : null);

            return response()->json([
                'success' => true,
                'data' => [
                    'entries' => $result['entries'],
                    'new_offset' => $result['new_offset'],
                    'file_exists' => $result['file_exists'],
                    'file' => $filename,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Truncate (clear) a log file — empties its contents but keeps the file.
     * POST /api/v1/admin/logs/{filename}/truncate
     */
    public function truncateLog(string $filename): JsonResponse
    {
        try {
            $safeName = basename($filename);
            $logPath = storage_path('logs/' . $safeName);

            if (!file_exists($logPath)) {
                return response()->json(['success' => false, 'message' => 'Log file not found'], 404);
            }

            if (!is_writable($logPath)) {
                return response()->json(['success' => false, 'message' => 'Log file is not writable'], 403);
            }

            if (file_put_contents($logPath, '') === false) {
                return response()->json(['success' => false, 'message' => 'Failed to truncate log file'], 500);
            }

            return response()->json([
                'success' => true,
                'message' => "Log file {$safeName} cleared",
                'data' => ['files' => $this->getLogFiles()],
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Archive a log file — moves it to storage/logs/archived/ with a timestamp suffix.
     * POST /api/v1/admin/logs/{filename}/archive
     */
    public function archiveLog(string $filename): JsonResponse
    {
        try {
            $safeName = basename($filename);
            $logPath = storage_path('logs/' . $safeName);

            if (!file_exists($logPath)) {
                return response()->json(['success' => false, 'message' => 'Log file not found'], 404);
            }

            if (!is_readable($logPath)) {
                return response()->json(['success' => false, 'message' => 'Log file is not readable'], 403);
            }

            $archiveDir = storage_path('logs/archived');
            if (!is_dir($archiveDir)) {
                if (!mkdir($archiveDir, 0755, true)) {
                    return response()->json(['success' => false, 'message' => 'Failed to create archive directory'], 500);
                }
            }

            $timestamp = date('Y-m-d_H-i-s');
            $archiveName = pathinfo($safeName, PATHINFO_FILENAME) . '_' . $timestamp . '.log.gz';
            $archivePath = $archiveDir . '/' . $archiveName;

            // Read original size BEFORE truncation
            $originalSize = filesize($logPath);

            // Gzip compress the log file into the archive
            $fpOut = gzopen($archivePath, 'wb9');
            if (!$fpOut) {
                return response()->json(['success' => false, 'message' => 'Failed to create archive file'], 500);
            }

            $fpIn = fopen($logPath, 'rb');
            if (!$fpIn) {
                gzclose($fpOut);
                return response()->json(['success' => false, 'message' => 'Failed to read log file'], 500);
            }

            stream_copy_to_stream($fpIn, $fpOut);
            fclose($fpIn);
            gzclose($fpOut);

            // Now truncate the original
            file_put_contents($logPath, '');

            return response()->json([
                'success' => true,
                'message' => "Log file archived to {$archiveName}",
                'data' => [
                    'archive_name' => $archiveName,
                    'archive_size' => $this->formatBytes($originalSize),
                    'files' => $this->getLogFiles(),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Download a log file from storage/logs.
     * GET /api/v1/admin/logs/{filename}/download
     */
    public function downloadLog(string $filename): \Illuminate\Http\Response
    {
        try {
            $safeName = basename($filename);
            $logPath = storage_path('logs/' . $safeName);

            if (!file_exists($logPath)) {
                return response()->json(['success' => false, 'message' => 'Log file not found'], 404);
            }

            return response()->download($logPath, $safeName);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * List archived .gz log files in storage/logs/archived/.
     * GET /api/v1/admin/logs/archived
     */
    public function archivedLogs(): JsonResponse
    {
        try {
            $archiveDir = storage_path('logs/archived');
            if (!is_dir($archiveDir)) {
                return response()->json([
                    'success' => true,
                    'data' => ['files' => []],
                ]);
            }

            $files = glob($archiveDir . '/*.gz');
            $result = [];
            $totalSize = 0;
            foreach ($files as $file) {
                $size = filesize($file);
                $totalSize += $size;
                $result[] = [
                    'name' => basename($file),
                    'size' => $size,
                    'size_formatted' => $this->formatBytes($size),
                    'modified_at' => date('Y-m-d H:i:s', filemtime($file)),
                ];
            }
            // Sort newest first
            usort($result, fn($a, $b) => strcmp($b['modified_at'], $a['modified_at']));

            return response()->json([
                'success' => true,
                'data' => [
                    'files' => $result,
                    'total_files' => count($result),
                    'total_size_formatted' => $this->formatBytes($totalSize),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * View contents of an archived .gz log file.
     * GET /api/v1/admin/logs/archived/{filename}
     */
    public function viewArchivedLog(string $filename): JsonResponse
    {
        try {
            $safeName = basename($filename);
            $archivePath = storage_path('logs/archived/' . $safeName);

            if (!file_exists($archivePath)) {
                return response()->json(['success' => false, 'message' => 'Archived log file not found'], 404);
            }

            if (!str_ends_with($safeName, '.gz')) {
                return response()->json(['success' => false, 'message' => 'Not a valid gzip archive'], 400);
            }

            // Decompress gzip content
            $fp = gzopen($archivePath, 'rb');
            if (!$fp) {
                return response()->json(['success' => false, 'message' => 'Failed to open archive'], 500);
            }

            $content = '';
            while (!gzeof($fp)) {
                $content .= gzread($fp, 8192);
            }
            gzclose($fp);

            // Parse into entries
            $lines = array_values(array_filter(explode("\n", $content), fn($l) => trim($l) !== ''));

            $entries = [];
            foreach ($lines as $line) {
                $entries[] = [
                    'raw' => $line,
                    'level' => $this->parseLogLevel($line),
                    'timestamp' => $this->parseLogTimestamp($line),
                    'has_stack_trace' => str_contains($line, '#') || str_contains($line, 'Stack trace:'),
                ];
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'filename' => $safeName,
                    'size' => filesize($archivePath),
                    'size_formatted' => $this->formatBytes(filesize($archivePath)),
                    'modified_at' => date('Y-m-d H:i:s', filemtime($archivePath)),
                    'total_lines' => count($entries),
                    'entries' => $entries,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Download an archived .gz log file.
     * GET /api/v1/admin/logs/archived/{filename}/download
     */
    public function downloadArchivedLog(string $filename): \Illuminate\Http\Response
    {
        try {
            $safeName = basename($filename);
            $archivePath = storage_path('logs/archived/' . $safeName);

            if (!file_exists($archivePath)) {
                return response()->json(['success' => false, 'message' => 'Archived log file not found'], 404);
            }

            if (!str_ends_with($safeName, '.gz')) {
                return response()->json(['success' => false, 'message' => 'Not a valid gzip archive'], 400);
            }

            return response()->download($archivePath, $safeName, [
                'Content-Type' => 'application/gzip',
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Delete an archived .gz log file.
     * DELETE /api/v1/admin/logs/archived/{filename}
     */
    public function deleteArchivedLog(string $filename): JsonResponse
    {
        try {
            $safeName = basename($filename);
            $archivePath = storage_path('logs/archived/' . $safeName);

            if (!file_exists($archivePath)) {
                return response()->json(['success' => false, 'message' => 'Archived log file not found'], 404);
            }

            if (!str_ends_with($safeName, '.gz')) {
                return response()->json(['success' => false, 'message' => 'Not a valid gzip archive'], 400);
            }

            if (!unlink($archivePath)) {
                return response()->json(['success' => false, 'message' => 'Failed to delete archive'], 500);
            }

            // Re-fetch updated list
            $updatedList = $this->getArchivedLogFiles();
            $totalSize = 0;
            foreach ($updatedList as $f) { $totalSize += $f['size']; }

            return response()->json([
                'success' => true,
                'message' => "Archived log {$safeName} deleted",
                'data' => [
                    'files' => $updatedList,
                    'total_files' => count($updatedList),
                    'total_size_formatted' => $this->formatBytes($totalSize),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Helper: list archived .gz files.
     */
    private function getArchivedLogFiles(): array
    {
        $archiveDir = storage_path('logs/archived');
        if (!is_dir($archiveDir)) {
            return [];
        }
        $files = glob($archiveDir . '/*.gz');
        $result = [];
        foreach ($files as $file) {
            $result[] = [
                'name' => basename($file),
                'size' => filesize($file),
                'size_formatted' => $this->formatBytes(filesize($file)),
                'modified_at' => date('Y-m-d H:i:s', filemtime($file)),
            ];
        }
        usort($result, fn($a, $b) => strcmp($b['modified_at'], $a['modified_at']));
        return $result;
    }
}
