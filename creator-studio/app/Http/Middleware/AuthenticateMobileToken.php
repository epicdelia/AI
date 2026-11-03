<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * The native app: `Authorization: Bearer <token>` on /api/* signs the request in as the
 * token's owner for that request only (no session). Only the token's SHA-256 is stored;
 * expired tokens and switched-off accounts are refused.
 */
class AuthenticateMobileToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();
        if ($token && $request->is('api/*') && strlen($token) >= 40 && strlen($token) <= 100) {
            $row = DB::table('mobile_tokens')->where('token_hash', hash('sha256', $token))->first();
            if (!$row || ($row->expires_at && now()->greaterThan($row->expires_at))) {
                return response()->json(['error' => 'Your session on this device has ended. Sign in again.'], 401);
            }
            $user = User::find($row->user_id);
            if (!$user || $user->isDisabled()) {
                return response()->json(['error' => 'This account has been switched off.'], 403);
            }
            Auth::guard('web')->setUser($user);
            $request->attributes->set('mobile_token_id', $row->id);
            if (!$row->last_used_at || now()->diffInMinutes($row->last_used_at, true) > 10) {
                DB::table('mobile_tokens')->where('id', $row->id)->update(['last_used_at' => now()]);
            }
        }

        return $next($request);
    }
}
