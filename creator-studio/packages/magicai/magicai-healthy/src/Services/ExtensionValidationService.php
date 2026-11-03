<?php

namespace MagicAI\Healthy\Services;

use App\Domains\Marketplace\Services\ExtensionUninstallService;
use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use MagicAI\Healthy\Exceptions\InvalidLicenseException;
use MagicAI\Healthy\Helpers\LicenseHelper;
use MagicAI\Healthy\Helpers\ObfuscationHelper;

class ExtensionValidationService
{
    private string $managerFolder = 'extensions';

    private int $cacheInterval = 3600;

    private int $maxRetries = 3;

    public function __construct(
        private LicenseHelper $licenseHelper,
        private ObfuscationHelper $obfuscationHelper
    ) {}

    public function validateSingle(string $extensionSlug): array
    {
        try {
            $cacheKey = $this->obfuscationHelper->getCacheKey($extensionSlug);
            $cached = Cache::get($cacheKey);

            if ($cached !== null) {
                return [
                    'slug'    => $extensionSlug,
                    'status'  => $cached ? 'valid' : 'invalid',
                    'source'  => 'cache',
                    'message' => $cached ? 'License valid from cache' : 'License invalid from cache',
                ];
            }

            $isValid = $this->performValidation($extensionSlug);
            Cache::put($cacheKey, $isValid, $this->cacheInterval);

            return [
                'slug'    => $extensionSlug,
                'status'  => $isValid ? 'valid' : 'invalid',
                'source'  => 'validation',
                'message' => $isValid ? 'License validated successfully' : 'License validation failed',
            ];

        } catch (InvalidLicenseException $e) {
            return [
                'slug'    => $extensionSlug,
                'status'  => 'invalid',
                'source'  => 'exception',
                'message' => $e->getMessage(),
            ];
        } catch (Exception $e) {
            Log::error('Extension check error', [
                'extension' => $extensionSlug,
                'error'     => $e->getMessage(),
            ]);

            return [
                'slug'    => $extensionSlug,
                'status'  => 'error',
                'source'  => 'exception',
                'message' => 'System error during validation',
            ];
        }
    }

    public function performValidation(string $registerKey): bool
    {
        $hashedKey = $this->obfuscationHelper->obfuscateKey($registerKey);
        $licenseFile = "{$this->managerFolder}/{$hashedKey}.lic";

        if (! Storage::disk('local')->exists($licenseFile)) {
            return $this->performRemoteValidation($registerKey);
        }

        return $this->validateLocalLicense($registerKey, $hashedKey);
    }

    private function validateLocalLicense(string $registerKey, string $hashedKey): bool
    {
        try {
            $content = $this->licenseHelper->decryptAndUnserialize($hashedKey);

            if (! $this->licenseHelper->validateLicenseContent($content)) {
                return $this->handleInvalidLicense($registerKey);
            }

            if ($content['domain'] !== $this->licenseHelper->getCurrentDomain()) {
                return $this->handleInvalidLicense($registerKey);
            }

            if ($content['domain_key'] !== $this->licenseHelper->getCurrentDomainKey()) {
                return $this->handleInvalidLicense($registerKey);
            }

            if (empty($content['extension'])) {
                return $this->handleInvalidLicense($registerKey);
            }

            if ($this->shouldPerformRemoteCheck($registerKey)) {
                return $this->performRemoteValidation($registerKey, true);
            }

            return true;

        } catch (Exception $e) {
            Log::error('License validation error', [
                'register_key' => $registerKey,
                'error'        => $e->getMessage(),
            ]);

            return $this->handleInvalidLicense($registerKey);
        }
    }

    private function performRemoteValidation(string $registerKey, bool $isPeriodicCheck = false): bool
    {
        $response = $this->getRemoteResponse($registerKey);

        if ($response === false || ! is_array($response)) {
            if (! $isPeriodicCheck) {
                app(ExtensionUninstallService::class)->uninstall($registerKey);

                throw new InvalidLicenseException('License key not found or invalid.');
            }

            return false;
        }

        $this->licenseHelper->saveLicenseData($registerKey, $response);

        return true;
    }

    private function shouldPerformRemoteCheck(string $registerKey): bool
    {
        $lastCheckKey = $this->obfuscationHelper->getLastCheckCacheKey($registerKey);
        $lastCheck = Cache::get($lastCheckKey);

        if ($lastCheck === null || (time() - $lastCheck) >= $this->cacheInterval) {
            Cache::put($lastCheckKey, time(), $this->cacheInterval * 2);

            return true;
        }

        return false;
    }

    private function getRemoteResponse(string $registerKey)
    {
        $retries = 0;

        while ($retries < $this->maxRetries) {
            try {
                $response = $this->licenseHelper->makeRemoteRequest(trim($registerKey));

                if ($response->getStatusCode() === 200 && $response->json('status') === true) {
                    return $response->json('data');
                }

                break;

            } catch (Exception $e) {
                $retries++;

                if ($retries >= $this->maxRetries) {
                    Log::error('Remote validation failed after retries', [
                        'register_key' => $registerKey,
                        'error'        => $e->getMessage(),
                    ]);

                    break;
                }

                sleep(1);
            }
        }

        return false;
    }

    private function handleInvalidLicense(string $registerKey): bool
    {
        app(ExtensionUninstallService::class)->uninstall($registerKey);

        $this->removeSecurityFile($registerKey);

        return false;
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
}
