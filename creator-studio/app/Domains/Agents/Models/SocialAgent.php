<?php

namespace App\Domains\Agents\Models;

use App\Domains\Social\Models\SocialPost;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SocialAgent extends Model
{
    public const POST_TYPES = [
        'informative' => ['Informative', 'Explain something useful from her field'],
        'tips_and_tricks' => ['Tips and tricks', 'Quick, practical how-tos'],
        'behind_the_scenes' => ['Behind the scenes', 'How she works, her setup, her process'],
        'opinions' => ['Hot takes', 'A clear opinion on news in her niche'],
        'stories' => ['Stories', 'Lessons from her own experience'],
        'announcements' => ['Announcements', 'New videos, launches and updates'],
        'promotions' => ['Promotions', 'Her videos, newsletter or products'],
        'engagement' => ['Questions & polls', 'Posts that start a conversation'],
    ];

    protected $fillable = [
        'name', 'platforms', 'site_url', 'brand', 'audience', 'post_types', 'options', 'posts_per_week',
        'approval', 'active', 'lessons', 'learned_at', 'last_run_at', 'next_run_at',
    ];

    protected function casts(): array
    {
        return [
            'platforms' => 'array', 'post_types' => 'array', 'options' => 'array', 'lessons' => 'array',
            'active' => 'boolean', 'learned_at' => 'datetime', 'last_run_at' => 'datetime', 'next_run_at' => 'datetime',
        ];
    }

    public function posts(): HasMany
    {
        return $this->hasMany(SocialPost::class, 'social_agent_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(AgentEvent::class);
    }

    public function evalReports(): HasMany
    {
        return $this->hasMany(AgentEvalReport::class);
    }

    public function option(string $key, mixed $default = null): mixed
    {
        return $this->options[$key] ?? $default;
    }
}
