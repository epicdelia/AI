<?php

namespace App\Domains\Social\Connect;

use App\Domains\Social\Models\SocialAccount;
use App\Domains\Social\Models\SocialPost;
use App\Models\Setting;

/**
 * X (OAuth 2.0 with PKCE). Signing in is free; reading data is billed per request on X's
 * pay-per-use plan, so syncing only runs when she has set a monthly request limit, and stops
 * at it.
 */
class XProvider extends Provider
{
    public const KEY = 'x';

    public function info(): array
    {
        return [
            'id_label' => 'OAuth 2.0 Client ID',
            'secret_label' => 'OAuth 2.0 Client secret',
            'console' => 'https://developer.x.com/en/portal/dashboard',
            'unlocks' => ['Follower count', 'Views, likes, replies, reposts and bookmarks for your last 20 posts', 'Agents can publish here (within your request limit)'],
            'limits' => 'Paid: X charges per request. Syncing and agent publishing stay off until you set a monthly request limit below (a sync uses 2, each published post 1); set a spending limit in the X developer console too.',
            'steps' => [
                'On the X developer console create a Project and an App (pay-per-use).',
                'App settings → User authentication settings: OAuth 2.0, type “Web App, Automated App or Bot”, permissions “Read and write”.',
                'Callback URI: paste the address below; Website URL: your site or profile link.',
                'Keys and tokens → OAuth 2.0 Client ID and Client Secret: copy them here.',
                'Billing: set a monthly spending limit in the console, then choose how many requests a month the studio may use.',
            ],
        ];
    }

    public function authorizeUrl(string $state, string $challenge): string
    {
        return 'https://x.com/i/oauth2/authorize?' . http_build_query([
            'response_type' => 'code',
            'client_id' => $this->clientId(),
            'redirect_uri' => $this->redirectUri(),
            'scope' => 'tweet.read tweet.write users.read offline.access',
            'state' => $state,
            'code_challenge' => $challenge,
            'code_challenge_method' => 'S256',
        ]);
    }

    public function exchange(string $code, string $verifier): array
    {
        return $this->token($this->json($this->http()->asForm()->withBasicAuth($this->clientId(), $this->clientSecret())
            ->post('https://api.x.com/2/oauth2/token', [
                'grant_type' => 'authorization_code',
                'code' => $code,
                'redirect_uri' => $this->redirectUri(),
                'code_verifier' => $verifier,
            ]), 'finish signing in', true));
    }

    public function refresh(SocialAccount $account): array
    {
        return $this->token($this->json(
            $this->http()->asForm()->withBasicAuth($this->clientId(), $this->clientSecret())
                ->post('https://api.x.com/2/oauth2/token', [
                    'grant_type' => 'refresh_token',
                    'refresh_token' => $account->refresh_token,
                ]),
            'renew the sign-in',
            true
        ));
    }

    public function profile(string $token): array
    {
        $this->spend(1);
        $u = $this->json($this->http()->withToken($token)->get('https://api.x.com/2/users/me', [
            'user.fields' => 'public_metrics,profile_image_url,username,name',
        ]), 'read your profile')['data'] ?? [];

        return [
            'external_id' => $u['id'] ?? null,
            'handle' => $u['username'] ?? null,
            'name' => $u['name'] ?? null,
            'avatar_url' => $u['profile_image_url'] ?? null,
            'followers' => self::int($u['public_metrics']['followers_count'] ?? null),
        ];
    }

    public function sync(SocialAccount $account, string $token): array
    {
        if (self::cap() - self::used() < 2) {
            throw new ConnectException(self::cap() === 0
                ? 'Syncing X is off: reading X data is paid. Set a monthly request limit in X → Set up to turn it on.'
                : 'This month’s X request limit is used up; syncing starts again next month.');
        }
        $profile = $this->profile($token);
        $this->spend(1);
        $res = $this->json($this->http()->withToken($token)->get("https://api.x.com/2/users/{$account->external_id}/tweets", [
            'max_results' => 20,
            'exclude' => 'retweets,replies',
            'tweet.fields' => 'public_metrics,created_at,attachments',
            'expansions' => 'attachments.media_keys',
            'media.fields' => 'url,preview_image_url',
        ]), 'read your posts');
        $tweets = $res['data'] ?? [];
        $media = [];
        foreach ($res['includes']['media'] ?? [] as $m) {
            $media[$m['media_key'] ?? ''] = $m['url'] ?? $m['preview_image_url'] ?? null;
        }

        $posts = array_map(fn ($t) => [
            'external_id' => (string)$t['id'],
            'url' => 'https://x.com/' . ($profile['handle'] ?? 'i') . '/status/' . $t['id'],
            'posted_at' => $t['created_at'] ?? null,
            'text' => $t['text'] ?? '',
            'format' => 'post',
            'thumb' => $media[$t['attachments']['media_keys'][0] ?? ''] ?? null,
            'metrics' => array_filter([
                'impressions' => self::int($t['public_metrics']['impression_count'] ?? null),
                'likes' => self::int($t['public_metrics']['like_count'] ?? null),
                'comments' => self::int($t['public_metrics']['reply_count'] ?? null),
                'shares' => isset($t['public_metrics']) ? (int)($t['public_metrics']['retweet_count'] ?? 0) + (int)($t['public_metrics']['quote_count'] ?? 0) : null,
                'saves' => self::int($t['public_metrics']['bookmark_count'] ?? null),
            ], fn ($v) => $v !== null),
        ], $tweets);

        return [
            'followers' => $profile['followers'],
            'posts' => $posts,
            'meta' => [
                'requests_this_month' => self::used(),
                'request_limit' => self::cap(),
            ],
        ];
    }

    public function canPublish(): bool
    {
        return true;
    }

    /**
     * A post, or a thread (parts chained as replies). Each post counts against the monthly limit.
     */
    public function publish(SocialAccount $account, string $token, SocialPost $post): array
    {
        $texts = array_values(array_filter(array_merge([trim((string)$post->body)], $post->parts ?? [])));
        if (self::cap() - self::used() < count($texts)) {
            throw new ConnectException('Not enough of this month’s X request limit left to publish this. Raise it in X → Set up, or post it by hand.');
        }
        $first = $previous = null;
        foreach ($texts as $text) {
            $this->spend(1);
            $d = $this->json($this->http()->withToken($token)->post('https://api.x.com/2/tweets', array_filter([
                'text' => $text,
                'reply' => $previous ? [
                    'in_reply_to_tweet_id' => $previous,
                ] : null,
            ])), 'publish the post')['data'] ?? [];
            $previous = (string)($d['id'] ?? '');
            $first ??= $previous;
        }

        return [
            'external_id' => (string)$first,
            'url' => $first ? 'https://x.com/' . ($account->handle ?: 'i') . '/status/' . $first : null,
        ];
    }

    public function revoke(SocialAccount $account): void
    {
        $this->http()->asForm()->withBasicAuth($this->clientId(), $this->clientSecret())
            ->post('https://api.x.com/2/oauth2/revoke', [
                'token' => $account->refresh_token ?: $account->access_token,
                'token_type_hint' => $account->refresh_token ? 'refresh_token' : 'access_token',
            ]);
    }

    public static function cap(): int
    {
        return max(0, (int)Setting::get('x_monthly_requests', '0'));
    }

    public static function used(): int
    {
        return (int)Setting::get('x_requests_' . now()->format('Y_m'), '0');
    }

    private function spend(int $n): void
    {
        Setting::put('x_requests_' . now()->format('Y_m'), (string)(self::used() + $n));
    }
}
