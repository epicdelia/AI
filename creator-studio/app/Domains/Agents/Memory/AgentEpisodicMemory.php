<?php

namespace App\Domains\Agents\Memory;

use App\Domains\Agents\Models\AgentEvent;
use App\Domains\Agents\Models\SocialAgent;
use App\Domains\Social\Models\SocialPost;
use Illuminate\Support\Collection;

/**
 * Episodic & Procedural Memory Engine.
 * Implements Big Tech frontier memory architectures: retrieves winning exemplars (procedural)
 * and rejected anti-patterns/human edit diffs (episodic) to provide dynamic few-shot context,
 * preventing cyclical hallucinations and repeated stylistic errors.
 */
class AgentEpisodicMemory
{
    /**
     * Retrieve top-performing posts (winning exemplars) for this agent or platform.
     *
     * @return Collection<int, array{id: int, platform: string, hook: string, body: string, rate: float, engagements: int}>
     */
    public function getWinningExemplars(SocialAgent $agent, ?string $platform = null, int $limit = 4): Collection
    {
        $query = SocialPost::where('status', 'posted')
            ->whereNotNull('metrics');

        if ($agent->id) {
            $query->where(function ($q) use ($agent) {
                $q->where('social_agent_id', $agent->id)
                    ->orWhereNull('social_agent_id');
            });
        }

        if ($platform) {
            $query->where('platform', $platform);
        }

        return $query->get()
            ->filter(fn ($p) => ($p->engagement['rate'] ?? 0) > 0 || ($p->engagement['total'] ?? 0) > 50)
            ->sortByDesc(fn ($p) => $p->engagement['rate'] ?? ($p->engagement['total'] ?? 0) / 100)
            ->take($limit)
            ->map(function ($p) {
                $text = $p->asText();
                $lines = array_values(array_filter(explode("\n", $text), fn ($l) => trim($l) !== ''));
                $hook = $lines[0] ?? mb_substr($text, 0, 100);

                return [
                    'id' => $p->id,
                    'platform' => $p->platform,
                    'hook' => $hook,
                    'body' => mb_substr($text, 0, 600),
                    'rate' => (float) ($p->engagement['rate'] ?? 0),
                    'engagements' => (int) ($p->engagement['total'] ?? 0),
                ];
            })
            ->values();
    }

    /**
     * Retrieve anti-patterns: past rejected drafts and reasons why the creator declined them.
     *
     * @return Collection<int, array{id: int, platform: string, reason: string, text_sample: string, created_at: string}>
     */
    public function getAntiPatterns(SocialAgent $agent, int $limit = 5): Collection
    {
        // 1. Fetch from AgentEvent rejected logs
        $events = AgentEvent::where('social_agent_id', $agent->id)
            ->where('type', 'rejected')
            ->latest()
            ->take($limit)
            ->get();

        $antiPatterns = [];
        foreach ($events as $e) {
            $antiPatterns[] = [
                'id' => $e->id,
                'platform' => $e->platform ?: 'general',
                'reason' => $e->message,
                'text_sample' => '',
                'created_at' => $e->created_at->toIso8601String(),
            ];
        }

        // 2. Fetch from SocialPost marked rejected
        $rejectedPosts = SocialPost::where('social_agent_id', $agent->id)
            ->where('approval', 'rejected')
            ->latest()
            ->take($limit)
            ->get();

        foreach ($rejectedPosts as $p) {
            $antiPatterns[] = [
                'id' => $p->id,
                'platform' => $p->platform,
                'reason' => $p->rejection_reason ?: 'Rejected by creator during review.',
                'text_sample' => mb_substr($p->asText(), 0, 200),
                'created_at' => $p->updated_at->toIso8601String(),
            ];
        }

        return collect($antiPatterns)->unique('reason')->take($limit)->values();
    }

    /**
     * Retrieve human-in-the-loop rewrite diffs (what the agent wrote vs what creator edited).
     *
     * @return Collection<int, array{id: int, platform: string, agent_wrote: string, creator_changed: string}>
     */
    public function getHumanEdits(SocialAgent $agent, int $limit = 3): Collection
    {
        return SocialPost::where('social_agent_id', $agent->id)
            ->whereNotNull('agent_original')
            ->where('approval', 'approved')
            ->latest()
            ->take(15)
            ->get()
            ->filter(fn ($p) => similar_text($p->agent_original, $p->asText()) / max(1, strlen($p->agent_original)) < 0.85)
            ->take($limit)
            ->map(fn ($p) => [
                'id' => $p->id,
                'platform' => $p->platform,
                'agent_wrote' => mb_substr($p->agent_original, 0, 300),
                'creator_changed' => mb_substr($p->asText(), 0, 300),
            ])
            ->values();
    }

    /**
     * Build dynamic few-shot episodic memory block to inject into the agent's prompt.
     */
    public function buildMemoryContext(SocialAgent $agent, ?string $platform = null): string
    {
        $winning = $this->getWinningExemplars($agent, $platform, 3);
        $antiPatterns = $this->getAntiPatterns($agent, 4);
        $humanEdits = $this->getHumanEdits($agent, 2);

        $out = "### AGENT EPISODIC & PROCEDURAL MEMORY\n";

        if ($winning->isNotEmpty()) {
            $out .= "#### Proven High-Engagement Exemplars on this Channel (Emulate hook structure):\n";
            foreach ($winning as $w) {
                $out .= "- [{$w['platform']}, ER {$w['rate']}%, {$w['engagements']} engagements]:\n  Hook: \"{$w['hook']}\"\n";
            }
            $out .= "\n";
        }

        if ($antiPatterns->isNotEmpty()) {
            $out .= "#### Known Anti-Patterns & Rejection Traps (NEVER repeat these mistakes):\n";
            foreach ($antiPatterns as $a) {
                $out .= "- Avoid: {$a['reason']}".($a['text_sample'] ? " (Draft: \"{$a['text_sample']}\")" : '')."\n";
            }
            $out .= "\n";
        }

        if ($humanEdits->isNotEmpty()) {
            $out .= "#### How Creator Previously Refined Agent Drafts:\n";
            foreach ($humanEdits as $h) {
                $out .= "- Agent originally wrote: \"{$h['agent_wrote']}\"\n  Creator refined to: \"{$h['creator_changed']}\"\n";
            }
            $out .= "\n";
        }

        return trim($out);
    }

    /**
     * Complete memory inspection payload for API and UI console.
     */
    public function inspect(SocialAgent $agent): array
    {
        return [
            'winning_exemplars' => $this->getWinningExemplars($agent, null, 6)->all(),
            'anti_patterns' => $this->getAntiPatterns($agent, 8)->all(),
            'human_edits' => $this->getHumanEdits($agent, 5)->all(),
            'total_memory_points' => $this->getWinningExemplars($agent)->count()
                + $this->getAntiPatterns($agent)->count()
                + $this->getHumanEdits($agent)->count(),
        ];
    }
}
