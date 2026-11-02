<?php

namespace App\Domains\Social\Models;

use App\Domains\Social\Platforms;
use App\Domains\Workflows\Engine;
use App\Models\Script;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SocialPost extends Model
{
    public const METRICS = ['impressions', 'likes', 'comments', 'shares', 'saves'];

    protected $fillable = [
        'platform', 'format', 'title', 'body', 'parts', 'status', 'scheduled_for', 'posted_at', 'url', 'thumbnail_url',
        'script_id', 'source', 'metrics', 'notes', 'source_post_id', 'media', 'pillar', 'external_id',
        'social_agent_id', 'approval', 'agent_original', 'publish_error',
    ];

    protected $appends = ['engagement'];

    public function script(): BelongsTo
    {
        return $this->belongsTo(Script::class);
    }

    public function socialAgent(): BelongsTo
    {
        return $this->belongsTo(\App\Domains\Agents\Models\SocialAgent::class, 'social_agent_id');
    }

    /**
     * Likes + comments + shares + saves, and the rate against impressions when she logged them.
     */
    public function getEngagementAttribute(): ?array
    {
        $m = $this->metrics ?? [];
        if (!array_filter($m, fn ($v) => $v !== null && $v !== '')) {
            return null;
        }
        $total = (int)($m['likes'] ?? 0) + (int)($m['comments'] ?? 0) + (int)($m['shares'] ?? 0) + (int)($m['saves'] ?? 0);
        $views = (int)($m['impressions'] ?? 0);

        return [
            'total' => $total,
            'rate' => $views > 0 ? round($total / $views * 100, 2) : null,
        ];
    }

    /**
     * The post as plain text, the way it's copied out (parts numbered for threads).
     */
    public function asText(): string
    {
        $parts = $this->parts ?? [];
        $label = Platforms::FORMATS[$this->platform][$this->format][1] ?? null;
        $list = $parts ? implode("\n\n", array_map(fn ($p, $i) => ($label ? "{$label} " . ($i + 1) . ":\n" : '') . $p, $parts, array_keys($parts))) : '';

        return trim(implode("\n\n", array_filter([(string)$this->body, $list])));
    }

    protected function casts(): array
    {
        return [
            'parts' => 'array',
            'metrics' => 'array',
            'media' => 'array',
            'scheduled_for' => 'datetime',
            'posted_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // Marking a post posted stamps when, so it lands on the calendar and in "this week".
        static::saving(function (self $post) {
            if ($post->status === 'posted' && !$post->posted_at) {
                $post->posted_at = now();
            }
            if ($post->status === 'scheduled' && !$post->scheduled_for) {
                $post->status = 'draft';
            }
        });

        // The landing page shows published posts: rebuild it when one appears, changes or goes away.
        $touchesLanding = fn (self $post) => $post->status === 'posted' || $post->getOriginal('status') === 'posted';
        static::saved(function (self $post) use ($touchesLanding) {
            if ($touchesLanding($post) && $post->wasChanged(['status', 'url', 'title', 'body', 'metrics', 'media', 'posted_at', 'thumbnail_url'])) {
                \App\Domains\Landing\LandingFeed::forget();
            }
        });
        static::deleted(function (self $post) use ($touchesLanding) {
            if ($touchesLanding($post)) {
                \App\Domains\Landing\LandingFeed::forget();
            }
        });

        // "Post published" workflows, whichever way it became posted (published here, marked, synced).
        static::saved(function (self $post) {
            if ($post->status === 'posted' && $post->wasChanged('status') && $post->source !== 'demo') {
                try {
                    Engine::dispatch('post_published', Engine::postContext($post), "post:{$post->id}");
                } catch (\Throwable $e) {
                    report($e); // never block saving a post
                }
            }
        });
    }
}
