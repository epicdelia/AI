<?php

namespace App\Domains\Social\Connect;

use App\Domains\Social\Models\SocialAccount;

/**
 * Instagram API with Instagram Login (no Facebook Page needed): followers and per-post reach,
 * likes, comments, saves and shares. Needs a Creator or Business account. Sign-ins are
 * long-lived (60 days) and renewed in the background.
 */
class InstagramProvider extends Provider
{
    public const KEY = 'instagram';

    private const GRAPH = 'https://graph.instagram.com/v21.0';

    public function info(): array
    {
        return [
            'id_label' => 'Instagram app ID',
            'secret_label' => 'Instagram app secret',
            'console' => 'https://developers.facebook.com/apps/',
            'unlocks' => ['Follower count, every day', 'Reach, likes, comments, saves and shares for your last 50 posts', 'Posts you made in the app show up in Analytics without logging them', 'Comments and DMs in your Inbox, with replies and keyword automations'],
            'limits' => 'Free. Your Instagram must be a Creator or Business account. Meta only accepts an https:// redirect address.',
            'steps' => [
                'In the Instagram app: Settings → Account type and tools → switch to a Creator (or Business) account.',
                'On Meta for Developers create an app → use case “Manage messaging & content on Instagram”.',
                'Instagram → API setup with Instagram business login → Business login settings: paste the redirect address below.',
                'App roles → Roles → add your Instagram account as an Instagram tester, then accept the invite in Instagram (Settings → Website permissions → Apps and websites → Tester invites).',
                'Copy the Instagram app ID and Instagram app secret (from the same API setup page) here. For DMs, also turn on “Allow access to messages” in Instagram → Settings → Messages and story replies → Message controls → Connected tools.',
            ],
        ];
    }

    public function needsHttps(): bool
    {
        return true;
    }

    public function authorizeUrl(string $state, string $challenge): string
    {
        return 'https://www.instagram.com/oauth/authorize?'.http_build_query([
            'client_id' => $this->clientId(), 'redirect_uri' => $this->redirectUri(), 'response_type' => 'code',
            'scope' => 'instagram_business_basic,instagram_business_manage_insights,instagram_business_manage_comments,instagram_business_manage_messages', 'state' => $state,
        ]);
    }

    public function exchange(string $code, string $verifier): array
    {
        $short = $this->json($this->http()->asForm()->post('https://api.instagram.com/oauth/access_token', [
            'client_id' => $this->clientId(), 'client_secret' => $this->clientSecret(), 'grant_type' => 'authorization_code',
            'redirect_uri' => $this->redirectUri(), 'code' => $code,
        ]), 'finish signing in', true);
        // Swap the 1-hour token for a 60-day one.
        $long = $this->json($this->http()->get('https://graph.instagram.com/access_token', [
            'grant_type' => 'ig_exchange_token', 'client_secret' => $this->clientSecret(), 'access_token' => $short['access_token'] ?? '',
        ]), 'finish signing in', true);

        return $this->token($long + ['permissions' => $short['permissions'] ?? []], ',');
    }

    public function refresh(SocialAccount $account): array
    {
        $t = $this->token($this->json($this->http()->get('https://graph.instagram.com/refresh_access_token', [
            'grant_type' => 'ig_refresh_token', 'access_token' => $account->access_token,
        ]), 'renew the sign-in', true));
        $t['scopes'] = $account->scopes ?? [];

        return $t;
    }

    /** Long-lived tokens can only be renewed, so renew a week before they lapse. */
    public function renewWindow(): int
    {
        return 7 * 86400;
    }

    public function engagement(): array
    {
        return ['comment', 'dm'];
    }

    public function inbox(SocialAccount $account, string $token, \DateTimeInterface $since): array
    {
        $out = [];
        $media = $this->json($this->http()->get(self::GRAPH.'/me/media', ['fields' => 'id,timestamp,comments_count', 'limit' => 15, 'access_token' => $token]), 'read your posts')['data'] ?? [];
        foreach ($media as $m) {
            if (! ($m['comments_count'] ?? 0) || strtotime($m['timestamp'] ?? 'now') < $since->getTimestamp() - 14 * 86400) {
                continue; // comments on older posts are rare; keeps the calls down
            }
            $r = $this->http()->get(self::GRAPH."/{$m['id']}/comments", ['fields' => 'id,text,timestamp,username,from', 'limit' => 50, 'access_token' => $token]);
            foreach ($r->successful() ? ($r->json('data') ?? []) : [] as $c) {
                if (strcasecmp((string) ($c['username'] ?? ''), (string) $account->handle) === 0 || strtotime($c['timestamp'] ?? 'now') < $since->getTimestamp()) {
                    continue;
                }
                $out[] = ['kind' => 'comment', 'external_id' => (string) $c['id'], 'thread_id' => (string) $m['id'], 'author' => $c['username'] ?? null,
                    'author_id' => $c['from']['id'] ?? null, 'text' => (string) ($c['text'] ?? ''), 'received_at' => $c['timestamp'] ?? null];
            }
        }
        $r = $this->http()->get(self::GRAPH.'/me/conversations', [
            'platform' => 'instagram', 'fields' => 'id,updated_time,messages.limit(5){id,message,from,created_time}', 'limit' => 20, 'access_token' => $token,
        ]);
        foreach ($r->successful() ? ($r->json('data') ?? []) : [] as $conv) {
            foreach ($conv['messages']['data'] ?? [] as $msg) {
                $from = $msg['from'] ?? [];
                if ((string) ($from['id'] ?? '') === (string) $account->external_id || strcasecmp((string) ($from['username'] ?? ''), (string) $account->handle) === 0
                    || strtotime($msg['created_time'] ?? 'now') < $since->getTimestamp() || trim((string) ($msg['message'] ?? '')) === '') {
                    continue;
                }
                $out[] = ['kind' => 'dm', 'external_id' => (string) $msg['id'], 'thread_id' => (string) $conv['id'], 'author' => $from['username'] ?? null,
                    'author_id' => $from['id'] ?? null, 'text' => (string) $msg['message'], 'received_at' => $msg['created_time'] ?? null];
            }
        }

        return $out;
    }

