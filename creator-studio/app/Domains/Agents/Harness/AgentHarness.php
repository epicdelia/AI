<?php

namespace App\Domains\Agents\Harness;

use App\Domains\Agents\AgentPlanner;
use App\Domains\Agents\Models\AgentEvent;
use App\Domains\Agents\Models\SocialAgent;
use App\Domains\Social\Models\SocialPost;
use App\Services\Llm;
use App\Services\Prompts;

/**
 * The Agent Execution Harness: an advanced agentic orchestrator providing
 * observation through tool registry, reflective self-evaluation, and automated quality remediation.
 */
class AgentHarness
{
    public function __construct(
        private Llm $llm,
        private ToolRegistry $tools
    ) {}

    /**
     * Run an agent through the complete harness loop.
     *
     * @return array{
     *     trace: array<int, array{phase: string, summary: string, meta?: mixed}>,
     *     posts: array<int, SocialPost>,
     *     evaluations: array<int, array>
     * }
     */
    public function execute(SocialAgent $agent): array
    {
        $trace = [];

        // 1. Observation Phase (Tool Registry)
        $breakouts = $this->tools->fetchBreakouts(4);
        $news = $this->tools->fetchNewsFeeds(4);
        $scripts = $this->tools->fetchRecentScripts(3);
        $asks = $this->tools->fetchAudienceAsks(3);
        $sponsors = $this->tools->fetchActiveSponsors();

        $trace[] = [
            'phase' => 'observation',
            'summary' => "Observed {$breakouts->count()} breakout videos, {$news->count()} news items, {$scripts->count()} video outlines, {$asks->count()} audience asks, and {$sponsors->count()} active sponsors.",
            'meta' => [
                'breakout_titles' => $breakouts->pluck('title')->all(),
                'news_sources' => $news->pluck('source')->unique()->values()->all(),
            ],
        ];

        // 2. Planning & Generation Phase
        $planner = new AgentPlanner($this->llm);
        $posts = $planner->run($agent);

        $trace[] = [
            'phase' => 'execution',
            'summary' => count($posts) ? 'Generated '.count($posts).' candidate post drafts.' : 'No new posts needed for target cadence.',
        ];

        // 3. Evaluation & Remediation Phase
        $evaluations = [];
        foreach ($posts as $post) {
            $eval = QualityEvaluator::evaluate([
                'platform' => $post->platform,
                'format' => $post->format,
                'body' => $post->body,
                'parts' => $post->parts,
            ]);

            // Auto-remediation if score is below threshold or hard cliches detected
            if ($eval['overall_score'] < 70 || $eval['rubric']['cliche_freedom'] < 80) {
                $refined = $this->remediate($post, $eval);
                if ($refined) {
                    $post->update([
                        'body' => $refined['body'],
                        'parts' => $refined['parts'] ?? $post->parts,
                    ]);
                    $eval = QualityEvaluator::evaluate([
                        'platform' => $post->platform,
                        'format' => $post->format,
                        'body' => $post->body,
                        'parts' => $post->parts,
                    ]);
                    $trace[] = [
                        'phase' => 'remediation',
                        'summary' => "Post #{$post->id} ({$post->platform}) auto-remediated: score elevated to {$eval['overall_score']}/100.",
                        'meta' => ['feedback_addressed' => $eval['feedback']],
                    ];
                }
            }

            $evaluations[] = [
                'post_id' => $post->id,
                'platform' => $post->platform,
                'score' => $eval['overall_score'],
                'rubric' => $eval['rubric'],
                'feedback' => $eval['feedback'],
            ];
        }

        AgentEvent::log(
            'harness_run',
            "Agent Harness executed: ".count($posts)." post(s) verified with average score ".
            (count($evaluations) ? round(array_sum(array_column($evaluations, 'score')) / count($evaluations)) : 0)."/100.",
            $agent->id
        );

        return [
            'trace' => $trace,
            'posts' => $posts,
            'evaluations' => $evaluations,
        ];
    }

    private function remediate(SocialPost $post, array $eval): ?array
    {
        try {
            $feedbackText = implode("\n- ", $eval['feedback']);
            $prompt = "The draft for {$post->platform} had these quality rubric deficiencies:\n- {$feedbackText}\n\n"
                ."Original text:\n{$post->body}\n\n"
                ."Rewrite this post in ".Prompts::name()."’s authentic voice, preserving the core developer takeaway while addressing every critique point. "
                .'Output JSON: {"body": "clean text", "parts": []}';

            $data = $this->llm->as('agent-remediate')->creativity(0.6)->json(
                Prompts::voice()."\n\n".Prompts::writingRules(),
                $prompt
            );

            if (! empty($data['body'])) {
                return [
                    'body' => trim((string) $data['body']),
                    'parts' => (array) ($data['parts'] ?? $post->parts),
                ];
            }
        } catch (\Throwable $e) {
            // Never break execution loop
        }

        return null;
    }
}

