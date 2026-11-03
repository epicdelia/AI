<?php

namespace App\Domains\Agents\Harness;

use App\Domains\Agents\Models\AgentEvalReport;
use App\Domains\Agents\Models\SocialAgent;
use App\Services\Llm;
use App\Services\Prompts;

/**
 * Standardized benchmark evaluation harness for social media AI agents.
 * Evaluates an agent across real-world creator scenarios:
 * - breaking_trend: reaction to viral competitor movement
 * - audience_faq: technical clarity on viewer questions
 * - sponsor_integration: natural disclosure and talking point coverage
 * - cliche_resistance: ability to avoid AI cliches and buzzwords
 */
class BenchmarkHarness
{
    public const SCENARIOS = [
        'breaking_trend' => [
            'name' => 'Breaking Trend Reaction',
            'prompt' => 'A competitor just broke out with a 6x video: "Why Everyone is Ditching Docker for MicroVMs". Write a first-person LinkedIn post with your own technical perspective on whether solo founders should switch.',
        ],
        'audience_faq' => [
            'name' => 'Audience FAQ Breakdown',
            'prompt' => 'A subscriber asked in comments: "How do you handle zero-downtime database migrations when you don\'t have a DevOps team?" Write an actionable post answering this simply.',
        ],
        'sponsor_integration' => [
            'name' => 'Sponsorship Integration',
            'prompt' => 'Integrate sponsor "Supabase" (Postgres + Auth, promo code UNICORN20) into a post about building scalable MVPs. Include proper disclosure and avoid sounding salesy.',
        ],
        'cliche_resistance' => [
            'name' => 'AI Cliche Resistance',
            'prompt' => 'Write a punchy post about shipping fast in 2026. Do NOT use em-dashes (—), and avoid cliches like "in today\'s fast-paced world", "game-changer", or "supercharge".',
        ],
    ];

    public function __construct(
        private Llm $llm
    ) {
    }

    /**
     * Run all benchmark scenarios for the given agent and persist reports.
     *
     * @return array{overall_average: int, reports: array<int, AgentEvalReport>}
     */
    public function runSuite(SocialAgent $agent): array
    {
        $reports = [];
        $scores = [];

        foreach (self::SCENARIOS as $key => $scenario) {
            $report = $this->evaluateScenario($agent, $key);
            $reports[] = $report;
            $scores[] = $report->overall_score;
        }

        $average = count($scores) ? (int)round(array_sum($scores) / count($scores)) : 0;

        return [
            'overall_average' => $average,
            'reports' => $reports,
        ];
    }

    /**
     * Evaluate a single scenario.
     */
    public function evaluateScenario(SocialAgent $agent, string $scenarioKey): AgentEvalReport
    {
        $scenario = self::SCENARIOS[$scenarioKey] ?? self::SCENARIOS['breaking_trend'];
        $platform = $agent->platforms[0] ?? 'linkedin';

        $system = "You are \"{$agent->name}\", the social media agent for " . Prompts::name() . ".\n"
            . Prompts::profile() . "\n\n" . Prompts::voice() . "\n\n" . Prompts::writingRules() . "\n\n"
            . "Brand:\n" . mb_substr($agent->brand, 0, 1000) . "\n\n"
            . 'Audience: ' . ($agent->audience ?: 'Engineers and builders') . "\n\n"
            . 'Format rule: ' . ($platform === 'x' ? 'Under 270 characters, punchy take.' : 'LinkedIn post, 150-250 words, line 1 is hook under 140 chars.');

        $output = $this->llm->as('agent-eval')->creativity(0.7)->chat($system, $scenario['prompt']);

        $evaluation = QualityEvaluator::evaluate([
            'platform' => $platform,
            'body' => $output,
        ]);

        return AgentEvalReport::create([
            'social_agent_id' => $agent->id,
            'scenario' => $scenarioKey,
            'overall_score' => $evaluation['overall_score'],
            'rubric' => $evaluation['rubric'],
            'output_sample' => $output,
            'feedback' => $evaluation['feedback'],
        ]);
    }
}
