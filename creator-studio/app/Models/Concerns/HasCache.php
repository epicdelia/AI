<?php

namespace App\Models\Concerns;

use Closure;
use Illuminate\Support\Facades\Cache;

trait HasCache
{
    protected static function registryKey(): string
    {
        return self::$cacheKey . '_registry';
    }

    public static function getCache(Closure $function, string $suffix = '')
    {
        $key = self::$cacheKey . $suffix;

        $suffixes = Cache::get(self::registryKey(), []);
        if (! in_array($key, $suffixes, true)) {
            $suffixes[] = $key;
            Cache::put(self::registryKey(), $suffixes, self::$cacheTtl);
        }

        return Cache::remember($key, self::$cacheTtl, $function);
    }

    public static function forgetCache(): void
    {
        $suffixes = Cache::get(self::registryKey(), []);

        foreach ($suffixes as $key) {
            Cache::forget($key);
        }

        Cache::forget(self::registryKey());
    }
}
