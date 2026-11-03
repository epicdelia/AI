<?php

namespace App\Domains\Social;

use App\Domains\Agents\Models\AgentEvent;
use App\Domains\Deals\FollowUps;
use App\Domains\Engagement\Models\EngagementItem;
use App\Domains\Social\Models\SocialPost;
use App\Domains\Workflows\Models\WorkflowRun;
use App\Models\Deal;
use App\Models\Setting;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * The dashboard's "your day" card, and follower goals with a forecast. No AI: it's all from
 * her own data, so it's instant and always true.
 */
class DailyBrief
{
    public static function build(): array
    {
        $tz = SocialAnalytics::tz();
        $now = CarbonImmutable::now($tz);
        $dayStart = $now->startOfDay();
        $weekStart = $now->startOfWeek();

        $due = SocialPost::whereIn('status', ['scheduled'])->whereBetween('scheduled_for', [$dayStart->utc(), $dayStart->addDay()->utc()])->orderBy('scheduled_for')->get();
        $posted24 = SocialPost::where('status', 'posted')->where('posted_at', '>=', now()->subDay())->where('source', '!=', 'demo')->get();
        $top = SocialPost::where('status', 'posted')->where('posted_at', '>=', now()->subDays(7))->where('source', '!=', 'demo')->get()
            ->filter(fn ($p) => $p->engagement !== null)->sortByDesc(fn ($p) => $p->engagement['total'])->first();

        // Cadence: posts this week vs her weekly target, with today's best slot when she's behind.
        $cadence = [];
        foreach (SocialScheduler::cadence() as $p => $target) {
            if ($target <= 0) {
                continue;
            }
            $done = SocialPost::where('platform', $p)->where(fn ($q) => $q->where(fn ($q) => $q->where('status', 'posted')->where('posted_at', '>=', $weekStart->utc()))
                ->orWhere(fn ($q) => $q->where('status', 'scheduled')->whereBetween('scheduled_for', [$weekStart->utc(), $weekStart->addWeek()->utc()])))->count();
            $row = [
                'platform' => $p,
                'label' => Platforms::PLATFORMS[$p]['label'],
                'done' => $done,
                'target' => $target,
            ];
            if ($done < $target) {
                $slot = SocialScheduler::nextSlot($p);
                $row['next_slot'] = $slot['at']->toIso8601String();
                $row['next_reason'] = $slot['source'] === 'your data' ? 'one of your best slots' : 'a starter time';
            }
            $cadence[] = $row;
        }

        $followers = [];
        foreach (array_keys(Platforms::PLATFORMS) as $p) {
            $latest = DB::table('follower_snapshots')->where('platform', $p)->orderByDesc('recorded_on')->first();
            if (!$latest) {
                continue;
            }
            $prev = DB::table('follower_snapshots')->where('platform', $p)->where('recorded_on', '<', $latest->recorded_on)
                ->where('recorded_on', '>=', CarbonImmutable::parse($latest->recorded_on)->subDays(8)->toDateString())->orderBy('recorded_on')->first();
            $followers[] = [
                'platform' => $p,
                'count' => (int)$latest->count,
                'change_7d' => $prev ? (int)$latest->count - (int)$prev->count : null,
            ];
        }

        $hour = $now->hour;

        return [
            'greeting' => $hour < 12 ? 'Good morning' : ($hour < 18 ? 'Good afternoon' : 'Good evening'),
            'date' => $now->toIso8601String(),
            'due_today' => $due->map(fn ($p) => [
                'id' => $p->id,
                'platform' => $p->platform,
                'title' => mb_substr($p->title ?: (string)$p->body, 0, 80),
                'at' => $p->scheduled_for->toIso8601String(),
                'auto' => $p->approval === 'approved' && ($p->social_agent_id || $p->source === 'workflow') && in_array($p->platform, ['linkedin', 'threads', 'x'], true),
            ])->values(),
            'approvals' => SocialPost::where('approval', 'pending')->count(),
            'inbox' => EngagementItem::whereIn('status', ['new', 'drafted', 'failed'])->where('intent', '!=', 'spam')->count(),
            'leads' => EngagementItem::whereIn('status', ['new', 'drafted'])->where('intent', 'lead')->count(),
            'published_24h' => $posted24->count(),
            'top_post' => $top ? [
                'id' => $top->id,
                'platform' => $top->platform,
                'title' => mb_substr($top->title ?: str_replace("\n", ' ', (string)$top->body), 0, 90),
                'engagements' => $top->engagement['total'],
                'rate' => $top->engagement['rate'],
            ] : null,
            'followers' => $followers,
            'cadence' => $cadence,
            'goals' => self::goals(),
            'failed_runs' => WorkflowRun::where('status', 'failed')->where('dry_run', false)->where('created_at', '>=', now()->subDay())->count(),
            'follow_ups' => rescue(fn () => FollowUps::list(3), [], false),
            'follow_ups_total' => rescue(fn () => FollowUps::due()->count(), 0, false),
            'meetings_today' => Deal::whereNotNull('meeting_at')->whereBetween('meeting_at', [$dayStart->utc(), $dayStart->addDay()->utc()])->orderBy('meeting_at')
                ->get(['id', 'brand_name', 'meeting_at'])->map(fn ($d) => [
                    'id' => $d->id,
                    'brand_name' => $d->brand_name,
                    'at' => $d->meeting_at->toIso8601String(),
                ])->values(),
            'agent_news' => AgentEvent::whereNull('read_at')->whereIn('type', ['published', 'learned', 'failed'])->latest()->limit(3)->pluck('message'),
        ];
    }

