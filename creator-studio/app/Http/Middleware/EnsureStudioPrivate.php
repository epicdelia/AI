<?php

namespace App\Http\Middleware;

use App\Enums\Role;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * On the studio group: staff work in the content studio, but her money, website, keys,
 * settings, backups and love pages (Role::PRIVATE_PATHS) need Role::STUDIO_PRIVATE.
 * One list, checked on every studio request, so a new private route can't be forgotten
 * in a route group.
 */
class EnsureStudioPrivate
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user && !$user->hasAbility(Role::STUDIO_PRIVATE) && Role::isPrivatePath($request->method(), $request->path())) {
            return response()->json([
                'error' => 'Only the studio owner can open this.',
            ], 403);
        }

        return $next($request);
    }
}
