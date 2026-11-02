<?php

namespace App\Domains\Social\Connect;

use App\Domains\Social\Models\SocialAccount;

/**
 * TikTok Login Kit + Display API: followers, likes, and per-video views, likes, comments and shares.
 */
class TikTokProvider extends Provider
{
    public const KEY = 'tiktok';

    private const SCOPES = 'user.info.basic,user.info.profile,user.info.stats,video.list';

    public function info(): array
    {
        return [
            'id_label' => 'Client key',
            'secret_label' => 'Client secret',
            'console' => 'https://developers.tiktok.com/apps/',
            'unlocks' => ['Followers and total likes, every day', 'Views, likes, comments and shares for your last 20 videos'],
            'limits' => 'Free. TikTok only accepts an https:// redirect address. Until TikTok reviews the app it runs in Sandbox, which is fine for your own account (add it as a target user).',
            'steps' => [
                'On TikTok for Developers create an app (Manage apps → Connect an app).',
                'Add the products “Login Kit” and “Display API”, and the scopes user.info.basic, user.info.profile, user.info.stats and video.list.',
                'Login Kit → Redirect URI: paste the address below (Web).',
                'Switch to Sandbox, then Sandbox settings → Target users: add your TikTok account.',
                'Copy the Client key and Client secret here.',
            ],
        ];
    }

    public function needsHttps(): bool
    {
        return true;
    }

    public function authorizeUrl(string $state, string $challenge): string
    {
        return 'https://www.tiktok.com/v2/auth/authorize/?' . http_build_query([
            'client_key' => $this->clientId(),
            'redirect_uri' => $this->redirectUri(),
            'response_type' => 'code',
            'scope' => self::SCOPES,
            'state' => $state,
        ]);
    }

    public function exchange(string $code, string $verifier): array
    {
        return $this->token($this->json($this->http()->asForm()->post('https://open.tiktokapis.com/v2/oauth/token/', [
            'client_key' => $this->clientId(),
            'client_secret' => $this->clientSecret(),
            'code' => $code,
            'grant_type' => 'authorization_code',
            'redirect_uri' => $this->redirectUri(),
        ]), 'finish signing in', true), ',');
    }

    public function refresh(SocialAccount $account): array
    {
        return $this->token($this->json($this->http()->asForm()->post('https://open.tiktokapis.com/v2/oauth/token/', [
            'client_key' => $this->clientId(),
            'client_secret' => $this->clientSecret(),
            'grant_type' => 'refresh_token',
            'refresh_token' => $account->refresh_token,
        ]), 'renew the sign-in', true), ',');
    }

    public function profile(string $token): array
    {
        $u = $this->json($this->http()->withToken($token)->get('https://open.tiktokapis.com/v2/user/info/', [
            'fields' => 'open_id,avatar_url,display_name,username,follower_count,likes_count,video_count',
        ]), 'read your profile')['data']['user'] ?? [];

        return [
            'external_id' => $u['open_id'] ?? null,
            'handle' => $u['username'] ?? null,
            'name' => $u['display_name'] ?? null,
            'avatar_url' => $u['avatar_url'] ?? null,
            'followers' => self::int($u['follower_count'] ?? null),
            'likes' => self::int($u['likes_count'] ?? null),
            'videos' => self::int($u['video_count'] ?? null),
        ];
    }

    public function sync(SocialAccount $account, string $token): array
    {
        $profile = $this->profile($token);
        $videos = $this->json(
            $this->http()->withToken($token)
                ->post('https://open.tiktokapis.com/v2/video/list/?fields=id,title,video_description,share_url,cover_image_url,create_time,view_count,like_count,comment_count,share_count', [
                    'max_count' => 20,
                ]),
            'read your videos'
        )['data']['videos'] ?? [];

        $posts = array_map(fn ($v) => [
            'external_id' => (string)$v['id'],
            'url' => $v['share_url'] ?? null,
            'posted_at' => isset($v['create_time']) ? date(DATE_ATOM, (int)$v['create_time']) : null,
            'text' => $v['video_description'] ?? $v['title'] ?? '',
            'format' => 'video',
            'thumb' => $v['cover_image_url'] ?? null,
            'metrics' => array_filter([
                'impressions' => self::int($v['view_count'] ?? null),
                'likes' => self::int($v['like_count'] ?? null),
                'comments' => self::int($v['comment_count'] ?? null),
                'shares' => self::int($v['share_count'] ?? null),
            ], fn ($x) => $x !== null),
        ], $videos);

        return [
            'followers' => $profile['followers'],
            'posts' => $posts,
            'meta' => [
                'likes' => $profile['likes'],
                'videos' => $profile['videos'],
            ],
        ];
    }

    public function revoke(SocialAccount $account): void
    {
        $this->http()->asForm()->post('https://open.tiktokapis.com/v2/oauth/revoke/', [
            'client_key' => $this->clientId(),
            'client_secret' => $this->clientSecret(),
            'token' => $account->access_token,
        ]);
    }
}
