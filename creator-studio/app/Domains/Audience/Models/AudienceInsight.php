<?php

namespace App\Domains\Audience\Models;

use Illuminate\Database\Eloquent\Model;

/** What viewers asked for in one video's comments (see CommentMiner). */
class AudienceInsight extends Model
{
    protected $fillable = ['video_id', 'video_title', 'url', 'own', 'comment_count', 'summary', 'items', 'used'];

    protected function casts(): array
    {
        return ['items' => 'array', 'own' => 'boolean', 'used' => 'boolean'];
    }

    /** Compact lines for the idea prompt, one per viewer need worth a video. */
    public function compactLines(): array
    {
        return collect($this->items ?? [])
            ->filter(fn ($i) => in_array($i['type'] ?? '', ['question', 'request', 'confusion', 'objection'], true))
            ->take(5)
            ->map(fn ($i) => json_encode([
                'viewer_need' => $i['text'],
                'type' => $i['type'],
                'mentions' => $i['mentions'] ?? 1,
                'on_video' => $this->video_title,
                'her_video' => $this->own,
                'url' => $this->url,
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE))
            ->values()->all();
    }
}
