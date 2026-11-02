<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * A disabled account is signed out on its next request, even with a live session.
 */
class EnsureActive
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->isDisabled()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return response()->json([
                'error' => 'This account has been switched off. Contact the studio if that’s a mistake.',
            ], 401);
        }

        return $next($request);
    }
}
