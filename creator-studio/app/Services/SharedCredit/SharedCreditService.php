<?php

declare(strict_types=1);

namespace App\Services\SharedCredit;

use App\Models\SharedCreditTransaction;
use App\Models\Team\Team;
use App\Models\User;

/**
 * MagicAI's team-wide shared credit pool, referenced by the ported Entity layer
 * (HasCreditLimit). The studio has no shared pool: credits are per user
 * (users.credit_balance + user_usage_credits), so this stays switched off and every
 * method is a safe no-op. HasCreditLimit checks isEnabled() before using anything else.
 */
class SharedCreditService
{
    public function isEnabled(): bool
    {
        return false;
    }

    public function topUp(User $user, float $value, string $reason = '', mixed $entity = null, string $type = 'increase'): ?SharedCreditTransaction
    {
        return null;
    }

    public function deduct(User $user, mixed $entity, float $quantity): ?SharedCreditTransaction
    {
        return null;
    }

    public function deductWithTeam(User $user, ?Team $team, mixed $entity, float $quantity): ?SharedCreditTransaction
    {
        return null;
    }

    public function getCostOverride(string $slug): ?float
    {
        return null;
    }
}
