<?php

namespace App\Domains\Community;

use App\Domains\Community\Models\CommunityItem;
use App\Domains\Community\Models\CommunityMembership;
use App\Domains\Community\Models\CommunityPoint;
use App\Enums\Role;
use App\Http\Controllers\Api\AuthController;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * The Community Hub's rules in one place: points and levels, the paid Pro tier, when to show
 * the "work with me" card, and the emails (answered, digest).
 */
class Community
{
    /**
     * Points per action. Votes given earn nothing (no farming); votes received do.
     */
    public const POINTS = [
        'asked' => 5,
        'upvoted' => 2,
        'answered' => 10,
        'showcase_approved' => 15,
        'featured' => 25,
        'lesson' => 3,
        'referred' => 10,
    ];

    /**
     * Quiet this many days → one "here's what you missed" email (at most every 60 days).
     */
    public const WINBACK_DAYS = 21;

    /**
     * Asking earns points for at most this many questions a day.
     */
    public const DAILY_ASK_POINTS = 3;

    /**
     * [min points, level name], lowest first.
     */
    public const LEVELS = [
        [0, 'Newcomer'],
        [20, 'Regular'],
        [60, 'Contributor'],
        [150, 'Insider'],
        [400, 'Legend'],
    ];

    public static function points(User $user): int
    {
        return (int)CommunityPoint::where('user_id', $user->id)->sum('points');
    }

    /**
     * @return array{level: int, name: string, points: int, next_at: ?int, next_name: ?string}
     */
    public static function level(int $points): array
    {
        $idx = 0;
        foreach (self::LEVELS as $i => [$min]) {
            if ($points >= $min) {
                $idx = $i;
            }
        }
        $next = self::LEVELS[$idx + 1] ?? null;

        return [
            'level' => $idx + 1,
            'name' => self::LEVELS[$idx][1],
            'points' => $points,
            'next_at' => $next[0] ?? null,
            'next_name' => $next[1] ?? null,
        ];
    }

    public static function award(?User $user, string $reason, ?CommunityItem $item = null): void
    {
        if (!$user || !isset(self::POINTS[$reason]) || $user->isAdmin()) {
            return;
        }
        if ($reason === 'asked') {
            $today = CommunityPoint::where('user_id', $user->id)->where('reason', 'asked')
                ->where('created_at', '>=', now()->startOfDay())->count();
            if ($today >= self::DAILY_ASK_POINTS) {
                return;
            }
        }
        CommunityPoint::create([
            'user_id' => $user->id,
            'points' => self::POINTS[$reason],
            'reason' => $reason,
            'item_id' => $item?->id,
        ]);
    }

    /**
     * Top members by points (her and staff left out).
     *
     * @return array<int, array{user_id: int, name: string, points: int, level: string}>
     */
    public static function leaderboard(int $limit = 10, ?int $days = null): array
    {
        return CommunityPoint::query()
            ->join('users', 'users.id', '=', 'community_points.user_id')
            ->whereNotIn('users.role', [Role::Admin->value, Role::Staff->value])
            ->when($days, fn ($q) => $q->where('community_points.created_at', '>=', now()->subDays($days)))
            ->groupBy('community_points.user_id', 'users.name')
            ->orderByDesc(DB::raw('SUM(community_points.points)'))
            ->limit($limit)
            ->get([
                'community_points.user_id',
                'users.name',
                DB::raw('SUM(community_points.points) as total'),
            ])
            ->map(fn ($r) => [
                'user_id' => (int)$r->user_id,
                'name' => $r->name,
                'points' => (int)$r->total,
                'level' => self::level((int)$r->total)['name'],
            ])->all();
    }

    // ---- Pro tier -------------------------------------------------------------------------

    /**
     * Her Pro tier settings. Off until she turns it on and sets a price.
     *
     * @return array{enabled: bool, name: string, amount: float, currency: string, interval: string, pitch: string, perks: array<int, string>}
     */
    public static function proConfig(): array
    {
        $saved = json_decode((string)Setting::get('community_pro', ''), true) ?: [];

        return [
            'enabled' => (bool)($saved['enabled'] ?? false),
            'name' => (string)($saved['name'] ?? 'Pro'),
            'amount' => (float)($saved['amount'] ?? 0),
            'currency' => strtolower((string)($saved['currency'] ?? 'usd')),
            'interval' => in_array($saved['interval'] ?? 'month', ['month', 'year'], true) ? ($saved['interval'] ?? 'month') : 'month',
            'pitch' => (string)($saved['pitch'] ?? ''),
            'perks' => array_values(array_filter(array_map('strval', (array)($saved['perks'] ?? [])))),
        ];
    }

    public static function proAvailable(): bool
    {
        $c = self::proConfig();

        return $c['enabled'] && $c['amount'] > 0;
    }

    public static function membership(User $user): ?CommunityMembership
    {
        return CommunityMembership::where('user_id', $user->id)->first();
    }

    /**
     * She and her staff see everything; members need an active membership.
     */
    public static function isPro(User $user): bool
    {
        if ($user->hasAbility(Role::STUDIO)) {
            return true;
        }

        return (bool)self::membership($user)?->isActive();
    }

    // ---- "Work with me" -------------------------------------------------------------------

