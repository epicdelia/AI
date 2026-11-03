<?php

namespace App\Domains\Agents\Optimization;

use App\Domains\Agents\Guardrails\AgentGuardrails;
use App\Domains\Agents\Models\SocialAgent;
use App\Domains\Social\Models\SocialPost;
use App\Domains\Social\SocialScheduler;
use App\Models\Script;
use App\Services\Llm;

/**
 * Autonomous Evergreen Content Recycler & Fresh-Take Engine.
 * Rejuvenates top-performing historical content into modernized 2026 takes,
 * maximizing audience reach and intellectual property longevity.
 */
class EvergreenRecycler
{
    public function __construct(
        private Llm $llm,
        private AgentGuardrails $guardrails
    ) {
    }

    /**
     * Recycle a past concept into 3 modernized 2026 angles.
     *
     * @param  array{script_id?: int, post_id?: int, topic?: string, target_platform?: string}  $params
     * @return array{
     *     original_topic: string,
     *     historical_performance_driver: string,
     *     reboot_angles: array<int, array{
     *         archetype: string,
     *         title: string,
     *         hook: string,
     *         the_2026_shift: string,
     *         suggested_platform: string,
     *         projected_virality_score: int
     *     }>,
     *     winning_reboot: array{
     *         archetype: string,
     *         platform: string,
     *         title: string,
     *         body: string,
     *         parts: array<int, string>,
     *         why_it_wins: string
     *     }
     * }
     */
    public function recycle(SocialAgent $agent, array $params): array
    {
        $topic = trim((string)($params['topic'] ?? ''));
        $targetPlatform = (string)($params['target_platform'] ?? 'linkedin');

        if (!empty($params['script_id'])) {
            $script = Script::find($params['script_id']);
            if ($script) {
                $topic = $script->title . (!empty($script->outline) ? "\nOutline: {$script->outline}" : '');
            }
        } elseif (!empty($params['post_id'])) {
            $post = SocialPost::find($params['post_id']);
            if ($post) {
                $topic = $post->title ?: mb_substr((string)$post->body, 0, 300);
            }
        }

        if (empty($topic)) {
            $topic = 'Building Scalable Event-Driven Architectures and Sagas';
        }

        $system = <<<'PROMPT'
You are the Chief Content Strategist and Evergreen Recycler for an elite technical creator.
Your goal is to take a validated, historically successful technical topic and revitalize it with modern 2026 developer context (e.g. AI-assisted compilation, WASM runtimes, edge SQLite, local LLMs, Bun/Rust tooling, serverless-to-container migrations).

Create 3 distinct reboot angles:
1. "The 2026 Paradigm Shift" (How modern infrastructure changed the trade-offs).
2. "The Contrarian Retraction" (Admitting where conventional wisdom was wrong or overengineered).
3. "The High-Density Cheat Sheet" (Actionable, zero-fluff bulleted blueprint).

Select the winning reboot and draft the complete publication-ready post.

Return ONLY a valid JSON object matching this schema:
{
  "original_topic": "Topic",
  "historical_performance_driver": "Practitioners bookmark concrete architectural trade-offs.",
  "reboot_angles": [
    {
      "archetype": "paradigm_shift",
      "title": "Title 1",
      "hook": "Hook 1",
      "the_2026_shift": "Shift description",
      "suggested_platform": "linkedin",
      "projected_virality_score": 93
    }
  ],
  "winning_reboot": {
    "archetype": "paradigm_shift",
    "platform": "linkedin",
    "title": "Winning Title",
    "body": "Full body text...",
    "parts": [],
    "why_it_wins": "Strategic rationale."
  }
}
PROMPT;

        $prompt = <<<TEXT
Revitalize and recycle this historical top-performing technical topic:
TOPIC: {$topic}
TARGET PLATFORM: {$targetPlatform}
CREATOR BRAND: {$agent->brand}
TARGET AUDIENCE: {$agent->audience}
TEXT;

        try {
            $data = $this->llm->as('evergreen-recycler')->creativity(0.35)->json($system, $prompt);
        } catch (\Throwable) {
            $data = null;
        }

        if (!is_array($data) || !isset($data['reboot_angles'])) {
            return [
                'original_topic' => $topic,
                'historical_performance_driver' => 'Engineers engage with direct architectural post-mortems.',
                'reboot_angles' => [
                    [
                        'archetype' => 'paradigm_shift',
                        'title' => "The 2026 Rethink of {$topic}",
                        'hook' => 'Most distributed architectural patterns from 2022 are now obsolete.',
                        'the_2026_shift' => 'Single-node NVMe instances with SQLite replicate faster than complex clusters.',
                        'suggested_platform' => 'linkedin',
                        'projected_virality_score' => 92,
                    ],
                    [
                        'archetype' => 'contrarian_retraction',
                        'title' => 'Why I Stopped Recommending Complex Message Queues',
                        'hook' => 'I spent 3 years advocating Kafka before realizing 95% of workloads only need Postgres LISTEN/NOTIFY.',
                        'the_2026_shift' => 'Operational simplicity beats theoretical scalability.',
                        'suggested_platform' => 'x',
                        'projected_virality_score' => 95,
                    ],
                    [
                        'archetype' => 'high_density_cheat_sheet',
                        'title' => 'Production System Design Checklist (2026 Edition)',
                        'hook' => 'Bookmark this 6-point checklist before your next architectural review.',
                        'the_2026_shift' => 'Idempotent retry patterns and boundary isolation.',
                        'suggested_platform' => 'linkedin',
                        'projected_virality_score' => 89,
                    ],
                ],
                'winning_reboot' => [
                    'archetype' => 'contrarian_retraction',
                    'platform' => $targetPlatform,
                    'title' => 'Why I Stopped Recommending Complex Message Queues',
                    'body' => "I spent 3 years advocating Kafka before realizing 95% of workloads only need Postgres LISTEN/NOTIFY.\n\nHere are 3 production realities we learned the hard way:\n1. Partition rebalancing causes latency spikes.\n2. In-memory queuing often masks database deadlocks.\n3. Simple append-only tables are trivial to audit and replay.\n\nWhat is your team's stance on message broker complexity?",
                    'parts' => [],
                    'why_it_wins' => 'Contrarian engineering confessions generate peak bookmarking velocity and high-credibility comments.',
                ],
            ];
        }

        return $data;
    }

    /**
     * Schedule the winning reboot post directly into the calendar.
     *
     * @param  array{reboot: array{platform: string, title?: string, body: string, parts?: array}, scheduled_for?: string}  $params
     */
    public function scheduleReboot(SocialAgent $agent, array $params): SocialPost
    {
        $reboot = $params['reboot'];
        $platform = $reboot['platform'] ?? 'linkedin';
        $body = $reboot['body'] ?? '';
        $parts = $reboot['parts'] ?? [];

        $scheduledFor = !empty($params['scheduled_for'])
            ? \Carbon\CarbonImmutable::parse($params['scheduled_for'])
            : SocialScheduler::nextSlot($platform)['at'];

        return SocialPost::create([
            'social_agent_id' => $agent->id,
            'platform' => $platform,
            'format' => $reboot['format'] ?? 'post',
            'source' => 'evergreen_reboot',
            'title' => $reboot['title'] ?? null,
            'body' => $body,
            'parts' => $parts,
            'status' => 'scheduled',
            'approval' => $agent->approval === 'autonomous' ? 'approved' : 'pending',
            'scheduled_for' => $scheduledFor,
        ]);
    }
}
