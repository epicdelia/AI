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

    public function swarmMissions(): HasMany
    {
        return $this->hasMany(SwarmMission::class);
    }

    public function promptVersions(): HasMany
    {
        return $this->hasMany(AgentPromptVersion::class);
    }

    public function option(string $key, mixed $default = null): mixed
    {
        return $this->options[$key] ?? $default;
    }

    /**
     * The "studio" agent behind the AI checks outside the Agents page (script editor, Deals,
     * post composer, Weekly goals, the Copilot): her first agent's brand, audience, voice and
     * lessons, but never saved, so nothing it drafts is credited to or learned by that agent.
     * Without any agent it's a plain one in review mode on every network.
     */
    public static function studio(): self
    {
        $base = self::where('active', true)->orderBy('id')->first() ?? self::orderBy('id')->first();
        $agent = $base ? $base->replicate(['last_run_at', 'next_run_at', 'learned_at']) : new self([
            'name' => 'Studio',
            'platforms' => array_keys(\App\Domains\Social\Platforms::PLATFORMS),
            'post_types' => array_keys(self::POST_TYPES),
            'posts_per_week' => 5,
            'approval' => 'review',
            'active' => false,
            'options' => [],
            'lessons' => [],
        ]);
        $agent->approval = 'review'; // studio drafts always wait for her
        $agent->exists = false;

        return $agent;
    }
}
