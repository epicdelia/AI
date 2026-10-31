<?php

namespace App\Domains\Agents;

use App\Domains\Agents\Models\AgentEvent;
use App\Domains\Agents\Models\SocialAgent;
use App\Domains\Audience\Models\AudienceInsight;
use App\Domains\Feeds\Models\FeedItem;
use App\Domains\Social\Models\SocialPost;
use App\Domains\Social\Platforms;
use App\Domains\Social\PostScorer;
use App\Domains\Social\Repurposer;
use App\Domains\Social\SocialScheduler;
use App\Models\Outlier;
use App\Models\Script;
use App\Services\AiTellsLinter;
use App\Services\Llm;
use App\Services\Prompts;
use Illuminate\Support\Collection;
use RuntimeException;

/**
 * An agent's daily run: top up the next 7 days to its posts-per-week target on each network,
 * write those posts (with its brand, audience, post types, learned lessons and live signals),
 * and put each in the next good free slot. In "review" mode they wait for her approval; in "auto" mode
 * they're scheduled and the publisher sends them at their time.
 */
class AgentPlanner
{
    /** Default format an agent writes per network (text-first, so they can be published). */
    public const FORMATS = ['linkedin' => 'post', 'threads' => 'post', 'x' => 'post', 'instagram' => 'carousel', 'tiktok' => 'photo', 'youtube' => 'community'];

    public function __construct(private Llm $llm) {}

    /** @return array<int, SocialPost> the posts it wrote */
    public function run(SocialAgent $agent): array
    {
        $needed = $this->needed($agent);
        $agent->update(['last_run_at' => now(), 'next_run_at' => now()->addDay()]);
        if (! array_sum($needed)) {
            return [];
        }

        $targets = [];
        foreach ($needed as $platform => $n) {
            for ($i = 0; $i < $n; $i++) {
                $targets[] = $platform;
            }
        }
        $data = $this->llm->as('agent')->creativity(0.8)->json($this->system($agent), $this->brief($agent, $targets));

        $made = [];
        $taken = $this->pending();
        foreach (array_values($data['posts'] ?? []) as $i => $p) {
            $platform = (string) ($p['platform'] ?? $targets[$i] ?? '');
            if (! in_array($platform, $agent->platforms, true) || ($needed[$platform] ?? 0) <= 0) {
                continue;
            }
            $needed[$platform]--;
            $format = self::FORMATS[$platform];
            $parts = Platforms::FORMATS[$platform][$format][1] !== null
                ? array_values(array_filter(array_map(fn ($x) => is_scalar($x) ? trim((string) $x) : '', (array) ($p['parts'] ?? [])))) : [];
            $body = trim((string) ($p['body'] ?? ''));
            if ($body === '' && ! $parts) {
                continue;
            }

            // Quality loop: lint for hard AI tells and self-correct if needed
            $findings = AiTellsLinter::lint($body);
            if (AiTellsLinter::hasHard($findings) && Llm::configured()) {
                $refined = $this->refineDraft($agent, $platform, $format, $body, $parts, $findings);
                if ($refined) {
                    $body = $refined['body'];
                    $parts = $refined['parts'];
                }
            }

            $scoreData = rescue(fn () => (new PostScorer($this->llm))->score([
                'platform' => $platform,
                'format' => $format,
                'body' => $body,
                'parts' => $parts,
            ]), null);
            $score = $scoreData['score'] ?? null;

            $slot = SocialScheduler::nextSlot($platform, null, null, $taken);
            $auto = $agent->approval === 'auto';
            $post = SocialPost::create([
                'platform' => $platform, 'format' => $format,
                'title' => mb_substr(trim((string) ($p['title'] ?? '')) ?: "{$agent->name} · ".Platforms::PLATFORMS[$platform]['label'], 0, 200),
                'body' => $body, 'parts' => array_slice($parts, 0, 20),
                'status' => $auto ? 'scheduled' : 'draft', 'scheduled_for' => $slot['at'],
                'source' => 'agent', 'social_agent_id' => $agent->id,
                'approval' => $auto ? 'approved' : 'pending',
                'agent_original' => trim($body."\n\n".implode("\n\n", $parts)),
                'pillar' => in_array($p['pillar'] ?? null, Repurposer::pillars(), true) ? $p['pillar'] : null,
                'notes' => trim(($score ? "Score: {$score}/100 (".($scoreData['label'] ?? 'Predicted').")\n" : '').'Why this: '.mb_substr((string) ($p['why'] ?? ''), 0, 300)."\nWhen: ".$slot['reason']),
            ]);
            $taken->push($post);
            $made[] = $post;
        }

        if ($made) {
            $nets = implode(', ', array_unique(array_map(fn ($p) => Platforms::PLATFORMS[$p->platform]['label'], $made)));
            AgentEvent::log('drafted', $agent->approval === 'auto'
                ? count($made)." new post(s) scheduled for {$nets}."
                : count($made)." new post(s) for {$nets} are waiting for your approval.", $agent->id);
        }

        return $made;
    }

