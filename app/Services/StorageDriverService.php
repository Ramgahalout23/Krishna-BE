<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Storage;

/**
 * StorageDriverService — dynamically selects the filesystem disk
 * based on the `storage_driver` setting stored in the database.
 *
 * When the setting is 's3', all uploads go to the pre-configured S3 disk
 * (credentials come from .env: AWS_ACCESS_KEY_ID, AWS_SECRET_ACCESS_KEY,
 * AWS_DEFAULT_REGION, AWS_BUCKET, AWS_URL).
 *
 * When the setting is 'local' (default), uploads use the local 'public' disk.
 */
class StorageDriverService
{
    /**
     * The database setting key that controls the storage driver.
     */
    public const SETTING_KEY = 'storage_driver';

    /**
     * Get the active disk name based on the stored setting.
     */
    public function getActiveDisk(): string
    {
        $driver = Setting::where('key', self::SETTING_KEY)->value('value');

        return $driver === 's3' ? 's3' : 'public';
    }

    /**
     * Check whether S3 storage is currently active.
     */
    public function isS3Active(): bool
    {
        return $this->getActiveDisk() === 's3';
    }

    /**
     * Store a file on the active disk and return the public URL.
     *
     * @param \Illuminate\Http\UploadedFile $file  The uploaded file
     * @param string                        $path  Directory path within the disk (e.g. 'uploads', 'uploads/reviews')
     * @return array{path: string, url: string}
     */
    public function storeFile($file, string $path = 'uploads'): array
    {
        $disk = $this->getActiveDisk();
        $storedPath = $file->store($path, $disk);

        if ($disk === 's3') {
            // S3 returns a full URL via Storage::url()
            $url = Storage::disk('s3')->url($storedPath);
        } else {
            // Local disk returns a relative /storage/… path
            $url = '/storage/' . $storedPath;
        }

        return [
            'path' => $storedPath,
            'url' => $url,
        ];
    }

    /**
     * Store multiple files on the active disk.
     *
     * @param \Illuminate\Http\UploadedFile[] $files
     * @param string                          $path  Directory path within the disk
     * @return array<int, array{path: string, url: string}>
     */
    public function storeFiles(array $files, string $path = 'uploads'): array
    {
        $results = [];
        foreach ($files as $file) {
            $results[] = $this->storeFile($file, $path);
        }
        return $results;
    }

    /**
     * Delete a file from the active disk.
     */
    public function delete(string $filePath): bool
    {
        $disk = $this->getActiveDisk();
        return Storage::disk($disk)->delete($filePath);
    }

    /**
     * Check if a file exists on the active disk.
     */
    public function exists(string $filePath): bool
    {
        $disk = $this->getActiveDisk();
        return Storage::disk($disk)->exists($filePath);
    }

    /**
     * Get the URL for a stored file path on the active disk.
     */
    public function url(string $filePath): string
    {
        $disk = $this->getActiveDisk();
        return Storage::disk($disk)->url($filePath);
    }
}
