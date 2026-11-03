<?php

namespace MagicAI\Healthy\Helpers;

use App\Domains\Marketplace\Repositories\Contracts\ExtensionRepositoryInterface;
use Illuminate\Support\Facades\Storage;

class LicenseHelper
{
    private string $managerFolder = 'extensions';

    public function __construct(
        private ObfuscationHelper $obfuscationHelper
    ) {}

    public function decryptAndUnserialize(string $hashedKey): array
    {
        $content = decrypt(
            Storage::disk('local')->get("{$this->managerFolder}/{$hashedKey}.lic")
        );

        return unserialize($content, ['allowed_classes' => false]);
    }

    public function validateLicenseContent(array $content): bool
    {
        $requiredFields = ['domain', 'domain_key', 'extension'];

        foreach ($requiredFields as $field) {
            if (! isset($content[$field])) {
                return false;
            }
        }

        if (! isset($content['checksum'])) {
            return false;
        }

        $expectedChecksum = $this->generateChecksum($content);

        return hash_equals($content['checksum'], $expectedChecksum);
    }

    public function generateChecksum(array $content): string
    {
        $data = $content['domain'] . $content['domain_key'] . $content['extension'] . $this->obfuscationHelper->getSalt('salt1');

        return hash_hmac('sha256', $data, $this->obfuscationHelper->getSalt('salt2'));
    }

    public function getCurrentDomain(): string
    {
        return request()->getHost();
    }

    public function getCurrentDomainKey(): string
    {
        return app(ExtensionRepositoryInterface::class)->domainKey();
    }

    public function saveLicenseData(string $registerKey, array $data): void
    {
        $data['checksum'] = $this->generateChecksum($data);
        $encrypt = $this->encrypt($data);
        $hashedKey = $this->obfuscationHelper->obfuscateKey($registerKey);

        $this->save($hashedKey, $encrypt);
    }

    public function makeRemoteRequest(string $registerKey)
    {
        return app(ExtensionRepositoryInterface::class)->request(
            'post',
            "extension/{$registerKey}/license",
        );
    }

    private function encrypt(array $content): string
    {
        return encrypt(serialize($content));
    }

    private function save(string $hashedKey, string $content): void
    {
        Storage::disk('local')->put("{$this->managerFolder}/{$hashedKey}.lic", $content);
    }
}
