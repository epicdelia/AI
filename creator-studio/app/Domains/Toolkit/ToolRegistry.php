<?php

namespace App\Domains\Toolkit;

use App\Domains\Toolkit\Models\CustomTool;
use App\Services\Prompts;
use InvalidArgumentException;

/**
 * Creator writing tools: built-in templates plus the ones she writes herself (custom_tools).
 * A tool is a set of fields and a prompt with {field} placeholders. Shared options (tone,
 * language, number of options, creativity) are added to every run.
 */
class ToolRegistry
{
    public const CATEGORIES = [
        'youtube' => 'YouTube',
        'social' => 'Shorts & social',
        'writing' => 'Writing',
        'business' => 'Brand deals',
        'custom' => 'My templates',
    ];

    /** @return array<string, array<string, mixed>> */
    public static function builtIn(): array
    {
        $topic = ['name' => 'topic', 'label' => 'What the video is about', 'type' => 'textarea', 'placeholder' => 'I tested 5 budgeting apps for a month as a student', 'required' => true];
        $script = ['name' => 'script', 'label' => 'Script or transcript', 'type' => 'textarea', 'placeholder' => 'Paste the script or transcript', 'required' => true, 'rows' => 8];

        return [
            'yt_titles' => [
                'name' => 'Video titles', 'category' => 'youtube', 'icon' => '🎯',
                'description' => 'Click-worthy titles in three styles: tension, curiosity and outcome.',
                'fields' => [$topic],
                'prompt' => "Write YouTube titles for this video:\n{topic}\n\nEach under 60 characters. Mix three styles: tension, curiosity and outcome, and label each. No clickbait the video can't pay off. No ALL CAPS words except one at most.",
                'results' => true,
            ],
            'yt_thumbnail' => [
                'name' => 'Thumbnail text & concepts', 'category' => 'youtube', 'icon' => '🖼️',
                'description' => '3 to 5 word thumbnail text plus a visual concept for each.',
                'fields' => [$topic, ['name' => 'title', 'label' => 'Title (if picked)', 'type' => 'text']],
                'prompt' => "Video: {topic}\nTitle: {title}\n\nPropose thumbnail concepts. For each: the thumbnail text (3 to 5 words, must add to the title, never repeat it), the facial expression or pose, the main visual, and the one colour that should pop.",
                'results' => true,
            ],
            'yt_hooks' => [
                'name' => 'Cold-open hooks', 'category' => 'youtube', 'icon' => '🪝',
                'description' => 'First 30 seconds that pay off the title immediately.',
                'fields' => [['name' => 'title', 'label' => 'Video title', 'type' => 'text', 'required' => true], $topic],
                'prompt' => "Title: {title}\nVideo: {topic}\n\nWrite cold-open hooks (the first 15 to 30 seconds, spoken). Each must deliver on the title's promise right away: show the result, the stakes, or the wild thing. No \"hey guys, welcome back\". Add a [VISUAL: ...] note for each.",
                'results' => true,
            ],
            'yt_description' => [
                'name' => 'Video description', 'category' => 'youtube', 'icon' => '📝',
                'description' => 'Search-friendly description with a strong first two lines.',
                'fields' => [['name' => 'title', 'label' => 'Video title', 'type' => 'text', 'required' => true], $topic, ['name' => 'links', 'label' => 'Links to include', 'type' => 'textarea', 'placeholder' => 'Newsletter, socials, tools mentioned']],
                'prompt' => "Write a YouTube description.\nTitle: {title}\nVideo: {topic}\nLinks: {links}\n\nThe first two lines must make someone want to watch (they show above the fold). Then a short paragraph, a \"Chapters\" placeholder, the links, and 3 hashtags at the end.",
            ],
            'yt_tags' => [
                'name' => 'Tags & keywords', 'category' => 'youtube', 'icon' => '🏷️',
                'description' => 'Search terms people would actually type.',
                'fields' => [['name' => 'title', 'label' => 'Video title', 'type' => 'text', 'required' => true], $topic],
                'prompt' => "Title: {title}\nVideo: {topic}\n\nList 15 to 25 YouTube tags as a single comma-separated line (under 500 characters total), most specific first. Then list the 5 search phrases this video could realistically rank for.",
            ],
            'yt_chapters' => [
                'name' => 'Chapters', 'category' => 'youtube', 'icon' => '⏱️',
                'description' => 'Chapter titles from a script or transcript.',
                'fields' => [$script],
                'prompt' => "Propose YouTube chapters for this video, in order. Each title under 40 characters and worth clicking. If the text has timestamps use them, otherwise estimate at about 150 spoken words per minute and mark times as estimates. The first chapter starts at 0:00.\n\n{script}",
            ],
            'yt_community' => [
                'name' => 'Community post', 'category' => 'youtube', 'icon' => '📣',
                'description' => 'A poll or teaser for the Community tab.',
                'fields' => [$topic, ['name' => 'goal', 'label' => 'Goal', 'type' => 'select', 'options' => ['Tease the next video', 'Poll: which video next', 'Behind the scenes', 'Ask the audience']]],
                'prompt' => "Write a YouTube Community post. Goal: {goal}\nAbout: {topic}\n\nKeep it under 80 words. If it's a poll, give 2 to 4 short options.",
                'results' => true,
            ],
            'yt_replies' => [
                'name' => 'Comment replies', 'category' => 'youtube', 'icon' => '💬',
                'description' => 'Warm, real replies to comments, including the tricky ones.',
                'fields' => [['name' => 'comments', 'label' => 'Comments (one per line)', 'type' => 'textarea', 'required' => true, 'rows' => 6]],
                'prompt' => "Write a reply to each of these YouTube comments, in her voice. Short, warm, human. For a hostile comment: don't grovel, don't fight; be gracious and confident. Quote each comment before its reply.\n\n{comments}",
            ],
            'shorts_script' => [
                'name' => 'Shorts / Reels script', 'category' => 'social', 'icon' => '📱',
                'description' => 'A 30 to 60 second vertical script with a hook in the first second.',
                'fields' => [$topic, ['name' => 'source', 'label' => 'From a long-form video? Paste the moment', 'type' => 'textarea']],
                'prompt' => "Write a 30 to 60 second vertical video script.\nAbout: {topic}\nSource moment: {source}\n\nLine 1 is the hook and must land in the first second. One idea only. On-screen text in [TEXT: ...], visuals in [VISUAL: ...]. End with a loop back to the start or a reason to watch the long-form.",
                'results' => true,
            ],
            'ig_caption' => [
                'name' => 'Instagram / TikTok caption', 'category' => 'social', 'icon' => '📸',
                'description' => 'Caption with a hook line and hashtags.',
                'fields' => [$topic, ['name' => 'platform', 'label' => 'Platform', 'type' => 'select', 'options' => ['Instagram', 'TikTok', 'Both']]],
                'prompt' => "Write a {platform} caption for this post: {topic}\n\nThe first line is the hook (it's all people see). Short lines. End with a question or a soft CTA, then 5 to 8 relevant hashtags (mix of broad and niche).",
                'results' => true,
            ],
            'hashtags' => [
                'name' => 'Hashtags', 'category' => 'social', 'icon' => '#️⃣',
                'description' => 'Broad, mid and niche hashtag sets.',
                'fields' => [['name' => 'keywords', 'label' => 'Keywords or topic', 'type' => 'text', 'required' => true]],
                'prompt' => "Give hashtag sets for: {keywords}\n\nThree groups: ## Broad (huge reach), ## Mid, ## Niche (small but loyal). 8 per group, one line each group, space-separated.",
            ],
            'x_thread' => [
                'name' => 'X / Threads thread', 'category' => 'social', 'icon' => '🧵',
                'description' => 'Turn a video or idea into a thread.',
                'fields' => [$topic, ['name' => 'video_url', 'label' => 'Video link (optional)', 'type' => 'url']],
                'prompt' => "Write a thread of 5 to 8 posts about: {topic}\nLink the video in the last post: {video_url}\n\nPost 1 is the hook and stands on its own. Each post under 270 characters. Number them 1/, 2/, ...",
            ],
            'linkedin' => [
                'name' => 'LinkedIn post', 'category' => 'social', 'icon' => '💼',
                'description' => 'A professional-but-human post about the video or a lesson.',
                'fields' => [$topic],
                'prompt' => "Write a LinkedIn post about: {topic}\n\nOpen with one line that earns the \"see more\" click. Short paragraphs. One concrete lesson. No hashtags beyond 3. No \"I'm humbled to announce\".",
            ],
            'newsletter' => [
                'name' => 'Newsletter email', 'category' => 'writing', 'icon' => '✉️',
                'description' => 'A short email to subscribers about the new video.',
                'fields' => [['name' => 'title', 'label' => 'Video title', 'type' => 'text', 'required' => true], $topic, ['name' => 'video_url', 'label' => 'Video link', 'type' => 'url']],
                'prompt' => "Write a short newsletter email about a new video.\nTitle: {title}\nVideo: {topic}\nLink: {video_url}\n\nGive 3 subject line options first. Then the email: a personal opening, why this video matters to the reader, the link, and one question to reply to. Under 250 words.",
            ],
            'rewrite' => [
                'name' => 'Rewriter', 'category' => 'writing', 'icon' => '🔁',
                'description' => 'Rewrite anything: shorter, punchier, warmer or more formal.',
                'fields' => [['name' => 'text', 'label' => 'Text', 'type' => 'textarea', 'required' => true, 'rows' => 7], ['name' => 'mode', 'label' => 'Make it', 'type' => 'select', 'options' => ['punchier', 'shorter', 'more spoken (for camera)', 'warmer', 'more formal', 'simpler (for beginners)']]],
                'prompt' => "Rewrite this to be {mode}. Keep every fact, name and number. Don't add new ideas.\n\n{text}",
                'results' => true,
            ],
            'tldr' => [
                'name' => 'Summarise / TL;DR', 'category' => 'writing', 'icon' => '⚡',
                'description' => 'The short version of anything long.',
                'fields' => [['name' => 'text', 'label' => 'Text', 'type' => 'textarea', 'required' => true, 'rows' => 8]],
                'prompt' => "Give a 2 to 3 sentence TL;DR, then the key points as bullets.\n\n{text}",
            ],
            'proofread' => [
                'name' => 'Proofread', 'category' => 'writing', 'icon' => '✅',
                'description' => 'Fix grammar, spelling and punctuation only.',
                'fields' => [['name' => 'text', 'label' => 'Text', 'type' => 'textarea', 'required' => true, 'rows' => 8]],
                'prompt' => "Fix grammar, spelling and punctuation only. Change nothing else. Return the corrected text, then a short bullet list of what you changed.\n\n{text}",
            ],
            'pros_cons' => [
                'name' => 'Pros & cons', 'category' => 'writing', 'icon' => '⚖️',
                'description' => 'Balanced pros and cons for a review or comparison video.',
                'fields' => [['name' => 'subject', 'label' => 'What are you reviewing?', 'type' => 'text', 'required' => true], ['name' => 'notes', 'label' => 'Your notes / experience', 'type' => 'textarea']],
                'prompt' => "Pros and cons of: {subject}\nMy notes: {notes}\n\nUse only what's in my notes plus widely known facts; mark anything you're unsure about [VERIFY]. Sections: ## Pros, ## Cons, ## Who it's for.",
            ],
            'sponsor_pitch' => [
                'name' => 'Sponsor pitch email', 'category' => 'business', 'icon' => '🤝',
                'description' => 'A short cold email to a brand you want to work with.',
                'fields' => [['name' => 'brand', 'label' => 'Brand', 'type' => 'text', 'required' => true], ['name' => 'why', 'label' => 'Why you and them', 'type' => 'textarea', 'placeholder' => 'I already use it, my audience asks about it…'], ['name' => 'stats', 'label' => 'Your numbers (only real ones)', 'type' => 'textarea', 'placeholder' => 'Subscribers, average views, audience location/age']],
                'prompt' => "Write a short pitch email to {brand}.\nWhy we fit: {why}\nMy real numbers: {stats}\n\nUnder 150 words, 2 subject line options first. Specific, confident, not needy. Use only the numbers given; if none, leave [YOUR NUMBERS].",
            ],
            'sponsor_read' => [
                'name' => 'Sponsor ad read', 'category' => 'business', 'icon' => '🎙️',
                'description' => 'A 30 to 60 second integration that doesn\'t feel like an ad.',
                'fields' => [['name' => 'brand', 'label' => 'Brand & product', 'type' => 'text', 'required' => true], ['name' => 'points', 'label' => 'Talking points from the brief', 'type' => 'textarea', 'required' => true], ['name' => 'video', 'label' => 'The video it goes in', 'type' => 'text']],
                'prompt' => "Write a 30 to 60 second spoken sponsor integration for {brand} inside a video about: {video}\nBrief talking points: {points}\n\nSegue naturally from the video topic. Honest, in her voice, one personal touch marked [YOUR STORY: ...]. Include the required points only; claim nothing extra.",
                'results' => true,
            ],
            'media_kit' => [
                'name' => 'Media kit bio', 'category' => 'business', 'icon' => '🌟',
                'description' => 'Short and long bios for a media kit or collab page.',
                'fields' => [['name' => 'facts', 'label' => 'Facts about you and the channel', 'type' => 'textarea', 'required' => true]],
                'prompt' => "From these facts, write a one-line bio, a 50-word bio and a 120-word bio for a creator media kit. Use only these facts:\n\n{facts}",
            ],
        ];
    }