    // ------------------------------------------------------------ goals

    /**
     * Saved goals: [platform => ['target' => int, 'by' => 'Y-m-d']]
     */
    public static function savedGoals(): array
    {
        return json_decode((string)Setting::get('growth_goals', '{}'), true) ?: [];
    }

    public static function saveGoals(array $goals): void
    {
        Setting::put('growth_goals', json_encode($goals));
    }

    /**
     * Each goal with progress and a forecast from her follower trend (least squares over 60 days).
     */
    public static function goals(): array
    {
        $out = [];
        foreach (self::savedGoals() as $p => $g) {
            if (!isset(Platforms::PLATFORMS[$p])) {
                continue;
            }
            $snaps = DB::table('follower_snapshots')->where('platform', $p)->where('recorded_on', '>=', now()->subDays(60)->toDateString())->orderBy('recorded_on')->get();
            $current = $snaps->last()?->count;
            $by = CarbonImmutable::parse($g['by']);
            $row = [
                'platform' => $p,
                'label' => Platforms::PLATFORMS[$p]['label'],
                'target' => (int)$g['target'],
                'by' => $by->toDateString(),
                'current' => $current !== null ? (int)$current : null,
                'per_day' => null,
                'needed_per_day' => null,
                'eta' => null,
                'status' => 'no_data',
            ];
            if ($current === null) {
                $out[] = $row;

                continue;
            }
            $daysLeft = max(1, now()->startOfDay()->diffInDays($by, false));
            $row['needed_per_day'] = round(max(0, ($g['target'] - $current) / $daysLeft), 1);
            if ($current >= $g['target']) {
                $row['status'] = 'reached';
            } elseif ($snaps->count() >= 2 && ($span = CarbonImmutable::parse($snaps->first()->recorded_on)->diffInDays(CarbonImmutable::parse($snaps->last()->recorded_on))) >= 3) {
                $t0 = CarbonImmutable::parse($snaps->first()->recorded_on);
                $xs = $snaps->map(fn ($s) => $t0->diffInDays(CarbonImmutable::parse($s->recorded_on)))->all();
                $ys = $snaps->pluck('count')->map(fn ($v) => (float)$v)->all();
                $n = count($xs);
                $mx = array_sum($xs) / $n;
                $my = array_sum($ys) / $n;
                $den = array_sum(array_map(fn ($x) => ($x - $mx) ** 2, $xs)) ?: 1;
                $slope = array_sum(array_map(fn ($x, $y) => ($x - $mx) * ($y - $my), $xs, $ys)) / $den;
                $row['per_day'] = round($slope, 1);
                if ($slope > 0) {
                    $row['eta'] = now()->addDays((int)ceil(($g['target'] - $current) / $slope))->toDateString();
                    $row['status'] = $row['eta'] <= $row['by'] ? 'on_track' : 'behind';
                } else {
                    $row['status'] = 'behind';
                }
            } else {
                $row['status'] = 'too_early'; // needs a few days of follower numbers
            }
            $out[] = $row;
        }

        return $out;
    }
}
