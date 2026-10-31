<?php

namespace App\Domains\Social\Connect;

use App\Domains\Social\Models\SocialAccount;
use Carbon\CarbonImmutable;

/**
 * Google sign-in for her own channel: subscribers, and per-video views, watch time and
 * retention from YouTube Analytics (which an API key alone can't read).
 */
class YouTubeProvider extends Provider
{
    public const KEY = 'youtube';

    private const SCOPES = ['https://www.googleapis.com/auth/youtube.force-ssl', 'https://www.googleapis.com/auth/yt-analytics.readonly'];

    public function info(): array
    {
        return [
            'id_label' => 'Client ID',
            'secret_label' => 'Client secret',
            'console' => 'https://console.cloud.google.com/apis/credentials',
            'unlocks' => ['Subscriber count, every day', 'Views, watch time and average % viewed per video (last 28 days)', 'Numbers for Shorts you drafted here', 'New comments in your Inbox, answered from the studio'],
            'limits' => 'Free. Keep the Google app “In production” (unverified is fine for personal use); in “Testing” Google signs you out every 7 days.',
            'steps' => [
                'Open Google Cloud Console and create a project (any name).',
                'APIs & Services → Library: enable “YouTube Data API v3” and “YouTube Analytics API”.',
                'OAuth consent screen: External, add your Google account as a test user, then press “Publish app” so sign-ins don’t expire after 7 days.',
                'Credentials → Create credentials → OAuth client ID → Web application.',
                'Under “Authorised redirect URIs” paste the address below, then copy the Client ID and Client secret here.',
                'Optional, for “Login with Google” on the sign-in page: add a second redirect URI, '.rtrim((string) config('app.url'), '/').'/auth/google/callback',
            ],
        ];
    }

    public function authorizeUrl(string $state, string $challenge): string
    {
        return 'https://accounts.google.com/o/oauth2/v2/auth?'.http_build_query([
            'client_id' => $this->clientId(), 'redirect_uri' => $this->redirectUri(), 'response_type' => 'code',
            'scope' => implode(' ', self::SCOPES), 'access_type' => 'offline', 'prompt' => 'consent',
            'include_granted_scopes' => 'true', 'state' => $state, 'code_challenge' => $challenge, 'code_challenge_method' => 'S256',
        ]);
    }

    public function exchange(string $code, string $verifier): array
    {
        return $this->token($this->json($this->http()->asForm()->post('https://oauth2.googleapis.com/token', [
            'code' => $code, 'client_id' => $this->clientId(), 'client_secret' => $this->clientSecret(),
            'redirect_uri' => $this->redirectUri(), 'grant_type' => 'authorization_code', 'code_verifier' => $verifier,
        ]), 'finish signing in', true));
    }

    public function refresh(SocialAccount $account): array
    {
        $t = $this->token($this->json($this->http()->asForm()->post('https://oauth2.googleapis.com/token', [
            'client_id' => $this->clientId(), 'client_secret' => $this->clientSecret(),
            'refresh_token' => $account->refresh_token, 'grant_type' => 'refresh_token',
        ]), 'renew the sign-in', true));
        $t['refresh_token'] ??= $account->refresh_token; // Google keeps the old refresh token

        return $t;
    }

    public function profile(string $token): array
    {
        $j = $this->json($this->http()->withToken($token)->get('https://www.googleapis.com/youtube/v3/channels', [
            'part' => 'snippet,statistics', 'mine' => 'true',
        ]), 'read your channel');
        $c = $j['items'][0] ?? null;
        if (! $c) {
            throw new ConnectException('That Google account has no YouTube channel. Sign in with the account that owns your channel.');
        }

        return [
            'external_id' => $c['id'], 'handle' => $c['snippet']['customUrl'] ?? null, 'name' => $c['snippet']['title'] ?? null,
            'avatar_url' => $c['snippet']['thumbnails']['default']['url'] ?? null,
            'followers' => ($c['statistics']['hiddenSubscriberCount'] ?? false) ? null : self::int($c['statistics']['subscriberCount'] ?? null),
        ];
    }

