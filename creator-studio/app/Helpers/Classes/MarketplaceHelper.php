<?php

declare(strict_types=1);

namespace App\Helpers\Classes;

/**
 * MagicAI marketplace extensions (ai-chat-pro, photo-studio…). The studio ships none of
 * them, so nothing is ever registered.
 */
class MarketplaceHelper
{
    public static function isRegistered(string $slug): bool
    {
        return false;
    }
}
