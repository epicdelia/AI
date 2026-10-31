<?php

namespace App\Domains\Landing;

use Illuminate\Support\Facades\DB;

/**
 * Her audience numbers, one source for the landing page, media kit, rate card and AI prompts.
 * Synced follower counts win (Connections sync, studio:stats, or Analytics → Update); until a
 * network has one, its public figure from her site is used.
 */
class Audience
{
    /** Public figures per network, used until the studio has a synced count for it. */
    public const FALLBACK = [
        'youtube' => 22000,
        'instagram' => 384000,
        'linkedin' => 25000,
        'tiktok' => 45000,
        'x' => 28000,
        'threads' => 15000,
    ];

    /** The headline her site states ("472K+"), kept until her biggest network is synced. */
    public const CLAIM = '472K+';

    /** @return array<string, array{count: int, live: bool}> */
    public static function counts(): array
    {
        // Latest real snapshot per network; "Show demo posts" sample followers never reach the public page.
        $synced = rescue(fn () => DB::table('follower_snapshots')->where('source', '!=', 'demo')->where('count', '>', 0)
            ->orderBy('recorded_on')->get(['platform', 'count'])->pluck('count', 'platform')->all(), [], false);
        $out = [];
        foreach (self::FALLBACK as $platform => $fallback) {
            $live = isset($synced[$platform]);
            $out[$platform] = ['count' => $live ? (int) $synced[$platform] : $fallback, 'live' => $live];
        }

        return $out;
    }

    public static function count(string $platform): int
    {
        return self::counts()[$platform]['count'] ?? 0;
    }

    public static function total(): int
    {
        return array_sum(array_column(self::counts(), 'count'));
    }

    /** "472K+" until Instagram (most of her audience) is synced, then the real total. */
    public static function headline(): string
    {
        $counts = self::counts();

        return $counts['instagram']['live'] ? self::compact(self::total()).'+' : self::CLAIM;
    }

    public static function compact(int $n): string
    {
        return match (true) {
            $n >= 1_000_000 => rtrim(rtrim(number_format($n / 1_000_000, 1), '0'), '.').'M',
            $n >= 1_000 => round($n / 1_000).'K',
            default => (string) $n,
        };
    }
}
