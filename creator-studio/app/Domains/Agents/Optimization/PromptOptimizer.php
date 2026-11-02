<?php

namespace App\Domains\Agents\Optimization;

use App\Domains\Agents\Harness\BenchmarkHarness;
use App\Domains\Agents\Models\AgentPromptVersion;
use App\Domains\Agents\Models\SocialAgent;
use App\Services\Llm;
use Throwable;

/**
 * Automated Prompt Optimizer (APO / DSPy-style self-tuner).
 * Analyzes rubric regressions and low benchmark scores, meta-prompts candidate variations,
 * verifies score improvements against the benchmark harness, and commits versioned checkpoints.
 */
class PromptOptimizer
{
    public function __construct(
        private Llm $llm,
        private BenchmarkHarness $benchmark
    ) {
    }

    /**
     * Run an optimization cycle for the agent's core system prompt.
     *
     * @return array{
     *     baseline_score: int,
     *     optimized_score: int,
     *     improved: bool,
     *     version: int,
     *     changes_summary: string,
     *     prompt: string
     * }
     */
    public function optimize(SocialAgent $agent): array
    {
        // 1. Establish baseline score using current prompt
        $baselineRes = $this->benchmark->runSuite($agent);
        $baselineScore = $baselineRes['overall_average'];

        // Extract lowest scoring rubric areas across reports
        $rubrics = array_column(
            array_map(fn ($r) => $r->rubric, $baselineRes['reports']),
            null
        );

        $weakAreas = [];
        foreach (['hook', 'voice', 'retention', 'cliche_freedom', 'safety'] as $factor) {
            $scores = array_column($rubrics, $factor);
            if (!empty($scores) && (array_sum($scores) / count($scores)) < 80) {
                $weakAreas[] = $factor;
            }
        }

        $currentPrompt = $agent->brand;

        // 2. Meta-prompt to generate improved system prompt candidate
        $metaPrompt = "Current Agent Prompt:\n\"{$currentPrompt}\"\n\n"
            . "Benchmark Performance Score: {$baselineScore}/100\n"
            . 'Weak Rubric Dimensions: ' . (!empty($weakAreas) ? implode(', ', $weakAreas) : 'hook and tone consistency') . "\n\n"
            . 'Analyze why the agent produces low scores in these dimensions. '
            . 'Propose an optimized system prompt that explicitly enforces constraints (e.g. opening with numbers, banning filler phrases, forcing authentic practitioner tone). '
            . "Output JSON:\n"
            . "{\n"
            . "  \"changes_summary\": \"What was added/fixed (1-2 sentences)\",\n"
            . "  \"optimized_prompt\": \"The refined prompt text\"\n"
            . '}';

        try {
            $proposal = $this->llm->as('prompt-optimizer')->creativity(0.4)->json(
                'You are an automated prompt optimization engine (DSPy-style). You edit system prompts to maximize objective benchmark rubric scores.',
                $metaPrompt
            );
            $candidatePrompt = trim((string)($proposal['optimized_prompt'] ?? $currentPrompt));
            $summary = trim((string)($proposal['changes_summary'] ?? 'Automated optimization'));
        } catch (Throwable $e) {
            $candidatePrompt = $currentPrompt . "\n\nRule: Always lead with a concrete metric or result in the first sentence. Avoid all corporate buzzwords.";
            $summary = 'Injected explicit hook anchor and buzzword elimination rules.';
        }

        // 3. Temporarily set candidate prompt to evaluate on benchmark
        $agent->brand = $candidatePrompt;
        $evalRes = $this->benchmark->runSuite($agent);
        $candidateScore = $evalRes['overall_average'];

        $improved = $candidateScore >= $baselineScore;

        if ($improved) {
            $agent->save(); // Commit the winning prompt
        } else {
            $agent->brand = $currentPrompt; // Revert to original
            $agent->save();
        }

        // 4. Record new prompt version checkpoint
        $latestVer = AgentPromptVersion::where('social_agent_id', $agent->id)->max('version') ?? 0;
        $newVer = $latestVer + 1;

        if ($improved) {
            AgentPromptVersion::where('social_agent_id', $agent->id)->update([
                'is_active' => false,
            ]);
        }

        $record = AgentPromptVersion::create([
            'social_agent_id' => $agent->id,
            'version' => $newVer,
            'system_prompt' => $candidatePrompt,
            'score' => $candidateScore,
            'eval_breakdown' => [
                'baseline_score' => $baselineScore,
                'candidate_score' => $candidateScore,
                'weak_areas_addressed' => $weakAreas,
            ],
            'changes_summary' => $summary,
            'is_active' => $improved,
        ]);

        return [
            'baseline_score' => $baselineScore,
            'optimized_score' => $candidateScore,
            'improved' => $improved,
            'version' => $newVer,
            'changes_summary' => $summary,
            'prompt' => $improved ? $candidatePrompt : $currentPrompt,
        ];
    }
}
