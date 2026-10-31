<?php

namespace App\Domains\Social;

use App\Domains\Social\Models\SocialPost;
use App\Services\Llm;
use App\Services\Prompts;
use RuntimeException;

/**
 * One piece of her work (a script, a transcript, a Library document, a note) into native drafts
 * for each platform she picks. Native means rewritten for the feed it lands in, not the same
 * text pasted four times.
 */
class Repurposer
{
    /** How each format is written. Kept short: every line is sent with each request. */
    public const GUIDES = [
        'linkedin.post' => 'LinkedIn text post, 150 to 300 words. Line 1 is the hook and stays under 140 characters (phones cut there). Short paragraphs of 1 or 2 lines. One concrete lesson or story from the source, told in first person. End with a question that invites comments. No hashtag walls (0 to 3). No links in the text.',
        'linkedin.carousel' => 'LinkedIn PDF carousel: body is the post text (under 100 words, hook in line 1). parts are 6 to 10 slides: slide 1 is a bold promise (under 10 words), each middle slide is one idea in under 35 words, the last slide is a takeaway plus "Follow for more".',
        'instagram.carousel' => 'Instagram carousel: parts are 5 to 10 slides (slide 1 = scroll-stopping hook under 10 words, one idea per slide under 35 words, last slide = save/share prompt). body is the caption: hook in the first 125 characters, 60 to 150 words, then 3 to 5 specific hashtags on the last line.',
        'instagram.reel' => 'Instagram Reel: parts are the spoken beats, 6 to 12 short lines, about 30 to 45 seconds in total. Beat 1 must hook in under 2 seconds. Put on-screen text in [TEXT: ...]. body is the caption: hook first, under 80 words, 3 to 5 hashtags.',
        'instagram.post' => 'Instagram single-image post: body is the caption, hook in the first 125 characters, 80 to 200 words, ends with a question, then 3 to 5 specific hashtags. Put the image idea in title.',
        'instagram.story' => 'Instagram Story: parts are 3 to 5 frames, one short line each (a poll or question sticker idea in [STICKER: ...] where it fits). body is empty.',
        'x.post' => 'X post: body under 270 characters. One sharp, specific take or result from the source. No hashtags, no emoji strings.',
        'x.thread' => 'X thread: parts are 5 to 9 posts, each under 270 characters. Post 1 is the hook and promise (no "a thread" or 🧵). Each post stands alone. The last post sums up and points to the video. body is empty.',
        'youtube.short' => 'YouTube Short: parts are spoken beats, 6 to 12 lines, under 60 seconds in total, beat 1 hooks immediately; put on-screen text in [TEXT: ...]. title is the Short title (under 60 characters). body is the description (1 or 2 lines).',
        'tiktok.video' => 'TikTok video: parts are spoken beats, 6 to 14 lines, 30 to 60 seconds in total. Beat 1 hooks in the first second (a bold claim, a result, or a question). Put on-screen text in [TEXT: ...]. body is the caption: hook in the first 70 characters, under 150 characters in total, then 3 to 5 hashtags.',
        'tiktok.photo' => 'TikTok photo carousel: parts are 5 to 10 slides (slide 1 a hook under 8 words, one idea per slide under 30 words). body is the caption: hook first, under 150 characters, 3 to 5 hashtags.',
        'threads.post' => 'Threads post: body under 450 characters. Conversational, one opinion or lesson from the source, ending with a question. At most one topic tag. No links in the text.',
        'threads.thread' => 'Threads thread: parts are 3 to 6 posts, each under 450 characters, post 1 is the hook. Conversational, not a listicle. body is empty.',
        'youtube.community' => 'YouTube community post: body is 40 to 120 words, teasing or extending the video, ending with a question or a poll idea.',
    ];

    public function __construct(private Llm $llm) {}

    /**
     * @param  array<int, array{platform: string, format: string}>  $targets
     * @return array<int, SocialPost>
     */
    public function run(string $title, string $source, array $targets, ?int $scriptId = null, string $extra = ''): array
    {
        $targets = array_values(array_filter($targets, fn ($t) => Platforms::valid((string) ($t['platform'] ?? ''), (string) ($t['format'] ?? ''))));
        if (! $targets) {
            throw new RuntimeException('Pick at least one platform and format.');
        }
        if (mb_strlen(trim($source)) < 80) {
            throw new RuntimeException('Give it a bit more to work with: at least a paragraph.');
        }

        $wanted = implode("\n", array_map(fn ($t) => "- {$t['platform']}.{$t['format']}: ".self::GUIDES["{$t['platform']}.{$t['format']}"], $targets));
        $system = "You turn {$this->name()}'s work into native posts for each platform. Each post must work on its own for someone who never saw the original.\n\n"
            .Prompts::profile()."\n\n".Prompts::voice()."\n\n".Prompts::writingRules()."\n\n"
            ."Write exactly these, in this order:\n{$wanted}\n\n"
            .'Output JSON: {"posts": [{"platform": "linkedin", "format": "post", "title": "a short internal label", "body": "text", "parts": ["..."]}]}. '
            .'Use parts only for formats that have them, otherwise []. Plain text inside strings (no Markdown headings). Never invent numbers, quotes or stories that are not in the source.';
        $user = "Source: {$title}\n\n".mb_substr(trim($source), 0, 24000).($extra !== '' ? "\n\nHer direction for these posts: {$extra}" : '');

        $data = $this->llm->as('repurpose')->json($system, $user);

        $made = [];
        foreach (array_values($data['posts'] ?? []) as $i => $p) {
            $platform = (string) ($p['platform'] ?? $targets[$i]['platform'] ?? '');
            $format = (string) ($p['format'] ?? $targets[$i]['format'] ?? '');
            if (! Platforms::valid($platform, $format)) {
                continue;
            }
            $hasParts = Platforms::FORMATS[$platform][$format][1] !== null;
            $parts = $hasParts ? array_values(array_filter(array_map(fn ($x) => is_scalar($x) ? trim((string) $x) : '', (array) ($p['parts'] ?? [])))) : [];
            $made[] = SocialPost::create([
                'platform' => $platform,
                'format' => $format,
                'title' => mb_substr(trim((string) ($p['title'] ?? '')) ?: "{$title} ({$format})", 0, 200),
                'body' => is_scalar($p['body'] ?? null) ? trim((string) $p['body']) : '',
                'parts' => array_slice($parts, 0, 25),
                'status' => 'draft',
                'script_id' => $scriptId,
                'source' => 'repurpose',
            ]);
        }
        if (! $made) {
            throw new RuntimeException('The AI didn’t return any usable posts. Try again.');
        }

        return $made;
    }

    private function name(): string
    {
        return Prompts::name();
    }
}