    /**
     * Autonomously turn a video script into a full campaign of native social posts across the agent's networks.
     *
     * @return array<int, SocialPost>
     */
    public function repurposeScript(SocialAgent $agent, Script $script): array
    {
        self::assertReady();

        $targets = [];
        foreach ($agent->platforms as $platform) {
            $format = self::FORMATS[$platform] ?? 'post';
            $targets[] = ['platform' => $platform, 'format' => $format];
        }

        $repurposer = new Repurposer($this->llm);
        $sourceText = "Title: {$script->title}\n\nOutline & Chapters:\n".($script->outline ? json_encode($script->outline, JSON_UNESCAPED_UNICODE) : 'None')."\n\nScript Body:\n".mb_substr((string) $script->body, 0, 16000);

        $posts = $repurposer->run($script->title, $sourceText, $targets, $script->id, "Voice of {$agent->name}. Tone: ".($agent->option('tone') ?? 'Authentic and developer-friendly.'));

        $made = [];
        $taken = $this->pending();

        foreach ($posts as $post) {
            $slot = SocialScheduler::nextSlot($post->platform, null, null, $taken);
            $auto = $agent->approval === 'auto';

            $post->update([
                'social_agent_id' => $agent->id,
                'source' => 'agent_repurpose',
                'script_id' => $script->id,
                'status' => $auto ? 'scheduled' : 'draft',
                'approval' => $auto ? 'approved' : 'pending',
                'scheduled_for' => $slot['at'],
                'agent_original' => trim($post->body."\n\n".implode("\n\n", $post->parts ?? [])),
                'notes' => "Autonomously repurposed from script: “{$script->title}”.\nSlot: {$slot['reason']}",
            ]);

            $taken->push($post);
            $made[] = $post;
        }

        if ($made) {
            AgentEvent::log('drafted', "Autonomously repurposed “{$script->title}” into ".count($made)." post(s) across ".implode(', ', $agent->platforms).'.', $agent->id);
        }

        return $made;
    }

    /**
     * Autonomously write a trending reaction post reacting to a breakout competitor video.
     */
    public function fromBreakout(SocialAgent $agent, Outlier $outlier, ?string $preferredPlatform = null): SocialPost
    {
        self::assertReady();

        $platform = $preferredPlatform && in_array($preferredPlatform, $agent->platforms, true)
            ? $preferredPlatform
            : ($agent->platforms[0] ?? 'linkedin');

        $format = self::FORMATS[$platform] ?? 'post';

        $prompt = 'A video just broke out in '.Prompts::name()."’s niche:\n"
            ."- Title: {$outlier->title}\n"
            .'- Channel: '.($outlier->channel?->title ?: 'Competitor')."\n"
            ."- Outlier score: {$outlier->outlier_score}x channel median views ({$outlier->views} views)\n"
            .($outlier->why_it_worked ? "- Why it worked: {$outlier->why_it_worked}\n" : '')
            ."\nWrite a first-person {$platform} {$format} giving her own authentic developer perspective on this exact trending topic. "
            ."Do not mention that it's a competitor video; treat it as an industry trend you have thoughts on.\n\n"
            .'Format rules: '.Repurposer::GUIDES["{$platform}.{$format}"]."\n\n"
            .'Output JSON: {"title": "short label", "body": "post text", "parts": [], "why": "the angle"}';

        $data = $this->llm->as('agent')->creativity(0.7)->json(
            $this->system($agent),
            $prompt
        );

        $taken = $this->pending();
        $slot = SocialScheduler::nextSlot($platform, null, null, $taken);
        $auto = $agent->approval === 'auto';
        $body = trim((string) ($data['body'] ?? ''));
        $parts = (array) ($data['parts'] ?? []);

        $post = SocialPost::create([
            'platform' => $platform,
            'format' => $format,
            'title' => mb_substr(trim((string) ($data['title'] ?? '')) ?: "Breakout Reaction: {$outlier->title}", 0, 200),
            'body' => $body,
            'parts' => $parts,
            'status' => $auto ? 'scheduled' : 'draft',
            'scheduled_for' => $slot['at'],
            'source' => 'agent_breakout',
            'social_agent_id' => $agent->id,
            'approval' => $auto ? 'approved' : 'pending',
            'agent_original' => trim($body."\n\n".implode("\n\n", $parts)),
            'notes' => "Inspired by breakout video: “{$outlier->title}” ({$outlier->outlier_score}x).\nSlot: {$slot['reason']}",
        ]);

        $outlier->update(['used' => true]);

        AgentEvent::log('drafted', "Drafted trending reaction on {$platform} to breakout: “{$outlier->title}”.", $agent->id, $post->id, $platform);

        return $post;
    }

