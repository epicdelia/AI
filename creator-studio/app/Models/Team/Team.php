<?php

namespace App\Models\Team;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Team extends Model
{
    protected $fillable = [
        'user_id',
        'is_shared',
        'name',
        'allow_seats',
        'used_image_credit',
        'word_credit',
        'entity_credits',
        'credit_system_type',
        'shared_credits',
    ];

    protected $casts = [
        'entity_credits'    => 'array',
        'shared_credits'    => 'float',
    ];

    public function isSharedCreditTeam(): bool
    {
        return $this->credit_system_type === 'shared';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function members(): HasMany
    {
        return $this->hasMany(TeamMember::class, 'team_id');
    }

    public function getCredit(string $engineKey, string $entityKey): array
    {
        return $this->entity_credits[$engineKey][$entityKey] ?? [
            'credit'      => 0,
            'isUnlimited' => false,
        ];
    }
}
