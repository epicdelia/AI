<?php

namespace App\Domains\Feeds\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FeedItem extends Model
{
    protected $fillable = ['feed_id', 'guid', 'title', 'link', 'summary', 'image', 'published_at', 'used'];

    public function feed(): BelongsTo
    {
        return $this->belongsTo(Feed::class);
    }

    /**
     * One compact line for the idea prompt, like Outlier::compactLine().
     */
    public function compactLine(): string
    {
        return json_encode([
            'title' => $this->title,
            'source' => $this->feed?->title,
            'url' => $this->link,
            'published' => $this->published_at?->toDateString(),
            'summary' => mb_substr(trim(strip_tags((string)$this->summary)), 0, 280),
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'used' => 'boolean',
        ];
    }
}
