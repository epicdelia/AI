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

    /** The memory jar: one question a day for both of them (same question all day). */
    public const QUESTIONS = [
        'What made you smile today?', 'What’s a small thing I do that you love?', 'Where should our next trip be?', 'What’s one thing you’re proud of this week?',
        'What song reminds you of us?', 'What was your favourite moment together this month?', 'What’s something new you want us to try?',
        'What do you want to remember about this week?', 'What’s your dream video to make one day?', 'What’s the best meal we’ve had together?',
        'If we had a free Saturday tomorrow, what would we do?', 'What’s something you learned recently?', 'What’s a goal you want me to cheer for?',
        'What’s a memory of us that always makes you laugh?', 'What does a perfect lazy Sunday look like?', 'What are you grateful for today?',
        'What’s something you wish people knew about your work?', 'Which comment from a viewer made your day?', 'What would you tell yourself one year ago?',
        'What’s our song right now?', 'Coffee date or dinner date this week?', 'What’s one thing that would make tomorrow easier?',
        'What’s a little tradition we should start?', 'What’s your favourite photo of us, and why?', 'What are you looking forward to this month?',
        'What’s one thing I could do to support your channel more?', 'What’s a compliment you got recently that stuck?', 'Which place felt most like home to you?',
        'What’s something brave you did lately?', 'If today had a title, what would it be?',
    ];

    public static function jarToday(): array
    {
        $today = CarbonImmutable::now(SocialAnalytics::tz())->toDateString();
        $row = DB::table('memory_jar')->where('asked_on', $today)->first();
        if (! $row) {
            $q = self::QUESTIONS[CarbonImmutable::parse($today)->dayOfYear % count(self::QUESTIONS)];
            DB::table('memory_jar')->insert(['asked_on' => $today, 'question' => $q, 'created_at' => now(), 'updated_at' => now()]);
            $row = DB::table('memory_jar')->where('asked_on', $today)->first();
        }

        return (array) $row;
    }

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
                AgentEvent::log('love', "A letter just unlocked for you: “{$l->title}”");
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
            AgentEvent::log('love', number_format($m['count']).' followers on '.$m['label'].'!', null, null, $m['platform']);
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
