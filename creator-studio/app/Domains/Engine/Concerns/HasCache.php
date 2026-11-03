<?php

declare(strict_types=1);

namespace App\Domains\Engine\Concerns;

use Closure;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

trait HasCache
{
    public int $cacheTtl = 300;

    /**
     * Global cache (backwards compatible).
     */
    public function cache(string $key, $value): mixed
    {
        return Cache::remember($key, $this->cacheTtl, $value);
    }

    /**
     * User-scoped cache (new).
     */
    public function userCache(string $key, Closure $callback): mixed
    {
        $userId = Auth::id();

        if (! $userId) {
            return $callback();
        }

        $key = "user:{$userId}:{$key}";

        return Cache::remember($key, $this->cacheTtl, $callback);
    }

    /**
     * Forget cache (works with global or user).
     */
    public function forget(string $key, bool $userScoped = false): void
    {
        if ($userScoped) {
            $userId = Auth::id();
            if ($userId) {
                $key = "user:{$userId}:{$key}";
            }
        }

        Cache::forget($key);
    }
}
