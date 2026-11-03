<?php

namespace App\Domains\Community\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One piece of the Community Hub: a starter kit, blueprint, dispatch (post), perk, a member's
 * showcase project or a member's question. She writes everything except showcase projects
 * and questions; those come from members (showcases wait for her approval).
 */
class CommunityItem extends Model
{
    public const TYPES = ['kit', 'blueprint', 'dispatch', 'perk', 'showcase', 'question'];

    public const TIERS = ['free', 'pro'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'tags' => 'array',
            'meta' => 'array',
            'featured' => 'boolean',
            'upvotes' => 'integer',
            'answered_at' => 'datetime',
            'published_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopePublished(Builder $q): Builder
    {
        return $q->where('status', 'published');
    }

    /**
     * For a member: locked Pro items show their title but not their content.
     *
     * @param  array<int, int>  $votedIds
     */
    public function toMember(bool $pro, array $votedIds = []): array
    {
        $locked = $this->tier === 'pro' && !$pro;

        return [
            'id' => $this->id,
            'type' => $this->type,
            'title' => $this->title,
            'body' => $locked ? null : $this->body,
            'answer' => $locked ? null : $this->answer,
            'answered_at' => $this->answered_at?->toIso8601String(),
            'url' => $locked ? null : $this->url,
            'url2' => $locked ? null : $this->url2,
            'code' => $locked ? null : $this->code,
            'tags' => $this->tags ?? [],
            'meta' => $locked ? [] : ($this->meta ?? []),
            'tier' => $this->tier,
            'locked' => $locked,
            'featured' => $this->featured,
            'upvotes' => $this->upvotes,
            'voted' => in_array($this->id, $votedIds, true),
            'author' => $this->user?->name,
            'mine' => $this->user_id !== null && $this->user_id === auth()->id(),
            'status' => $this->status,
            'published_at' => ($this->published_at ?? $this->created_at)?->toIso8601String(),
        ];
    }

    /**
     * Everything, for her admin view.
     */
    public function toAdmin(): array
    {
        return $this->toMember(true) + [
            'user_id' => $this->user_id,
            'author_email' => $this->user?->email,
            'idea_id' => $this->idea_id,
        ];
    }
}
