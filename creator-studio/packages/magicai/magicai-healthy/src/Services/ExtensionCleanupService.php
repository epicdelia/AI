<?php

namespace MagicAI\Healthy\Services;

use App\Domains\Marketplace\Services\ExtensionUninstallService;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use MagicAI\Healthy\Helpers\ObfuscationHelper;
use MagicAI\Healthy\Repositories\ExtensionRepository;

class ExtensionCleanupService
{
    private string $managerFolder = 'extensions';

    public function __construct(
        public ExtensionValidationService $validationService,
        public ExtensionRepository $extensionRepository,
        public ObfuscationHelper $obfuscationHelper,
        public ExtensionCacheService $cacheService
    ) {}

    public function cleanupInvalidExtensions(): array
    {
        $checkResults = $this->extensionRepository->checkAllRegisteredExtensions();
        $uninstalledExtensions = [];

        foreach ($checkResults as $result) {
            if ($result['status'] === 'invalid') {
                try {
                    // Extension'ı uninstall et
                    app(ExtensionUninstallService::class)->uninstall($result['slug']);

                    // Lisans dosyasını sil
                    $this->removeSecurityFile($result['slug']);

                    // Cache'i temizle
                    $this->cacheService->clearAll($result['slug']);

                    $uninstalledExtensions[] = $result['slug'];

                    Log::info('Extension removed due to security violation', [
                        'extension' => $result['slug'],
                    ]);

                } catch (Exception $e) {
                    Log::error('Failed to remove invalid extension', [
                        'extension' => $result['slug'],
                        'error'     => $e->getMessage(),
                    ]);
                }
            }
        }

        return $uninstalledExtensions;
    }

    public function removeSecurityFile(string $extensionSlug): bool
    {
        try {
            $hashedKey = $this->obfuscationHelper->obfuscateKey($extensionSlug);
            $securityFile = "{$this->managerFolder}/{$hashedKey}.lic";

            if (Storage::disk('local')->exists($securityFile)) {
                Storage::disk('local')->delete($securityFile);

                Log::info('Security file removed', [
                    'extension' => $extensionSlug,
                ]);

                return true;
            }

            return false;

        } catch (Exception $e) {
            Log::error('Failed to remove security file', [
                'extension' => $extensionSlug,
                'error'     => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function fullCleanup(string $extensionSlug): bool
    {
        try {
            // 1. Extension'ı uninstall et
            app(ExtensionUninstallService::class)->uninstall($extensionSlug);

            // 2. Security dosyasını sil
            $this->removeSecurityFile($extensionSlug);

            // 3. Cache'i temizle
            $this->cacheService->clearAll($extensionSlug);

            Log::info('Full cleanup completed', [
                'extension' => $extensionSlug,
            ]);

            return true;

        } catch (Exception $e) {
            Log::error('Full cleanup failed', [
                'extension' => $extensionSlug,
                'error'     => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function cleanupOrphanedFiles(): array
    {
        $cleanedFiles = [];

        try {
            $registeredExtensions = $this->extensionRepository->getRegisteredExtensions();
            $allSecurityFiles = Storage::disk('local')->files($this->managerFolder);

            foreach ($allSecurityFiles as $file) {
                if (! str_ends_with($file, '.lic')) {
                    continue;
                }

                $fileName = basename($file, '.lic');
                $isOrphaned = true;

                // Kayıtlı extension'larla karşılaştır
                foreach (array_keys($registeredExtensions) as $extensionSlug) {
                    $hashedKey = $this->obfuscationHelper->obfuscateKey($extensionSlug);

                    if ($fileName === $hashedKey) {
                        $isOrphaned = false;

                        break;
                    }
                }

                if ($isOrphaned) {
                    Storage::disk('local')->delete($file);
                    $cleanedFiles[] = $file;

                    Log::info('Orphaned security file removed', [
                        'file' => $file,
                    ]);
                }
            }

        } catch (Exception $e) {
            Log::error('Failed to cleanup orphaned files', [
                'error' => $e->getMessage(),
            ]);
        }

        return $cleanedFiles;
    }
}
