<?php

namespace App\Domains\Copilot;

use App\Domains\Audience\Models\AudienceInsight;
use App\Domains\Social\Models\SocialPost;
use App\Domains\Social\Platforms;
use App\Domains\Social\SocialAnalytics;
use App\Http\Controllers\Api\SocialController;
use App\Domains\Copilot\Models\CopilotAction;
use App\Domains\Copilot\Models\CopilotThread;
use App\Domains\Feeds\FeedReader;
use App\Domains\Feeds\Models\Feed;
use App\Domains\Memory\Memory;
use App\Domains\Search\WebSearch;
use App\Domains\Toolkit\Models\Document;
use App\Domains\Toolkit\ToolRegistry;
use App\Models\Channel;
use App\Models\Idea;
use App\Models\Outlier;
use App\Models\Script;
use App\Models\Week;
use App\Services\AiTellsLinter;
use App\Services\Llm;
use App\Services\NextStep;
use RuntimeException;
use Throwable;

/**
 * Everything the Copilot can do in the studio.
 *
 *  - read tools run straight away (looking things up never changes her data);
 *  - client tools are handed to the browser (e.g. open a page);
 *  - write tools become proposals (copilot_actions) that only run when she presses Apply,
 *    and record what's needed to Undo. In Ask mode write tools aren't offered at all.
 */
class CopilotTools
{
    public const WRITE = ['edit_script', 'create_script', 'create_idea', 'update_idea', 'add_competitor_channels', 'add_news_feed', 'save_to_library', 'set_week_video', 'create_social_post'];

    public const CLIENT = ['open_page'];

    public function __construct(private Memory $memory, private Llm $llm) {}

