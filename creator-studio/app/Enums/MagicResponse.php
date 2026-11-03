<?php

declare(strict_types=1);

namespace App\Enums;

use RuntimeException;

/**
 * Error responses the ported MagicAI Entity layer throws.
 */
enum MagicResponse: string
{
    case NO_CREDITS_LEFT = 'no_credits_left';

    public function message(): string
    {
        return match ($this) {
            self::NO_CREDITS_LEFT => 'You have no AI credits left.',
        };
    }

    /**
     * @return never
     */
    public function exception(): never
    {
        throw new RuntimeException($this->message(), 402);
    }
}
