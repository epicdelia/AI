<?php

namespace App\Domains\Agents;

use App\Domains\Agents\Models\AgentEvent;
use App\Domains\Agents\Models\SocialAgent;
use App\Domains\Social\Models\SocialPost;
use App\Domains\Social\Platforms;
use App\Domains\Social\Repurposer;
use App\Domains\Social\SocialScheduler;
use App\Services\Llm;
use App\Services\Prompts;
use Illuminate\Support\Collection;
use RuntimeException;

/**
 * An agent's daily run: top up the next 7 days to its posts-per-week target on each network,
 * write those posts (with its brand, audience, post types and learned lessons), and put each
 * in the next good free slot. In "review" mode they wait for her approval; in "auto" mode
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
                'notes' => trim('Why this: '.mb_substr((string) ($p['why'] ?? ''), 0, 300)."\nWhen: ".$slot['reason']),
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

        return 'Write these posts, in this order: '.implode(', ', array_map(fn ($p, $n) => "{$n} for {$p}", array_keys($count), $count)).".\n\n"
            .($recent ? "Recent posts (don't repeat them):\n{$recent}" : 'This is the first batch.');
    }

    public static function assertReady(): void
    {
        if (! Llm::configured()) {
            throw new RuntimeException('Agents need an AI key: add one in Settings → AI.');
        }
    }
}
