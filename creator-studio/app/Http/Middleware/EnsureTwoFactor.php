<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Roles that require two-factor sign-in (Role::requiresTwoFactor: admins) can do nothing but set
 * it up until it's on. The dashboard shows the setup screen when it gets this answer.
 */
class EnsureTwoFactor
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user && $user->role()->requiresTwoFactor() && !$user->hasTwoFactor()) {
            return response()->json([
                'error' => 'Turn on two-factor sign-in first.',
                'two_factor_setup_required' => true,
            ], 403);
        }

        return $next($request);
    }
}
