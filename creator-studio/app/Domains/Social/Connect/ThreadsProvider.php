<?php

namespace App\Domains\Social\Connect;

use App\Domains\Social\Models\SocialAccount;
use App\Domains\Social\Models\SocialPost;

/**
 * Threads API: followers and per-post views, likes, replies and reposts. 60-day sign-ins, renewed in the background.
 */
class ThreadsProvider extends Provider
{
    public const KEY = 'threads';

    private const GRAPH = 'https://graph.threads.net/v1.0';

    public function info(): array
    {
        return [
            'id_label' => 'Threads app ID',
            'secret_label' => 'Threads app secret',
            'console' => 'https://developers.facebook.com/apps/',
            'unlocks' => ['Follower count, every day', 'Views, likes, replies and reposts for your last 50 posts', 'Agents can publish here', 'Replies in your Inbox, answered from the studio'],
            'limits' => 'Free. Meta only accepts an https:// redirect address, and Threads doesn’t accept “localhost”: use a name like studio.test (see “Secure address” above).',
            'steps' => [
                'On Meta for Developers create an app → use case “Access the Threads API”.',
                'Use cases → Customise: add the permissions threads_basic, threads_manage_insights, threads_content_publish, threads_read_replies and threads_manage_replies.',
                'Settings: paste the redirect address below into “Redirect Callback URLs” (and the uninstall/delete fields too).',
                'App roles → Roles → add your Threads account as a Threads tester, then accept it in Threads (Settings → Account → Website permissions → Invites).',
                'Copy the Threads app ID and Threads app secret (App settings → Basic) here.',
            ],
        ];
    }

    public function needsHttps(): bool
    {
        return true;
    }

    public function authorizeUrl(string $state, string $challenge): string
    {
        return 'https://threads.net/oauth/authorize?' . http_build_query([
            'client_id' => $this->clientId(),
            'redirect_uri' => $this->redirectUri(),
            'response_type' => 'code',
            'scope' => 'threads_basic,threads_manage_insights,threads_content_publish,threads_read_replies,threads_manage_replies',
            'state' => $state,
        ]);
    }

    public function exchange(string $code, string $verifier): array
    {
        $short = $this->json($this->http()->asForm()->post('https://graph.threads.net/oauth/access_token', [
            'client_id' => $this->clientId(),
            'client_secret' => $this->clientSecret(),
            'grant_type' => 'authorization_code',
            'redirect_uri' => $this->redirectUri(),
            'code' => $code,
        ]), 'finish signing in', true);
        $long = $this->json($this->http()->get('https://graph.threads.net/access_token', [
            'grant_type' => 'th_exchange_token',
            'client_secret' => $this->clientSecret(),
            'access_token' => $short['access_token'] ?? '',
        ]), 'finish signing in', true);

        return $this->token($long + [
            'scope' => 'threads_basic,threads_manage_insights,threads_content_publish,threads_read_replies,threads_manage_replies',
        ], ',');
    }

    public function refresh(SocialAccount $account): array
    {
        $t = $this->token($this->json($this->http()->get('https://graph.threads.net/refresh_access_token', [
            'grant_type' => 'th_refresh_token',
            'access_token' => $account->access_token,
        ]), 'renew the sign-in', true));
        $t['scopes'] = $account->scopes ?? [];

        return $t;
    }

    public function renewWindow(): int
    {
        return 7 * 86400;
    }

    public function canPublish(): bool
    {
        return true;
    }

    /**
     * A text post; a thread's parts are chained as replies. Each needs a container, then publish.
     */
    public function publish(SocialAccount $account, string $token, SocialPost $post): array
    {
        $texts = array_values(array_filter(array_merge([trim((string)$post->body)], $post->parts ?? [])));
        $first = $previous = null;
        foreach ($texts as $text) {
            $c = $this->json($this->http()->asForm()->post(self::GRAPH . '/me/threads', array_filter([
                'media_type' => 'TEXT',
                'text' => mb_substr($text, 0, 500),
                'reply_to_id' => $previous,
                'access_token' => $token,
            ])), 'prepare the post');
            $p = $this->json($this->http()->asForm()->post(self::GRAPH . '/me/threads_publish', [
                'creation_id' => $c['id'] ?? '',
                'access_token' => $token,
            ]), 'publish the post');
            $previous = (string)($p['id'] ?? '');
            $first ??= $previous;
        }
        $link = $first ? $this->http()->get(self::GRAPH . "/{$first}", [
            'fields' => 'permalink',
            'access_token' => $token,
        ])->json('permalink') : null;

        return [
            'external_id' => (string)$first,
            'url' => $link,
        ];
    }

    public function engagement(): array
    {
        return ['comment'];
    }

