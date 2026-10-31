<?php

namespace App\Domains\Social;

use App\Domains\Social\Models\SocialPost;
use Carbon\CarbonImmutable;
use RuntimeException;
use Throwable;

/**
 * Import post numbers from a platform's analytics export (CSV). Every platform names its
 * columns differently and changes them, so nothing is hard-coded: columns are guessed from
 * common names and she confirms the mapping. Rows match her existing posts by link, or by
 * day plus the opening text; anything else becomes a new posted post.
 */
class AnalyticsImport
{
    public const FIELDS = ['date', 'text', 'url', 'impressions', 'likes', 'comments', 'shares', 'saves'];

    /** Header words that suggest each field (lower-case, checked in order). */
    private const GUESS = [
        'url' => ['permalink', 'post link', 'link', 'url'],
        'date' => ['date', 'posted', 'published', 'created', 'time'],
        'text' => ['post text', 'caption', 'content', 'text', 'copy', 'message', 'tweet', 'title', 'description'],
        'impressions' => ['impressions', 'views', 'video views', 'plays', 'reach'],
        'likes' => ['likes', 'reactions', 'like'],
        'comments' => ['comments', 'replies', 'reply'],
        'shares' => ['reposts', 'retweets', 'reshares', 'shares', 'share'],
        'saves' => ['saves', 'saved', 'bookmarks'],
    ];

    public const MAX_ROWS = 2000;

    /** @return array{headers: array<int, string>, rows: array<int, array<int, string>>} */
    public static function read(string $path): array
    {
        $raw = (string) file_get_contents($path);
        $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw); // UTF-8 BOM
        if (str_starts_with($raw, "\xFF\xFE") || str_starts_with($raw, "\xFE\xFF")) {
            $raw = mb_convert_encoding($raw, 'UTF-8', 'UTF-16'); // Excel's "Unicode text"
        }
        if (trim($raw) === '') {
            throw new RuntimeException('That file is empty.');
        }
        if (str_starts_with($raw, "PK\x03\x04") || str_starts_with($raw, "\xD0\xCF\x11\xE0")) {
            throw new RuntimeException('That’s an Excel file. Open it and use “Save as → CSV”, then import the CSV.');
        }
        $first = strtok($raw, "\n");
        $delim = collect([',' => substr_count($first, ','), ';' => substr_count($first, ';'), "\t" => substr_count($first, "\t")])->sortDesc()->keys()->first();

        $fh = fopen('php://temp', 'r+');
        fwrite($fh, $raw);
        rewind($fh);
        $headers = null;
        $rows = [];
        while (($r = fgetcsv($fh, 0, $delim, '"', '')) !== false) {
            if ($r === [null] || ! array_filter($r, fn ($c) => trim((string) $c) !== '')) {
                continue;
            }
            if ($headers === null) {
                $headers = array_map(fn ($h) => trim((string) $h), $r);

                continue;
            }
            $rows[] = array_map(fn ($c) => trim((string) $c), $r);
            if (count($rows) >= self::MAX_ROWS) {
                break;
            }
        }
        fclose($fh);
        if (! $headers || ! $rows) {
            throw new RuntimeException('Couldn’t find a header row and data rows in that file.');
        }

        return ['headers' => $headers, 'rows' => $rows];
    }

    /** field => column index (or null), from the header names. */
    public static function guess(array $headers): array
    {
        $lower = array_map(fn ($h) => mb_strtolower($h), $headers);
        $taken = [];
        $out = array_fill_keys(self::FIELDS, null);
        foreach (self::GUESS as $field => $words) {
            foreach ($words as $w) {
                foreach ($lower as $i => $h) {
                    if (! isset($taken[$i]) && str_contains($h, $w) && ! ($field === 'likes' && str_contains($h, 'rate'))) {
                        $out[$field] = $i;
                        $taken[$i] = true;
                        break 2;
                    }
                }
            }
        }

        return $out;
    }

    /** "1,234", "1.2K", "3M", "" → int|null */
    public static function number(?string $v): ?int
    {
        $v = trim(str_replace([' ', "\u{00A0}", '"'], '', (string) $v));
        if ($v === '' || $v === '-' || $v === '–') {
            return null;
        }
        if (preg_match('/^([\d.,]+)\s*([kKmM])$/', $v, $m)) {
            return (int) round((float) str_replace(',', '.', $m[1]) * (strtolower($m[2]) === 'k' ? 1000 : 1000000));
        }
        if (preg_match('/^\d{1,3}([.,]\d{3})+$/', $v)) {
            return (int) preg_replace('/\D/', '', $v); // 1,234 or 1.234 (thousands)
        }
        $digits = preg_replace('/[^\d]/', '', explode('.', str_replace(',', '.', $v))[0]);

        return $digits === '' ? null : (int) $digits;
    }

    /** @return array{created: int, updated: int, skipped: int} */
    public static function import(string $platform, array $rows, array $map, string $tz): array
    {
        $col = fn (array $row, string $field) => isset($map[$field]) && $map[$field] !== null && $map[$field] !== '' ? ($row[(int) $map[$field]] ?? null) : null;
        $result = ['created' => 0, 'updated' => 0, 'skipped' => 0];

        foreach ($rows as $row) {
            $metrics = [];
            foreach (['impressions', 'likes', 'comments', 'shares', 'saves'] as $m) {
                if (($n = self::number($col($row, $m))) !== null) {
                    $metrics[$m] = $n;
                }
            }
            $url = filter_var($col($row, 'url'), FILTER_VALIDATE_URL) ?: null;
            $text = trim((string) $col($row, 'text'));
            try {
                $date = ($d = $col($row, 'date')) ? CarbonImmutable::parse($d, $tz)->utc() : null;
            } catch (Throwable) {
                $date = null;
            }
            if (! $metrics || (! $url && ! $date)) {
                $result['skipped']++;

                continue;
            }

            $post = $url ? SocialPost::where('platform', $platform)->where('url', $url)->first() : null;
            if (! $post && $date) {
                $opening = mb_strtolower(mb_substr(preg_replace('/\s+/', ' ', $text), 0, 40));
                $post = SocialPost::where('platform', $platform)->where('status', 'posted')
                    ->whereBetween('posted_at', [$date->startOfDay()->subDay(), $date->endOfDay()->addDay()])->get()
                    ->first(fn ($p) => $opening === '' || str_starts_with(mb_strtolower(preg_replace('/\s+/', ' ', $p->body ?: implode(' ', $p->parts ?? []))), mb_substr($opening, 0, 25)));
            }

            if ($post) {
                $post->update(['metrics' => array_merge($post->metrics ?? [], $metrics), 'url' => $post->url ?: $url]);
                $result['updated']++;
            } else {
                SocialPost::create([
                    'platform' => $platform, 'format' => array_key_first(Platforms::FORMATS[$platform]),
                    'body' => $text !== '' ? mb_substr($text, 0, 60000) : null, 'status' => 'posted',
                    'posted_at' => $date ?? now(), 'url' => $url, 'metrics' => $metrics, 'source' => 'import',
                ]);
                $result['created']++;
            }
        }

        return $result;
    }
}
