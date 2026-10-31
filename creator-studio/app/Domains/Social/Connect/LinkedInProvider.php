<?php

namespace App\Domains\Social\Connect;

use App\Domains\Social\Models\SocialAccount;
use App\Domains\Social\Models\SocialPost;
use App\Models\Setting;

/**
 * LinkedIn's self-serve products: who she is (OpenID Connect). Post analytics need LinkedIn's
 * vetted Community Management API, which isn't offered to individuals, so her LinkedIn numbers
 * stay on CSV import. Sign-ins last 60 days and can't be renewed without that approval.
 */
class LinkedInProvider extends Provider
{
    public const KEY = 'linkedin';

    public function info(): array
    {
        return [
            'id_label' => 'Client ID',
            'secret_label' => 'Primary client secret',
            'console' => 'https://www.linkedin.com/developers/apps',
            'unlocks' => ['Your name, photo and profile on the LinkedIn page', 'Agents can publish text posts here (Share on LinkedIn)'],
            'limits' => 'Free. LinkedIn doesn’t give individuals post analytics through its API, so keep importing the CSV from LinkedIn → Analytics. The sign-in lasts 60 days; the studio reminds you to reconnect.',
            'steps' => [
                'On LinkedIn Developers create an app (it asks for a LinkedIn Page: any page you admin, or create a simple one).',
                'Products: request “Sign In with LinkedIn using OpenID Connect” and “Share on LinkedIn” (both are instant).',
                'Auth → OAuth 2.0 settings → Authorized redirect URLs: paste the address below.',
                'Copy the Client ID and Primary client secret here.',
            ],
        ];
    }

    public function authorizeUrl(string $state, string $challenge): string
    {
        return 'https://www.linkedin.com/oauth/v2/authorization?'.http_build_query([
            'response_type' => 'code', 'client_id' => $this->clientId(), 'redirect_uri' => $this->redirectUri(),
            'scope' => 'openid profile w_member_social', 'state' => $state,
        ]);
    }

    public function exchange(string $code, string $verifier): array
    {
        return $this->token($this->json($this->http()->asForm()->post('https://www.linkedin.com/oauth/v2/accessToken', [
            'grant_type' => 'authorization_code', 'code' => $code, 'redirect_uri' => $this->redirectUri(),
            'client_id' => $this->clientId(), 'client_secret' => $this->clientSecret(),
        ]), 'finish signing in', true));
    }

    public function canPublish(): bool
    {
        return true;
    }

    /** A text post on her profile (Share on LinkedIn, w_member_social). */
    public function publish(SocialAccount $account, string $token, SocialPost $post): array
    {
        $r = $this->http()->withToken($token)->withHeaders([
            'LinkedIn-Version' => (string) Setting::get('linkedin_api_version', '202509'), 'X-Restli-Protocol-Version' => '2.0.0',
        ])->post('https://api.linkedin.com/rest/posts', [
            'author' => "urn:li:person:{$account->external_id}",
            'commentary' => $post->asText(),
            'visibility' => 'PUBLIC',
            'distribution' => ['feedDistribution' => 'MAIN_FEED', 'targetEntities' => [], 'thirdPartyDistributionChannels' => []],
            'lifecycleState' => 'PUBLISHED',
            'isReshareDisabledByAuthor' => false,
        ]);
        $this->json($r, 'publish the post');
        $urn = (string) $r->header('x-restli-id');

        return ['external_id' => $urn, 'url' => $urn ? "https://www.linkedin.com/feed/update/{$urn}/" : null];
    }

    public function profile(string $token): array
    {
        $j = $this->json($this->http()->withToken($token)->get('https://api.linkedin.com/v2/userinfo'), 'read your profile');

        return ['external_id' => $j['sub'] ?? null, 'handle' => null, 'name' => $j['name'] ?? null, 'avatar_url' => $j['picture'] ?? null, 'followers' => null];
    }
}
