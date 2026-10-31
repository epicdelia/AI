<?php

namespace App\Domains\Agents\Outliers;

use App\Domains\Agents\Guardrails\AgentGuardrails;
use App\Domains\Agents\Models\SocialAgent;
use App\Models\Outlier;
use App\Services\Llm;
use App\Services\Prompts;
use Throwable;

/**
 * Competitor Outlier Reverse-Engineering & Blue Ocean Angle Hunter.
 * Deconstructs 3x-10x viral competitor breakout videos to identify the underlying
 * psychological triggers, viewer demand gaps, and synthesizes 3 unique "Blue Ocean"
 * counter-angles that have not been saturated in the niche.
 */
class CompetitorReverseEngineer
{
    public function __construct(
        private Llm $llm,
        private AgentGuardrails $guardrails
    ) {}

    /**
     * Reverse engineer an outlier breakout and generate 3 Blue Ocean angles.
     *
     * @param  array{outlier_id?: int, title?: string, competitor_name?: string, why_it_worked?: string}  $params
     * @return array{
     *     outlier_summary: array{title: string, competitor: string, outlier_multiplier: string, core_topic: string},
     *     psychological_triggers: array<string, string>,
     *     audience_demand_gap: string,
     *     blue_ocean_angles: array<int, array{
     *         angle_name: string,
     *         hook_line: string,
     *         thesis: string,
     *         why_it_stands_out: string,
     *         predicted_multiplier: string,
     *         suggested_format: string
     *     }>,
     *     actionable_concept: array{
     *         title: string,
     *         elevator_pitch: string,
     *         key_talking_points: array<int, string>
     *     }
     * }
     */
    public function deconstruct(SocialAgent $agent, array $params): array
    {
        $outlier = null;
        if (! empty($params['outlier_id'])) {
            $outlier = Outlier::with('channel')->find($params['outlier_id']);
        }

        $title = $outlier?->title ?? trim((string) ($params['title'] ?? ''));
        $competitor = $outlier?->channel?->title ?? $outlier?->channel_title ?? trim((string) ($params['competitor_name'] ?? 'Competitor Channel'));
        $whyItWorked = $outlier?->why_it_worked ?? trim((string) ($params['why_it_worked'] ?? ''));
        $multiplier = $outlier ? "{$outlier->outlier_score}x" : '3.5x';

        if (empty($title)) {
            $title = 'Why Everyone is Moving Back to Monoliths';
            $whyItWorked = 'Contrarian architecture take challenging microservices complexity.';
        }

        $creatorName = Prompts::name();
        $brandTone = $agent->brand ?: 'Pragmatic, high-signal software engineering with zero fluff.';

        $systemPrompt = <<<SYS
You are the Chief Content Strategist & Outlier Reverse-Engineer for {$creatorName} (@Tech.Unicorn).
Brand identity: {$brandTone}
Her target audience: Senior software engineers, engineering leads, system architects, and tech builders.

OBJECTIVE:
Deconstruct this viral breakout competitor video.
Identify WHY it outperformed the channel's baseline, what psychological curiosity triggers it activated, and what unresolved questions the audience still has.
Then, develop 3 distinct "Blue Ocean" angles that {$creatorName} can produce to capture that surging audience demand without copying the competitor.

THE 3 BLUE OCEAN ANGLE TYPES:
1. The Empirical Benchmark / Reality Check: Instead of philosophical opinion, test it empirically with measurements under load.
2. The Production Failure Post-Mortem: Show what breaks in real-world scale (edge cases, latency spikes, operational overhead).
3. The 10x Simplification / Primitives: How to achieve the same result using 90% simpler architectural primitives.

Return a valid JSON object matching this schema:
{
  "outlier_summary": {
    "title": "{$title}",
    "competitor": "{$competitor}",
    "outlier_multiplier": "{$multiplier}",
    "core_topic": "Summarized core topic"
  },
  "psychological_triggers": {
    "primary_trigger": "Status / Loss Aversion / Secret Knowledge / Dogma Challenge",
    "tension_mechanism": "Why viewers felt compelled to click",
    "audience_friction": "What the competitor left unanswered or glossed over"
  },
  "audience_demand_gap": "The key insight the audience is craving next",
  "blue_ocean_angles": [
    {
      "angle_name": "Angle 1 Name",
      "hook_line": "First line of video/post",
      "thesis": "Core technical argument",
      "why_it_stands_out": "How this differentiates from the competitor",
      "predicted_multiplier": "3.5x",
      "suggested_format": "YouTube Deep-Dive / LinkedIn Breakdown"
    },
    {
      "angle_name": "Angle 2 Name",
      "hook_line": "...",
      "thesis": "...",
      "why_it_stands_out": "...",
      "predicted_multiplier": "4.0x",
      "suggested_format": "YouTube Tutorial + GitHub Demo"
    },
    {
      "angle_name": "Angle 3 Name",
      "hook_line": "...",
      "thesis": "...",
      "why_it_stands_out": "...",
      "predicted_multiplier": "2.8x",
      "suggested_format": "X Thread + Interactive Carousel"
    }
  ],
  "actionable_concept": {
    "title": "Recommended Winning Video / Post Title",
    "elevator_pitch": "2-3 sentence overview of the concept",
    "key_talking_points": ["Point 1", "Point 2", "Point 3", "Point 4"]
  }
}
SYS;

        $userPrompt = "Competitor Outlier Video:\n"
            ."- Title: \"{$title}\"\n"
            ."- Competitor: {$competitor}\n"
            ."- Outlier Multiplier: {$multiplier}\n"
            .($whyItWorked ? "- Why It Worked Note: {$whyItWorked}\n" : '');

        $this->guardrails->recordTokens((int) (mb_strlen($systemPrompt . $userPrompt) / 4), 800);

        try {
            $data = $this->llm->as('outlier-reverse-engineer')->creativity(0.5)->json($systemPrompt, $userPrompt);

            if (! isset($data['blue_ocean_angles'], $data['actionable_concept'])) {
                throw new \RuntimeException('Incomplete outlier deconstruction response.');
            }

            return $data;
        } catch (Throwable $e) {
            return $this->fallbackDeconstruction($title, $competitor, $multiplier);
        }
    }

