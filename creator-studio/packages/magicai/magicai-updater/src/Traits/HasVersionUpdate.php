<?php

namespace MagicAI\Updater\Traits;

use App\Domains\Marketplace\Repositories\Contracts\ExtensionRepositoryInterface;
use App\Helpers\Classes\InstallationHelper;
use App\Models\Setting;
use Exception;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

trait HasVersionUpdate
{
    public ?string $downloadFileCacheKey = 'downloadFileCacheKey';

    public function downloadNewVersion(): void
    {
        set_time_limit(0); // unlimited max execution time
        ini_set('memory_limit', '-1'); // increase memory_limit to 1GB

        $blackList = app(ExtensionRepositoryInterface::class)->blacklist();

        if ($blackList) {
            throw ValidationException::withMessages([
                'message' => 'Please try again later!',
            ]);
        }

        $version = $this->nextVersion;

        $downloadUrl = config('magicai-updater.base_url') . $this->versionZipFile;

        try {
            $downloadFile = $this->download($downloadUrl, 'new-version' . $version . '.zip');

            Cache::remember($this->downloadFileCacheKey, now()->addMinutes(30), function () use ($downloadFile, $version) {
                return [
                    'file'    => $downloadFile,
                    'version' => $version,
                ];
            });

        } catch (Exception $exception) {

            Log::error($exception->getMessage());

            throw ValidationException::withMessages([
                'message' => $exception->getMessage(),
            ]);
        }
    }

    public function getDownloadVersion(): ?string
    {
        $array = Cache::get($this->downloadFileCacheKey) ?: [];

        return data_get($array, 'version');
    }

    public function updateNewVersion(string $backupFileName): bool
    {
        set_time_limit(0); // unlimited max execution time
        ini_set('memory_limit', '-1'); // increase memory_limit to 1GB

        $blackList = app(ExtensionRepositoryInterface::class)->blacklist();

        if ($blackList) {
            throw ValidationException::withMessages([
                'message' => 'Please try again later!',
            ]);
        }

        $version = $this->nextVersion;

        $downloadFile = data_get(cache($this->downloadFileCacheKey), 'file');

        if (! $downloadFile || ! file_exists($downloadFile)) {
            throw ValidationException::withMessages([
                'message' => 'The download file could not be found. Please try again.',
            ]);
        }

        try {
            if (File::exists(base_path('bootstrap/cache/packages.php'))) {
                File::delete(base_path('bootstrap/cache/packages.php'));
            }

            if (File::exists(base_path('bootstrap/cache/services.php'))) {
                File::delete(base_path('bootstrap/cache/services.php'));
            }

            Artisan::call('optimize:clear');

            DB::beginTransaction();

            Artisan::call('down');

            $this->unzip($downloadFile);

            $this->migrate();

            $this->updateVersion($version);

            InstallationHelper::runInstallation();

            Artisan::call('up');

            DB::commit();
        } catch (Throwable $e) {

            Log::error('Update failed: ' . $e->getMessage(), [
                'exception' => $e,
                'trace'     => $e->getTraceAsString(),
            ]);

            try {
                DB::rollBack();
            } catch (Throwable $dbException) {
                Log::error('Database rollback failed: ' . $dbException->getMessage());
            }

            try {
                $this->rollbackBackup($backupFileName);
            } catch (Throwable $rollbackException) {
                Log::error('Backup rollback failed: ' . $rollbackException->getMessage());
            }

            try {
                Artisan::call('up');
            } catch (Throwable $upException) {
                Log::error('Failed to bring application up: ' . $upException->getMessage());
            }

            throw ValidationException::withMessages([
                'message' => 'Update failed: ' . $e->getMessage(),
            ]);
        }

        return true;
    }

    private function rollbackBackup(string $backupFileName): void
    {
        if (! File::exists(base_path($backupFileName))) {
            return;
        }

        $this->unzip(base_path($backupFileName));
    }

    private function updateVersion(string $version): void
    {
        $setting = Setting::getCache();
        $setting->script_version = $version;
        $setting->save();

        File::put(base_path('version.txt'), $version);
    }

    private function migrate(): void
    {
        Artisan::call('migrate', ['--force' => true]);
    }
}
