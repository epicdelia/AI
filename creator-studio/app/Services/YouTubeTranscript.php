<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Fetches a video's public captions (auto-generated or uploaded) from the watch page.
 * No API key and no quota, but YouTube sometimes blocks it: callers should offer
 * "paste the transcript" as the fallback.
 */
class YouTubeTranscript
{
    /**
     * @var callable|null test hook: fn(string): array{text: string, segments: array}
     */
    public static $fake = null;

    public static function videoId(string $input): ?string
    {
        $input = trim($input);
        if (preg_match('/^[\w-]{11}$/', $input)) {
            return $input;
        }
        if (preg_match('~(?:youtu\.be/|youtube\.com/(?:watch\?(?:.*&)?v=|shorts/|embed/|live/|v/))([\w-]{11})~i', $input, $m)) {
            return $m[1];
        }

        return null;
    }

    /**
     * @return array{video_id: string, title: ?string, language: ?string, text: string, segments: array<int, array{start: float, text: string}>}
     */
    public function fetch(string $url, string $lang = 'en'): array
    {
        $id = self::videoId($url);
        if (!$id) {
            throw new RuntimeException("That doesn't look like a YouTube video link.");
        }
        if (self::$fake) {
            return [
                'video_id' => $id,
                'title' => null,
                'language' => $lang,
            ] + (self::$fake)($id);
        }

        $page = Http::timeout(20)->withHeaders([
            'Accept-Language' => 'en-US,en;q=0.9',
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0 Safari/537.36',
            'Cookie' => 'CONSENT=YES+1',
        ])->get("https://www.youtube.com/watch?v={$id}");

        if ($page->failed()) {
            throw new RuntimeException("YouTube refused the request ({$page->status()}). Paste the transcript instead.");
        }
        $html = $page->body();
        $title = preg_match('/<meta name="title" content="([^"]*)"/', $html, $tm) ? html_entity_decode($tm[1], ENT_QUOTES) : null;

        if (!preg_match('/"captionTracks":(\[.*?\])/', $html, $m)) {
            throw new RuntimeException('No captions found for this video (or YouTube hid them from this server). Paste the transcript instead.');
        }
        $tracks = json_decode($m[1], true) ?: [];
        $track = collect($tracks)->first(fn ($t) => ($t['languageCode'] ?? '') === $lang && ($t['kind'] ?? '') !== 'asr')
            ?? collect($tracks)->firstWhere('languageCode', $lang)
            ?? ($tracks[0] ?? null);
        if (!$track || empty($track['baseUrl'])) {
            throw new RuntimeException('No usable caption track. Paste the transcript instead.');
        }

        $caps = Http::timeout(20)->get(html_entity_decode($track['baseUrl'], ENT_QUOTES) . '&fmt=json3');
        $segments = $caps->successful() ? self::parseJson3($caps->json() ?? []) : [];
        if (!$segments) {
            $xml = Http::timeout(20)->get(html_entity_decode($track['baseUrl'], ENT_QUOTES))->body();
            $segments = self::parseXml($xml);
        }
        if (!$segments) {
            throw new RuntimeException('YouTube returned empty captions (it may be blocking this server). Paste the transcript instead.');
        }

        return [
            'video_id' => $id,
            'title' => $title,
            'language' => $track['languageCode'] ?? null,
            'text' => trim(implode(' ', array_column($segments, 'text'))),
            'segments' => $segments,
        ];
    }

    public static function parseJson3(array $data): array
    {
        $out = [];
        foreach ($data['events'] ?? [] as $ev) {
            if (empty($ev['segs'])) {
                continue;
            }
            $text = trim(preg_replace('/\s+/', ' ', implode('', array_column($ev['segs'], 'utf8'))));
            if ($text !== '') {
                $out[] = [
                    'start' => round(($ev['tStartMs'] ?? 0) / 1000, 1),
                    'text' => $text,
                ];
            }
        }

        return $out;
    }

    public static function parseXml(string $xml): array
    {
        $doc = @simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA);
        if ($doc === false) {
            return [];
        }
        $out = [];
        foreach ($doc->text as $node) {
            $text = trim(preg_replace('/\s+/', ' ', html_entity_decode((string)$node, ENT_QUOTES)));
            if ($text !== '') {
                $out[] = [
                    'start' => round((float)$node['start'], 1),
                    'text' => $text,
                ];
            }
        }

        return $out;
    }

    /**
     * "[1:23] text" lines, for prompts that need timing (chapters).
     */
    public static function timestamped(array $segments): string
    {
        $lines = [];
        $lastMark = -60;
        $buffer = '';
        foreach ($segments as $s) {
            if ($s['start'] - $lastMark >= 30) {
                if ($buffer !== '') {
                    $lines[] = $buffer;
                }
                $lastMark = $s['start'];
                $buffer = '[' . gmdate($s['start'] >= 3600 ? 'G:i:s' : 'i:s', (int)$s['start']) . '] ';
            }
            $buffer .= $s['text'] . ' ';
        }
        if ($buffer !== '') {
            $lines[] = $buffer;
        }

        return implode("\n", array_map('trim', $lines));
    }
}
