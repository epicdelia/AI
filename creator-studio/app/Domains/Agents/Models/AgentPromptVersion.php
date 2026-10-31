<?php

namespace App\Domains\Agents\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgentPromptVersion extends Model
{
    protected $fillable = [
        'social_agent_id',
        'version',
        'system_prompt',
        'score',
        'eval_breakdown',
        'changes_summary',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'score' => 'integer',
            'eval_breakdown' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(SocialAgent::class, 'social_agent_id');
    }
}