    public function inbox(SocialAccount $account, string $token, \DateTimeInterface $since): array
    {
        $out = [];
        $items = $this->json($this->http()->get(self::GRAPH . '/me/threads', [
            'fields' => 'id,timestamp',
            'limit' => 10,
            'access_token' => $token,
        ]), 'read your posts')['data'] ?? [];
        foreach ($items as $t) {
            $r = $this->http()->get(self::GRAPH . "/{$t['id']}/replies", [
                'fields' => 'id,text,username,timestamp',
                'access_token' => $token,
            ]);
            foreach ($r->successful() ? ($r->json('data') ?? []) : [] as $c) {
                if (strcasecmp((string)($c['username'] ?? ''), (string)$account->handle) === 0 || strtotime($c['timestamp'] ?? 'now') < $since->getTimestamp()) {
                    continue;
                }
                $out[] = [
                    'kind' => 'comment',
                    'external_id' => (string)$c['id'],
                    'thread_id' => (string)$t['id'],
                    'author' => $c['username'] ?? null,
                    'author_id' => null,
                    'text' => (string)($c['text'] ?? ''),
                    'received_at' => $c['timestamp'] ?? null,
                ];
            }
        }

        return $out;
    }

    public function reply(SocialAccount $account, string $token, array $item, string $text): string
    {
        $c = $this->json($this->http()->asForm()->post(self::GRAPH . '/me/threads', [
            'media_type' => 'TEXT',
            'text' => mb_substr($text, 0, 500),
            'reply_to_id' => $item['external_id'],
            'access_token' => $token,
        ]), 'prepare the reply');

        return (string)($this->json($this->http()->asForm()->post(self::GRAPH . '/me/threads_publish', [
            'creation_id' => $c['id'] ?? '',
            'access_token' => $token,
        ]), 'post the reply')['id'] ?? '');
    }

    public function profile(string $token): array
    {
        $j = $this->json($this->http()->get(self::GRAPH . '/me', [
            'fields' => 'id,username,name,threads_profile_picture_url',
            'access_token' => $token,
        ]), 'read your profile');
        $f = $this->http()->get(self::GRAPH . '/me/threads_insights', [
            'metric' => 'followers_count',
            'access_token' => $token,
        ]);

        return [
            'external_id' => (string)($j['id'] ?? ''),
            'handle' => $j['username'] ?? null,
            'name' => $j['name'] ?? null,
            'avatar_url' => $j['threads_profile_picture_url'] ?? null,
            'followers' => $f->successful() ? self::int($f->json('data.0.total_value.value')) : null,
        ];
    }

    public function sync(SocialAccount $account, string $token): array
    {
        $profile = $this->profile($token);
        $items = $this->json($this->http()->get(self::GRAPH . '/me/threads', [
            'fields' => 'id,text,permalink,timestamp,media_type,media_url,thumbnail_url,is_quote_post',
            'limit' => 50,
            'access_token' => $token,
        ]), 'read your posts')['data'] ?? [];

        $posts = [];
        foreach ($items as $t) {
            if (($t['media_type'] ?? '') === 'REPOST_FACADE') {
                continue; // her reposts of other people's posts
            }
            $metrics = [];
            $r = $this->http()->get(self::GRAPH . "/{$t['id']}/insights", [
                'metric' => 'views,likes,replies,reposts,quotes',
                'access_token' => $token,
            ]);
            if ($r->successful()) {
                $m = [];
                foreach ($r->json('data') ?? [] as $row) {
                    $m[$row['name'] ?? ''] = self::int($row['values'][0]['value'] ?? $row['total_value']['value'] ?? null);
                }
                $metrics = array_filter([
                    'impressions' => $m['views'] ?? null,
                    'likes' => $m['likes'] ?? null,
                    'comments' => $m['replies'] ?? null,
                    'shares' => isset($m['reposts']) || isset($m['quotes']) ? (int)($m['reposts'] ?? 0) + (int)($m['quotes'] ?? 0) : null,
                ], fn ($v) => $v !== null);
            }
            $posts[] = [
                'external_id' => (string)$t['id'],
                'url' => $t['permalink'] ?? null,
                'posted_at' => $t['timestamp'] ?? null,
                'text' => $t['text'] ?? '',
                'format' => 'post',
                'metrics' => $metrics,
                'thumb' => ($t['media_type'] ?? '') === 'VIDEO' ? ($t['thumbnail_url'] ?? null) : (($t['media_type'] ?? '') === 'IMAGE' ? ($t['media_url'] ?? null) : null),
            ];
        }

        return [
            'followers' => $profile['followers'],
            'posts' => $posts,
            'meta' => [
                'posts_read' => count($posts),
            ],
        ];
    }
}
