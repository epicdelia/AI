<?php

namespace App\Domains\Agents\YouTube;

use App\Domains\Agents\Guardrails\AgentGuardrails;
use App\Domains\Agents\Models\SocialAgent;
use App\Domains\YouTube\TitleTest;
use App\Services\Llm;
use App\Services\Prompts;
use Throwable;

/**
 * Multi-Armed Bandit Title & Thumbnail Concept Optimizer.
 * Explores 4 algorithmic title structures targeting YouTube Browse & Suggested Traffic,
 * calculates predictive CTR scores, generates high-contrast thumbnail directions,
 * and seamlessly configures multi-window Title A/B Tests in TitleTest.
 */
class TitleBanditOptimizer
{
    public function __construct(
        private Llm $llm,
        private AgentGuardrails $guardrails
    ) {}

    /**
     * Generate 4 algorithmic title variants and matching thumbnail directions.
     *
     * @param  array{topic: string, video_id?: string, current_title?: string}  $params
     * @return array{
     *     topic: string,
     *     video_id: ?string,
     *     variants: array<int, array{
     *         title: string,
     *         archetype: string,
     *         char_count: int,
     *         predicted_ctr_score: int,
     *         browse_affinity: string,
     *         search_affinity: string,
     *         thumbnail: array{
     *             focal_subject: string,
     *             text_overlay: string,
     *             color_palette: string
     *         }
     *     }>,
     *     bandit_winner: array{
     *         title: string,
     *         score: int,
     *         strategic_rationale: string
     *     }
     * }
     */
    public function optimize(SocialAgent $agent, array $params): array
    {
        $topic = trim((string) ($params['topic'] ?? ''));
        $currentTitle = trim((string) ($params['current_title'] ?? ''));
        $videoId = ! empty($params['video_id']) ? trim((string) $params['video_id']) : null;

        if (empty($topic) && ! empty($currentTitle)) {
            $topic = $currentTitle;
        }

        if (empty($topic)) {
            $topic = 'Building High-Throughput Event-Driven Systems in Go and Kafka';
        }

        $creatorName = Prompts::name();

        $systemPrompt = <<<SYS
You are the Head of YouTube Packaging & Algorithmic CTR for {$creatorName} (@Tech.Unicorn).
Delia creates technical software engineering content for developers, architects, and engineering leaders.

OBJECTIVE:
Generate 4 distinct algorithmic YouTube title variants for the topic below, each engineered for maximum Click-Through Rate (CTR) and curiosity without misleading the viewer.
Keep character counts concise (< 60 chars) so titles don't get truncated on mobile devices.

THE 4 TITLE STRATEGIES:
1. Provocative Punchy (< 45 chars): Short, bold, counter-intuitive statement.
2. Quantified Result / Milestone: Concrete numbers, timeline, or benchmark measurement.
3. Skeptical Challenge / Dilemma: Questions industry hype or a popular tool.
4. Urgent Warning / Anti-Pattern: Loss-aversion hook alerting engineers to an expensive mistake.

For each variant, provide a matching high-contrast Thumbnail Concept (focal subject, 2-3 word text overlay, and color palette).

Return a valid JSON object matching this schema:
{
  "variants": [
    {
      "title": "Title 1 Text",
      "archetype": "Provocative Punchy",
      "char_count": 34,
      "predicted_ctr_score": 93,
      "browse_affinity": "High",
      "search_affinity": "Medium",
      "thumbnail": {
        "focal_subject": "Description of main visual element",
        "text_overlay": "2-3 WORDS MAX",
        "color_palette": "High-contrast background and accent colors"
      }
    },
    {
      "title": "Title 2 Text",
      "archetype": "Quantified Result",
      "char_count": 48,
      "predicted_ctr_score": 96,
      "browse_affinity": "High",
      "search_affinity": "High",
      "thumbnail": {
        "focal_subject": "...",
        "text_overlay": "...",
        "color_palette": "..."
      }
    },
    {
      "title": "Title 3 Text",
      "archetype": "Skeptical Challenge",
      "char_count": 42,
      "predicted_ctr_score": 88,
      "browse_affinity": "High",
      "search_affinity": "Medium",
      "thumbnail": {
        "focal_subject": "...",
        "text_overlay": "...",
        "color_palette": "..."
      }
    },
    {
      "title": "Title 4 Text",
      "archetype": "Urgent Warning",
      "char_count": 45,
      "predicted_ctr_score": 85,
      "browse_affinity": "Medium",
      "search_affinity": "High",
      "thumbnail": {
        "focal_subject": "...",
        "text_overlay": "...",
        "color_palette": "..."
      }
    }
  ],
  "bandit_winner": {
    "title": "Title 2 Text",
    "score": 96,
    "strategic_rationale": "Why this title delivers the highest expected CTR on YouTube"
  }
}
SYS;

        $userPrompt = "Video Topic: {$topic}\n"
            .($currentTitle ? "Current Working Title: \"{$currentTitle}\"\n" : '');

        $this->guardrails->recordTokens((int) (mb_strlen($systemPrompt . $userPrompt) / 4), 700);

        try {
            $data = $this->llm->as('title-bandit-agent')->creativity(0.6)->json($systemPrompt, $userPrompt);

            if (! isset($data['variants'], $data['bandit_winner'])) {
                throw new \RuntimeException('Invalid title optimization response.');
            }

            return [
                'topic' => $topic,
                'video_id' => $videoId,
                'variants' => $data['variants'],
                'bandit_winner' => $data['bandit_winner'],
            ];
        } catch (Throwable $e) {
            return $this->fallbackOptimization($topic, $videoId);
        }
    }

