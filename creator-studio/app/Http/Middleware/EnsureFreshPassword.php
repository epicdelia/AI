<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The most sensitive studio actions (downloading the whole database, exporting subscribers,
 * changing API keys) ask for the password again if it wasn't typed in the last 15 minutes, so a
 * session left open or stolen can't walk off with everything. The dashboard asks and retries
 * (core.js: api() and freshPassword()).
 */
class EnsureFreshPassword
{
    public const MINUTES = 15;

    public function handle(Request $request, Closure $next): Response
    {
        if (! self::fresh($request)) {
            return response()->json(['error' => 'Please enter your password again to continue.', 'password_confirm_required' => true], 423);
        }

        return $next($request);
    }

    public static function fresh(Request $request): bool
    {
        $at = (int) $request->session()->get('auth.password_confirmed_at', 0);

        return $at > 0 && time() - $at < self::MINUTES * 60;
    }
}