    public function sync(SocialAccount $account, string $token): array
    {
        $profile = $this->profile($token);
        $end = CarbonImmutable::now('UTC')->subDay();
        $start = $end->subDays(27);
        $j = $this->json($this->http()->withToken($token)->get('https://youtubeanalytics.googleapis.com/v2/reports', [
            'ids' => 'channel==MINE', 'startDate' => $start->toDateString(), 'endDate' => $end->toDateString(),
            'metrics' => 'views,likes,comments,shares,estimatedMinutesWatched,averageViewPercentage',
            'dimensions' => 'video', 'sort' => '-views', 'maxResults' => 25,
        ]), 'read YouTube Analytics');

        $cols = array_map(fn ($h) => $h['name'], $j['columnHeaders'] ?? []);
        $videos = [];
        foreach ($j['rows'] ?? [] as $row) {
            $r = array_combine($cols, $row);
            $videos[] = ['id' => $r['video'], 'views' => (int) $r['views'], 'likes' => (int) $r['likes'], 'comments' => (int) $r['comments'],
                'shares' => (int) $r['shares'], 'minutes' => (int) $r['estimatedMinutesWatched'], 'avg_pct' => round((float) $r['averageViewPercentage'], 1)];
        }

        // Only her own drafted Shorts are updated (matched by video id in their link); long-form
        // videos live on My channel, not in the social calendar.
        $posts = array_map(fn ($v) => ['external_id' => $v['id'], 'match_only' => true,
            'metrics' => ['impressions' => $v['views'], 'likes' => $v['likes'], 'comments' => $v['comments'], 'shares' => $v['shares']]], $videos);

        return [
            'followers' => $profile['followers'],
            'posts' => $posts,
            'meta' => ['period' => $start->toDateString().' – '.$end->toDateString(), 'views_28d' => array_sum(array_column($videos, 'views')),
                'minutes_28d' => array_sum(array_column($videos, 'minutes')), 'top_videos' => array_slice($videos, 0, 5)],
        ];
    }

    public function engagement(): array
    {
        return ['comment'];
    }

    public function inbox(SocialAccount $account, string $token, \DateTimeInterface $since): array
    {
        $j = $this->json($this->http()->withToken($token)->get('https://www.googleapis.com/youtube/v3/commentThreads', [
            'part' => 'snippet', 'allThreadsRelatedToChannelId' => $account->external_id, 'maxResults' => 50, 'order' => 'time',
        ]), 'read your comments');
        $out = [];
        foreach ($j['items'] ?? [] as $t) {
            $c = $t['snippet']['topLevelComment']['snippet'] ?? [];
            if (($c['authorChannelId']['value'] ?? null) === $account->external_id || strtotime($c['publishedAt'] ?? 'now') < $since->getTimestamp()) {
                continue;
            }
            $out[] = ['kind' => 'comment', 'external_id' => (string) $t['id'], 'thread_id' => $t['snippet']['videoId'] ?? null,
                'author' => $c['authorDisplayName'] ?? null, 'author_id' => $c['authorChannelId']['value'] ?? null,
                'text' => html_entity_decode(strip_tags((string) ($c['textDisplay'] ?? $c['textOriginal'] ?? '')), ENT_QUOTES), 'received_at' => $c['publishedAt'] ?? null];
        }

        return $out;
    }

    public function reply(SocialAccount $account, string $token, array $item, string $text): string
    {
        return (string) ($this->json($this->http()->withToken($token)->post('https://www.googleapis.com/youtube/v3/comments?part=snippet', [
            'snippet' => ['parentId' => $item['external_id'], 'textOriginal' => $text],
        ]), 'reply to the comment')['id'] ?? '');
    }

    public function revoke(SocialAccount $account): void
    {
        $this->http()->asForm()->post('https://oauth2.googleapis.com/revoke', ['token' => $account->refresh_token ?: $account->access_token]);
    }
}
