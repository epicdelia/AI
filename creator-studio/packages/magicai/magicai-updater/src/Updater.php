<?php

namespace MagicAI\Updater;

use App\Helpers\Classes\VersionComparator;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use MagicAI\Updater\Traits\HasBackup;
use MagicAI\Updater\Traits\HasDownloader;
use MagicAI\Updater\Traits\HasUpdater;
use MagicAI\Updater\Traits\HasVersionPrepare;
use MagicAI\Updater\Traits\HasVersionUpdate;
use MagicAI\Updater\Traits\HasZipper;

class Updater
{
    use HasBackup;
    use HasDownloader;
    use HasUpdater;
    use HasVersionPrepare;
    use HasVersionUpdate;
    use HasZipper;

    public function __construct()
    {
        $this->prepareVersion();
    }

    public function versionCheck(): bool|int
    {
        $magicAIVersion = Cache::remember('magicai_next_version_cache', 60 * 10, function () {
            return $this->nextVersion;
        });

        if (! $magicAIVersion) {
            $versions = $this->prepareVersion();

            $magicAIVersion = $versions['version'];
        }

        $currentMagicAIVersion = $this->currentMagicAIVersion();

        return VersionComparator::compareVersion($magicAIVersion, $currentMagicAIVersion, '=');
    }

    public function backupView(): array
    {
        $magicAIVersion = $this->nextVersion;

        $currentMagicAIVersion = $this->currentMagicAIVersion();

        if (VersionComparator::compareVersion($magicAIVersion, $currentMagicAIVersion, '=')) {
            return [
                'updated' => true,
            ];
        }

        return [
            'updated' => false,
            'title'   => trans('MagicAI installed successfully'),
            'version' => $this->nextVersion,
            'view'    => 'magicai-updater::particles.backup',
            'step'    => 4,
        ];
    }

    public function downloadView(): array
    {
        $magicAIVersion = $this->nextVersion;

        $currentMagicAIVersion = $this->currentMagicAIVersion();

        if (VersionComparator::compareVersion($magicAIVersion, $currentMagicAIVersion, '=')) {
            return [
                'updated' => true,
            ];
        }

        return [
            'updated'    => false,
            'title'      => trans('MagicAI installed successfully'),
            'version'    => $magicAIVersion,
            'isDownload' => $magicAIVersion === $this->getDownloadVersion(),
            'view'       => 'magicai-updater::particles.download',
            'step'       => 4,
        ];
    }

    public function checker(): array
    {
        $updaterVersion = $this->newUpdaterVersion();

        $currentUpdater = $this->currentUpdater();

        $magicAIVersion = $this->nextVersion;

        $currentMagicAIVersion = $this->currentMagicAIVersion();

        if (VersionComparator::compareVersion($magicAIVersion, $currentMagicAIVersion, '=')) {
            Cache::forget('magicai_next_version_cache');

            return [
                'title'   => trans('MagicAI installed successfully'),
                'version' => $magicAIVersion,
                'view'    => 'magicai-updater::particles.updated',
            ];
        }

        if ($updaterVersion && VersionComparator::compareVersion($updaterVersion, $currentUpdater['version'], '=')) {

            Cache::forget('magicai_next_version_cache');

            return [
                'title'           => trans('MagicAI is ready to update'),
                'updater'         => $this->currentUpdater(),
                'version'         => $magicAIVersion,
                'updater_version' => $this->json('updater_version'),
                'view'            => 'magicai-updater::particles.update',
                'step'            => 2,
            ];
        }

        return [
            'title'           => trans('MagicAI is ready to download check for updates'),
            'version'         => $magicAIVersion,
            'updater_version' => $this->json('updater_version'),
            'view'            => 'magicai-updater::particles.updater',
            'step'            => 1,
        ];
    }

    public function forPanel(): array
    {
        return $this->prepareVersion();
    }

    public function currentMagicAIVersion(): false|string
    {
        $this->currentMagicAIVersion = trim(File::get(base_path('version.txt')));

        return $this->currentMagicAIVersion;
    }

    public function json(string $key): null|string|array
    {
        return $this->versionRequest()->json($key);
    }

    public function versionRequest(): Response
    {
        return once(static function () {
            return Http::get(config('magicai-updater.version_url'));
        });
    }
}
