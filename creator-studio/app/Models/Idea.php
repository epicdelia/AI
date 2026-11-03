<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Idea extends Model
{
    public const STATUSES = ['new', 'picked', 'scripted', 'dropped'];

    protected $fillable = [
        'idea_brief_id', 'title', 'thumbnail_text', 'premise', 'why_now', 'source_url', 'angle',
        'format', 'effort', 'shelf_life', 'risk', 'status', 'notes',
    ];

    public function brief(): BelongsTo
    {
        return $this->belongsTo(IdeaBrief::class, 'idea_brief_id');
    }

    public function scripts(): HasMany
    {
        return $this->hasMany(Script::class);
    }

    /**
     * The idea as a markdown block, the way the script writer reads it.
     */
    public function asMarkdown(): string
    {
        return collect([
            "### {$this->title}",
            $this->thumbnail_text ? "**Thumbnail text:** {$this->thumbnail_text}" : null,
            $this->premise ? "**Premise in one sentence:** {$this->premise}" : null,
            $this->why_now ? "**Why now:** {$this->why_now}" : null,
            $this->source_url ? "**Source:** {$this->source_url}" : null,
            $this->angle ? "**My angle:** {$this->angle}" : null,
            $this->format ? "**Format:** {$this->format}" : null,
            $this->effort ? "**Effort:** {$this->effort}" : null,
            $this->shelf_life ? "**Shelf life:** {$this->shelf_life}" : null,
            $this->risk ? "**Risk:** {$this->risk}" : null,
            $this->notes ? "**My notes:** {$this->notes}" : null,
        ])->filter()->implode("\n");
    }
}
