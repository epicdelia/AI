<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * `ability:studio`, `ability:portal.requests`…: the signed-in user's role (App\Enums\Role) must
 * grant it. Runs before route model binding, so a client asking for a studio record learns
 * nothing (always 403, never 404).
 */
class EnsureAbility
{
    public function handle(Request $request, Closure $next, string ...$abilities): Response
    {
        $user = $request->user();
        foreach ($abilities as $ability) {
            if (!$user || !$user->hasAbility($ability)) {
                return response()->json([
                    'error' => 'You don’t have access to this.',
                ], 403);
            }
        }

        return $next($request);
    }
}
