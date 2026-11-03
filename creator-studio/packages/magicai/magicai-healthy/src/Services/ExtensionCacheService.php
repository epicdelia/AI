<?php

namespace MagicAI\Healthy\Services;

use Illuminate\Support\Facades\Cache;
use MagicAI\Healthy\Helpers\ObfuscationHelper;

class ExtensionCacheService
{
    private string $cachePrefix = 'ext_lic_';

    private int $cacheInterval = 3600;

    public function __construct(
        private ObfuscationHelper $obfuscationHelper
    ) {}

    public function get(string $registerKey)
    {
        $cacheKey = $this->getCacheKey($registerKey);

        return Cache::get($cacheKey);
    }

    public function put(string $registerKey, bool $value): void
    {
        $cacheKey = $this->getCacheKey($registerKey);
        Cache::put($cacheKey, $value, $this->cacheInterval);
    }

    public function forget(string $registerKey): void
    {
        $cacheKey = $this->getCacheKey($registerKey);
        Cache::forget($cacheKey);
    }

    public function clearAll(string $registerKey): void
    {
        $cacheKey = $this->getCacheKey($registerKey);
        $lastCheckKey = $this->getLastCheckCacheKey($registerKey);

        Cache::forget($cacheKey);
        Cache::forget($lastCheckKey);
    }

    private function getCacheKey(string $registerKey): string
    {
        return $this->cachePrefix . $this->obfuscationHelper->obfuscateKey($registerKey);
    }

    private function getLastCheckCacheKey(string $registerKey): string
    {
        return $this->cachePrefix . 'last_check_' . $this->obfuscationHelper->obfuscateKey($registerKey);
    }
}
