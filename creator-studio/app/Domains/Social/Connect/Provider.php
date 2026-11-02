<?php

namespace App\Domains\Social\Connect;

use App\Domains\Social\Models\SocialAccount;
use App\Domains\Social\Models\SocialPost;
use App\Models\Setting;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * One network's OAuth sign-in and data pull. Each network registers its own developer app;
 * she pastes that app's client id and secret into Connections, and the studio does the rest.
 *
 * Tokens come back normalised as: access_token, refresh_token, expires_in, refresh_expires_in,
 * scopes (array). Profiles as: external_id, handle, name, avatar_url, followers (int|null).
 * A sync returns: followers (int|null), posts (list of external_id, url, posted_at, text,
 * format, metrics) and meta (anything worth showing on the card).
 */
abstract class Provider
{
    public const KEY = '';

    /**
     * Network label, the name of its id field, where to create the app, and the setup steps.
     */
    abstract public function info(): array;

    abstract public function authorizeUrl(string $state, string $challenge): string;

    abstract public function exchange(string $code, string $verifier): array;

    abstract public function profile(string $token): array;

    public function refresh(SocialAccount $account): array
    {
        throw new ConnectException(static::label() . ' sign-ins can’t be renewed automatically. Reconnect to continue.', reconnect: true);
    }

    public function sync(SocialAccount $account, string $token): array
    {
        return [
            'followers' => $this->profile($token)['followers'] ?? null,
            'posts' => [],
            'meta' => [],
        ];
    }

    public function revoke(SocialAccount $account): void
    {
    }

    /**
     * Whether agents can publish text posts here (the rest stay as "due" reminders she posts by hand).
     */
    public function canPublish(): bool
    {
        return false;
    }

    /**
     * Publish a post. Returns the network's id and link.
     *
     * @return array{external_id: string, url: ?string}
     */
    public function publish(SocialAccount $account, string $token, SocialPost $post): array
    {
        throw new ConnectException(static::label() . ' doesn’t allow publishing from here; post it from the app.');
    }

    /**
     * What the inbox can read and answer here: [] or some of 'comment', 'dm'.
     */
    public function engagement(): array
    {
        return [];
    }

    /**
     * New comments / DMs since $since: list of kind, external_id, thread_id (post or conversation),
     * author, author_id, text, received_at. Her own messages are left out.
     */
    public function inbox(SocialAccount $account, string $token, \DateTimeInterface $since): array
    {
        return [];
    }

    /**
     * Answer a comment publicly (kind comment) or a DM (kind dm). Returns the reply's id.
     */
    public function reply(SocialAccount $account, string $token, array $item, string $text): string
    {
        throw new ConnectException(static::label() . ' doesn’t allow replying from here.');
    }

    /**
     * Send a private DM in answer to a comment (Instagram "private replies").
     */
    public function privateReply(SocialAccount $account, string $token, array $item, string $text): string
    {
        throw new ConnectException(static::label() . ' doesn’t allow DMs from here.');
    }

    /**
     * Renew the sign-in this many seconds before it lapses.
     */
    public function renewWindow(): int
    {
        return 300;
    }

    /**
     * Whether the network insists on an https:// redirect address.
     */
    public function needsHttps(): bool
    {
        return false;
    }

    public static function label(): string
    {
        return \App\Domains\Social\Platforms::PLATFORMS[static::KEY]['label'] ?? static::KEY;
    }

    public function clientId(): string
    {
        return (string)Setting::get(static::KEY . '_client_id', '');
    }

    public function clientSecret(): string
    {
        return (string)Setting::get(static::KEY . '_client_secret', '');
    }

    public function configured(): bool
    {
        return $this->clientId() !== '' && $this->clientSecret() !== '';
    }

    public function redirectUri(): string
    {
        return rtrim((string)config('app.url'), '/') . '/oauth/' . static::KEY . '/callback';
    }

    protected function http(): PendingRequest
    {
        return Http::timeout(20)->acceptJson();
    }

    /**
     * The JSON body, or a readable error (never including tokens). 400/401 on a token call means "reconnect".
     */
    protected function json(Response $r, string $what, bool $tokenCall = false): array
    {
        if ($r->successful()) {
            return (array)$r->json();
        }
        $body = (array)$r->json();
        $msg = $body['error_description'] ?? $body['error']['message'] ?? $body['message'] ?? $body['detail'] ?? $body['title']
            ?? (is_string($body['error'] ?? null) ? $body['error'] : null) ?? mb_substr(trim($r->body()), 0, 160);

        throw new ConnectException(
            static::label() . " couldn’t {$what}: " . (is_string($msg) ? $msg : 'HTTP ' . $r->status()),
            reconnect: $tokenCall && in_array($r->status(), [400, 401], true) || $r->status() === 401
        );
    }

    /**
     * Normalise a token response.
     */
    protected function token(array $t, string $scopeSep = ' '): array
    {
        $scopes = $t['scope'] ?? $t['permissions'] ?? [];

        return [
            'access_token' => (string)($t['access_token'] ?? ''),
            'refresh_token' => $t['refresh_token'] ?? null,
            'expires_in' => isset($t['expires_in']) ? (int)$t['expires_in'] : null,
            'refresh_expires_in' => isset($t['refresh_expires_in']) ? (int)$t['refresh_expires_in'] : null,
            'scopes' => is_array($scopes) ? array_values($scopes) : array_values(array_filter(preg_split('/[\s,]+/', (string)$scopes))),
        ];
    }

    protected static function int(mixed $v): ?int
    {
        return is_numeric($v) ? (int)$v : null;
    }
}
