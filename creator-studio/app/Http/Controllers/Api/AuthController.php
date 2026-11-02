<?php

namespace App\Http\Controllers\Api;

use App\Domains\Agents\Models\AgentEvent;
use App\Domains\Auth\TwoFactor;
use App\Domains\Clients\ClientLink;
use App\Domains\Clients\DemoClient;
use App\Domains\Security\Audit;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rules\Password;
use Throwable;

/**
 * Signing in and out, for her (the admin) and her clients. Built for a public server:
 * - the first install creates the admin; public sign-up only ever creates clients (Role::forSignUp)
 * - clients prove their email before anything is linked to them (ClientLink)
 * - accounts with two-factor sign-in need a code after the password (TwoFactor)
 * - answers never reveal whether an email has an account
 */
class AuthController extends Controller
{
    /**
     * How long the password step stays valid while the authenticator code is asked for.
     */
    private const TWO_FACTOR_WINDOW = 300;

    public function setup(Request $request): JsonResponse
    {
        if (User::count() > 0) {
            return response()->json([
                'error' => 'Application is already initialized.',
            ], 403);
        }

        $data = $request->validate([
            'name' => 'required|string|max:100',
            'email' => 'required|email|max:200',
            'password' => ['required', 'confirmed', self::passwordRule()],
        ]);

        $user = new User([
            'name' => $data['name'],
            'email' => strtolower($data['email']),
            'password' => $data['password'],
        ]);
        $user->forceFill([
            'role' => Role::Admin,
            'email_verified_at' => now(),
        ])->save();

        if (!Setting::has('creator_name')) {
            Setting::put('creator_name', $data['name']);
        }

        $this->signIn($request, $user, true);

        return response()->json([
            'message' => "Welcome, {$user->name}! One last step: turn on two-factor sign-in.",
            'user' => $user->toSession(),
        ]);
    }

    /**
     * Client sign-up. Always the same answer, so it can't be used to find out who has an account.
     */
    public function register(Request $request): JsonResponse
    {
        if (User::count() === 0) {
            return response()->json([
                'error' => 'Set up the studio first.',
            ], 409);
        }

        $data = $request->validate([
            'name' => 'required|string|max:100',
            'email' => 'required|email|max:200',
            'company' => 'nullable|string|max:150',
            'password' => ['required', 'confirmed', self::passwordRule()],
            'terms' => 'accepted',
        ]);
        $email = strtolower(trim($data['email']));

        $existing = User::whereRaw('lower(email) = ?', [$email])->first();
        if ($existing) {
            self::mail(
                $email,
                'Someone tried to sign up with your email',
                'Someone (hopefully you) tried to create a client account at ' . url('/') . " with this email, but you already have one.\n\nSign in at " . url('/login') . ", or use \"Forgot password?\" there.\n\nIf it wasn't you, you can ignore this email."
            );
        } else {
            $user = new User([
                'name' => $data['name'],
                'email' => $email,
                'password' => $data['password'],
                'company' => $data['company'] ?? null,
            ]);
            $user->forceFill([
                'role' => Role::forSignUp(),
            ])->save();
            rescue(fn () => $user->sendEmailVerificationNotification(), null, true);
            AgentEvent::log('lead', "New client sign-up: {$user->name} ({$user->email}), waiting for email confirmation.");
        }

        return response()->json([
            'ok' => true,
            'message' => "Almost there! We've sent a confirmation link to {$email}. Open it, then sign in.",
        ], 202);
    }

    /**
     * The link from the confirmation email (signed, expires after 60 minutes).
     */
    public function verifyEmail(Request $request, int $id, string $hash): RedirectResponse
    {
        $user = User::find($id);
        if (!$user || !hash_equals(sha1($user->getEmailForVerification()), $hash)) {
            return redirect('/login?' . http_build_query([
                'login_error' => 'That confirmation link isn’t valid. Sign in to get a new one.',
            ]));
        }
        if (!$user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
            $linked = ClientLink::claim($user);
            AgentEvent::log('lead', "Client account confirmed: {$user->name} ({$user->email})" . ($linked ? ", {$linked} request(s) linked." : '.'));
        }

        return redirect('/login?verified=1');
    }

    public function resendVerification(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user->hasVerifiedEmail()) {
            return response()->json([
                'message' => 'Your email is already confirmed.',
            ]);
        }
        rescue(fn () => $user->sendEmailVerificationNotification(), null, true);