    /** All tools, as the frontend sees them. */
    public static function all(): array
    {
        $tools = [];
        foreach (self::builtIn() as $key => $t) {
            $tools[] = ['key' => $key, 'custom' => false, 'results' => $t['results'] ?? false] + collect($t)->except('prompt')->all();
        }
        foreach (CustomTool::orderBy('name')->get() as $c) {
            $tools[] = [
                'key' => "custom_{$c->id}", 'id' => $c->id, 'custom' => true, 'results' => true,
                'name' => $c->name, 'category' => 'custom', 'icon' => $c->icon, 'description' => $c->description,
                'fields' => $c->fields, 'prompt' => $c->prompt,
            ];
        }

        return $tools;
    }

    /** @return array{name: string, fields: array, prompt: string, results: bool} */
    public static function find(string $key): array
    {
        if (preg_match('/^custom_(\d+)$/', $key, $m)) {
            $c = CustomTool::find($m[1]);
            if ($c) {
                return ['name' => $c->name, 'fields' => $c->fields, 'prompt' => $c->prompt, 'results' => true];
            }
        }
        $tool = self::builtIn()[$key] ?? null;
        if (! $tool) {
            throw new InvalidArgumentException("Unknown tool: {$key}");
        }

        return $tool + ['results' => false];
    }

