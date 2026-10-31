<?php

namespace App\Domains\Audience;

use App\Models\Setting;
use App\Services\Llm;
use App\Services\Prompts;
use App\Services\YouTube;
use RuntimeException;

/**
 * Draft replies to the newest comments on one of her videos, in her voice. Replying in the
 * first hours is something creators commonly recommend; the studio can't post them (that
 * needs her Google sign-in), so she reads, edits and pastes each one.
 */
class ReplyDrafter
{
    public const MAX = 15;

    public function __construct(private YouTube $yt, private Llm $llm) {}

    /** @return array<int, array{id: string, text: string, likes: int, published_at: ?string, reply: string}> */
    public function draft(string $videoId, ?string $title = null): array
    {
        $mine = ($channel = Setting::get('channel_id')) ? $this->yt->channelId($channel) : null;
        $comments = array_values(array_filter($this->yt->comments($videoId, 50, 'time'), fn ($c) => ($mine === null || $c['author_channel'] !== $mine)
            && $c['replies'] === 0
            && mb_strlen(trim($c['text'])) >= 3
            && ! preg_match('~https?://|t\.me/|whatsapp|telegram~i', $c['text'])));
        $comments = array_slice($comments, 0, self::MAX);
        if (! $comments) {
            throw new RuntimeException('No new comments waiting for a reply.');
        }
        $title ??= $this->yt->videoTitle($videoId) ?? 'her video';

        $name = Prompts::name();
        $list = implode("\n", array_map(fn ($c, $i) => "[{$i}] ".str_replace(["\r", "\n"], ' ', mb_substr($c['text'], 0, 500)), $comments, array_keys($comments)));
        $data = $this->llm->as('replies')->json(
            "You draft replies to YouTube comments for {$name}, on her video \"{$title}\". She'll edit and post them herself.\n\n"
            .Prompts::profile()."\n\n".Prompts::voice()."\n\n".Prompts::writingRules()."\n\n"
            ."Rules: 1 or 2 short sentences, warm and specific to what they said. Answer questions plainly; if you don't know the answer from the comment, say she'll look into it rather than guessing. "
            ."Never promise a video, a date, a giveaway or a collab. No links, no hashtags, at most one emoji. Skip spam, hostile bait and comments that need no reply by leaving them out.\n\n"
            .'Output JSON: {"replies": [{"index": 0, "reply": "text"}]}',
            "Newest comments:\n{$list}",
        );

        $out = [];
        foreach ((array) ($data['replies'] ?? []) as $r) {
            $i = $r['index'] ?? null;
            $reply = trim((string) ($r['reply'] ?? ''));
            if (is_int($i) && isset($comments[$i]) && $reply !== '') {
                $out[$i] = $comments[$i] + ['reply' => mb_substr($reply, 0, 600)];
            }
        }
        ksort($out);

        return array_values(array_map(fn ($c) => array_intersect_key($c, array_flip(['id', 'text', 'likes', 'published_at', 'reply'])), $out));
    }
}