        return response()->json([
            'message' => "A new confirmation link is on its way to {$user->email}.",
        ]);
    }

    public function login(Request $request): JsonResponse
    {
        $creds = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);
        $remember = $request->boolean('remember', true);

        // Brute-force guard, after MagicAI's Helpers\RateLimiter (attempts per identifier,
        // cached with a decay window): 5 wrong passwords per email+IP locks login for 15 minutes.
        $key = 'login:' . strtolower($creds['email']) . '|' . $request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            $minutes = (int)ceil(RateLimiter::availableIn($key) / 60);

            return response()->json([
                'error' => "Too many attempts. Try again in {$minutes} minute(s).",
            ], 429);
        }

        $user = User::whereRaw('lower(email) = ?', [strtolower(trim($creds['email']))])->first();
        if (!$user || !Hash::check($creds['password'], $user->password)) {
            RateLimiter::hit($key, 15 * 60);
            if ($user) {
                Audit::log('login_failed', $user);
            }

            return response()->json([
                'error' => 'Incorrect email or password.',
            ], 401);
        }
        RateLimiter::clear($key);

        if ($user->isDisabled()) {
            return response()->json([
                'error' => 'This account has been switched off. Contact the studio if that’s a mistake.',
            ], 403);
        }

        if ($user->hasTwoFactor()) {
            $request->session()->put('two_factor', [
                'id' => $user->id,
                'remember' => $remember,
                'until' => time() + self::TWO_FACTOR_WINDOW,
            ]);

            return response()->json([
                'two_factor' => true,
                'message' => 'Enter the 6-digit code from your authenticator app.',
            ]);
        }

        $this->signIn($request, $user, $remember);

        return response()->json([
            'message' => 'Logged in successfully.',
            'user' => $user->toSession(),
        ]);
    }

    /**
     * Second step: the authenticator code (or a recovery code) after a correct password.
     */
    public function twoFactorChallenge(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => 'required|string|max:32',
        ]);
        $pending = $request->session()->get('two_factor');
        $user = $pending && $pending['until'] >= time() ? User::find($pending['id']) : null;
        if (!$user || $user->isDisabled()) {
            $request->session()->forget('two_factor');

            return response()->json([
                'error' => 'That took too long. Please sign in again.',
            ], 401);
        }

        $key = 'two-factor:' . $user->id;
        if (RateLimiter::tooManyAttempts($key, 5)) {
            $request->session()->forget('two_factor');

            return response()->json([
                'error' => 'Too many wrong codes. Wait a few minutes, then sign in again.',
            ], 429);
        }
        if (!TwoFactor::check($user, $data['code'])) {
            RateLimiter::hit($key, 15 * 60);
            Audit::log('two_factor_failed', $user);

            return response()->json([
                'error' => 'That code didn’t work. Check the time on your phone and try the newest code.',
            ], 422);
        }
        RateLimiter::clear($key);
        $request->session()->forget('two_factor');
        $this->signIn($request, $user, (bool)$pending['remember']);

        $left = TwoFactor::recoveryCodesLeft($user);

        return response()->json([
            'message' => 'Logged in successfully.',
            'user' => $user->toSession(),
        ]
            + ($left <= 2 ? [
                'warning' => "Only {$left} recovery code(s) left: make new ones in Account.",
            ] : []));
    }

    /**
     * One click into the demo client account (only while DEMO_CLIENT_LOGIN is on).
     */
    public function demoClient(Request $request): JsonResponse
    {
        abort_unless(DemoClient::enabled(), 404);
        $user = DemoClient::account();
        $this->signIn($request, $user, false);

        return response()->json([
            'message' => 'Signed in as the demo client.',
            'user' => $user->toSession(),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json([
            'message' => 'Logged out.',
        ]);
    }

    public function forgotPassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => 'required|email',
        ]);

        // The code only ever leaves the server by email (or the server log when mail isn't set
        // up), and the answer is the same whether or not the account exists.
        $email = strtolower(trim($data['email']));
        $user = User::whereRaw('lower(email) = ?', [$email])->first();
        if ($user && !$user->isDisabled()) {
            $code = (string)random_int(100000, 999999);

            DB::table('password_reset_tokens')->updateOrInsert(
                [
                    'email' => $user->email,
                ],
                [
                    'token' => Hash::make($code),
                    'created_at' => now(),
                ]
            );
            RateLimiter::clear('reset:' . strtolower($user->email));

            if (!self::mail($user->email, 'Your Creator Studio reset code', "Your Creator Studio reset code is {$code}. It expires in 60 minutes.\n\nIf you didn't ask for this, ignore this email.")) {
                Log::warning("Password reset email failed. Reset code for {$user->email}: {$code}");
            }
        }

        return response()->json([
            'ok' => true,
            'message' => 'If that email has an account, a 6-digit code is on its way. No email set up? Run "php artisan studio:password" on the server.',
        ]);
    }

    /**
     * A new password signs out every session; they sign in again (with two-factor, if on).
     */
    public function resetPassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => 'required|email',
            'code' => 'required|string',
            'password' => ['required', 'confirmed', self::passwordRule()],
        ]);

        $user = User::whereRaw('lower(email) = ?', [strtolower(trim($data['email']))])->first();
        $record = $user ? DB::table('password_reset_tokens')->where('email', $user->email)->first() : null;
        $email = $user?->email;

        if (!$record) {
            return response()->json([
                'error' => 'No password reset requested for this account.',
            ], 400);
        }

        if (CarbonImmutable::parse($record->created_at)->addMinutes(60)->isPast()) {
            return response()->json([
                'error' => 'The reset code has expired. Please request a new one.',
            ], 400);
        }

        // A 6-digit code can't survive guessing: 5 wrong tries burn it.
        $limiterKey = 'reset:' . strtolower($email);
        if (!Hash::check(trim($data['code']), $record->token)) {
            RateLimiter::hit($limiterKey, 60 * 60);
            if (RateLimiter::tooManyAttempts($limiterKey, 5)) {
                DB::table('password_reset_tokens')->where('email', $email)->delete();

                return response()->json([
                    'error' => 'Too many wrong codes. Please request a new one.',
                ], 429);
            }

            return response()->json([
                'error' => 'Invalid verification code. Please check and try again.',
            ], 422);
        }
        RateLimiter::clear($limiterKey);

        $user->forceFill([
            'password' => Hash::make($data['password']),
            'remember_token' => null,
        ])->save();
        DB::table('password_reset_tokens')->where('email', $email)->delete();
        self::endSessions($user);
        Audit::log('password_reset', $user);

        return response()->json([
            'ok' => true,
            'message' => 'Password changed. Sign in with your new password.',
        ]);
    }

    // ------------------------------------------------------------ shared helpers

    /**
     * At least 10 characters with letters and numbers.
     */
    public static function passwordRule(): Password
    {
        return Password::min(10)->letters()->numbers();
    }

    /**
     * Sign out every session of a user (all devices), except optionally the current one.
     */
    public static function endSessions(User $user, ?string $keepSessionId = null): void
    {
        $user->forceFill([
            'remember_token' => null,
        ])->saveQuietly();
        if (config('session.driver') === 'database') {
            DB::table(config('session.table', 'sessions'))->where('user_id', $user->id)
                ->when($keepSessionId, fn ($q) => $q->where('id', '!=', $keepSessionId))->delete();
        }
    }

    /**
     * Plain-text email through the configured SMTP server; false (and logged) when it fails.
     */
    public static function mail(string $to, string $subject, string $body): bool
    {
        try {
            $fromAddress = config('mail.from.address');
            if (empty($fromAddress) || $fromAddress === 'null') {
                $fromAddress = 'hello@example.com';
            }
            $fromName = config('mail.from.name') ?: 'Creator Studio';

            Mail::raw($body, function ($m) use ($to, $subject, $fromAddress, $fromName) {
                $m->from($fromAddress, $fromName)->to($to)->subject($subject);
            });

            return true;
        } catch (Throwable $e) {
            Log::warning("Email to {$to} failed: {$e->getMessage()}");

            return false;
        }
    }

    private function signIn(Request $request, User $user, bool $remember): void
    {
        Auth::login($user, $remember);
        $request->session()->regenerate();
        $user->forceFill([
            'last_login_at' => now(),
        ])->saveQuietly();
        // The security log, and an email when this account signs in from a new device.
        if ($user->email !== DemoClient::EMAIL) {
            Audit::signedIn($user, $request);
        }
    }
}