    /** Fill {placeholders}; empty optional fields become "(not given)". */
    public static function fill(string $template, array $fields, array $inputs): string
    {
        $replace = [];
        foreach ($fields as $f) {
            $value = trim((string) ($inputs[$f['name']] ?? ''));
            $replace['{'.$f['name'].'}'] = $value !== '' ? mb_substr($value, 0, 20000) : '(not given)';
        }

        return strtr($template, $replace);
    }

    /** @param array{tone?: string, language?: string, results?: int} $options */
    public static function userPrompt(array $tool, array $inputs, array $options): string
    {
        $prompt = self::fill($tool['prompt'], $tool['fields'], $inputs);
        $extra = [];
        if (! empty($options['tone'])) {
            $extra[] = "Tone for this piece: {$options['tone']}.";
        }
        if (! empty($options['language'])) {
            $extra[] = "Write it in {$options['language']}.";
        }
        $n = (int) ($options['results'] ?? 1);
        if ($tool['results'] && $n > 1) {
            $extra[] = "Give {$n} different options.";
        }

        return $prompt.($extra ? "\n\n".implode(' ', $extra) : '');
    }

    public static function system(): string
    {
        return Prompts::toolSystem();
    }

    /** Validate a custom tool's fields array from the frontend. */
    public static function cleanFields(array $fields): array
    {
        $out = [];
        foreach ($fields as $f) {
            $name = preg_replace('/[^a-z0-9_]/', '', strtolower((string) ($f['name'] ?? '')));
            if ($name === '' || isset($out[$name])) {
                continue;
            }
            $out[$name] = [
                'name' => $name,
                'label' => mb_substr(trim((string) ($f['label'] ?? $name)), 0, 100) ?: $name,
                'type' => in_array($f['type'] ?? '', ['text', 'textarea', 'url'], true) ? $f['type'] : 'textarea',
                'required' => (bool) ($f['required'] ?? false),
            ];
        }

        return array_values($out);
    }
}
