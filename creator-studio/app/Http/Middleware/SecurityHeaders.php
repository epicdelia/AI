<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Security headers on every response, and a Content Security Policy on every HTML page (the
 * studio and landing page shell, the link-in-bio page). Scripts only from this site plus the
 * page's own inline scripts, allowed by their hash (computed here, so editing one never breaks
 * the page). Frames only from YouTube (the hero's video player); connections only to this site
 * and AssemblyAI's streaming (dictation). Images and media may come from any https host:
 * thumbnails and post media live on YouTube, social CDNs and her uploads.
 */
class SecurityHeaders
{
    public const FRAMES = ['https://www.youtube-nocookie.com', 'https://www.youtube.com'];

    public const CONNECT = ['wss://streaming.assemblyai.com', 'https://streaming.assemblyai.com'];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $h = $response->headers;

        $h->set('X-Content-Type-Options', 'nosniff', false);
        $h->set('Referrer-Policy', 'strict-origin-when-cross-origin', false);
        // Dictation and voice input need the microphone; nothing here needs location or payments APIs.
        $h->set('Permissions-Policy', 'microphone=(self), camera=(self), geolocation=(), payment=(), usb=()', false);

        if (! $h->has('Content-Security-Policy') && str_contains((string) $h->get('Content-Type', 'text/html'), 'text/html')) {
            $h->set('Content-Security-Policy', self::policy(self::html($response)));
        }

        return $response;
    }

    public static function policy(string $html): string
    {
        $scripts = ["'self'", ...self::inlineScriptHashes($html)];

        return implode('; ', [
            "default-src 'self'",
            'script-src '.implode(' ', $scripts),
            // The UI sets inline styles all over (positions, widths, accent colours).
            "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com",
            "font-src 'self' data: https://fonts.gstatic.com",
            "img-src 'self' data: blob: https:",
            "media-src 'self' data: blob: https:",
            "connect-src 'self' ".implode(' ', self::CONNECT),
            'frame-src '.implode(' ', self::FRAMES),
            "worker-src 'self' blob:",
            "manifest-src 'self'",
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'self'",
        ]);
    }

    /** sha256 of each <script> without a src, the way browsers hash them. */
    public static function inlineScriptHashes(string $html): array
    {
        preg_match_all('#<script\b(?![^>]*\bsrc\s*=)[^>]*>(.*?)</script>#is', $html, $m);

        return array_values(array_unique(array_map(fn ($code) => "'sha256-".base64_encode(hash('sha256', $code, true))."'", $m[1])));
    }

    /** The body, also for the SPA shell (sent as a file, so not in getContent()). */
    private static function html(Response $response): string
    {
        if ($response instanceof \Symfony\Component\HttpFoundation\BinaryFileResponse) {
            return (string) @file_get_contents($response->getFile()->getPathname());
        }

        return (string) $response->getContent();
    }
}
