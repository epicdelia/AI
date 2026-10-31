<?php

namespace App\Domains\Agents\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SwarmMission extends Model
{
    protected $fillable = [
        'social_agent_id',
        'title',
        'goal',
        'status',
        'current_step',
        'steps',
        'context',
        'result',
    ];

    protected function casts(): array
    {
        return [
            'steps' => 'array',
            'context' => 'array',
            'result' => 'array',
            'current_step' => 'integer',
        ];
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(SocialAgent::class, 'social_agent_id');
    }
}