    /** @return array<int, array{name: string, description: string, parameters: array}> */
    public function definitions(string $mode): array
    {
        $s = fn (array $props, array $required = []) => ['type' => 'object', 'properties' => (object) $props, 'required' => $required, 'additionalProperties' => false];
        $str = fn (string $d, ?array $enum = null) => array_filter(['type' => 'string', 'description' => $d, 'enum' => $enum]);
        $int = fn (string $d) => ['type' => 'integer', 'description' => $d];

        $tools = [
            ['name' => 'get_studio_overview', 'description' => "Her current week, scoreboard, picked ideas, scripts in progress and top breakouts. Use first when you need context.", 'parameters' => $s([])],
            ['name' => 'get_script', 'description' => 'Read one script: title, status, full body, chapter plan, and whether it has a review or fact-check.', 'parameters' => $s(['script_id' => $int('Script id')], ['script_id'])],
            ['name' => 'list_scripts', 'description' => 'List her scripts (id, title, status, words, last edited).', 'parameters' => $s(['status' => $str('Filter', ['draft', 'reviewed', 'filmed', 'published'])])],
            ['name' => 'list_ideas', 'description' => 'List video ideas, optionally filtered by status or a search word.', 'parameters' => $s(['status' => $str('Filter', ['new', 'picked', 'scripted', 'dropped']), 'query' => $str('Search title/premise')])],
            ['name' => 'list_breakouts', 'description' => 'Recent breakout videos in her niche (views far above their channel median).', 'parameters' => $s(['limit' => $int('How many, max 20')])],
            ['name' => 'list_viewer_requests', 'description' => 'What viewers asked for in the comments of her videos (and any competitor videos she read): questions, requests, confusion, pushback, each with mention counts and a real quote. Strongest signal for what to make next.', 'parameters' => $s([])],
            ['name' => 'get_social_analytics', 'description' => 'Her social numbers for the last 4 weeks per platform (posts, engagement, change, best formats), her top posts, and proven posts worth a fresh take. Use for "what is working" questions.', 'parameters' => $s([])],
            ['name' => 'list_brand_deals', 'description' => 'Her brand sponsorships and deals pipeline: brand, contact, stage, fee, deliverables, and payment status.', 'parameters' => $s(['stage' => $str('Filter stage', \App\Models\Deal::STAGES)])],
            ['name' => 'get_rate_card', 'description' => 'Recommended rate card benchmarks across YouTube, LinkedIn, TikTok, Instagram and X based on her actual viewership.', 'parameters' => $s([])],
            ['name' => 'list_social_posts', 'description' => 'Her LinkedIn, Instagram, X, TikTok, Threads and YouTube (Shorts, community) posts: status, schedule, text and logged numbers, plus this week’s progress against her targets.', 'parameters' => $s(['platform' => $str('Filter', ['linkedin', 'instagram', 'x', 'tiktok', 'threads', 'youtube']), 'status' => $str('Filter', ['idea', 'draft', 'scheduled', 'posted'])])],
            ['name' => 'search_my_work', 'description' => 'Search her past scripts, on-camera transcripts, notes, ideas and Library. Use to match her voice or check if she already covered something.', 'parameters' => $s(['query' => $str('What to look for')], ['query'])],
            ['name' => 'search_library', 'description' => 'Search saved Library documents (titles, captions, uploaded briefs) by keyword.', 'parameters' => $s(['query' => $str('Keyword')], ['query'])],
            ['name' => 'check_ai_tells', 'description' => 'Run the AI-tell linter on text (em dashes, banned clichés, missing contractions). Free and instant: use it on anything you write for her.', 'parameters' => $s(['text' => $str('Text to check')], ['text'])],
            ['name' => 'run_writing_tool', 'description' => 'Run a Toolkit template: yt_titles, yt_thumbnail, yt_hooks, yt_description, yt_tags, yt_chapters, yt_community, yt_replies, shorts_script, ig_caption, hashtags, x_thread, linkedin, newsletter, rewrite, tldr, proofread, pros_cons, sponsor_pitch, sponsor_read, media_kit. Inputs are the template fields (usually "topic", "title", "text" or "script").', 'parameters' => $s(['tool' => $str('Template key'), 'inputs' => ['type' => 'object', 'description' => 'Field values', 'additionalProperties' => ['type' => 'string']]], ['tool', 'inputs'])],
            ['name' => 'open_page', 'description' => "Open a page in her studio: '/', '/ideas', '/scripts', '/scripts/{id}', '/breakouts', '/channel', '/calendar', '/analytics', '/deals', '/mediakit', '/snippets', '/repurpose', '/linkedin', '/instagram', '/x', '/tiktok', '/threads', '/posts/{id}', '/goals', '/tools', '/library', '/chat', '/settings'.", 'parameters' => $s(['path' => $str('App path')], ['path'])],
        ];
        if (WebSearch::available()) {
            $tools[] = ['name' => 'search_web', 'description' => 'Live web search. Use for facts, news, dates, prices or anything that may have changed. Cite the links.', 'parameters' => $s(['query' => $str('Search query')], ['query'])];
        }

        if ($mode === 'agent') {
            $tools = array_merge($tools, [
                ['name' => 'edit_script', 'description' => "Propose an edit to a script (she approves it). operation: 'replace_text' swaps an exact passage (find) for new text; 'replace_all' rewrites the whole body; 'append' adds to the end. Prefer replace_text for targeted edits. Optional new_title renames it.",
                    'parameters' => $s(['script_id' => $int('Script id'), 'operation' => $str('How to edit', ['replace_text', 'replace_all', 'append']), 'find' => $str('Exact text to replace (replace_text only), copied from the script'), 'text' => $str('The new text'), 'new_title' => $str('Optional new title'), 'reason' => $str('One line: why')], ['script_id', 'operation', 'text'])],
                ['name' => 'create_script', 'description' => 'Propose a new script (optionally from an idea, optionally with a first draft).', 'parameters' => $s(['title' => $str('Working title'), 'body' => $str('Optional draft'), 'idea_id' => $int('Optional idea id')], ['title'])],
                ['name' => 'create_idea', 'description' => 'Propose saving a new video idea (status picked).', 'parameters' => $s(['title' => $str('Working title'), 'premise' => $str('One sentence'), 'angle' => $str('Her angle'), 'thumbnail_text' => $str('3-5 words'), 'notes' => $str('Notes')], ['title'])],
                ['name' => 'update_idea', 'description' => 'Propose changing an idea: status, title or notes.', 'parameters' => $s(['idea_id' => $int('Idea id'), 'status' => $str('New status', ['new', 'picked', 'scripted', 'dropped']), 'title' => $str('New title'), 'notes' => $str('Replace notes')], ['idea_id'])],
                ['name' => 'add_competitor_channels', 'description' => 'Propose adding YouTube channels to her breakout watchlist.', 'parameters' => $s(['handles' => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => '@handles or channel URLs']], ['handles'])],
                ['name' => 'add_news_feed', 'description' => 'Propose adding an RSS/Atom feed as an idea signal.', 'parameters' => $s(['url' => $str('Feed URL')], ['url'])],
                ['name' => 'save_to_library', 'description' => 'Propose saving text (titles, captions, an outline...) to her Library.', 'parameters' => $s(['title' => $str('Title'), 'content' => $str('Markdown content')], ['title', 'content'])],
                ['name' => 'set_week_video', 'description' => "Propose setting a week's planned video on her scoreboard.", 'parameters' => $s(['week_number' => $int('Week number'), 'video_title' => $str('Video title'), 'script_id' => $int('Optional script id')], ['week_number', 'video_title'])],
                ['name' => 'create_social_post', 'description' => 'Propose a draft post (she approves it). Formats: linkedin post|carousel, instagram carousel|reel|post|story, x post|thread, tiktok video|photo, threads post|thread, youtube short|community. Put thread posts, carousel slides or Reel/Short beats in parts.',
                    'parameters' => $s(['platform' => $str('Platform', ['linkedin', 'instagram', 'x', 'tiktok', 'threads', 'youtube']), 'format' => $str('Format'), 'title' => $str('Short internal label'), 'body' => $str('Post text or caption'), 'parts' => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'Thread posts, slides or beats'], 'script_id' => $int('Optional source script id')], ['platform', 'format', 'body'])],
            ]);
        }

        return $tools;
    }

    public static function kind(string $tool): string
    {
        return in_array($tool, self::WRITE, true) ? 'write' : (in_array($tool, self::CLIENT, true) ? 'client' : 'read');
    }

    /** Human label for the step list ("Reading script #4…"). */
    public static function label(string $tool, array $args): string
    {
        return match ($tool) {
            'get_studio_overview' => 'Looking at your studio',
            'get_script' => 'Reading script #'.($args['script_id'] ?? '?'),
            'list_scripts' => 'Listing scripts',
            'list_ideas' => 'Looking through ideas',
            'list_breakouts' => 'Checking breakouts',
            'list_viewer_requests' => 'Reading what your viewers asked for',
            'list_social_posts' => 'Looking at your social posts',
            'get_social_analytics' => 'Looking at your social numbers',
            'search_my_work' => 'Searching your past work for “'.mb_strimwidth((string) ($args['query'] ?? ''), 0, 40, '…').'”',
            'search_library' => 'Searching the Library',
            'search_web' => 'Searching the web for “'.mb_strimwidth((string) ($args['query'] ?? ''), 0, 40, '…').'”',
            'check_ai_tells' => 'Checking for AI tells',
            'run_writing_tool' => 'Running the '.str_replace('_', ' ', (string) ($args['tool'] ?? 'writing')).' tool',
            'open_page' => 'Opening '.($args['path'] ?? 'page'),
            default => 'Preparing a change: '.str_replace('_', ' ', $tool),
        };
    }

    /** Run a read tool; returns the text handed back to the model. */
    public function read(string $tool, array $a): string
    {
        $json = fn ($v) => json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);

        return match ($tool) {
            'get_studio_overview' => $json(self::overview()),
            'get_script' => $json(self::scriptDetail($a['script_id'] ?? 0)),
            'list_scripts' => $json(Script::query()->when($a['status'] ?? null, fn ($q, $st) => $q->where('status', $st))
                ->latest('updated_at')->take(30)->get(['id', 'title', 'status', 'body', 'updated_at'])
                ->map(fn ($s) => ['id' => $s->id, 'title' => $s->title, 'status' => $s->status, 'words' => $s->wordCount(), 'edited' => $s->updated_at?->diffForHumans()])),
            'list_ideas' => $json(Idea::query()
                ->when($a['status'] ?? null, fn ($q, $st) => $q->where('status', $st))
                ->when($a['query'] ?? null, fn ($q, $w) => $q->where(fn ($x) => $x->where('title', 'like', "%{$w}%")->orWhere('premise', 'like', "%{$w}%")))
                ->latest()->take(25)->get(['id', 'title', 'premise', 'angle', 'status', 'why_now'])),
            'list_breakouts' => $json(Outlier::orderByDesc('outlier_score')->take(min(20, max(1, (int) ($a['limit'] ?? 10))))
                ->get(['id', 'title', 'channel_title', 'views', 'outlier_score', 'url', 'published_at'])),
            'get_social_analytics' => $json((function () {
                $o = SocialAnalytics::overview(4);

                return ['platforms' => collect($o['platforms'])->map(fn ($p) => array_diff_key($p, ['series' => 1])),
                    'top' => $o['top']->map(fn ($t) => ['id' => $t['post']->id, 'platform' => $t['post']->platform, 'text' => mb_substr($t['post']->asText(), 0, 200), 'vs_median' => $t['vs_median']]),
                    'worth_a_fresh_take' => SocialAnalytics::recyclable(5)->map(fn ($t) => ['id' => $t['post']->id, 'platform' => $t['post']->platform, 'vs_median' => $t['vs_median']])];
            })()),
            'list_brand_deals' => $json((function () use ($a) {
                $q = \App\Models\Deal::orderBy('created_at', 'desc');
                if (!empty($a['stage'])) {
                    $q->where('stage', $a['stage']);
                }
                return $q->take(20)->get(['id', 'brand_name', 'stage', 'rate', 'currency', 'payment_status', 'deliverables', 'created_at']);
            })()),
            'get_rate_card' => $json(\App\Domains\Deals\DealCalculator::calculateRates()),
            'list_social_posts' => $json([
                'progress' => SocialController::progress(),
                'posts' => SocialPost::query()->when($a['platform'] ?? null, fn ($q, $p) => $q->where('platform', $p))->when($a['status'] ?? null, fn ($q, $st) => $q->where('status', $st))
                    ->latest('updated_at')->take(20)->get()->map(fn ($p) => ['id' => $p->id, 'platform' => $p->platform, 'format' => $p->format, 'status' => $p->status, 'title' => $p->title,
                        'text' => mb_substr($p->asText(), 0, 600), 'scheduled_for' => $p->scheduled_for?->toIso8601String(), 'posted_at' => $p->posted_at?->toIso8601String(), 'engagement' => $p->engagement]),
            ]),
            'list_viewer_requests' => $json(AudienceInsight::latest('updated_at')->take(10)->get()
                ->map(fn ($i) => ['video' => $i->video_title, 'her_video' => $i->own, 'url' => $i->url, 'summary' => $i->summary, 'items' => $i->items])),
            'search_my_work' => $json(array_map(fn ($h) => ['type' => $h['source_type'], 'id' => $h['source_id'], 'title' => $h['title'], 'excerpt' => mb_substr($h['content'], 0, 900)],
                $this->memory->search((string) ($a['query'] ?? ''), 5))),
            'search_library' => $json(Document::query()->where(fn ($q) => $q->where('title', 'like', '%'.($a['query'] ?? '').'%')->orWhere('content', 'like', '%'.($a['query'] ?? '').'%'))
                ->latest('updated_at')->take(8)->get(['id', 'title', 'tool', 'content'])->map(fn ($d) => ['id' => $d->id, 'title' => $d->title, 'tool' => $d->tool, 'excerpt' => mb_substr($d->content, 0, 700)])),
            'search_web' => WebSearch::forPrompt(app(WebSearch::class)->search((string) ($a['query'] ?? ''), 6)),
            'check_ai_tells' => $json(AiTellsLinter::lint((string) ($a['text'] ?? ''))) ?: '[]',
            'run_writing_tool' => $this->writingTool((string) ($a['tool'] ?? ''), (array) ($a['inputs'] ?? [])),
            default => throw new RuntimeException("Unknown tool: {$tool}"),
        };
    }

    private function writingTool(string $key, array $inputs): string
    {
        $tool = ToolRegistry::find($key);
        $inputs = array_map(fn ($v) => is_scalar($v) ? (string) $v : json_encode($v), $inputs);

        return $this->llm->as('copilot:tool:'.$key)->chat(ToolRegistry::system(), ToolRegistry::userPrompt($tool, $inputs, ['results' => 3]));
    }

    public static function overview(): array
    {
        $w = Week::current();

        return [
            'next_step' => NextStep::current(),
            'this_week' => $w ? ['number' => $w->number, 'due' => $w->due_date?->toDateString(), 'status' => $w->status, 'video_title' => $w->video_title] : null,
            'scoreboard' => Week::orderBy('number')->get(['number', 'status', 'video_title'])->map(fn ($x) => "W{$x->number}: {$x->status}".($x->video_title ? " ({$x->video_title})" : ''))->all(),
            'picked_ideas' => Idea::where('status', 'picked')->latest()->take(8)->get(['id', 'title', 'premise']),
            'scripts_in_progress' => Script::whereIn('status', ['draft', 'reviewed', 'filmed'])->latest('updated_at')->take(8)->get(['id', 'title', 'status']),
            'top_breakouts' => Outlier::orderByDesc('outlier_score')->take(5)->get(['id', 'title', 'channel_title', 'outlier_score']),
        ];
    }

    private static function scriptDetail(int $id): array
    {
        $s = Script::find($id) ?? throw new RuntimeException("No script with id {$id}. Use list_scripts.");

        return [
            'id' => $s->id, 'title' => $s->title, 'status' => $s->status, 'words' => $s->wordCount(),
            'body' => mb_substr((string) $s->body, 0, 25000),
            'plan' => $s->outline,
            'has_review' => (bool) $s->review, 'review_excerpt' => $s->review ? mb_substr($s->review, 0, 1500) : null,
            'has_fact_check' => (bool) $s->fact_check,
            'youtube_url' => $s->youtube_url,
        ];
    }

    // ---- write tools: propose → apply → undo ------------------------------------------

    /** Validate and store a proposal; returns the action and a preview for the panel. */
    public function propose(CopilotThread $thread, string $tool, array $a): CopilotAction
    {
        [$summary, $preview] = match ($tool) {
            'edit_script' => $this->previewEdit($a),
            'create_script' => ['New script: “'.self::need($a, 'title').'”', ['body_words' => str_word_count((string) ($a['body'] ?? ''))]],
            'create_idea' => ['Save idea: “'.self::need($a, 'title').'”', ['premise' => $a['premise'] ?? null]],
            'update_idea' => $this->previewIdea($a),
            'add_competitor_channels' => ['Add '.count((array) self::need($a, 'handles')).' channel(s) to the watchlist: '.implode(', ', array_map(fn ($h) => Channel::normalize((string) $h), (array) $a['handles'])), []],
            'add_news_feed' => ['Add news feed: '.self::need($a, 'url'), []],
            'create_social_post' => (function () use ($a) {
                if (! Platforms::valid((string) self::need($a, 'platform'), (string) self::need($a, 'format'))) {
                    throw new RuntimeException('Unknown platform/format. Use one from the tool description.');
                }

                return ['New '.Platforms::PLATFORMS[$a['platform']]['label'].' '.$a['format'].' draft', ['text' => mb_substr((string) ($a['body'] ?? ''), 0, 400), 'parts' => count((array) ($a['parts'] ?? []))]];
            })(),
            'save_to_library' => ['Save to Library: “'.self::need($a, 'title').'”', ['words' => str_word_count((string) self::need($a, 'content'))]],
            'set_week_video' => $this->previewWeek($a),
            default => throw new RuntimeException("Unknown tool: {$tool}"),
        };

        return CopilotAction::create([
            'copilot_thread_id' => $thread->id, 'tool' => $tool, 'args' => $a,
            'summary' => mb_substr($summary, 0, 300), 'status' => 'pending', 'result' => ['preview' => $preview],
        ]);
    }

    private function previewEdit(array $a): array
    {
        $s = Script::find((int) self::need($a, 'script_id')) ?? throw new RuntimeException('No script with that id. Use list_scripts.');
        $op = self::need($a, 'operation');
        $text = (string) self::need($a, 'text');
        $body = (string) $s->body;
        if ($op === 'replace_text') {
            $find = (string) self::need($a, 'find');
            if ($find === '' || ! str_contains($body, $find)) {
                throw new RuntimeException('The "find" text is not in the script exactly as given. Call get_script and copy the passage exactly.');
            }
        } elseif (! in_array($op, ['replace_all', 'append'], true)) {
            throw new RuntimeException('operation must be replace_text, replace_all or append.');
        }
        $after = self::applyEdit($body, $op, $text, $a['find'] ?? null);
        $label = ['replace_text' => 'Edit a passage in', 'replace_all' => 'Rewrite', 'append' => 'Add to the end of'][$op];

        return ["{$label} “{$s->title}”".(! empty($a['new_title']) ? " (rename to “{$a['new_title']}”)" : ''), [
            'script_id' => $s->id, 'operation' => $op, 'reason' => $a['reason'] ?? null,
            'before' => $op === 'replace_text' ? $a['find'] : ($op === 'append' ? null : mb_substr($body, 0, 1200)),
            'after' => $op === 'append' ? $text : ($op === 'replace_text' ? $text : mb_substr($after, 0, 1200)),
            'words_before' => str_word_count($body), 'words_after' => str_word_count($after),
            'new_title' => $a['new_title'] ?? null,
        ]];
    }

    private function previewIdea(array $a): array
    {
        $i = Idea::find((int) self::need($a, 'idea_id')) ?? throw new RuntimeException('No idea with that id. Use list_ideas.');
        $changes = array_filter(['status' => $a['status'] ?? null, 'title' => $a['title'] ?? null, 'notes' => $a['notes'] ?? null], fn ($v) => $v !== null && $v !== '');
        if (! $changes) {
            throw new RuntimeException('Nothing to change: give status, title or notes.');
        }

        return ['Update idea “'.$i->title.'”: '.implode(', ', array_keys($changes)), $changes];
    }

    private function previewWeek(array $a): array
    {
        $w = Week::where('number', (int) self::need($a, 'week_number'))->first() ?? throw new RuntimeException('No week with that number. Check get_studio_overview.');
        if (! empty($a['script_id']) && ! Script::whereKey($a['script_id'])->exists()) {
            throw new RuntimeException('No script with that id.');
        }

        return ["Week {$w->number}: plan “".self::need($a, 'video_title').'”', ['previous' => $w->video_title]];
    }

    /** Execute an approved proposal. Returns the result (with what's needed to undo). */
    public function apply(CopilotAction $action): array
    {
        $a = $action->args;

        return match ($action->tool) {
            'edit_script' => (function () use ($a) {
                $s = Script::findOrFail($a['script_id']);
                $before = ['body' => $s->body, 'title' => $s->title];
                $body = (string) $s->body;
                if ($a['operation'] === 'replace_text' && ! str_contains($body, (string) $a['find'])) {
                    throw new RuntimeException('The script changed since this was proposed: the passage is no longer there. Ask the Copilot again.');
                }
                $s->update(array_filter(['body' => self::applyEdit($body, $a['operation'], (string) $a['text'], $a['find'] ?? null), 'title' => $a['new_title'] ?? null], fn ($v) => $v !== null));

                return ['message' => 'Script updated.', 'link' => "/scripts/{$s->id}", 'script' => $s->fresh(), 'undo' => ['script_id' => $s->id] + $before];
            })(),
            'create_script' => (function () use ($a) {
                $s = Script::create(['title' => $a['title'], 'body' => $a['body'] ?? '', 'idea_id' => ! empty($a['idea_id']) && Idea::whereKey($a['idea_id'])->exists() ? $a['idea_id'] : null]);

                return ['message' => 'Script created.', 'link' => "/scripts/{$s->id}", 'undo' => ['script_id' => $s->id]];
            })(),
            'create_idea' => (function () use ($a) {
                $i = Idea::create(array_intersect_key($a, array_flip(['title', 'premise', 'angle', 'thumbnail_text', 'notes'])) + ['status' => 'picked']);

                return ['message' => 'Idea saved to Picked.', 'link' => '/ideas', 'undo' => ['idea_id' => $i->id]];
            })(),
            'update_idea' => (function () use ($a) {
                $i = Idea::findOrFail($a['idea_id']);
                $fields = array_filter(array_intersect_key($a, array_flip(['status', 'title', 'notes'])), fn ($v) => $v !== null && $v !== '');
                $before = array_intersect_key($i->only(array_keys($fields)), $fields);
                $i->update($fields);

                return ['message' => 'Idea updated.', 'link' => '/ideas', 'undo' => ['idea_id' => $i->id, 'before' => $before]];
            })(),
            'add_competitor_channels' => (function () use ($a) {
                $ids = [];
                foreach ((array) $a['handles'] as $raw) {
                    $h = Channel::normalize((string) $raw);
                    if ($h !== '' && ! Channel::where('handle', $h)->exists()) {
                        $ids[] = Channel::create(['handle' => $h])->id;
                    }
                }

                return ['message' => count($ids).' channel(s) added. Run a scan on Breakouts.', 'link' => '/breakouts', 'undo' => ['channel_ids' => $ids]];
            })(),
            'add_news_feed' => (function () use ($a) {
                $reader = app(FeedReader::class);
                $url = preg_match('~^https?://~i', $a['url']) ? $a['url'] : 'https://'.$a['url'];
                if (Feed::where('url', $url)->exists()) {
                    throw new RuntimeException('That feed is already on your list.');
                }
                $parsed = $reader->parse($url);
                $feed = Feed::create(['url' => $url, 'title' => $parsed['title']]);
                $new = $reader->refresh($feed, $parsed);

                return ['message' => "Feed added with {$new} item(s).", 'link' => '/breakouts', 'undo' => ['feed_id' => $feed->id]];
            })(),
            'create_social_post' => (function () use ($a) {
                $p = SocialPost::create([
                    'platform' => $a['platform'], 'format' => $a['format'], 'title' => mb_substr((string) ($a['title'] ?? ''), 0, 200) ?: null,
                    'body' => (string) ($a['body'] ?? ''), 'parts' => array_values(array_map('strval', (array) ($a['parts'] ?? []))),
                    'status' => 'draft', 'source' => 'copilot',
                    'script_id' => ! empty($a['script_id']) && Script::whereKey($a['script_id'])->exists() ? $a['script_id'] : null,
                ]);

                return ['message' => Platforms::PLATFORMS[$p->platform]['label'].' draft saved.', 'link' => "/posts/{$p->id}", 'undo' => ['post_id' => $p->id]];
            })(),
            'save_to_library' => (function () use ($a) {
                $d = Document::create(['title' => mb_substr($a['title'], 0, 200), 'content' => $a['content'], 'tool' => 'copilot']);

                return ['message' => 'Saved to your Library.', 'link' => "/library?doc={$d->id}", 'undo' => ['document_id' => $d->id]];
            })(),
            'set_week_video' => (function () use ($a) {
                $w = Week::where('number', $a['week_number'])->firstOrFail();
                $before = $w->only(['video_title', 'script_id']);
                $w->update(['video_title' => $a['video_title'], 'script_id' => $a['script_id'] ?? $w->script_id]);

                return ['message' => "Week {$w->number} updated.", 'link' => '/goals', 'undo' => ['week_id' => $w->id, 'before' => $before]];
            })(),
            default => throw new RuntimeException('Unknown action.'),
        };
    }

    public function undo(CopilotAction $action): string
    {
        $u = $action->result['undo'] ?? null;
        if (! $u) {
            throw new RuntimeException('This change can’t be undone.');
        }
        switch ($action->tool) {
            case 'edit_script':
                Script::whereKey($u['script_id'])->update(['body' => $u['body'], 'title' => $u['title']]);
                Script::find($u['script_id'])?->touch();
                break;
            case 'create_script':
                Script::find($u['script_id'])?->delete();
                break;
            case 'create_idea':
                Idea::find($u['idea_id'])?->delete();
                break;
            case 'update_idea':
                Idea::find($u['idea_id'])?->update($u['before']);
                break;
            case 'add_competitor_channels':
                Channel::whereIn('id', $u['channel_ids'])->get()->each->delete();
                break;
            case 'add_news_feed':
                Feed::find($u['feed_id'])?->delete();
                break;
            case 'save_to_library':
                Document::find($u['document_id'])?->delete();
                break;
            case 'create_social_post':
                SocialPost::find($u['post_id'])?->delete();
                break;
            case 'set_week_video':
                Week::find($u['week_id'])?->update($u['before']);
                break;
            default:
                throw new RuntimeException('This change can’t be undone.');
        }

        return 'Undone.';
    }

    public static function applyEdit(string $body, string $op, string $text, ?string $find): string
    {
        return match ($op) {
            'replace_text' => (static function () use ($body, $text, $find) {
                $pos = strpos($body, (string) $find);

                return $pos === false ? $body : substr_replace($body, $text, $pos, strlen((string) $find));
            })(),
            'replace_all' => $text,
            'append' => rtrim($body)."\n\n".ltrim($text),
            default => $body,
        };
    }

    private static function need(array $a, string $key): mixed
    {
        if (! array_key_exists($key, $a) || $a[$key] === null || $a[$key] === '' || $a[$key] === []) {
            throw new RuntimeException("Missing \"{$key}\".");
        }

        return $a[$key];
    }

    /** Run any tool safely for the agent loop: errors come back as text the model can react to. */
    public function safeRead(string $tool, array $args): string
    {
        try {
            return mb_substr($this->read($tool, $args), 0, 30000);
        } catch (Throwable $e) {
            return 'ERROR: '.$e->getMessage();
        }
    }
}
