<?php

namespace App\Http\Controllers;

use App\Domains\Agents\Models\AgentEvent;
use App\Domains\Clients\ClientLink;
use App\Domains\Security\Audit;
use App\Domains\Social\Connect\LinkedInProvider;
use App\Domains\Social\Connect\YouTubeProvider;
use App\Enums\Role;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Throwable;

class OAuthLoginController extends Controller
{
    public static function googleAvailable(): bool
    {
        return app(YouTubeProvider::class)->configured() || !empty(env('GOOGLE_CLIENT_ID'));
    }

    public static function linkedinAvailable(): bool
    {
        return app(LinkedInProvider::class)->configured() || !empty(env('LINKEDIN_CLIENT_ID'));
    }

    public static function redirectUri(string $provider): string
    {
        return rtrim((string)config('app.url'), '/') . "/auth/{$provider}/callback";
    }

    public function googleRedirect(Request $request): RedirectResponse
    {
        $clientId = app(YouTubeProvider::class)->clientId() ?: (string)env('GOOGLE_CLIENT_ID');
        if ($clientId === '') {
            // In development / demo when keys are unset, provide instant mock sign-in
            if (app()->environment('local', 'testing')) {
                return $this->handleMockOAuth('google', 'Google User', 'google.follower@example.com', $request);
            }
            return $this->fail('Google sign-in isn’t configured: set up Google OAuth credentials first.');
        }

        $state = Str::random(40);
        $verifier = Str::random(64);
        $request->session()->put('oauth_login', [
            'provider' => 'google',
            'state' => $state,
            'verifier' => $verifier,
            'role' => $request->query('role', 'member'),
            'remember' => $request->boolean('remember', true),
        ]);

        return redirect('https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
            'client_id' => $clientId,
            'redirect_uri' => self::redirectUri('google'),
            'response_type' => 'code',
            'scope' => 'openid profile email',
            'state' => $state,
            'prompt' => 'select_account',
            'code_challenge' => rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '='),
            'code_challenge_method' => 'S256',
        ]));
    }

    public function googleCallback(Request $request): RedirectResponse
    {
        $saved = $request->session()->pull('oauth_login');
        if (!$saved || !hash_equals($saved['state'], (string)$request->query('state'))) {
            return $this->fail('That sign-in link expired. Try again.');
        }
        if ($request->filled('error') || !$request->filled('code')) {
            return $this->fail('Google sign-in was cancelled.');
        }

        $clientId = app(YouTubeProvider::class)->clientId() ?: (string)env('GOOGLE_CLIENT_ID');
        $clientSecret = app(YouTubeProvider::class)->clientSecret() ?: (string)env('GOOGLE_CLIENT_SECRET');

        try {
            $token = Http::asForm()->timeout(15)->post('https://oauth2.googleapis.com/token', [
                'code' => $request->query('code'),
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'redirect_uri' => self::redirectUri('google'),
                'grant_type' => 'authorization_code',
                'code_verifier' => $saved['verifier'],
            ])->throw()->json('access_token');

            $info = Http::withToken((string)$token)->timeout(15)
                ->get('https://openidconnect.googleapis.com/v1/userinfo')->throw()->json();
        } catch (Throwable) {
            return $this->fail('Google didn’t confirm the sign-in. Try again, or use your password.');
        }

        $email = strtolower((string)($info['email'] ?? ''));
        $name = (string)($info['name'] ?? 'Google User');

        return $this->loginOrCreateUser($email, $name, $saved['role'] ?? 'member', (bool)($saved['remember'] ?? true), $request);
    }

    public function linkedinRedirect(Request $request): RedirectResponse
    {
        $clientId = app(LinkedInProvider::class)->clientId() ?: (string)env('LINKEDIN_CLIENT_ID');
        if ($clientId === '') {
            if (app()->environment('local', 'testing')) {
                return $this->handleMockOAuth('linkedin', 'LinkedIn Professional', 'linkedin.follower@example.com', $request);
            }
            return $this->fail('LinkedIn sign-in isn’t configured: set up LinkedIn OAuth credentials first.');
        }

        $state = Str::random(40);
        $request->session()->put('oauth_login', [
            'provider' => 'linkedin',
            'state' => $state,
            'role' => $request->query('role', 'member'),
            'remember' => $request->boolean('remember', true),
        ]);

        return redirect('https://www.linkedin.com/oauth/v2/authorization?' . http_build_query([
            'response_type' => 'code',
            'client_id' => $clientId,
            'redirect_uri' => self::redirectUri('linkedin'),
            'scope' => 'openid profile email',
            'state' => $state,
        ]));
    }

    public function linkedinCallback(Request $request): RedirectResponse
    {
        $saved = $request->session()->pull('oauth_login');
        if (!$saved || !hash_equals($saved['state'], (string)$request->query('state'))) {
            return $this->fail('That sign-in link expired. Try again.');
        }
        if ($request->filled('error') || !$request->filled('code')) {
            return $this->fail('LinkedIn sign-in was cancelled.');
        }

        $clientId = app(LinkedInProvider::class)->clientId() ?: (string)env('LINKEDIN_CLIENT_ID');
        $clientSecret = app(LinkedInProvider::class)->clientSecret() ?: (string)env('LINKEDIN_CLIENT_SECRET');

        try {
            $tokenRes = Http::asForm()->timeout(15)->post('https://www.linkedin.com/oauth/v2/accessToken', [
                'grant_type' => 'authorization_code',
                'code' => $request->query('code'),
                'redirect_uri' => self::redirectUri('linkedin'),
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
            ])->throw()->json();

            $token = (string)($tokenRes['access_token'] ?? '');
            $info = Http::withToken($token)->timeout(15)->get('https://api.linkedin.com/v2/userinfo')->throw()->json();
        } catch (Throwable) {
            return $this->fail('LinkedIn didn’t confirm the sign-in. Try again, or use your password.');
        }

        $email = strtolower((string)($info['email'] ?? ''));
        $name = (string)($info['name'] ?? 'LinkedIn User');

        return $this->loginOrCreateUser($email, $name, $saved['role'] ?? 'member', (bool)($saved['remember'] ?? true), $request);
    }

    private function handleMockOAuth(string $provider, string $defaultName, string $defaultEmail, Request $request): RedirectResponse
    {
        $role = $request->query('role', 'member');
        $email = $defaultEmail;
        $name = $defaultName;
        return $this->loginOrCreateUser($email, $name, $role, true, $request);
    }

    private function loginOrCreateUser(string $email, string $name, string $targetRole, bool $remember, Request $request): RedirectResponse
    {
        if ($email === '') {
            return $this->fail('Could not retrieve email address from social login provider.');
        }

        $user = User::whereRaw('lower(email) = ?', [$email])->first();
        if (!$user) {
            $roleEnum = Role::forSignUp($targetRole);
            $user = new User([
                'name' => $name,
                'email' => $email,
                'password' => Hash::make(Str::random(32)),
            ]);
            $user->forceFill([
                'role' => $roleEnum,
                'email_verified_at' => now(),
            ])->save();

            AgentEvent::log('oauth', "New {$roleEnum->label()} signed up via OAuth: {$user->name} ({$user->email})");
        }

        if ($user->isDisabled()) {
            return $this->fail('This account has been switched off. Contact the studio if that’s a mistake.');
        }

        if (!$user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
            ClientLink::claim($user);
        }

        if ($user->hasTwoFactor()) {
            $request->session()->put('two_factor', [
                'id' => $user->id,
                'remember' => $remember,
                'until' => time() + 300,
            ]);
            return redirect('/login?two_factor=1');
        }

        Auth::login($user, $remember);
        $request->session()->regenerate();
        $user->forceFill(['last_login_at' => now()])->saveQuietly();
        Audit::signedIn($user, $request);

        return redirect('/');
    }

    private function fail(string $message): RedirectResponse
    {
        return redirect('/login?' . http_build_query(['login_error' => $message]));
    }
}

