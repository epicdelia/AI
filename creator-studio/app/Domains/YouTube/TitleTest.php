<?php

namespace App\Domains\YouTube;

use Illuminate\Database\Eloquent\Model;

class TitleTest extends Model
{
    protected $fillable = ['video_id', 'original_title', 'titles', 'windows', 'current', 'window_started_at', 'views_at_start', 'hours', 'rounds', 'status', 'winner', 'error'];

    protected $appends = ['results'];

    /**
     * Views per hour for each title over its windows.
     */
    public function rates(?array $windows = null): array
    {
        $views = $hours = array_fill(0, count($this->titles), 0);
        foreach ($windows ?? $this->windows ?? [] as $w) {
            $views[$w['title_index']] += $w['views'];
            $hours[$w['title_index']] += max(0.01, $w['hours']);
        }
        $out = [];
        foreach ($views as $i => $v) {
            $out[$i] = $hours[$i] > 0 ? $v / $hours[$i] : 0;
        }

        return $out;
    }

    public function getResultsAttribute(): array
    {
        $rates = $this->rates();
        $windows = collect($this->windows ?? []);

        return array_map(fn ($t, $i) => [
            'title' => $t,
            'views_per_hour' => round($rates[$i], 2),
            'windows' => $windows->where('title_index', $i)->count(),
            'views' => $windows->where('title_index', $i)->sum('views'),
        ], $this->titles, array_keys($this->titles));
    }

    protected function casts(): array
    {
        return [
            'titles' => 'array',
            'windows' => 'array',
            'window_started_at' => 'datetime',
        ];
    }
}
