<?php

namespace App\Domains\Social\Connect;

use App\Domains\Social\Models\SocialAccount;
use App\Domains\Social\Models\SocialPost;
use App\Domains\Social\Platforms;
use App\Domains\Social\SocialDashboard;
use App\Domains\Workflows\Engine;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Throwable;

/**
 * Her connected accounts: start a sign-in, finish it, keep the token fresh, and pull follower
 * counts and post numbers into the studio. The state value and PKCE verifier live in the cache
 * for 10 minutes and are tied to the signed-in user, so a forged or replayed return is refused.
 */
class Connections
{
    public const PROVIDERS = [
        'youtube' => YouTubeProvider::class,
        'instagram' => InstagramProvider::class,
        'tiktok' => TikTokProvider::class,
        'threads' => ThreadsProvider::class,
        'linkedin' => LinkedInProvider::class,
        'x' => XProvider::class,
    ];

    /** Sync on open when the last sync is older than this. */
    public const STALE_MINUTES = 60;

    public static function provider(string $platform): Provider
    {
        $class = self::PROVIDERS[$platform] ?? throw new ConnectException('Unknown network.');

        return app($class);
    }

    /** The URL to send her to, after remembering state + verifier for this user. */
    public static function start(string $platform, int $userId): string
    {
        $p = self::provider($platform);
        if (! $p->configured()) {
            throw new ConnectException('Add the '.$p::label().' app keys first (Set up).');
        }
        if ($p->needsHttps() && ! str_starts_with((string) config('app.url'), 'https://')) {
            throw new ConnectException($p::label().' only signs in to an https:// address. Set up the secure address first (see the top of this page).');
        }
        $state = Str::random(40);
        $verifier = Str::random(64);
        Cache::put("oauth:{$state}", ['platform' => $platform, 'verifier' => $verifier, 'user' => $userId], now()->addMinutes(10));

        return $p->authorizeUrl($state, self::challenge($verifier));
    }

    public static function challenge(string $verifier): string
    {
        return rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
    }

    /** Finish the sign-in: check state, swap the code for tokens, read the profile, save. */
    public static function finish(string $platform, ?string $state, ?string $code, int $userId): SocialAccount
    {
        $saved = $state ? Cache::pull("oauth:{$state}") : null;
        if (! $saved || $saved['platform'] !== $platform || (int) $saved['user'] !== $userId) {
            throw new ConnectException('That sign-in link expired or didn’t start here. Press Connect again.');
        }
        if (! $code) {
            throw new ConnectException('No sign-in code came back.');
        }
        $p = self::provider($platform);
        $t = $p->exchange($code, $saved['verifier']);
        if ($t['access_token'] === '') {
            throw new ConnectException($p::label().' didn’t return a sign-in token.');
        }
        $profile = $p->profile($t['access_token']);

        $account = SocialAccount::updateOrCreate(['platform' => $platform], self::tokenFields($t) + [
            'external_id' => $profile['external_id'] ?? null, 'handle' => $profile['handle'] ?? null,
            'name' => $profile['name'] ?? null, 'avatar_url' => $profile['avatar_url'] ?? null,
            'status' => 'connected', 'last_error' => null,
            'meta' => array_filter(['followers' => $profile['followers'] ?? null]),
        ]);
        if (isset($profile['followers'])) {
            SocialDashboard::recordFollowers([$platform => $profile['followers']], 'api');
        }

        return $account;
    }

    private static function tokenFields(array $t): array
    {
        return array_filter([
            'access_token' => $t['access_token'],
            'refresh_token' => $t['refresh_token'] ?? null,
            'expires_at' => $t['expires_in'] ? now()->addSeconds($t['expires_in']) : null,
            'refresh_expires_at' => $t['refresh_expires_in'] ? now()->addSeconds($t['refresh_expires_in']) : null,
            'scopes' => $t['scopes'] ?: null,
        ], fn ($v) => $v !== null) + ['expires_at' => $t['expires_in'] ? now()->addSeconds($t['expires_in']) : null];
    }

    /** A working access token, renewed first when it's about to lapse. Marks the account "expired" when it can't be. */
    public static function token(SocialAccount $account): string
    {
        $p = self::provider($account->platform);
        if (! $account->expiresSoon($p->renewWindow())) {
            return $account->access_token;
        }
        $expired = $account->expires_at && $account->expires_at->isPast();

        return Cache::lock("oauth-refresh:{$account->platform}", 30)->block(20, function () use ($account, $p, $expired) {
            $account->refresh();
            if (! $account->expiresSoon($p->renewWindow())) {
                return $account->access_token; // renewed by someone else meanwhile
            }
            try {
                $account->update(self::tokenFields($p->refresh($account)) + ['status' => 'connected', 'last_error' => null]);
            } catch (ConnectException $e) {
                // Still valid for now (inside the renew window): keep using it and try again later.
                if (! $expired && ! $e->reconnect) {
                    return $account->access_token;
                }
                $account->update(['status' => 'expired', 'last_error' => $e->getMessage()]);
                throw $e;
            }

            return $account->access_token;
        });
    }

    /** Pull numbers for one account. Returns a one-line summary. */
    public static function sync(SocialAccount $account): string
    {
        $p = self::provider($account->platform);
        try {
            $data = $p->sync($account, self::token($account));
        } catch (ConnectException $e) {
            $account->update(['status' => $e->reconnect ? 'expired' : $account->status, 'last_error' => $e->getMessage()]);
            throw $e;
        }

        if (isset($data['followers'])) {
            SocialDashboard::recordFollowers([$account->platform => $data['followers']], 'api');
        }
        [$updated, $added] = self::savePosts($account->platform, $data['posts'] ?? []);
        try {
            Engine::checkPerformance($account->platform); // "Post takes off" workflows
        } catch (Throwable $e) {
            report($e);
        }
        $account->update([
            'status' => 'connected', 'last_error' => null, 'last_synced_at' => now(),
            'meta' => array_filter(($data['meta'] ?? []) + ['followers' => $data['followers'] ?? null], fn ($v) => $v !== null),
        ]);

        return $p::label().': '.(($data['followers'] ?? null) !== null ? number_format($data['followers']).' followers, ' : '')
            ."{$updated} post(s) updated, {$added} added.";
    }

