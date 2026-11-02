<?php

namespace App\Domains\Engagement\Models;

use App\Domains\Social\Models\SocialPost;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A comment or DM from one of her connected accounts, and what happened to it.
 */
class EngagementItem extends Model
{
    protected $fillable = [
        'platform', 'kind', 'external_id', 'thread_id', 'social_post_id', 'author', 'author_id', 'text', 'received_at',
        'status', 'intent', 'reply', 'reply_draft', 'replied_by', 'engagement_rule_id', 'replied_at', 'error',
    ];

    public function post(): BelongsTo
    {
        return $this->belongsTo(SocialPost::class, 'social_post_id');
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(EngagementRule::class, 'engagement_rule_id');
    }

    /**
     * The shape the network providers expect.
     */
    public function forProvider(): array
    {
        return [
            'kind' => $this->kind,
            'external_id' => $this->external_id,
            'thread_id' => $this->thread_id,
            'author_id' => $this->author_id,
        ];
    }

    protected function casts(): array
    {
        return [
            'received_at' => 'datetime',
            'replied_at' => 'datetime',
        ];
    }
}
