<?php

namespace App\Http\Controllers;

use App\Domains\Social\Connect\YouTubeProvider;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Throwable;

/**
 * "Login with Google": reuses the Google app she set up for YouTube (Connections). Only the
 * studio's own account can sign in: Google must confirm the same, verified email address.
 */
class GoogleLoginController extends Controller
{
    public static function available(): bool
    {
        return app(YouTubeProvider::class)->configured();
    }

    public static function redirectUri(): string
    {
        return rtrim((string) config('app.url'), '/').'/auth/google/callback';
    }

    public function redirect(Request $request): RedirectResponse
    {
        $p = app(YouTubeProvider::class);
        if (! $p->configured()) {
            return $this->fail('Google sign-in isn’t set up: add the Google app keys under Connections → YouTube first.');
        }
        $state = Str::random(40);
        $verifier = Str::random(64);
        $request->session()->put('google_login', ['state' => $state, 'verifier' => $verifier, 'remember' => $request->boolean('remember', true)]);

        return redirect('https://accounts.google.com/o/oauth2/v2/auth?'.http_build_query([
            'client_id' => $p->clientId(), 'redirect_uri' => self::redirectUri(), 'response_type' => 'code',
            'scope' => 'openid email', 'state' => $state, 'prompt' => 'select_account',
            'code_challenge' => rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '='), 'code_challenge_method' => 'S256',
        ]));
    }

    public function callback(Request $request): RedirectResponse
    {
        $saved = $request->session()->pull('google_login');
        if (! $saved || ! hash_equals($saved['state'], (string) $request->query('state'))) {
            return $this->fail('That sign-in link expired. Try again.');
        }
        if ($request->filled('error') || ! $request->filled('code')) {
            return $this->fail('Google sign-in was cancelled.');
        }
        $key = 'google-login|'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 10)) {
            return $this->fail('Too many attempts. Try again in a few minutes.');
        }
        $p = app(YouTubeProvider::class);
        try {
            $token = Http::asForm()->timeout(15)->post('https://oauth2.googleapis.com/token', [
                'code' => $request->query('code'), 'client_id' => $p->clientId(), 'client_secret' => $p->clientSecret(),
                'redirect_uri' => self::redirectUri(), 'grant_type' => 'authorization_code', 'code_verifier' => $saved['verifier'],
            ])->throw()->json('access_token');
            $info = Http::withToken((string) $token)->timeout(15)->get('https://openidconnect.googleapis.com/v1/userinfo')->throw()->json();
        } catch (Throwable) {
            RateLimiter::hit($key, 15 * 60);

            return $this->fail('Google didn’t confirm the sign-in. Try again, or use your password.');
        }
        $email = strtolower((string) ($info['email'] ?? ''));
        $user = $email !== '' && ($info['email_verified'] ?? false) ? User::whereRaw('lower(email) = ?', [$email])->first() : null;
        if (! $user) {
            RateLimiter::hit($key, 15 * 60);

            return $this->fail('That Google account isn’t this studio’s account. Use the Google account with the same email as your login.');
        }
        RateLimiter::clear($key);
        Auth::login($user, (bool) ($saved['remember'] ?? true));
        $request->session()->regenerate();

        return redirect('/');
    }

    private function fail(string $message): RedirectResponse
    {
        return redirect('/?'.http_build_query(['login_error' => $message]));
    }
}
