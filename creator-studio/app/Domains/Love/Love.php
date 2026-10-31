<?php

namespace App\Domains\Love;

use App\Domains\Agents\Models\AgentEvent;
use App\Domains\Social\Models\SocialPost;
use App\Domains\Social\Platforms;
use App\Domains\Social\SocialAnalytics;
use App\Models\Setting;
use App\Models\Week;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Love personalisation: special dates with countdowns, "open when…" letters that unlock on a
 * date or one of her milestones, and follower-milestone celebrations. Everything is worked
 * out when asked (no background job), so it's always in step with her real numbers.
 */
class Love
{
    /** Follower counts worth celebrating. */
    public const MILESTONES = [100, 250, 500, 1000, 2000, 2500, 5000, 7500, 10000, 15000, 20000, 25000, 50000, 75000, 100000, 150000, 200000, 250000, 500000, 1000000];

    public static function enabled(): bool
    {
        return Setting::get('love_notes', '1') === '1';
    }

    /** [{label, date, kind, yearly}] */
    public static function dates(): array
    {
        return json_decode((string) Setting::get('love_dates', '[]'), true) ?: [];
    }

    /** Unlock what's due: dates passed, milestones reached, first post, shipping streak. Returns newly unlocked letters. */
    public static function unlockDue(): array
    {
        $tz = SocialAnalytics::tz();
        $today = CarbonImmutable::now($tz)->toDateString();
        $new = [];
        foreach (LoveLetter::whereNull('unlocked_at')->where('unlock', '!=', 'anytime')->get() as $l) {
            $due = match ($l->unlock) {
                'date' => $l->unlock_value && $l->unlock_value <= $today,
                'followers' => (function () use ($l) {
                    [$p, $n] = array_pad(explode(':', (string) $l->unlock_value), 2, 0);

                    return (int) DB::table('follower_snapshots')->where('platform', $p)->max('count') >= (int) $n && (int) $n > 0;
                })(),
                'first_post' => SocialPost::where('status', 'posted')->whereNotIn('source', ['demo', 'synced'])->exists(),
                'streak' => self::streak() >= max(1, (int) $l->unlock_value),
                default => false,
            };
            if ($due) {
                $l->update(['unlocked_at' => now()]);
                AgentEvent::log('love', "💌 A letter just unlocked for you: “{$l->title}”");
                $new[] = $l;
            }
        }

        return $new;
    }

    /** Follower milestones crossed since last time (each celebrated once). */
    public static function newMilestones(): array
    {
        $done = json_decode((string) Setting::get('love_milestones_seen', '[]'), true) ?: [];
        $first = ! Setting::has('love_milestones_seen');
        $out = [];
        foreach (array_keys(Platforms::PLATFORMS) as $p) {
            $max = (int) DB::table('follower_snapshots')->where('platform', $p)->where('source', '!=', 'demo')->max('count');
            foreach (self::MILESTONES as $m) {
                $key = "{$p}:{$m}";
                if ($max >= $m && ! in_array($key, $done, true)) {
                    $done[] = $key;
                    // On the very first check, past milestones are recorded quietly (no flood of old news).
                    if (! $first) {
                        $out[$p] = ['platform' => $p, 'label' => Platforms::PLATFORMS[$p]['label'], 'count' => $m]; // the biggest wins
                    }
                }
            }
        }
        Setting::put('love_milestones_seen', json_encode($done));
        foreach ($out as $m) {
            AgentEvent::log('love', '🎉 '.number_format($m['count']).' followers on '.$m['label'].'!', null, null, $m['platform']);
        }

        return array_values($out);
    }

    private static function streak(): int
    {
        $streak = 0;
        foreach (Week::where('status', '!=', 'pending')->orderByDesc('number')->get() as $w) {
            if ($w->status !== 'shipped') {
                break;
            }
            $streak++;
        }

        return $streak;
    }

    /** Countdowns and "today is…" for special dates, plus days together. */
    public static function calendar(): array
    {
        $tz = SocialAnalytics::tz();
        $today = CarbonImmutable::now($tz)->startOfDay();
        $upcoming = [];
        $todayEvents = [];
        foreach (self::dates() as $d) {
            try {
                $date = CarbonImmutable::parse($d['date'], $tz)->startOfDay();
            } catch (\Throwable) {
                continue;
            }
            $next = $date;
            $years = null;
            if (! empty($d['yearly'])) {
                $next = $date->setYear($today->year);
                if ($next->lt($today)) {
                    $next = $next->addYear();
                }
                $years = $next->year - $date->year;
            }
            if ($next->lt($today)) {
                continue;
            }
            $row = ['label' => $d['label'], 'kind' => $d['kind'] ?? 'other', 'date' => $next->toDateString(), 'days' => (int) $today->diffInDays($next), 'years' => $years];
            if ($row['days'] === 0) {
                $todayEvents[] = $row;
            }
            $upcoming[] = $row;
        }
        usort($upcoming, fn ($a, $b) => $a['days'] <=> $b['days']);
        $since = Setting::get('love_together_since');
        $together = null;
        if ($since) {
            try {
                $together = (int) CarbonImmutable::parse($since, $tz)->startOfDay()->diffInDays($today);
            } catch (\Throwable) {
            }
        }

        return ['upcoming' => array_slice($upcoming, 0, 5), 'today' => $todayEvents, 'together_days' => $together];
    }

    /** Letters as she sees them: titles and unlock hints always, text only once unlocked. */
    public static function letters(): array
    {
        return LoveLetter::orderByRaw('unlocked_at is null')->orderByDesc('unlocked_at')->orderBy('id')->get()->map(fn ($l) => [
            'id' => $l->id, 'title' => $l->title, 'unlock' => $l->unlock, 'unlock_value' => $l->unlock_value,
            'hint' => self::hint($l), 'open' => $l->unlock === 'anytime' || $l->unlocked_at !== null,
            'unlocked_at' => $l->unlocked_at, 'opened_at' => $l->opened_at,
            'body' => $l->opened_at ? $l->body : null,
        ])->all();
    }

    public static function hint(LoveLetter $l): string
    {
        return match ($l->unlock) {
            'anytime' => 'Open it whenever you need it',
            'date' => 'Opens on '.CarbonImmutable::parse($l->unlock_value)->format('j F Y'),
            'followers' => (function () use ($l) {
                [$p, $n] = array_pad(explode(':', (string) $l->unlock_value), 2, 0);

                return 'Opens at '.number_format((int) $n).' followers on '.(Platforms::PLATFORMS[$p]['label'] ?? $p);
            })(),
            'first_post' => 'Opens when you publish your first post from the studio',
            'streak' => 'Opens after a '.(int) $l->unlock_value.'-week shipping streak',
            default => 'Sealed',
        };
    }
}
