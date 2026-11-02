<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Outlier extends Model
{
    protected $fillable = [
        'video_id', 'channel_id', 'channel_title', 'title', 'url', 'views', 'channel_median',
        'outlier_score', 'age_days', 'published_at', 'used',
    ];

    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class);
    }

    /**
     * One compact line for the AI: every field here costs tokens.
     */
    public function compactLine(): string
    {
        return json_encode([
            'title' => $this->title,
            'channel' => $this->channel_title,
            'url' => $this->url,
            'views' => $this->views,
            'outlier_score' => $this->outlier_score,
            'age_days' => $this->age_days,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'used' => 'boolean',
            'outlier_score' => 'float',
            'age_days' => 'float',
        ];
    }
}
