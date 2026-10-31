<?php

namespace App\Domains\Agents\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgentEvalReport extends Model
{
    protected $fillable = [
        'social_agent_id',
        'scenario',
        'overall_score',
        'rubric',
        'output_sample',
        'feedback',
    ];

    protected function casts(): array
    {
        return [
            'rubric' => 'array',
            'feedback' => 'array',
            'overall_score' => 'integer',
        ];
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(SocialAgent::class, 'social_agent_id');
    }
}