    /** Sync every connected account (or only stale ones). Returns [platform => summary or error]. */
    public static function syncAll(bool $staleOnly = false): array
    {
        $out = [];
        $accounts = SocialAccount::where('status', '!=', 'expired')
            ->when($staleOnly, fn ($q) => $q->where(fn ($q) => $q->whereNull('last_synced_at')->orWhere('last_synced_at', '<', now()->subMinutes(self::STALE_MINUTES))))
            ->get();
        foreach ($accounts as $a) {
            // Paid X reads never run from the "sync on open" path unless she set a limit.
            if ($a->platform === 'x' && XProvider::cap() === 0) {
                continue;
            }
            try {
                $out[$a->platform] = ['ok' => true, 'message' => self::sync($a)];
            } catch (Throwable $e) {
                $out[$a->platform] = ['ok' => false, 'message' => $e->getMessage()];
            }
        }

        return $out;
    }

    /**
     * Upsert pulled posts. Matched by the network's id, then by link; unmatched posts become
     * posted posts (source "synced") so Analytics sees them without typing. "match_only" rows
     * (YouTube videos) only update posts she already has.
     *
     * @return array{0: int, 1: int} [updated, added]
     */
    public static function savePosts(string $platform, array $posts): array
    {
        $updated = $added = 0;
        foreach ($posts as $row) {
            $id = (string) ($row['external_id'] ?? '');
            if ($id === '') {
                continue;
            }
            $post = SocialPost::where('platform', $platform)->where('external_id', $id)->first()
                ?? (! empty($row['url']) ? SocialPost::where('platform', $platform)->whereNull('external_id')->where('url', $row['url'])->first() : null)
                ?? (! empty($row['match_only']) ? SocialPost::where('platform', $platform)->whereNull('external_id')->where('url', 'like', '%'.$id.'%')->first() : null);
            $metrics = array_map('intval', $row['metrics'] ?? []);

            if ($post) {
                $post->fill(['external_id' => $id, 'metrics' => array_merge($post->metrics ?? [], $metrics)]);
                if (! empty($row['thumb'])) {
                    $post->thumbnail_url = $row['thumb'];
                }
                if (! $post->url && ! empty($row['url'])) {
                    $post->url = $row['url'];
                }
                if ($post->status !== 'posted') {
                    $post->status = 'posted';
                    $post->posted_at ??= self::date($row['posted_at'] ?? null);
                }
                $post->save();
                $updated++;
            } elseif (empty($row['match_only'])) {
                $format = $row['format'] ?? 'post';
                SocialPost::create([
                    'platform' => $platform, 'format' => Platforms::valid($platform, $format) ? $format : array_key_first(Platforms::FORMATS[$platform]),
                    'body' => mb_substr((string) ($row['text'] ?? ''), 0, 10000), 'status' => 'posted',
                    'posted_at' => self::date($row['posted_at'] ?? null) ?? now(), 'url' => $row['url'] ?? null,
                    'external_id' => $id, 'source' => 'synced', 'metrics' => $metrics, 'thumbnail_url' => $row['thumb'] ?? null,
                ]);
                $added++;
            }
        }

        return [$updated, $added];
    }

    private static function date(?string $v): ?CarbonImmutable
    {
        try {
            return $v ? CarbonImmutable::parse($v)->utc() : null;
        } catch (Throwable) {
            return null;
        }
    }

    public static function disconnect(string $platform): void
    {
        $account = SocialAccount::where('platform', $platform)->first();
        if (! $account) {
            return;
        }
        try {
            self::provider($platform)->revoke($account); // best effort: the local copy goes either way
        } catch (Throwable) {
        }
        $account->delete();
    }

    /** Everything the Connections page shows. Never includes tokens or secrets. */
    public static function catalog(): array
    {
        $accounts = SocialAccount::all()->keyBy('platform');
        $https = str_starts_with((string) config('app.url'), 'https://');
        $out = [];
        foreach (array_keys(self::PROVIDERS) as $key) {
            $p = self::provider($key);
            $a = $accounts[$key] ?? null;
            $out[] = [
                'platform' => $key, 'label' => $p::label(), 'configured' => $p->configured(),
                'client_id' => $p->clientId(), 'has_secret' => $p->clientSecret() !== '',
                'needs_https' => $p->needsHttps(), 'blocked' => $p->needsHttps() && ! $https,
                'redirect_uri' => $p->redirectUri(), 'info' => $p->info(),
                'account' => $a ? [
                    'handle' => $a->handle, 'name' => $a->name, 'avatar_url' => $a->avatar_url, 'status' => $a->status,
                    'expires_at' => $a->refresh_token ? ($a->refresh_expires_at?->toIso8601String()) : $a->expires_at?->toIso8601String(),
                    'renews' => (bool) $a->refresh_token || in_array($key, ['instagram', 'threads'], true),
                    'last_synced_at' => $a->last_synced_at?->toIso8601String(), 'last_error' => $a->last_error,
                    'scopes' => $a->scopes ?? [], 'meta' => $a->meta ?? [], 'connected_at' => $a->created_at?->toIso8601String(),
                ] : null,
            ] + ($key === 'x' ? ['x' => ['limit' => XProvider::cap(), 'used' => XProvider::used()]] : []);
        }

        return $out;
    }
}
