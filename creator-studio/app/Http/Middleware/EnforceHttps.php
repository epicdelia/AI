<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * On a public server (FORCE_HTTPS=true): every plain-http request is sent to https, so session
 * cookies (Secure) and passwords never travel unencrypted. The health check stays reachable.
 */
class EnforceHttps
{
    public function handle(Request $request, Closure $next): Response
    {
        if (config('app.force_https') && !$request->secure() && !$request->is('up')) {
            return redirect()->secure($request->getRequestUri(), 301);
        }

        return $next($request);
    }
}
