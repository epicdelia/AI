<?php

namespace App\Domains\Agents;

use App\Services\Llm;
use App\Services\Prompts;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * The agent wizard's "tell us about your brand" and "who are you trying to reach" steps: read
 * her website (a few pages on the same site) into a brand summary, and suggest audiences.
 */
class BrandResearch
{
    public const MAX_PAGES = 5;

    public function __construct(
        private Llm $llm
    ) {
    }

    /**
     * @return array{summary: string, pages: int}
     */
    public function scrape(string $url): array
    {
        $url = self::safeUrl($url);
        $host = parse_url($url, PHP_URL_HOST);
        $queue = [$url];
        $seen = [];
        $text = '';
        while ($queue && count($seen) < self::MAX_PAGES) {
            $page = array_shift($queue);
            if (isset($seen[$page])) {
                continue;
            }
            $seen[$page] = true;

            try {
                // Redirects are followed by hand so each hop is checked against private addresses.
                $r = Http::timeout(12)->withoutRedirecting()->withHeaders([
                    'User-Agent' => 'CreatorStudio/1.0 (+brand summary)',
                ])->get($page);
                if ($r->redirect() && ($to = self::absolute((string)$r->header('Location'), $page))) {
                    $queue[] = self::safeUrl($to);
                    $host = parse_url($to, PHP_URL_HOST);

                    continue;
                }
            } catch (\Throwable) {
                continue;
            }
            if (!$r->successful() || !str_contains((string)$r->header('Content-Type'), 'html')) {
                continue;
            }
            $html = (string)$r->body();
            $text .= "\n\n# {$page}\n" . self::text($html);
            // Same-site links that usually say what a brand is about.
            if (preg_match_all('~href=["\']([^"\'#]+)["\']~i', $html, $m)) {
                foreach ($m[1] as $href) {
                    $abs = self::absolute($href, $page);
                    if ($abs && parse_url($abs, PHP_URL_HOST) === $host && preg_match('~/(about|services|work|products?|shop|newsletter|courses?|media|press)~i', $abs)) {
                        $queue[] = $abs;
                    }
                }
            }
        }
        if (trim($text) === '') {
            throw new RuntimeException('Couldn’t read that website. Check the address, or describe the brand instead.');
        }
        $summary = $this->llm->as('agent-setup')->chat(
            'Summarise this website for a social media agent: who it is, what they offer, their topics, tone, and anything to promote (links, products, newsletter). 120 to 200 words, plain text, facts only from the pages.',
            mb_substr($text, 0, 30000),
        );

        return [
            'summary' => trim($summary),
            'pages' => count($seen),
        ];
    }

    /**
     * @return array<int, array{id: string, name: string, description: string}>
     */
    public function audiences(string $brand): array
    {
        $data = $this->llm->as('agent-setup')->json(
            'Suggest 6 distinct target audiences for this creator\'s social media, each specific enough to write for. '
            . 'Output JSON: {"audiences": [{"name": "2 to 5 words", "description": "one sentence: who they are and what they want"}]}',
            'Creator: ' . Prompts::name() . "\n\nBrand:\n" . mb_substr($brand, 0, 6000),
        );

        $out = [];
        foreach (array_values(array_filter((array)($data['audiences'] ?? []), 'is_array')) as $i => $a) {
            if (trim((string)($a['name'] ?? '')) !== '') {
                $out[] = [
                    'id' => 'a' . $i,
                    'name' => mb_substr(trim($a['name']), 0, 80),
                    'description' => mb_substr(trim((string)($a['description'] ?? '')), 0, 300),
                ];
            }
        }

        return $out;
    }

    /**
     * Only public http(s) addresses: no localhost or private networks.
     */
    public static function safeUrl(string $url): string
    {
        $url = trim($url);
        if (!preg_match('~^https?://~i', $url)) {
            $url = 'https://' . $url;
        }
        $host = parse_url($url, PHP_URL_HOST);
        if (!$host || !filter_var($url, FILTER_VALIDATE_URL)) {
            throw new RuntimeException('That doesn’t look like a web address.');
        }
        $ips = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : (gethostbynamel($host) ?: []);
        if (!$ips) {
            throw new RuntimeException('Couldn’t find that website.');
        }
        foreach ($ips as $ip) {
            if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                throw new RuntimeException('That address points to a private network.');
            }
        }

        return $url;
    }

    private static function text(string $html): string
    {
        $html = preg_replace('~<(script|style|noscript|svg|nav|footer)\b.*?</\1>~is', ' ', $html);
        $text = html_entity_decode(strip_tags(preg_replace('~<(br|p|div|li|h[1-6])\b~i', "\n<$1", $html)), ENT_QUOTES | ENT_HTML5);

        return mb_substr(trim(preg_replace('~[ \t]+~', ' ', preg_replace('~\n\s*\n+~', "\n", $text))), 0, 8000);
    }

    private static function absolute(string $href, string $base): ?string
    {
        if (preg_match('~^https?://~i', $href)) {
            return strtok($href, '?');
        }
        if (str_starts_with($href, '//') || preg_match('~^[a-z]+:~i', $href)) {
            return null;
        }
        $p = parse_url($base);

        return $p['scheme'] . '://' . $p['host'] . (str_starts_with($href, '/') ? '' : '/') . ltrim(strtok($href, '?'), '/');
    }
}
