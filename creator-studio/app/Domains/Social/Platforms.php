<?php

namespace App\Domains\Social;

use App\Models\Setting;
use App\Services\AiTellsLinter;

/**
 * What each platform allows and where it cuts text off, plus the checks the composer runs.
 *
 * Official limits: X 280 characters per post (25,000 with Premium, URLs count as 23), LinkedIn
 * 3,000, Instagram 2,200 with up to 20 carousel slides. The "see more" cut-offs (LinkedIn about
 * 140 characters on phones, Instagram about 125) are observed, not published. Instagram's
 * 5-hashtag cap was announced in December 2025 but its help page still mentions 30, so more than
 * 5 is a warning and more than 30 is an error. Threads allows 500 characters per post;
 * TikTok captions 4,000 and photo carousels 35 images.
 */
class Platforms
{
    public const PLATFORMS = [
        'linkedin' => [
            'label' => 'LinkedIn',
            'limit' => 3000,
            'fold' => 140,
        ],
        'instagram' => [
            'label' => 'Instagram',
            'limit' => 2200,
            'fold' => 125,
        ],
        'x' => [
            'label' => 'X',
            'limit' => 280,
            'fold' => null,
        ],
        'youtube' => [
            'label' => 'YouTube',
            'limit' => 5000,
            'fold' => null,
        ],
        'tiktok' => [
            'label' => 'TikTok',
            'limit' => 4000,
            'fold' => 70,
        ],
        'threads' => [
            'label' => 'Threads',
            'limit' => 500,
            'fold' => null,
        ],
    ];

    /**
     * format => [label, label for the parts list or null]
     */
    public const FORMATS = [
        'linkedin' => [
            'post' => ['Text post', null],
            'carousel' => ['Carousel (PDF document)', 'Slides'],
        ],
        'instagram' => [
            'carousel' => ['Carousel', 'Slides'],
            'reel' => ['Reel', 'Beats'],
            'post' => ['Single image', null],
            'story' => ['Story', 'Frames'],
        ],
        'x' => [
            'post' => ['Post', null],
            'thread' => ['Thread', 'Posts'],
        ],
        'youtube' => [
            'short' => ['Short', 'Beats'],
            'community' => ['Community post', null],
        ],
        'tiktok' => [
            'video' => ['Video', 'Beats'],
            'photo' => ['Photo carousel', 'Slides'],
        ],
        'threads' => [
            'post' => ['Post', null],
            'thread' => ['Thread', 'Posts'],
        ],
    ];

    public const STATUSES = ['idea', 'draft', 'scheduled', 'posted'];

    public const MAX_SLIDES = 20;

    /**
     * TikTok photo carousels take up to 35 images.
     */
    public const MAX_SLIDES_BY_PLATFORM = [
        'tiktok' => 35,
    ];

    public static function valid(string $platform, string $format): bool
    {
        return isset(self::FORMATS[$platform][$format]);
    }

    public static function catalog(): array
    {
        $out = [];
        foreach (self::PLATFORMS as $key => $p) {
            $out[$key] = $p + [
                'limit' => self::limit($key),
                'formats' => array_map(fn ($f) => [
                    'label' => $f[0],
                    'parts' => $f[1],
                ], self::FORMATS[$key]),
                'handle' => Setting::get("{$key}_handle", ''),
                'target' => (int)Setting::get("cadence_{$key}", '0'),
            ];
        }

        return $out;
    }

    public static function limit(string $platform): int
    {
        return $platform === 'x' && Setting::get('x_premium') === '1' ? 25000 : self::PLATFORMS[$platform]['limit'];
    }

    /**
     * Characters as X counts them: every link is 23.
     */
    public static function xLength(string $text): int
    {
        return mb_strlen(preg_replace('~https?://\S+~u', str_repeat('x', 23), $text));
    }

    public static function hashtags(string $text): int
    {
        return preg_match_all('/(?<![\w&])#[\p{L}\p{N}_]+/u', $text);
    }

