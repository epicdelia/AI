<?php

namespace App\Http\Controllers\Api;

use App\Domains\Auth\TwoFactor;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * Sign-in for the native app: email + password (+ the authenticator code when two-factor is
 * on) → a bearer token shown once, valid 90 days. The same brute-force lock as the website.
 * Clients must have confirmed their email first (on the website).
 */
class MobileTokenController extends Controller
{
    public const DAYS = 90;

    public function issue(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
            'code' => 'nullable|string|max:20',
            'device_name' => 'nullable|string|max:100',
        ]);
        $key = 'login:' . strtolower($data['email']) . '|' . $request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            return response()->json(['error' => 'Too many attempts. Try again in ' . (int)ceil(RateLimiter::availableIn($key) / 60) . ' minute(s).'], 429);
        }
        $user = User::whereRaw('lower(email) = ?', [strtolower(trim($data['email']))])->first();
        if (!$user || !Hash::check($data['password'], $user->password)) {
            RateLimiter::hit($key, 15 * 60);

            return response()->json(['error' => 'Incorrect email or password.'], 401);
        }
        if ($user->isDisabled()) {
            return response()->json(['error' => 'This account has been switched off.'], 403);
        }
        if ($user->hasTwoFactor()) {
            if (empty($data['code'])) {
                return response()->json(['two_factor' => true, 'message' => 'Enter the 6-digit code from your authenticator app.'], 202);
            }
            if (!TwoFactor::check($user, $data['code'])) {
                RateLimiter::hit($key, 15 * 60);

                return response()->json(['error' => 'That code didn’t work.'], 401);
            }
        }
        if ($user->needsEmailVerification()) {
            return response()->json(['error' => 'Confirm your email first: open the link we sent you, then sign in here.'], 403);
        }
        RateLimiter::clear($key);

        $token = Str::random(64);
        DB::table('mobile_tokens')->insert([
            'user_id' => $user->id,
            'name' => mb_substr($data['device_name'] ?? 'Mobile app', 0, 100),
            'token_hash' => hash('sha256', $token),
            'expires_at' => now()->addDays(self::DAYS),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'token' => $token,
            'expires_at' => now()->addDays(self::DAYS)->toIso8601String(),
            'user' => $user->toSession(),
        ], 201);
    }

    /**
     * Sign this device out.
     */
    public function revokeCurrent(Request $request): JsonResponse
    {
        $id = $request->attributes->get('mobile_token_id');
        abort_unless($id, 422, 'This request isn’t from the app.');
        DB::table('mobile_tokens')->where('id', $id)->delete();

        return response()->json(['ok' => true]);
    }

    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'devices' => DB::table('mobile_tokens')->where('user_id', $request->user()->id)->latest('id')
                ->get(['id', 'name', 'last_used_at', 'expires_at', 'created_at']),
        ]);
    }

    public function revoke(Request $request, int $id): JsonResponse
    {
        DB::table('mobile_tokens')->where('user_id', $request->user()->id)->where('id', $id)->delete();

        return response()->json(['ok' => true]);
    }
}