    /**
     * @return array{enabled: bool, min_points: int, headline: string, body: string, cta_label: string, cta_url: string}
     */
    public static function hireConfig(): array
    {
        $saved = json_decode((string)Setting::get('community_hire', ''), true) ?: [];

        return [
            'enabled' => (bool)($saved['enabled'] ?? true),
            'min_points' => max(0, (int)($saved['min_points'] ?? 20)),
            'headline' => (string)($saved['headline'] ?? 'Want help with your own project?'),
            'body' => (string)($saved['body'] ?? 'You’ve been an active part of the community. If you’d like hands-on help, book an intro call.'),
            'cta_label' => (string)($saved['cta_label'] ?? 'Book an intro call'),
            'cta_url' => (string)($saved['cta_url'] ?? '/#book'),
        ];
    }

    /**
     * The card for engaged members who aren't clients yet.
     */
    public static function hireCard(User $user, int $points): ?array
    {
        $c = self::hireConfig();
        if (!$c['enabled'] || $user->role() !== Role::Member || $points < $c['min_points']) {
            return null;
        }

        return [
            'headline' => $c['headline'],
            'body' => $c['body'],
            'cta_label' => $c['cta_label'],
            'cta_url' => $c['cta_url'],
        ];
    }

    // ---- Email --------------------------------------------------------------------------

    public static function notifyAnswered(CommunityItem $q): void
    {
        $u = $q->user;
        if (!$u || !($u->notify_email ?? true)) {
            return;
        }
        AuthController::mail(
            $u->email,
            'Your question was answered',
            "Hi {$u->name},\n\nYour question in the community was answered:\n\n“{$q->title}”\n\n{$q->answer}\n\nSee it in the Community Hub: " . url('/community') . "\n\nYou can turn these emails off under Account & security."
        );
    }

    /**
     * Members (and clients) quiet for 21+ days get the three most-voted things they missed.
     * Returns emails sent.
     */
    public static function sendWinbacks(): int
    {
        $top = CommunityItem::published()->whereIn('type', ['dispatch', 'question', 'showcase', 'kit'])
            ->where('published_at', '>=', now()->subDays(self::WINBACK_DAYS + 9))
            ->orderByDesc('upvotes')->latest('published_at')->take(3)->get();
        if ($top->isEmpty()) {
            return 0;
        }
        $lines = $top->map(fn ($i) => '• ' . ($i->type === 'question' ? 'Asked: ' : '') . $i->title . ($i->tier === 'pro' ? ' (Pro)' : ''))->implode("\n");
        $sent = 0;
        User::where('role', Role::Member->value)->whereNull('disabled_at')->where('notify_email', true)->where('community_digest', true)
            ->where(fn ($q) => $q->where('community_seen_at', '<', now()->subDays(self::WINBACK_DAYS))
                ->orWhere(fn ($q) => $q->whereNull('community_seen_at')->where('created_at', '<', now()->subDays(self::WINBACK_DAYS))))
            ->where(fn ($q) => $q->whereNull('winback_sent_at')->orWhere('winback_sent_at', '<', now()->subDays(60)))
            ->chunkById(200, function ($users) use ($lines, &$sent) {
                foreach ($users as $u) {
                    if (AuthController::mail($u->email, 'What you missed in the community', "Hi {$u->name},\n\nHere's what people liked most lately:\n\n{$lines}\n\nCatch up: " . url('/community') . "\n\nTurn these emails off under Account & security.")) {
                        $sent++;
                    }
                    $u->forceFill(['winback_sent_at' => now()])->save();
                }
            });

        return $sent;
    }

    /**
     * The weekly digest: what's new since the last one. Returns how many emails went out.
     */
    public static function sendDigests(): int
    {
        $since = now()->subDays(7);
        $new = CommunityItem::published()->whereIn('type', ['kit', 'blueprint', 'dispatch', 'perk'])
            ->where('published_at', '>=', $since)->latest('published_at')->take(8)->get();
        $answered = CommunityItem::published()->where('type', 'question')
            ->where('answered_at', '>=', $since)->latest('answered_at')->take(5)->get();
        if ($new->isEmpty() && $answered->isEmpty()) {
            return 0;
        }

        $lines = [];
        foreach ($new as $i) {
            $lines[] = '• ' . ucfirst($i->type) . ': ' . $i->title . ($i->tier === 'pro' ? ' (Pro)' : '');
        }
        foreach ($answered as $i) {
            $lines[] = '• Answered: ' . $i->title;
        }
        $body = "New in the community this week:\n\n" . implode("\n", $lines) . "\n\nOpen the hub: " . url('/community')
            . "\n\nYou get this because you’re a community member. Turn it off under Account & security.";

        $sent = 0;
        User::whereIn('role', [Role::Member->value, Role::Client->value])
            ->where('community_digest', true)->where('notify_email', true)
            ->where(fn ($q) => $q->whereNull('community_digest_at')->orWhere('community_digest_at', '<', now()->subDays(6)))
            ->whereNull('disabled_at')
            ->chunkById(200, function ($users) use ($body, &$sent) {
                foreach ($users as $u) {
                    if (AuthController::mail($u->email, 'This week in the community', "Hi {$u->name},\n\n" . $body)) {
                        $sent++;
                    }
                    $u->forceFill(['community_digest_at' => now()])->save();
                }
            });

        return $sent;
    }
}