    public function reply(SocialAccount $account, string $token, array $item, string $text): string
    {
        if ($item['kind'] === 'dm') {
            return $this->dm($token, ['id' => $item['author_id']], $text);
        }

        return (string) ($this->json($this->http()->asForm()->post(self::GRAPH."/{$item['external_id']}/replies", [
            'message' => $text, 'access_token' => $token,
        ]), 'reply to the comment')['id'] ?? '');
    }

    /** One private DM per comment, within 7 days of it (Instagram's rule). */
    public function privateReply(SocialAccount $account, string $token, array $item, string $text): string
    {
        return $this->dm($token, ['comment_id' => $item['external_id']], $text);
    }

    private function dm(string $token, array $recipient, string $text): string
    {
        return (string) ($this->json($this->http()->withToken($token)->post(self::GRAPH.'/me/messages', [
            'recipient' => $recipient, 'message' => ['text' => mb_substr($text, 0, 1000)],
        ]), 'send the DM')['message_id'] ?? '');
    }

    public function profile(string $token): array
    {
        $j = $this->json($this->http()->get(self::GRAPH.'/me', [
            'fields' => 'user_id,username,name,profile_picture_url,followers_count,media_count', 'access_token' => $token,
        ]), 'read your profile');

        return ['external_id' => (string) ($j['user_id'] ?? $j['id'] ?? ''), 'handle' => $j['username'] ?? null, 'name' => $j['name'] ?? null,
            'avatar_url' => $j['profile_picture_url'] ?? null, 'followers' => self::int($j['followers_count'] ?? null)];
    }

    public function sync(SocialAccount $account, string $token): array
    {
        $profile = $this->profile($token);
        $media = $this->json($this->http()->get(self::GRAPH.'/me/media', [
            'fields' => 'id,caption,media_type,media_product_type,media_url,thumbnail_url,permalink,timestamp,like_count,comments_count', 'limit' => 50, 'access_token' => $token,
        ]), 'read your posts')['data'] ?? [];

        $posts = [];
        foreach ($media as $m) {
            $metrics = ['likes' => self::int($m['like_count'] ?? null), 'comments' => self::int($m['comments_count'] ?? null)];
            // Insights are per post; a post too old or of the wrong type just keeps likes and comments.
            $r = $this->http()->get(self::GRAPH."/{$m['id']}/insights", ['metric' => 'reach,saved,shares', 'access_token' => $token]);
            if ($r->successful()) {
                foreach ($r->json('data') ?? [] as $row) {
                    $v = self::int($row['values'][0]['value'] ?? $row['total_value']['value'] ?? null);
                    match ($row['name'] ?? '') { 'reach' => $metrics['impressions'] = $v, 'saved' => $metrics['saves'] = $v, 'shares' => $metrics['shares'] = $v, default => null };
                }
            }
            $posts[] = [
                'external_id' => (string) $m['id'], 'url' => $m['permalink'] ?? null, 'posted_at' => $m['timestamp'] ?? null,
                'text' => $m['caption'] ?? '', 'metrics' => array_filter($metrics, fn ($v) => $v !== null),
                'thumb' => ($m['media_type'] ?? '') === 'VIDEO' ? ($m['thumbnail_url'] ?? null) : ($m['media_url'] ?? $m['thumbnail_url'] ?? null),
                'format' => match (true) {
                    ($m['media_product_type'] ?? '') === 'REELS' => 'reel',
                    ($m['media_product_type'] ?? '') === 'STORY' => 'story',
                    ($m['media_type'] ?? '') === 'CAROUSEL_ALBUM' => 'carousel',
                    default => 'post',
                },
            ];
        }

        return ['followers' => $profile['followers'], 'posts' => $posts, 'meta' => ['posts_read' => count($posts)]];
    }
}