    private function fallbackDeconstruction(string $title, string $competitor, string $multiplier): array
    {
        return [
            'outlier_summary' => [
                'title' => $title,
                'competitor' => $competitor,
                'outlier_multiplier' => $multiplier,
                'core_topic' => "Architectural trade-offs in modern backends",
            ],
            'psychological_triggers' => [
                'primary_trigger' => 'Dogma Challenge & Fatigue with Over-Engineering',
                'tension_mechanism' => 'Engineers are tired of managing complex microservice fleets and want permission to simplify.',
                'audience_friction' => 'The competitor made sweeping claims without benchmarking concurrency bottlenecks or data migration.',
            ],
            'audience_demand_gap' => 'Concrete production proof: how do you actually maintain modularity without microservice overhead?',
            'blue_ocean_angles' => [
                [
                    'angle_name' => 'The Empirical Production Reality-Check',
                    'hook_line' => "We put {$competitor}'s exact architecture recommendations through a 50,000 req/s stress test.",
                    'thesis' => 'Monoliths win on latency, but fail without strict in-process domain boundaries.',
                    'why_it_stands_out' => 'Replaces theoretical dogma with concrete load-testing graphs and CPU flamegraphs.',
                    'predicted_multiplier' => '4.2x',
                    'suggested_format' => 'YouTube Deep-Dive with Live Grafana Dashboards',
                ],
                [
                    'angle_name' => 'The Modular Monolith Primitives Guide',
                    'hook_line' => "You don't need Kubernetes to have clean domain boundaries. Here is the 4-layer pattern we use.",
                    'thesis' => 'Hexagonal architecture in a single process eliminates 95% of network serialization latency.',
                    'why_it_stands_out' => 'Gives developers an actionable repo template instead of high-level philosophical rants.',
                    'predicted_multiplier' => '3.8x',
                    'suggested_format' => 'YouTube Tutorial + Open-Source GitHub Repo',
                ],
                [
                    'angle_name' => 'The Expensive Migration Post-Mortem',
                    'hook_line' => 'How one startup spent $400k breaking apart their database—and why they rolled it back 6 months later.',
                    'thesis' => 'Distributed data ownership creates distributed transaction hell unless event sagas are planned from day 1.',
                    'why_it_stands_out' => 'High-stakes financial loss aversion creates immense viral retention.',
                    'predicted_multiplier' => '3.4x',
                    'suggested_format' => 'LinkedIn Case Study + X Viral Thread',
                ],
            ],
            'actionable_concept' => [
                'title' => 'I Tested Monoliths vs Microservices Under 50k req/s (With Real Numbers)',
                'elevator_pitch' => 'A hands-on benchmark comparing single-process modular architecture against microservices. Shows latency percentiles (p50, p99), deployment friction, and memory footprint.',
                'key_talking_points' => [
                    'Network overhead vs serialized IPC benchmark',
                    'Database connection pool starvation in distributed services',
                    'The Modular Monolith design pattern in Go / Node / Python',
                    'When you genuinely DO need to split services',
                ],
            ],
        ];
    }
}

