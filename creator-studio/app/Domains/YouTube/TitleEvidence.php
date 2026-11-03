<?php

namespace App\Domains\YouTube;

use App\Services\YouTube;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * What her own channel says about a title. Her long-form videos (from the daily numbers in
 * video_stats, at least 7 days old) each get views ÷ her median; a new title is compared with
 * her 5 most similar past titles by simple features (length, a number, a question, "you",
 * a colon or brackets, a CAPS word, "I/my"). Below 8 videos there's no score, only "not enough
 * data yet". Finished title tests add which wording actually won on a live video.
 */
class TitleEvidence
{
    public const MIN_VIDEOS = 8;

    public const NEIGHBOURS = 5;

    /**
     * @return Collection<int, array{title: string, ratio: float, views: int, features: array}>
     */
    public static function history(): Collection
    {
        $latest = DB::table('video_stats')->select('video_id', DB::raw('max(captured_on) as day'))->groupBy('video_id');
        $rows = DB::table('video_stats as v')->joinSub($latest, 'l', fn ($j) => $j->on('v.video_id', '=', 'l.video_id')->on('v.captured_on', '=', 'l.day'))
            ->where('v.age_days', '>=', 7)->whereNotNull('v.title')->get(['v.title', 'v.views']);
        if ($rows->isEmpty()) {
            return collect();
        }
        $median = YouTube::median($rows->pluck('views')->map(fn ($v) => (int)$v)->all()) ?: 1;

        return $rows->map(fn ($r) => [
            'title' => $r->title,
            'views' => (int)$r->views,
            'ratio' => round($r->views / $median, 2),
            'features' => self::features($r->title),
        ])->values();
    }

    public static function features(string $title): array
    {
        $len = mb_strlen($title);

        return [
            'length' => $len < 40 ? 'short' : ($len <= 60 ? 'medium' : 'long'),
            'number' => (bool)preg_match('/\d/', $title),
            'question' => str_contains($title, '?'),
            'you' => (bool)preg_match('/\byou(r)?\b/i', $title),
            'personal' => (bool)preg_match('/\b(I|I\'m|my|me)\b/', $title),
            'colon' => (bool)preg_match('/[:|(\[]/', $title),
            'caps' => (bool)preg_match('/\b[A-Z]{3,}\b/', $title),
        ];
    }

    /**
     * Score one title against her history.
     *
     * @return array{basis: string, videos: int, needed: int, predicted_vs_median: ?float, label: string, similar: list<array{title: string, ratio: float}>}
     */
    public static function score(string $title, ?Collection $history = null): array
    {
        $history ??= self::history();
        $out = [
            'basis' => 'not enough data',
            'videos' => $history->count(),
            'needed' => self::MIN_VIDEOS,
            'predicted_vs_median' => null,
            'label' => 'Not enough of your videos yet',
            'similar' => [],
        ];
        if ($history->count() < self::MIN_VIDEOS) {
            return $out;
        }
        $f = self::features($title);
        $near = $history->map(fn ($h) => $h + [
            'match' => count(array_intersect_assoc($f, $h['features'])),
        ])
            ->sortByDesc(fn ($h) => [$h['match'], $h['ratio']])->take(self::NEIGHBOURS)->values();
        $predicted = round($near->avg('ratio'), 2);

        return [
            'basis' => 'your videos',
            'videos' => $history->count(),
            'needed' => self::MIN_VIDEOS,
            'predicted_vs_median' => $predicted,
            'label' => match (true) {
                $predicted >= 1.5 => 'Like your best titles',
                $predicted >= 1.1 => 'Above your usual',
                $predicted >= 0.9 => 'Around your usual',
                default => 'Like your weaker titles',
            },
            'similar' => $near->map(fn ($h) => [
                'title' => $h['title'],
                'ratio' => $h['ratio'],
            ])->all(),
        ];
    }

    /**
     * Finished live title tests: the wording that won and what it beat, for the title prompt.
     */
    public static function testWinners(int $limit = 5): array
    {
        return TitleTest::where('status', 'done')->whereNotNull('winner')->latest('updated_at')->take($limit)->get()
            ->map(function (TitleTest $t) {
                $r = collect($t->results);
                $win = $r[$t->winner] ?? null;

                return $win ? [
                    'winner' => $win['title'],
                    'winner_views_per_hour' => $win['views_per_hour'],
                    'beat' => $r->except($t->winner)->map(fn ($x) => [
                        'title' => $x['title'],
                        'views_per_hour' => $x['views_per_hour'],
                    ])->values()->all(),
                ] : null;
            })->filter()->values()->all();
    }
}
