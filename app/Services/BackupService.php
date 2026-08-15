<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class BackupService
{
    private string $backupDir;
    private int $maxBackups = 10;

    public function __construct()
    {
        $this->backupDir = storage_path('app/backups');
        if (!is_dir($this->backupDir)) {
            mkdir($this->backupDir, 0755, true);
        }
    }

    /**
     * Create a new database backup.
     * Tries mysqldump first, falls back to SQL export via DB queries.
     */
    public function createBackup(): array
    {
        try {
            // Try mysqldump first
            if ($this->isMysqldumpAvailable()) {
                return $this->createMysqldumpBackup();
            }

            Log::info('[BackupService] mysqldump not available — using SQL fallback');
            return $this->createSqlFallbackBackup();
        } catch (\Exception $e) {
            Log::error('[BackupService] Backup failed: ' . $e->getMessage());
            $this->updateLastRun();
            throw $e;
        }
    }

    /**
     * List all available backups, including in-progress queued/processing jobs.
     */
    public function listBackups(): array
    {
        $files = array_diff(scandir($this->backupDir), ['.', '..']);
        $backups = [];

        foreach ($files as $file) {
            $filePath = $this->backupDir . '/' . $file;

            // ── Completed .sql backup files ──
            if (is_file($filePath) && pathinfo($file, PATHINFO_EXTENSION) === 'sql') {
                $backups[] = [
                    'filename' => $file,
                    'size' => filesize($filePath),
                    'size_formatted' => $this->formatSize(filesize($filePath)),
                    'created_at' => date('Y-m-d H:i:s', filemtime($filePath)),
                    'status' => 'completed',
                    'backup_id' => null,
                ];
                continue;
            }

            // ── In-progress job directories (bkp-<uuid>) ──
            if (is_dir($filePath) && str_starts_with($file, 'bkp-')) {
                $statusPath = $filePath . '/status.txt';
                if (file_exists($statusPath)) {
                    $status = trim(file_get_contents($statusPath));
                    if (in_array($status, ['queued', 'processing'], true)) {
                        $resultPath = $filePath . '/result.json';
                        $backups[] = [
                            'filename' => $file,
                            'size' => null,
                            'size_formatted' => '—',
                            'created_at' => date('Y-m-d H:i:s', filemtime($statusPath)),
                            'status' => $status,
                            'backup_id' => $file,
                        ];
                    }
                }
            }
        }

        // Sort by created_at descending (newest first)
        usort($backups, fn($a, $b) => strcmp($b['created_at'], $a['created_at']));

        return $backups;
    }

    /**
     * Get the full path for a backup file.
     */
    public function getBackupPath(string $filename): ?string
    {
        $filePath = $this->backupDir . '/' . basename($filename);
        return file_exists($filePath) ? $filePath : null;
    }

    /**
     * Delete a backup file.
     */
    public function deleteBackup(string $filename): bool
    {
        $filePath = $this->backupDir . '/' . basename($filename);
        if (file_exists($filePath)) {
            return unlink($filePath);
        }
        throw new \Exception('Backup file not found');
    }

    /**
     * Get backup settings.
     */
    public function getBackupSettings(): array
    {
        $keys = ['backup_frequency', 'backup_time', 'backup_day_of_week', 'backup_last_run'];
        $settings = Setting::whereIn('key', $keys)->pluck('value', 'key')->toArray();

        return [
            'backup_frequency' => $settings['backup_frequency'] ?? 'manual',
            'backup_time' => $settings['backup_time'] ?? '02:00',
            'backup_day_of_week' => $settings['backup_day_of_week'] ?? 'Monday',
            'backup_last_run' => $settings['backup_last_run'] ?? null,
        ];
    }

    /**
     * Update backup settings.
     */
    public function updateBackupSettings(array $data): array
    {
        foreach ($data as $key => $value) {
            if (!is_null($value)) {
                Setting::updateOrCreate(
                    ['key' => $key, 'module' => 'SYSTEM'],
                    ['value' => $value]
                );
            }
        }
        return $this->getBackupSettings();
    }

    /**
     * Check if mysqldump is available.
     */
    private function isMysqldumpAvailable(): bool
    {
        $output = null;
        $returnCode = null;
        exec('mysqldump --version 2>&1', $output, $returnCode);
        return $returnCode === 0;
    }

    /**
     * Create backup using mysqldump.
     */
    private function createMysqldumpBackup(): array
    {
        $timestamp = now()->format('Y-m-d-H-i-s');
        $filename = "backup_{$timestamp}.sql";
        $filePath = "{$this->backupDir}/{$filename}";

        $dbConfig = $this->getDatabaseConfig();

        // Check if running in Docker
        $isDocker = $dbConfig['host'] === 'mysql' || str_contains($dbConfig['host'], 'mysql');

        if ($isDocker) {
            $cmd = sprintf(
                'docker exec ecommerce-mysql mysqldump --user=%s --password=%s --no-tablespaces --routines --triggers --single-transaction --quick %s > %s 2>&1',
                escapeshellarg($dbConfig['username']),
                escapeshellarg($dbConfig['password']),
                escapeshellarg($dbConfig['database']),
                escapeshellarg($filePath)
            );
        } else {
            $cmd = sprintf(
                'mysqldump --host=%s --port=%s --user=%s --password=%s --no-tablespaces --routines --triggers --single-transaction --quick %s > %s 2>&1',
                escapeshellarg($dbConfig['host']),
                escapeshellarg($dbConfig['port']),
                escapeshellarg($dbConfig['username']),
                escapeshellarg($dbConfig['password']),
                escapeshellarg($dbConfig['database']),
                escapeshellarg($filePath)
            );
        }

        Log::info("[BackupService] Starting mysqldump backup (Docker: " . ($isDocker ? 'yes' : 'no') . ")");

        $output = null;
        $returnCode = null;
        exec($cmd, $output, $returnCode);

        if ($returnCode !== 0 || !file_exists($filePath) || filesize($filePath) === 0) {
            throw new \Exception('mysqldump failed: ' . implode("\n", $output ?? []));
        }

        $size = filesize($filePath);
        $this->enforceRetention();
        $this->updateLastRun();

        Log::info("[BackupService] Backup completed: {$filename} ({$this->formatSize($size)})");

        return [
            'filename' => $filename,
            'path' => $filePath,
            'size' => $size,
            'size_formatted' => $this->formatSize($size),
            'created_at' => now()->toDateTimeString(),
            'status' => 'completed',
        ];
    }

    /**
     * Create backup using SQL queries (fallback when mysqldump not available).
     */
    private function createSqlFallbackBackup(): array
    {
        $timestamp = now()->format('Y-m-d-H-i-s');
        $filename = "backup_{$timestamp}.sql";
        $filePath = "{$this->backupDir}/{$filename}";

        $tables = DB::select("SHOW TABLES");
        $dbName = DB::getDatabaseName();
        $key = "Tables_in_{$dbName}";

        $sql = "-- SQL Fallback Backup\n";
        $sql .= "-- Generated: " . now()->toDateTimeString() . "\n";
        $sql .= "-- Database: {$dbName}\n\n";
        $sql .= "SET FOREIGN_KEY_CHECKS = 0;\n\n";

        foreach ($tables as $tableObj) {
            $tableName = $tableObj->$key;

            // Get CREATE TABLE
            $createStmts = DB::select("SHOW CREATE TABLE `{$tableName}`");
            $createStmt = $createStmts[0]->{'Create Table'} ?? null;
            if ($createStmt) {
                $sql .= "DROP TABLE IF EXISTS `{$tableName}`;\n";
                $sql .= "{$createStmt};\n\n";
            }

            // Get data
            $rows = DB::table($tableName)->get();
            if ($rows->isNotEmpty()) {
                $sql .= "-- Data for {$tableName} (" . $rows->count() . " rows)\n";
                foreach ($rows as $row) {
                    $row = (array) $row;
                    $columns = array_keys($row);
                    $values = array_map(function ($val) {
                        if (is_null($val)) return 'NULL';
                        if (is_numeric($val)) return $val;
                        return "'" . str_replace("'", "''", $val) . "'";
                    }, array_values($row));
                    $sql .= "INSERT INTO `{$tableName}` (`" . implode('`, `', $columns) . "`) VALUES (" . implode(', ', $values) . ");\n";
                }
                $sql .= "\n";
            }
        }

        $sql .= "SET FOREIGN_KEY_CHECKS = 1;\n";
        file_put_contents($filePath, $sql);

        $size = filesize($filePath);
        $this->enforceRetention();
        $this->updateLastRun();

        Log::info("[BackupService] SQL fallback backup completed: {$filename} ({$this->formatSize($size)})");

        return [
            'filename' => $filename,
            'path' => $filePath,
            'size' => $size,
            'size_formatted' => $this->formatSize($size),
            'created_at' => now()->toDateTimeString(),
            'status' => 'completed',
        ];
    }

    /**
     * Enforce backup retention — delete oldest backups beyond the limit.
     */
    private function enforceRetention(): void
    {
        $files = array_diff(scandir($this->backupDir), ['.', '..']);
        $backupFiles = [];

        foreach ($files as $file) {
            $filePath = $this->backupDir . '/' . $file;
            if (is_file($filePath) && pathinfo($file, PATHINFO_EXTENSION) === 'sql') {
                $backupFiles[$file] = filemtime($filePath);
            }
        }

        if (count($backupFiles) <= $this->maxBackups) return;

        // Sort by modification time (oldest first)
        asort($backupFiles);
        $toDelete = array_slice(array_keys($backupFiles), 0, count($backupFiles) - $this->maxBackups);

        foreach ($toDelete as $file) {
            $filePath = $this->backupDir . '/' . $file;
            if (file_exists($filePath)) {
                unlink($filePath);
                Log::info("[BackupService] Deleted old backup: {$file}");
            }
        }
    }

    /**
     * Update the last run timestamp.
     */
    private function updateLastRun(): void
    {
        Setting::updateOrCreate(
            ['key' => 'backup_last_run', 'module' => 'SYSTEM'],
            ['value' => now()->toDateTimeString()]
        );
    }

    /**
     * Get database configuration from Laravel config.
     */
    private function getDatabaseConfig(): array
    {
        return [
            'host' => config('database.connections.mysql.host', '127.0.0.1'),
            'port' => config('database.connections.mysql.port', '3306'),
            'username' => config('database.connections.mysql.username', 'root'),
            'password' => config('database.connections.mysql.password', ''),
            'database' => config('database.connections.mysql.database', 'ecommerce'),
        ];
    }

    /**
     * Format bytes to human-readable size.
     */
    private function formatSize(int $bytes): string
    {
        if ($bytes < 1024) return "{$bytes} B";
        if ($bytes < 1048576) return round($bytes / 1024, 1) . ' KB';
        if ($bytes < 1073741824) return round($bytes / 1048576, 1) . ' MB';
        return round($bytes / 1073741824, 2) . ' GB';
    }
}