    /**
     * Checks for one post: [{severity: HARD|WARN, part: ?int, message}].
     * HARD means the platform will refuse it or cut it off.
     */
    public static function check(string $platform, string $format, ?string $body, ?array $parts = []): array
    {
        $body = (string)$body;
        $parts = array_values(array_map('strval', $parts ?? []));
        $out = [];
        $hard = function ($m, $part = null) use (&$out) {
            $out[] = [
                'severity' => 'HARD',
                'part' => $part,
                'message' => $m,
            ];
        };
        $warn = function ($m, $part = null) use (&$out) {
            $out[] = [
                'severity' => 'WARN',
                'part' => $part,
                'message' => $m,
            ];
        };
        $limit = self::limit($platform);
        $firstLine = trim(strtok(ltrim($body), "\n") ?: '');

        switch ($platform) {
            case 'x':
                if ($format === 'thread') {
                    if (count($parts) < 2) {
                        $warn('A thread needs at least 2 posts.');
                    }
                    foreach ($parts as $i => $p) {
                        $n = self::xLength($p);
                        if ($n > $limit) {
                            $hard('Post ' . ($i + 1) . " is {$n} characters; the limit is {$limit}.", $i);
                        } elseif (trim($p) === '') {
                            $warn('Post ' . ($i + 1) . ' is empty.', $i);
                        }
                    }
                } elseif (($n = self::xLength($body)) > $limit) {
                    $hard("{$n} characters; the limit is {$limit}. Turn it into a thread, or switch on X Premium in Settings if you have it.");
                }
                break;
            case 'linkedin':
                if (mb_strlen($body) > $limit) {
                    $hard(mb_strlen($body) . " characters; LinkedIn allows {$limit}.");
                }
                if (mb_strlen($firstLine) > 140) {
                    $warn('The first line runs past about 140 characters, so on phones the hook is cut by “see more”. Make line one short.');
                }
                if (preg_match('~https?://~', $body)) {
                    $warn('There’s a link in the post. Creators commonly report less reach for posts with links; many put it in the first comment instead.');
                }
                break;
            case 'instagram':
                if (mb_strlen($body) > $limit) {
                    $hard(mb_strlen($body) . " characters; Instagram captions allow {$limit}.");
                }
                $tags = self::hashtags($body);
                if ($tags > 30) {
                    $hard("{$tags} hashtags; Instagram allows 30 at most.");
                } elseif ($tags > 5) {
                    $warn("{$tags} hashtags. Instagram announced a 5-hashtag cap in December 2025, so extra tags may be dropped. Keep the 3 to 5 most specific.");
                }
                if (mb_strlen($firstLine) > 125) {
                    $warn('The first line runs past about 125 characters, where the feed cuts to “more”. Lead with the hook.');
                }
                break;
            case 'threads':
                $posts = $format === 'thread' ? $parts : [$body];
                if ($format === 'thread' && count($parts) < 2) {
                    $warn('A thread needs at least 2 posts.');
                }
                foreach ($posts as $i => $p) {
                    if (($n = mb_strlen($p)) > $limit) {
                        $hard(($format === 'thread' ? 'Post ' . ($i + 1) . ' is ' : '') . "{$n} characters; Threads allows {$limit} per post.", $format === 'thread' ? $i : null);
                    }
                }
                if (self::hashtags($body . ' ' . implode(' ', $parts)) > 1) {
                    $warn('Threads uses one topic tag per post; extra hashtags don’t become tags.');
                }
                break;
            case 'tiktok':
                if (mb_strlen($body) > $limit) {
                    $hard(mb_strlen($body) . " characters; TikTok captions allow {$limit}.");
                }
                if (mb_strlen($firstLine) > 70) {
                    $warn('Only about the first line of a TikTok caption shows over the video. Put the hook in the first ~70 characters.');
                }
                break;
        }

        $label = self::FORMATS[$platform][$format][1] ?? null;
        if ($label === 'Slides') {
            $max = self::MAX_SLIDES_BY_PLATFORM[$platform] ?? self::MAX_SLIDES;
            if (count($parts) > $max) {
                $hard(count($parts) . " slides; {$platform} carousels allow {$max}.");
            } elseif (count($parts) < 2) {
                $warn('A carousel needs at least 2 slides.');
            }
            foreach ($parts as $i => $p) {
                if (str_word_count($p) > 45) {
                    $warn('Slide ' . ($i + 1) . ' has ' . str_word_count($p) . ' words. Slides read best under about 40.', $i);
                }
            }
        }
        if ($label === 'Beats') {
            $words = array_sum(array_map('str_word_count', $parts));
            if ($words > 450) {
                $warn("The beats add up to {$words} words, about " . round($words / 150, 1) . ' minutes spoken. Shorts and Reels top out at 3 minutes; most good ones are under 60 seconds.');
            }
        }

        // The same AI-tell rules as scripts, on everything she'll publish.
        foreach (array_merge([$body], $parts) as $i => $text) {
            foreach (AiTellsLinter::lint($text) as $f) {
                $part = $i === 0 ? null : $i - 1;
                $out[] = [
                    'severity' => $f['severity'],
                    'part' => $part,
                    'message' => "{$f['rule']}: “{$f['match']}”" . ($part !== null ? ' (' . strtolower($label ?? 'part') . ' ' . ($part + 1) . ')' : ''),
                ];
            }
        }

        return $out;
    }

    public static function hasHard(array $findings): bool
    {
        return collect($findings)->contains('severity', 'HARD');
    }
}
