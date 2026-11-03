<?php

namespace App\Domains\YouTube;

use Illuminate\Database\Eloquent\Model;

/**
 * Multi-Variant A/B/C Testing Engine for YouTube Videos (Thumbnails + Titles).
 */
class VideoAbTest extends Model
{
    protected $fillable = [
        'video_id',
        'status',
        'original_title',
        'original_thumbnail_media_id',
        'variants',
        'winner_index',
        'confidence_score',
        'statistical_summary',
        'started_at',
        'concluded_at',
        'error',
    ];

    public function isConcluded(): bool
    {
        return in_array($this->status, ['concluded', 'stopped'], true);
    }

    public function winnerVariant(): ?array
    {
        if ($this->winner_index === null || !is_array($this->variants)) {
            return null;
        }

        return $this->variants[$this->winner_index] ?? null;
    }

    protected function casts(): array
    {
        return [
            'variants' => 'array',
            'winner_index' => 'integer',
            'confidence_score' => 'float',
            'statistical_summary' => 'array',
            'started_at' => 'datetime',
            'concluded_at' => 'datetime',
        ];
    }
}
