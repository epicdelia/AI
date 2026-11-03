<?php

namespace App\Domains\Community\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A paid (or comped) community tier. Active while status is active or comped and the period
 * hasn't ended (Stripe renews it through the webhook).
 */
class CommunityMembership extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['current_period_end' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'comped'
            || ($this->status === 'active' && ($this->current_period_end === null || $this->current_period_end->isFuture()));
    }
}
