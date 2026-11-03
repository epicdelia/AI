<?php

namespace MagicAI\Healthy\Helpers;

class ObfuscationHelper
{
    private string $cachePrefix = 'ext_lic_';

    private array $obfuscationKeys = [
        'salt1' => 'x7k9m2n5',
        'salt2' => 'p3q8r1t6',
        'salt3' => 'z4w7j0v9',
    ];

    public function obfuscateKey(string $key): string
    {
        $combined = $key . $this->obfuscationKeys['salt3'];

        return hash('sha256', $combined);
    }

    public function getCacheKey(string $registerKey): string
    {
        return $this->cachePrefix . $this->obfuscateKey($registerKey);
    }

    public function getLastCheckCacheKey(string $registerKey): string
    {
        return $this->cachePrefix . 'last_check_' . $this->obfuscateKey($registerKey);
    }

    public function getSalt(string $key): string
    {
        return $this->obfuscationKeys[$key] ?? '';
    }
}