    /**
     * Gather current studio intelligence and market signals to inspire the agent's writing.
     *
     * @return array{breakouts: Collection, news: Collection, scripts: Collection, asks: Collection}
     */
    public static function gatherSignals(): array
    {
        $breakouts = Outlier::where('created_at', '>=', now()->subDays(14))
            ->orderByDesc('outlier_score')
            ->take(4)
            ->get(['id', 'title', 'outlier_score', 'channel_title', 'why_it_worked']);

        $news = FeedItem::latest()
            ->take(4)
            ->get(['id', 'title', 'source', 'published_at']);

        $scripts = Script::whereIn('status', ['published', 'reviewed', 'filmed'])
            ->latest('updated_at')
            ->take(3)
            ->get(['id', 'title', 'status']);

        $asks = AudienceInsight::latest()
            ->take(3)
            ->get(['id', 'video_title', 'summary', 'items']);

        return [
            'breakouts' => $breakouts,
            'news' => $news,
            'scripts' => $scripts,
            'asks' => $asks,
        ];
    }

    private function refineDraft(SocialAgent $agent, string $platform, string $format, string $body, array $parts, array $findings): ?array
    {
        try {
            $issues = [];
            foreach ($findings as $f) {
                if ($f['severity'] === AiTellsLinter::HARD) {
                    $issues[] = "Hard AI tell on line {$f['line']}: avoid banned phrase \"{$f['match']}\".";
                }
            }
            if (! $issues) {
                return null;
            }

            $prompt = "The initial draft for a {$platform} {$format} had these issues:\n".implode("\n", $issues)."\n\n"
                ."Initial body:\n{$body}\n"
                .($parts ? "Initial parts:\n".json_encode($parts, JSON_UNESCAPED_UNICODE)."\n" : '')
                ."\nRewrite it cleanly to fix all issues while preserving the core insight in ".Prompts::name().'’s voice. '
                .'Output JSON: {"body": "refined text", "parts": []}';

            $data = $this->llm->as('agent-critic')->creativity(0.6)->json(
                Prompts::voice()."\n\n".Prompts::writingRules(),
                $prompt
            );

            if (! empty($data['body']) || ! empty($data['parts'])) {
                return [
                    'body' => trim((string) ($data['body'] ?? $body)),
                    'parts' => (array) ($data['parts'] ?? $parts),
                ];
            }
        } catch (\Throwable $e) {
            // Never break creation on self-refinement failure
        }

        return null;
    }

    /** Posts still to write per network for the next 7 days. */
    public function needed(SocialAgent $agent): array
    {
        $out = [];
        foreach ($agent->platforms as $platform) {
            $have = SocialPost::where('social_agent_id', $agent->id)->where('platform', $platform)
                ->whereIn('status', ['draft', 'scheduled'])->whereNotNull('scheduled_for')
                ->whereBetween('scheduled_for', [now(), now()->addDays(7)])->count();
            $out[$platform] = max(0, min(14, $agent->posts_per_week) - $have);
        }

        return $out;
    }

    /** Agent drafts waiting for approval still hold their slot. */
    private function pending(): Collection
    {
        return SocialPost::where('approval', 'pending')->whereNotNull('scheduled_for')->where('scheduled_for', '>=', now())->get();
    }

