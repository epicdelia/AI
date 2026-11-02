<?php

namespace App\Domains\Landing;

use App\Domains\Social\Models\SocialPost;

/**
 * Where a website lead came from. The landing page sends what it saw on arrival (?ref=,
 * utm_* tags, the referring site); this turns it into one channel (youtube, linkedin,
 * search, direct…) and, when a link said so, the exact video or post. Nothing about the
 * visitor is kept beyond that.
 *
 * Tracked links use ?ref=: yt-<video id>, post-<social post id>, or a channel name
 * (li, ig, x, tt, th, bio, nl, email).
 */
class Attribution
{
    public const KEYS = ['ref', 'utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'referrer', 'landing_path', 'last_item'];

    public const LABELS = [
        'youtube' => 'YouTube',
        'linkedin' => 'LinkedIn',
        'instagram' => 'Instagram',
        'x' => 'X',
        'tiktok' => 'TikTok',
        'threads' => 'Threads',
        'newsletter' => 'Newsletter',
        'email' => 'Email',
        'link_in_bio' => 'Link in bio',
        'media_kit' => 'Media kit',
        'search' => 'Search',
        'referral' => 'Other sites',
        'direct' => 'Direct',
        'other' => 'Other',
    ];

    /**
     * Short names a ?ref= or utm_source can use.
     */
    private const ALIASES = [
        'yt' => 'youtube',
        'youtube' => 'youtube',
        'li' => 'linkedin',
        'linkedin' => 'linkedin',
        'ig' => 'instagram',
        'instagram' => 'instagram',
        'x' => 'x',
        'tw' => 'x',
        'twitter' => 'x',
        'tt' => 'tiktok',
        'tiktok' => 'tiktok',
        'th' => 'threads',
        'threads' => 'threads',
        'bio' => 'link_in_bio',
        'links' => 'link_in_bio',
        'nl' => 'newsletter',
        'newsletter' => 'newsletter',
        'substack' => 'newsletter',
        'email' => 'email',
        'kit' => 'media_kit',
        'post' => 'other',
        'google' => 'search',
        'bing' => 'search',
    ];

    /**
     * Referring host (suffix) => channel.
     */
    private const HOSTS = [
        'youtube.com' => 'youtube',
        'youtu.be' => 'youtube',
        'linkedin.com' => 'linkedin',
        'lnkd.in' => 'linkedin',
        'instagram.com' => 'instagram',
        'x.com' => 'x',
        'twitter.com' => 'x',
        't.co' => 'x',
        'tiktok.com' => 'tiktok',
        'threads.net' => 'threads',
        'threads.com' => 'threads',
        'substack.com' => 'newsletter',
        'google.com' => 'search',
        'bing.com' => 'search',
        'duckduckgo.com' => 'search',
        'yahoo.com' => 'search',
        'ecosia.org' => 'search',
        'perplexity.ai' => 'search',
        'chatgpt.com' => 'search',
    ];

    /**
     * Validation rules for the `attribution` object the landing page posts with a form.
     */
    public static function rules(): array
    {
        $rules = [
            'attribution' => 'nullable|array',
        ];
        foreach (self::KEYS as $k) {
            $rules["attribution.{$k}"] = 'nullable|string|max:300';
        }

        return $rules;
    }

    /**
     * @return array{source: string, attribution: ?array}
     */
    public static function resolve(?array $raw, ?string $ownHost = null): array
    {
        $a = [];
        foreach (self::KEYS as $k) {
            $v = $raw[$k] ?? null;
            if (is_string($v) && trim($v) !== '') {
                $a[$k] = mb_substr(trim($v), 0, 300);
            }
        }
        if (isset($a['referrer']) && self::host($a['referrer']) === self::normalHost($ownHost ?? (string)parse_url((string)config('app.url'), PHP_URL_HOST))) {
            unset($a['referrer']); // moving around her own site isn't a source
        }
        $source = self::classify($a);
        if ($item = self::item($a)) {
            $a['item'] = $item;
            // ?ref=post-12: credit the network that post went out on.
            if (str_starts_with($item, 'post:') && in_array($source, ['other', 'direct'], true)) {
                $platform = SocialPost::whereKey((int)substr($item, 5))->value('platform');
                $source = $platform && isset(self::LABELS[$platform]) ? $platform : $source;
            }
        }

        return [
            'source' => $source,
            'attribution' => $a ?: null,
        ];
    }

    public static function classify(array $a): string
    {
        $ref = strtolower($a['ref'] ?? '');
        if ($ref !== '') {
            $prefix = preg_split('/[-_:.]/', $ref)[0];
            if (isset(self::ALIASES[$prefix])) {
                return self::ALIASES[$prefix];
            }
        }
        $utm = strtolower($a['utm_source'] ?? '');
        if ($utm !== '') {
            return self::ALIASES[$utm] ?? (self::fromHost($utm) ?? 'other');
        }
        if (!empty($a['referrer'])) {
            return self::fromHost(self::host($a['referrer'])) ?? 'referral';
        }

        return $ref !== '' ? 'other' : 'direct';
    }

    /**
     * The content item a tracked link points at: "yt:<id>" or "post:<id>".
     */
    public static function item(array $a): ?string
    {
        foreach ([$a['ref'] ?? '', $a['utm_content'] ?? ''] as $v) {
            if (preg_match('/^(?:yt|youtube)[-_:]([\w-]{11})$/i', $v, $m)) {
                return 'yt:' . $m[1];
            }
            if (preg_match('/^(?:post|li|ig|x|tt|th)[-_:](\d{1,10})$/i', $v, $m)) {
                return 'post:' . $m[1];
            }
        }

        return null;
    }

    /**
     * A link to her site that says where it was shared, e.g. ?ref=yt-abc123def45.
     */
    public static function trackedUrl(string $base, string $ref): string
    {
        $parts = parse_url($base);
        if ($parts === false || empty($parts['host'])) {
            return $base;
        }
        parse_str($parts['query'] ?? '', $q);
        $q['ref'] = $ref;
        $fragment = isset($parts['fragment']) ? '#' . $parts['fragment'] : '';

        return ($parts['scheme'] ?? 'https') . '://' . $parts['host'] . (isset($parts['port']) ? ':' . $parts['port'] : '')
            . ($parts['path'] ?? '/') . '?' . http_build_query($q) . $fragment;
    }

    public static function label(?string $source): string
    {
        return self::LABELS[$source ?? 'direct'] ?? ucfirst((string)$source);
    }

    private static function fromHost(?string $host): ?string
    {
        if (!$host) {
            return null;
        }
        foreach (self::HOSTS as $suffix => $source) {
            if ($host === $suffix || str_ends_with($host, '.' . $suffix)) {
                return $source;
            }
        }

        // google.ca, google.co.uk…
        return preg_match('/(^|\.)google\.[a-z.]+$/', $host) ? 'search' : null;
    }

    private static function host(string $url): ?string
    {
        $host = parse_url(str_contains($url, '://') ? $url : "https://{$url}", PHP_URL_HOST);

        return self::normalHost(is_string($host) ? $host : null);
    }

    private static function normalHost(?string $host): ?string
    {
        return $host ? preg_replace('/^(www\.|m\.)/', '', strtolower($host)) : null;
    }
}
