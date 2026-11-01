<?php

namespace App\Domains\Deals;

use App\Models\Deal;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Brands paying for videos in her niche. Every breakout scan reads the descriptions of the
 * competitor uploads it already fetches; lines like "This video is sponsored by Acme" or a
 * sponsor link with a discount code become sightings. Aggregated, that's a list of brands
 * with a budget for her audience, ranked by how many channels they sponsor. No extra API calls.
 */
class SponsorProspects
{
    private const PHRASES = [
        '/(?:this (?:video|episode) is |video )?sponsored by:?\s+(?<b>[^\n.!,;:()|]{2,40})/iu',
        '/thanks?(?: you)?(?: so much)? to\s+(?<b>[^\n.!,;:()|]{2,40}?)\s+for (?:sponsoring|supporting|partnering)/iu',
        '/brought to you by:?\s+(?<b>[^\n.!,;:()|]{2,40})/iu',
        '/in (?:paid )?partnership with:?\s+(?<b>[^\n.!,;:()|]{2,40})/iu',
        '/today\'?s sponsor(?: is)?:?\s+(?<b>[^\n.!,;:()|]{2,40})/iu',
    ];

    /** Domains that are platforms or link tools, never the sponsor. */
    private const NOT_SPONSORS = [
        'youtube.com', 'youtu.be', 'twitter.com', 'x.com', 'instagram.com', 'linkedin.com', 'tiktok.com', 'facebook.com',
        'github.com', 'patreon.com', 'discord.gg', 'discord.com', 'threads.net', 'bit.ly', 'linktr.ee', 'amzn.to', 'amazon.com',
        'goo.gl', 'buymeacoffee.com', 'ko-fi.com', 'substack.com', 'medium.com', 'twitch.tv', 'spotify.com', 'apple.com', 'google.com',
    ];

    /** @return list<array{brand: string, domain: ?string, snippet: string}> */
    public static function detect(?string $description): array
    {
        $found = [];
        foreach (preg_split('/\R/u', (string) $description) as $line) {
            $line = trim($line);
            if ($line === '' || mb_strlen($line) > 400) {
                continue;
            }
            $domain = self::sponsorDomain($line);
            foreach (self::PHRASES as $re) {
                if (preg_match($re, $line, $m) && ($brand = self::cleanBrand($m['b']))) {
                    $found[self::key($brand)] = ['brand' => $brand, 'domain' => $domain, 'snippet' => mb_substr($line, 0, 300)];
                    continue 2;
                }
            }
            // "Use code TECH20 at acme.dev for 20% off" / "Try Acme free: https://acme.dev/?ref=chan"
            if ($domain && preg_match('/\bcode\b|\bcoupon\b|%\s?off\b|\bfree trial\b|\bdiscount\b|\bsponsor|\baffiliate\b|[?&](?:ref|utm_source|via)=/iu', $line)) {
                $brand = self::brandFromDomain($domain);
                $found[self::key($brand)] ??= ['brand' => $brand, 'domain' => $domain, 'snippet' => mb_substr($line, 0, 300)];
            }
        }

        return array_values($found);
    }

    /** Save what a scan saw: $videos are the scanner's video rows with a description. */
    public static function record(array $videos, ?string $channelTitle = null): int
    {
        $rows = [];
        foreach ($videos as $v) {
            foreach (self::detect($v['description'] ?? null) as $s) {
                $rows[] = [
                    'brand' => $s['brand'], 'domain' => $s['domain'], 'video_id' => $v['id'], 'video_title' => mb_substr((string) ($v['title'] ?? ''), 0, 255),
                    'channel_title' => $v['channel'] ?? $channelTitle, 'views' => (int) ($v['views'] ?? 0),
                    'published_at' => isset($v['published_at']) ? CarbonImmutable::parse($v['published_at']) : null,
                    'snippet' => $s['snippet'], 'created_at' => now(), 'updated_at' => now(),
                ];
            }
        }

        return $rows ? DB::table('sponsor_sightings')->insertOrIgnore($rows) : 0;
    }

    /** Brands ranked by reach in her niche, with whether they're already in her pipeline. */
    public static function list(int $days = 180, int $limit = 30): array
    {
        $rows = DB::table('sponsor_sightings')->where('created_at', '>=', now()->subDays($days))->orderByDesc('published_at')->get();
        $deals = Deal::get(['id', 'brand_name', 'stage'])->keyBy(fn ($d) => self::key($d->brand_name));

        return $rows->groupBy(fn ($r) => self::key($r->brand))->map(function ($g, $key) use ($deals) {
            $latest = $g->first();
            $deal = $deals->get($key);

            return [
                'brand' => $latest->brand,
                'domain' => $g->pluck('domain')->filter()->first(),
                'mentions' => $g->count(),
                'channels' => $g->pluck('channel_title')->filter()->unique()->values()->all(),
                'views' => (int) $g->sum('views'),
                'last_seen' => $latest->published_at,
                'example' => ['video_id' => $latest->video_id, 'title' => $latest->video_title, 'url' => "https://youtu.be/{$latest->video_id}", 'snippet' => $latest->snippet],
                'deal' => $deal ? ['id' => $deal->id, 'stage' => $deal->stage] : null,
            ];
        })->sortByDesc(fn ($p) => [count($p['channels']), $p['mentions'], $p['views']])->values()->take($limit)->all();
    }

    public static function key(string $brand): string
    {
        return preg_replace('/[^a-z0-9]/', '', strtolower($brand));
    }

    private static function cleanBrand(string $raw): ?string
    {
        $b = trim(preg_replace('/\s+(?:for|to|and|who|which|with|at|on|-|–|—).*$/iu', '', trim($raw)), " \t-–—'\"*");
        $b = preg_replace('/^(?:the|our friends at|my friends at)\s+/iu', '', $b);
        $words = preg_split('/\s+/u', $b);
        if ($b === '' || count($words) > 4 || mb_strlen($b) < 2 || preg_match('/^(?:me|us|you|my|this|our|viewers?|people)$/iu', $b)) {
            return null;
        }

        return implode(' ', $words);
    }

    private static function sponsorDomain(string $line): ?string
    {
        if (! preg_match_all('~(?:https?://)?(?:www\.)?((?:[a-z0-9-]+\.)+[a-z]{2,})(?:/[^\s]*)?~i', $line, $m)) {
            return null;
        }
        foreach ($m[1] as $host) {
            $host = strtolower($host);
            $root = implode('.', array_slice(explode('.', $host), -2));
            if (! in_array($root, self::NOT_SPONSORS, true) && ! in_array($host, self::NOT_SPONSORS, true)) {
                return $host;
            }
        }

        return null;
    }

    private static function brandFromDomain(string $domain): string
    {
        $labels = array_values(array_filter(explode('.', $domain), fn ($l) => ! in_array($l, ['www', 'go', 'try', 'get', 'join', 'app'], true)));

        return ucfirst($labels[0] ?? $domain);
    }
}