    private function system(SocialAgent $agent): string
    {
        $guides = implode("\n", array_map(fn ($p) => "- {$p}: ".Repurposer::GUIDES[$p.'.'.self::FORMATS[$p]], $agent->platforms));
        $types = implode(', ', array_map(fn ($t) => SocialAgent::POST_TYPES[$t][0] ?? $t, $agent->post_types ?: ['informative']));
        $o = fn ($k, $d = null) => $agent->option($k, $d);
        $style = [];
        $style[] = $o('hashtags', true) ? 'Hashtags: at most '.(int) $o('hashtag_count', 3).' per post, specific ones, where the format allows them.' : 'No hashtags.';
        $style[] = $o('emoji', false) ? 'An emoji or two is fine where it fits.' : 'No emoji.';
        if ($o('cta')) {
            $style[] = 'When it fits naturally, point to: '.$o('cta');
        }
        if ($o('tone')) {
            $style[] = 'Tone: '.$o('tone');
        }
        $lessons = $agent->lessons ? "\n\nWhat you've learned from her results and edits (follow these):\n".implode("\n", array_map(fn ($l) => '- '.$l['text'], $agent->lessons)) : '';
        $pillars = Repurposer::pillars();

        return "You are \"{$agent->name}\", the social media agent for ".Prompts::name().". You plan and write her posts so each one earns attention on its own network.\n\n"
            .Prompts::profile()."\n\n".Prompts::voice()."\n\n".Prompts::writingRules()."\n\n"
            ."About her brand:\n".mb_substr($agent->brand, 0, 4000)."\n\n"
            .'Audience: '.($agent->audience ?: 'decide who engages most with this brand and write for them')."\n"
            ."Post types to mix: {$types}.\n".implode("\n", $style)."\n\nHow to write for each network:\n{$guides}{$lessons}\n\n"
            .'Every post has a different angle; never repeat an idea from the list of recent posts. Never invent numbers, quotes, clients or events. '
            .'Output JSON: {"posts": [{"platform": "linkedin", "title": "short internal label", "body": "text", "parts": [], "why": "one line: the idea and why it should work"'
            .($pillars ? ', "pillar": one of '.json_encode($pillars, JSON_UNESCAPED_UNICODE) : '').'}]}. Use parts only where the format has slides or thread posts.';
    }

    private function brief(SocialAgent $agent, array $targets): string
    {
        $recent = SocialPost::where('social_agent_id', $agent->id)->latest()->limit(15)->get()
            ->map(fn ($p) => "- [{$p->platform}] ".mb_substr(str_replace("\n", ' ', (string) ($p->title ?: $p->body)), 0, 120))->implode("\n");
        $count = array_count_values($targets);

        $signals = self::gatherSignals();
        $signalLines = [];
        if ($signals['breakouts']->isNotEmpty()) {
            $signalLines[] = "Trending competitor breakout videos:\n".$signals['breakouts']->map(fn ($b) => "- \"{$b->title}\" ({$b->outlier_score}x median on {$b->channel_title})".($b->why_it_worked ? " · {$b->why_it_worked}" : ''))->implode("\n");
        }
        if ($signals['news']->isNotEmpty()) {
            $signalLines[] = "Fresh industry news feeds:\n".$signals['news']->map(fn ($n) => "- \"{$n->title}\" ({$n->source})")->implode("\n");
        }
        if ($signals['asks']->isNotEmpty()) {
            $askItems = [];
            foreach ($signals['asks'] as $a) {
                if (! empty($a->summary)) {
                    $askItems[] = "- {$a->video_title}: {$a->summary}";
                } elseif (is_array($a->items)) {
                    foreach (array_slice($a->items, 0, 2) as $item) {
                        $askItems[] = '- '.($item['text'] ?? $item['quote'] ?? '');
                    }
                }
            }
            if ($askItems) {
                $signalLines[] = "Recent audience questions:\n".implode("\n", array_filter($askItems));
            }
        }
        if ($signals['scripts']->isNotEmpty()) {
            $signalLines[] = "Your latest video concepts:\n".$signals['scripts']->map(fn ($s) => "- \"{$s->title}\" ({$s->status})")->implode("\n");
        }

        $intel = $signalLines ? "\n\nLive signals and breakout trends to draw inspiration from:\n".implode("\n\n", $signalLines) : '';

        return 'Write these posts, in this order: '.implode(', ', array_map(fn ($p, $n) => "{$n} for {$p}", array_keys($count), $count)).".\n\n"
            .($recent ? "Recent posts (don't repeat them):\n{$recent}" : 'This is the first batch.')
            .$intel;
    }

    public static function assertReady(): void
    {
        if (! Llm::configured()) {
            throw new RuntimeException('Agents need an AI key: add one in Settings → AI.');
        }
    }
}
