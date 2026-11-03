<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Public forms (sign-up, booking, checkout, reviews): a hidden `website` field people never see
 * (bots fill it in), and `_t`, how long the page was open before sending (the dashboard adds it;
 * nobody fills a form in under 1.5 s). A bot gets a friendly "thanks" so it doesn't retry
 * differently, and nothing is stored.
 */
class BlockBots
{
    public const MIN_MS = 1500;

    public function handle(Request $request, Closure $next): Response
    {
        $trap = trim((string)$request->input('website', ''));
        $ms = $request->input('_t');
        if ($trap !== '' || (is_numeric($ms) && (int)$ms < self::MIN_MS)) {
            logger()->info('Blocked a bot form post', [
                'path' => $request->path(),
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'ok' => true,
                'message' => 'Thanks! We’ll be in touch.',
            ], 202);
        }

        return $next($request);
    }
}
