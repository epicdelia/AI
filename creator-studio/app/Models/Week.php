<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Week extends Model
{
    public const STATUSES = ['pending', 'shipped', 'missed', 'late'];

    protected $fillable = [
        'number', 'start_date', 'due_date', 'status', 'script_id', 'video_title', 'youtube_url',
        'published_on', 'views_7d', 'note', 'verdict',
    ];

    public function script(): BelongsTo
    {
        return $this->belongsTo(Script::class);
    }

    /**
     * The week whose due date is today or later (the one she's working on now).
     */
    public static function current(): ?self
    {
        $today = now(Setting::get('timezone'))->toDateString();

        return self::query()->whereDate('due_date', '>=', $today)->orderBy('due_date')->first()
            ?? self::query()->orderByDesc('due_date')->first();
    }

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'due_date' => 'date',
            'published_on' => 'date',
        ];
    }
}
