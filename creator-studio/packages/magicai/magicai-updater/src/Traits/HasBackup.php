<?php

namespace MagicAI\Updater\Traits;

use Exception;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use ZipArchive;

trait HasBackup
{
    public string $backupFileNameCacheKey = 'backupFileName';

    public array $excepts = [
        '.git',
        'node_modules',
        '__MACOSX',
        '.idea',
        '.github',
        'storage/logs',
        'storage/framework/cache',
        'storage/framework/sessions',
        'storage/framework/views',
        'storage/framework/testing',
        'storage/app/backups',
        'vendor/magicai/magicai-updater/vendor',
        'public/uploads',
        'public/upload',
    ];

    //    public function getBackupFileNameCacheKey(): string
    //    {
    //        return $this->backupFileNameCacheKey;
    //    }

    public function backup(): bool
    {
        $this->configurePhp();

        Artisan::call('optimize:clear');

        $fileName = $this->backupFileName();

        $zipName = $this->backupFilePath($fileName);

        $zip = new ZipArchive;

        try {
            if ($zip->open($zipName, ZipArchive::CREATE) === true) {

                $this->addFolderToZip(base_path(), $zip);

                $zip->close();
            }

        } catch (Exception $e) {

            Log::error('Backup failed: ', [
                'message' => $e->getMessage(),
            ]);

            Cache::forget($this->backupFileNameCacheKey);

            throw ValidationException::withMessages([
                'message' => __('Server Error:') . ' ' . $e->getMessage(),
            ]);
        }

        return $fileName;
    }

    public function exceptFolder(string $folder): bool
    {
        $exceptArray = array_map(static function ($item) {
            return base_path($item);
        }, $this->excepts);

        if (in_array($folder, $exceptArray, true)) {
            return true;
        }

        return false;
    }

    private function exceptFile(string $file): bool
    {
        if ($file === '.' || $file === '..') {
            return true;
        }

        // except .zip files
        if (pathinfo($file, PATHINFO_EXTENSION) === 'zip') {
            return true;
        }

        return false;
    }

    private function addFolderToZip($folder, ZipArchive $zip, $parentFolder = ''): void
    {
        if ($this->exceptFolder($folder)) {
            return;
        }

        $files = scandir($folder);

        foreach ($files as $file) {
            if ($this->exceptFile($file)) {
                continue;
            }

            $filePath = $folder . DIRECTORY_SEPARATOR . $file;

            $relativePath = $parentFolder ? $parentFolder . DIRECTORY_SEPARATOR . $file : $file;

            if (is_dir($filePath)) {
                $zip->addEmptyDir($relativePath);

                $this->addFolderToZip($filePath, $zip, $relativePath);
            } else {
                if (is_file($filePath)) {
                    $zip->addFile($filePath, $relativePath);
                }
            }
        }
    }

    private function backupFilePath(string $fileName): string
    {
        return base_path($fileName);
    }

    private function backupFileName(): string
    {
        return 'backup-' . date('Y-m-d_H-i') . '.zip';
    }

    public function isLastBackupRecent(int $minutes = 30): bool
    {
        $lastBackup = $this->findLastBackup();

        if ($lastBackup === null) {
            return false;
        }

        // backup-2025-05-28_09-17.zip → 2025-05-28 09:17
        if (preg_match('/backup-(\d{4}-\d{2}-\d{2})_(\d{2}-\d{2})\.zip/', $lastBackup, $matches)) {
            $backupDateTime = Carbon::createFromFormat('Y-m-d H-i', "{$matches[1]} {$matches[2]}");

            return $backupDateTime->greaterThanOrEqualTo(now()->subMinutes($minutes));
        }

        return false;
    }

    public function findLastBackup(): ?string
    {
        $backupFiles = glob(base_path('/backup-*.zip'));

        if (! empty($backupFiles)) {
            usort($backupFiles, function ($a, $b) {
                return strcmp(basename($b), basename($a));
            });

            $latestBackup = $backupFiles[0];

            return basename($latestBackup);
        }

        return null;
    }

    //    public function backupFileNameGetFromCache()
    //    {
    //        return Cache::get($this->backupFileNameCacheKey);
    //    }

    private function configurePhp(): void
    {
        // unlimited max execution time
        set_time_limit(0);

        // increase memory_limit to 1GB
        ini_set('memory_limit', '-1');

        // increase max_execution_time to 1 hour
        ini_set('max_execution_time', 3600);
    }
}
