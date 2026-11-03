<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Fall back database session to file if sessions table is not yet migrated (prevents crash on /install)
        $middleware->web(prepend: [
            \App\Http\Middleware\EnsureSessionDriverAvailable::class,
        ]);
        // Security headers everywhere, and a Content Security Policy on HTML pages.
        $middleware->append(\App\Http\Middleware\SecurityHeaders::class);
        // FORCE_HTTPS=true (public server): plain http is redirected before anything else runs.
        $middleware->prepend(\App\Http\Middleware\EnforceHttps::class);
        // Behind a load balancer or Cloudflare: TRUSTED_PROXIES=* (or their IPs) so https is detected.
        if ($proxies = env('TRUSTED_PROXIES')) {
            $middleware->trustProxies(at: $proxies === '*' ? '*' : explode(',', $proxies));
        }
        $middleware->validateCsrfTokens(except: [
            'api/*',
        ]);
        // There's no "login" route (the SPA shows sign-in itself): signed-out API calls get a 401,
        // even plain ones like an <img> or a download, instead of a crash looking for that route.
        $middleware->redirectGuestsTo(fn (Request $request) => $request->is('api/*') ? null : '/');
        // Roles (App\Enums\Role): `ability:<name>` guards a route, `active` signs out disabled
        // accounts, `two-factor` holds admins until their second factor is on. The ability check
        // runs before route model binding, so a client probing studio URLs only ever sees 403.
        $middleware->alias([
            'ability' => \App\Http\Middleware\EnsureAbility::class,
            'active' => \App\Http\Middleware\EnsureActive::class,
            'two-factor' => \App\Http\Middleware\EnsureTwoFactor::class,
            'bots' => \App\Http\Middleware\BlockBots::class,
            'fresh-password' => \App\Http\Middleware\EnsureFreshPassword::class,
        ]);
        $middleware->prependToPriorityList(\Illuminate\Routing\Middleware\SubstituteBindings::class, \App\Http\Middleware\EnsureAbility::class);
        $middleware->prependToPriorityList(\App\Http\Middleware\EnsureAbility::class, \App\Http\Middleware\EnsureTwoFactor::class);
        $middleware->prependToPriorityList(\App\Http\Middleware\EnsureTwoFactor::class, \App\Http\Middleware\EnsureActive::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
