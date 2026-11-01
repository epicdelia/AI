<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Script extends Model
{
    public const STATUSES = ['draft', 'reviewed', 'filmed', 'published'];

    protected $fillable = [
        'idea_id', 'title', 'body', 'outline', 'review', 'fact_check', 'fact_checked_at', 'transcript', 'substack', 'edit_brief', 'status',
        'youtube_url', 'published_on', 'publish_kit',
        'is_ai_assisted', 'ai_model_name', 'human_reviewed_at', 'editorial_notes',
    ];

    protected function casts(): array
    {
        return [
            'published_on' => 'date',
            'fact_checked_at' => 'datetime',
            'outline' => 'array',
            'is_ai_assisted' => 'boolean',
            'human_reviewed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // Every path that rewrites the body (autosave, AI, Copilot, restore) keeps the old text first.
        static::updating(fn (self $script) => ScriptVersion::beforeChange($script));
    }

    public function idea(): BelongsTo
    {
        return $this->belongsTo(Idea::class);
    }

    public function deals(): HasMany
    {
        return $this->hasMany(Deal::class);
    }

    public function versions(): HasMany
    {
        return $this->hasMany(ScriptVersion::class);
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