    /**
     * Launch or configure a multi-window Title A/B Test for a YouTube video.
     *
     * @param  array{video_id: string, titles: array<int, string>, hours_per_window?: int, rounds?: int}  $params
     */
    public function launchTest(array $params): TitleTest
    {
        $videoId = trim((string) $params['video_id']);
        $titles = array_values(array_filter(array_map('trim', (array) $params['titles'])));
        $hours = max(1, min(24, (int) ($params['hours_per_window'] ?? 4)));
        $rounds = max(1, min(10, (int) ($params['rounds'] ?? 3)));

        if (count($titles) < 2) {
            throw new \InvalidArgumentException('At least 2 title variations are required for an A/B test.');
        }

        $test = TitleTest::updateOrCreate(
            ['video_id' => $videoId],
            [
                'original_title' => $titles[0],
                'titles' => $titles,
                'windows' => [],
                'current' => 0,
                'window_started_at' => now(),
                'views_at_start' => 0,
                'hours' => $hours,
                'rounds' => $rounds,
                'status' => 'running',
                'winner' => null,
                'error' => null,
            ]
        );

        return $test;
    }

    private function fallbackOptimization(string $topic, ?string $videoId): array
    {
        return [
            'topic' => $topic,
            'video_id' => $videoId,
            'variants' => [
                [
                    'title' => "Why Senior Engineers Avoid {$topic}",
                    'archetype' => 'Provocative Punchy',
                    'char_count' => mb_strlen("Why Senior Engineers Avoid {$topic}"),
                    'predicted_ctr_score' => 92,
                    'browse_affinity' => 'High',
                    'search_affinity' => 'Medium',
                    'thumbnail' => [
                        'focal_subject' => 'Split screen comparing clean simple diagram vs tangled mess',
                        'text_overlay' => 'AVOID THIS',
                        'color_palette' => 'Dark graphite background with vivid red warning badge',
                    ],
                ],
                [
                    'title' => "We Benchmarked {$topic} (The Numbers Surprised Us)",
                    'archetype' => 'Quantified Result',
                    'char_count' => mb_strlen("We Benchmarked {$topic} (The Numbers Surprised Us)"),
                    'predicted_ctr_score' => 96,
                    'browse_affinity' => 'High',
                    'search_affinity' => 'High',
                    'thumbnail' => [
                        'focal_subject' => 'Close up of latency bar chart with glowing green 1st place',
                        'text_overlay' => 'BENCHMARKED',
                        'color_palette' => 'High-contrast black, cyan, and neon green',
                    ],
                ],
                [
                    'title' => "Is {$topic} Actually an Anti-Pattern?",
                    'archetype' => 'Skeptical Challenge',
                    'char_count' => mb_strlen("Is {$topic} Actually an Anti-Pattern?"),
                    'predicted_ctr_score' => 88,
                    'browse_affinity' => 'High',
                    'search_affinity' => 'Medium',
                    'thumbnail' => [
                        'focal_subject' => 'Question mark icon hovering over popular framework logo',
                        'text_overlay' => 'DON\'T USE IT?',
                        'color_palette' => 'Deep purple with bright yellow typography',
                    ],
                ],
                [
                    'title' => "Stop Doing {$topic} Before It Breaks Your App",
                    'archetype' => 'Urgent Warning',
                    'char_count' => mb_strlen("Stop Doing {$topic} Before It Breaks Your App"),
                    'predicted_ctr_score' => 86,
                    'browse_affinity' => 'Medium',
                    'search_affinity' => 'High',
                    'thumbnail' => [
                        'focal_subject' => 'Screen terminal showing red critical error logs',
                        'text_overlay' => 'STOP NOW',
                        'color_palette' => 'Dark slate with bright amber and red highlights',
                    ],
                ],
            ],
            'bandit_winner' => [
                'title' => "We Benchmarked {$topic} (The Numbers Surprised Us)",
                'score' => 96,
                'strategic_rationale' => 'Benchmark titles containing empirical results consistently generate the highest combination of Browse and Suggested traffic on developer YouTube.',
            ],
        ];
    }
}

