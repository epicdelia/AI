<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Placeholder for MagicAI's shared-credit ledger. Only queried when SharedCreditService is
 * enabled, which it never is in the studio, so the table doesn't need to exist.
 */
class SharedCreditTransaction extends Model
{
    protected $table = 'shared_credit_transactions';

    protected $guarded = [];
}
