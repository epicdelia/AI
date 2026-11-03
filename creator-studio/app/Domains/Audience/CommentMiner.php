<?php

namespace App\Domains\Audience;

use App\Domains\Audience\Models\AudienceInsight;
use App\Models\Setting;
use App\Services\Llm;
use App\Services\Prompts;
use App\Services\YouTube;
use RuntimeException;

/**
 * Read a video's comments and pull out what viewers are asking for: questions, requests,
 * confusion and objections, each a candidate for her next video. Quotes the AI returns are
 * checked against the real comments and dropped if they don't appear there word for word.
 */
class CommentMiner
{
    public const TYPES = ['question', 'request', 'confusion', 'objection', 'praise'];

    public function __construct(
        private YouTube $yt,
        private Llm $llm
    ) {
    }

    public function mine(string $videoId, ?string $title = null, bool $own = false): AudienceInsight
    {
        $comments = $this->yt->comments($videoId, 100);
        if (count($comments) < 3) {
            throw new RuntimeException('This video has too few comments to learn from yet.');
        }
        $title ??= $this->yt->videoTitle($videoId) ?? 'Untitled video';

        // Most-liked first: likes are viewers agreeing with the comment.
        usort($comments, fn ($a, $b) => ($b['likes'] + $b['replies']) <=> ($a['likes'] + $a['replies']));
        $lines = array_map(fn ($c) => '- ' . ($c['likes'] ? "[{$c['likes']} likes] " : '') . str_replace(["\r", "\n"], ' ', mb_substr($c['text'], 0, 600)), $comments);

        $data = $this->llm->as('comments')->json(
            Prompts::commentsSystem(),
            "Video: \"{$title}\"" . ($own ? ' (her own video)' : ' (another creator\'s video)') . "\n\n" . count($comments) . " top comments:\n" . implode("\n", $lines),
        );

        return AudienceInsight::updateOrCreate([
            'video_id' => $videoId,
        ], [
            'video_title' => mb_substr($title, 0, 250),
            'url' => "https://youtu.be/{$videoId}",
            'own' => $own,
            'comment_count' => count($comments),
            'summary' => mb_substr(trim((string)($data['summary'] ?? '')), 0, 1000) ?: null,
            'items' => self::clean($data['items'] ?? [], $comments),
            'used' => false,
        ]);
    }

    /**
     * Keep well-formed items only, and only quotes that really appear in a comment.
     */
    public static function clean(mixed $items, array $comments): array
    {
        $haystack = self::normalise(implode("\n", array_column($comments, 'text')));
        $out = [];
        foreach (is_array($items) ? $items : [] as $i) {
            $text = trim((string)($i['text'] ?? ''));
            $type = strtolower(trim((string)($i['type'] ?? '')));
            if ($text === '' || !in_array($type, self::TYPES, true)) {
                continue;
            }
            $quote = trim((string)($i['quote'] ?? ''), " \t\n\"“”");
            $idea = trim((string)($i['idea'] ?? ''));
            $out[] = [
                'type' => $type,
                'text' => mb_substr($text, 0, 300),
                'mentions' => max(1, min(count($comments), (int)($i['mentions'] ?? 1))),
                'quote' => $quote !== '' && str_contains($haystack, self::normalise($quote)) ? mb_substr($quote, 0, 400) : null,
                'idea' => $idea !== '' && strtolower($idea) !== 'null' ? mb_substr($idea, 0, 120) : null,
            ];
        }
        usort($out, fn ($a, $b) => $b['mentions'] <=> $a['mentions']);

        return array_slice($out, 0, 10);
    }

    /**
     * Her channel handle or ID from Settings, or an error she can act on.
     */
    public static function channel(): string
    {
        $channel = Setting::get('channel_id');
        if (!$channel) {
            throw new RuntimeException('Add your channel handle or ID in Settings first.');
        }

        return $channel;
    }

    private static function normalise(string $s): string
    {
        $s = str_replace(['’', '‘', '“', '”'], ["'", "'", '"', '"'], mb_strtolower($s));

        return trim(preg_replace('/\s+/u', ' ', $s));
    }
}
