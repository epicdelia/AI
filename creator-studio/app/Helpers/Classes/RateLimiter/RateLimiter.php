<?php

declare(strict_types=1);

namespace App\Helpers\Classes\RateLimiter;

use Illuminate\Support\Facades\RateLimiter as Limiter;

/**
 * A daily attempt counter per key (MagicAI's guest-chat limiter), on Laravel's RateLimiter.
 */
class RateLimiter
{
    public function __construct(private string $name, private int $maxPerDay)
    {
    }

    public function attempt(string $key): bool
    {
        $id = $this->name . ':' . sha1($key);
        if (Limiter::tooManyAttempts($id, max(0, $this->maxPerDay))) {
            return false;
        }
        Limiter::hit($id, 86400);

        return true;
    }
}
