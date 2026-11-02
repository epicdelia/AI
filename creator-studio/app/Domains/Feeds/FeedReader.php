<?php

namespace App\Domains\Feeds;

use App\Domains\Feeds\Models\Feed;
use App\Domains\Feeds\Models\FeedItem;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use SimpleXMLElement;
use Throwable;

/**
 * Port of MagicAI's parseRSS() helper (helpers.php). Same item fields (link, title,
 * description, enclosure image), hardened: HTTP timeout instead of file_get_contents,
 * http(s) only and no private/loopback hosts, Atom as well as RSS 2.0, and dedupe by guid.
 */
class FeedReader
{
    /**
     * @var callable|null test hook: fn(string): string (the XML)
     */
    public static $fake = null;

    /**
     * @return array{title: ?string, items: array<int, array{guid: string, title: string, link: ?string, summary: ?string, image: ?string, published_at: ?CarbonImmutable}>}
     */
    public function parse(string $url, int $limit = 20): array
    {
        $xml = self::$fake ? (self::$fake)($url) : $this->download($url);

        $prev = libxml_use_internal_errors(true);
        $doc = simplexml_load_string($xml, SimpleXMLElement::class, LIBXML_NOCDATA | LIBXML_NONET);
        libxml_use_internal_errors($prev);
        if ($doc === false) {
            throw new RuntimeException("That URL didn't return a valid RSS or Atom feed.");
        }

        $items = [];
        if (isset($doc->channel)) { // RSS 2.0 (MagicAI's original path)
            $title = trim((string)$doc->channel->title) ?: null;
            foreach ($doc->channel->item as $item) {
                if (count($items) >= $limit) {
                    break;
                }
                $media = $item->children('http://search.yahoo.com/mrss/');
                $items[] = $this->item(
                    (string)($item->guid ?: $item->link ?: $item->title),
                    (string)$item->title,
                    (string)$item->link,
                    (string)$item->description,
                    $item->enclosure ? (string)$item->enclosure['url'] : (isset($media->content) ? (string)$media->content->attributes()['url'] : null),
                    (string)$item->pubDate,
                );
            }
        } elseif ($doc->getName() === 'feed') { // Atom
            $title = trim((string)$doc->title) ?: null;
            foreach ($doc->entry as $entry) {
                if (count($items) >= $limit) {
                    break;
                }
                $link = null;
                foreach ($entry->link as $l) {
                    if (!isset($l['rel']) || (string)$l['rel'] === 'alternate') {
                        $link = (string)$l['href'];
                        break;
                    }
                }
                $items[] = $this->item(
                    (string)($entry->id ?: $link ?: $entry->title),
                    (string)$entry->title,
                    $link,
                    (string)($entry->summary ?: $entry->content),
                    null,
                    (string)($entry->published ?: $entry->updated),
                );
            }
        } else {
            throw new RuntimeException("That URL didn't return a valid RSS or Atom feed.");
        }

        return [
            'title' => $title,
            'items' => array_values(array_filter($items, fn ($i) => $i['title'] !== '')),
        ];
    }

    /**
     * Fetch one feed (or use an already-parsed copy) and store new items. Returns how many were new.
     */
    public function refresh(Feed $feed, ?array $parsed = null): int
    {
        try {
            $parsed ??= $this->parse($feed->url);
        } catch (Throwable $e) {
            $feed->update([
                'last_error' => mb_substr($e->getMessage(), 0, 250),
                'last_fetched_at' => now(),
            ]);

            return 0;
        }

        $new = 0;
        foreach ($parsed['items'] as $item) {
            $row = FeedItem::firstOrCreate(
                [
                    'feed_id' => $feed->id,
                    'guid' => mb_substr($item['guid'], 0, 500),
                ],
                [
                    'title' => mb_substr($item['title'], 0, 500),
                    'link' => $item['link'] ? mb_substr($item['link'], 0, 1000) : null,
                    'summary' => $item['summary'],
                    'image' => $item['image'] ? mb_substr($item['image'], 0, 1000) : null,
                    'published_at' => $item['published_at'],
                ],
            );
            $new += $row->wasRecentlyCreated ? 1 : 0;
        }

        $feed->update([
            'title' => $feed->title ?: $parsed['title'],
            'last_error' => null,
            'last_fetched_at' => now(),
        ]);

        return $new;
    }

    /**
     * Only public http(s) hosts: a feed URL must never reach the local network.
     */
    public static function assertPublicUrl(string $url): void
    {
        $parts = parse_url($url);
        if (!in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true) || empty($parts['host'])) {
            throw new RuntimeException('Feed URLs must start with http:// or https://.');
        }
        $host = trim($parts['host'], '[]');
        $ips = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : (gethostbynamel($host) ?: []);
        if (!$ips) {
            throw new RuntimeException("Couldn't find the server {$host}.");
        }
        foreach ($ips as $ip) {
            if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                throw new RuntimeException('Feeds on local or private network addresses are not allowed.');
            }
        }
    }

    private function item(string $guid, string $title, ?string $link, ?string $summary, ?string $image, string $date): array
    {
        $published = null;
        if (trim($date) !== '') {
            try {
                $published = CarbonImmutable::parse($date);
            } catch (Throwable) {
                $published = null;
            }
        }
        $summary = trim(html_entity_decode(strip_tags((string)$summary), ENT_QUOTES | ENT_HTML5));

        return [
            'guid' => trim($guid),
            'title' => trim(html_entity_decode($title, ENT_QUOTES | ENT_HTML5)),
            'link' => $link ? trim($link) : null,
            'summary' => $summary !== '' ? mb_substr(preg_replace('/\s+/', ' ', $summary), 0, 2000) : null,
            'image' => $image ?: null,
            'published_at' => $published,
        ];
    }

    private function download(string $url): string
    {
        self::assertPublicUrl($url);
        $request = fn (array $extra = []) => Http::timeout(20)->connectTimeout(8)
            ->withOptions([
                'allow_redirects' => [
                    'max' => 3,
                    'protocols' => ['http', 'https'],
                ],
            ] + $extra)
            ->withHeaders([
                'User-Agent' => 'CreatorStudio/1.0 (+feed reader)',
                'Accept' => 'application/rss+xml, application/atom+xml, application/xml, text/xml;q=0.9, */*;q=0.5',
            ])
            ->get($url);

        try {
            $resp = $request();
        } catch (ConnectionException) {
            // Home networks with broken IPv6 make curl hang on the AAAA address: retry over IPv4.
            $resp = $request([
                'force_ip_resolve' => 'v4',
            ]);
        }
        if ($resp->failed()) {
            throw new RuntimeException("The feed returned HTTP {$resp->status()}.");
        }
        if (strlen($resp->body()) > 5 * 1048576) {
            throw new RuntimeException('The feed is larger than 5 MB.');
        }

        return $resp->body();
    }
}
