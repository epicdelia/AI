<?php

namespace App\Domains\Agents\Models;

use App\Domains\Social\Models\SocialPost;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One line in the notifications drawer: what an agent or an automation did, or what needs her. */
class AgentEvent extends Model
{
    protected $fillable = ['social_agent_id', 'social_post_id', 'type', 'platform', 'message', 'read_at'];

    protected function casts(): array
    {
        return ['read_at' => 'datetime'];
    }

    public static function log(string $type, string $message, ?int $agentId = null, ?int $postId = null, ?string $platform = null): self
    {
        return self::create(['type' => $type, 'message' => mb_substr($message, 0, 2000), 'social_agent_id' => $agentId,
            'social_post_id' => $postId, 'platform' => $platform]);
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(SocialAgent::class, 'social_agent_id');
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(SocialPost::class, 'social_post_id');
    }
}
