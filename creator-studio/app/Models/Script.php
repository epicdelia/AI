<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Script extends Model
{
    public const STATUSES = ['draft', 'reviewed', 'filmed', 'published'];

    protected $fillable = [
        'idea_id', 'title', 'body', 'review', 'transcript', 'substack', 'edit_brief', 'status',
        'youtube_url', 'published_on',
    ];

    protected function casts(): array
    {
        return ['published_on' => 'date'];
    }

    public function idea(): BelongsTo
    {
        return $this->belongsTo(Idea::class);
    }

    public function wordCount(): int
    {
        return str_word_count(strip_tags((string) $this->body));
    }

    /** About 150 spoken words per minute. */
    public function minutes(): float
    {
        return round($this->wordCount() / 150, 1);
    }
}

