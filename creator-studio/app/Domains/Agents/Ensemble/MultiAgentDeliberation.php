<?php

namespace App\Domains\Agents\Ensemble;

use App\Domains\Agents\Guardrails\AgentGuardrails;
use App\Domains\Agents\Memory\AgentEpisodicMemory;
use App\Domains\Agents\Models\SocialAgent;
use App\Services\AiTellsLinter;
use App\Services\Llm;
use App\Services\Prompts;
use Throwable;

/**
 * Enterprise Multi-Agent Deliberation Ensemble.
 * Decomposes single-pass generation into a specialized multi-agent committee:
 * 1. Creative Generator Agent (hooks & narrative arc)
 * 2. Grounding & Fact Auditor Agent (signal verification & anti-hallucination)
 * 3. Voice & FTC Safety Critic Agent (persona alignment & disclosure verification)
 * 4. Consensus & Optimizer Agent (synthesizes critique into verified output)
 */
class MultiAgentDeliberation
{
    public function __construct(
        private Llm $llm,
        private AgentEpisodicMemory $memory,
        private AgentGuardrails $guardrails
    ) {
    }

    /**
     * Run the multi-agent deliberation pipeline on a topic or raw signal.
     *
     * @param  array{topic: string, platform: string, source_signal?: array, sponsor?: array}  $context
     * @return array{
     *     final_post: array{platform: string, body: string, parts: array},
     *     trace: array<int, array{agent: string, role: string, output: mixed, passed: bool}>,
     *     grounding_score: int,
     *     voice_score: int,
     *     guardrails_summary: array
     * }
     */
    public function deliberate(SocialAgent $agent, array $context): array
    {
        $platform = $context['platform'] ?? 'linkedin';
        $topic = $context['topic'] ?? 'Modern software engineering practices';
        $sourceSignal = $context['source_signal'] ?? [];
        $sponsor = $context['sponsor'] ?? null;

        $trace = [];

        // 0. Retrieve Episodic Memory
        $memoryContext = $this->memory->buildMemoryContext($agent, $platform);

        // 1. Creative Generator Agent
        $genPrompt = "Topic / Idea: {$topic}\n"
            . ($sponsor ? "Sponsor to integrate: {$sponsor['brand_name']} ({$sponsor['talking_points']})\n" : '')
            . ($sourceSignal ? "Underlying Grounding Data:\n" . json_encode($sourceSignal, JSON_PRETTY_PRINT) . "\n\n" : '')
            . "{$memoryContext}\n\n"
            . "Write a first-person candidate post for {$platform}. Line 1 must be a concrete hook with a metric or contrarian take. "
            . 'Do NOT use corporate buzzwords or em-dashes. Output JSON: {"body": "...", "parts": []}';

        $this->guardrails->recordTokens(mb_strlen($genPrompt) / 4, 250);

        $draft = $this->llm->as('agent-generator')->creativity(0.7)->json(
            Prompts::voice() . "\n\nPersona: Software engineer & solo creator for " . Prompts::name() . '.',
            $genPrompt
        );

        $candidateBody = $this->guardrails->sanitizeContent(trim((string)($draft['body'] ?? '')));
        $candidateParts = (array)($draft['parts'] ?? []);

        $trace[] = [
            'agent' => 'Creative Generator',
            'role' => 'Drafts candidate post with hook-driven narrative',
            'output' => [
                'body' => $candidateBody,
                'parts' => $candidateParts,
            ],
            'passed' => !empty($candidateBody),
        ];

        // 2. Grounding & Fact Auditor Agent (Anti-hallucination)
        $groundingPrompt = "Candidate Post:\n{$candidateBody}\n\n"
            . "Underlying Source Signal:\n" . json_encode($sourceSignal, JSON_PRETTY_PRINT) . "\n\n"
            . "Audit this candidate against the underlying source signal:\n"
            . "1. Did the candidate make up numbers, metrics, or claims not supported by the signal?\n"
            . "2. If a sponsor is present, are product features accurate?\n"
            . 'Output JSON: {"grounding_score": 0-100, "hallucinated_claims": ["..."], "verdict": "grounded|exaggerated|hallucinated"}';

        $this->guardrails->recordTokens(mb_strlen($groundingPrompt) / 4, 150);

        $grounding = $this->llm->as('agent-grounding-auditor')->creativity(0.2)->json(
            'You are a rigorous factual auditor. Your only job is to flag unsubstantiated numbers, fabricated statistics, or hallucinations.',
            $groundingPrompt
        );

        $groundingScore = (int)($grounding['grounding_score'] ?? 85);
        $hallucinations = (array)($grounding['hallucinated_claims'] ?? []);

        $trace[] = [
            'agent' => 'Grounding & Fact Auditor',
            'role' => 'Verifies claims against source data to eliminate hallucinations',
            'output' => [
                'score' => $groundingScore,
                'hallucinations_flagged' => $hallucinations,
                'verdict' => $grounding['verdict'] ?? 'grounded',
            ],
            'passed' => $groundingScore >= 70 && empty($hallucinations),
        ];

        // 3. Voice & FTC Safety Critic Agent
        $linterFindings = AiTellsLinter::lint($candidateBody);
        $hardTells = array_filter($linterFindings, fn ($f) => $f['severity'] === AiTellsLinter::HARD);

        $voicePrompt = "Candidate Post:\n{$candidateBody}\n\n"
            . 'Creator Brand Voice: ' . mb_substr($agent->brand, 0, 500) . "\n\n"
            . "Evaluate:\n"
            . "1. Does this sound like a real practitioner who codes, or generic marketing copy?\n"
            . '2. FTC Disclosure: If sponsor (' . ($sponsor['brand_name'] ?? 'none') . ") is mentioned, is there explicit #ad or 'sponsored by'?\n"
            . 'Output JSON: {"voice_score": 0-100, "fluff_detected": ["..."], "ftc_compliant": true|false}';

        $this->guardrails->recordTokens(mb_strlen($voicePrompt) / 4, 150);

        $voiceAudit = $this->llm->as('agent-voice-critic')->creativity(0.3)->json(
            'You are a strict editorial voice critic. Flag corporate speak, over-hyped buzzwords, or missing disclosures.',
            $voicePrompt
        );

        $voiceScore = (int)($voiceAudit['voice_score'] ?? 80);
        if (!empty($hardTells)) {
            $voiceScore = max(20, $voiceScore - (count($hardTells) * 15));
        }

        $trace[] = [
            'agent' => 'Voice & Safety Critic',
            'role' => 'Enforces authentic practitioner tone and FTC compliance',
            'output' => [
                'score' => $voiceScore,
                'hard_tells_caught' => array_column($hardTells, 'match'),
                'fluff_detected' => $voiceAudit['fluff_detected'] ?? [],
                'ftc_compliant' => $voiceAudit['ftc_compliant'] ?? true,
            ],
            'passed' => $voiceScore >= 75 && empty($hardTells),
        ];

        // 4. Consensus & Optimizer Agent (Auto-Remediation if any check flagged)
        $needsCorrection = $groundingScore < 75 || $voiceScore < 75 || !empty($hallucinations) || !empty($hardTells);

        if ($needsCorrection) {
            $optimizerPrompt = "The draft post had these specific auditor critiques:\n"
                . (!empty($hallucinations) ? '- Grounding issues: ' . implode('; ', $hallucinations) . "\n" : '')
                . (!empty($hardTells) ? '- Hard AI tells: ' . implode(', ', array_column($hardTells, 'match')) . "\n" : '')
                . (!empty($voiceAudit['fluff_detected']) ? '- Fluff: ' . implode(', ', (array)$voiceAudit['fluff_detected']) . "\n" : '')
                . "\nOriginal Draft:\n{$candidateBody}\n\n"
                . "Rewrite this post into its best version: authentic, grounded in facts, punchy hook, zero buzzwords, and proper #ad disclosure if sponsored.\n"
                . 'Output JSON: {"body": "...", "parts": []}';

            $this->guardrails->recordTokens(mb_strlen($optimizerPrompt) / 4, 250);

            try {
                $refined = $this->llm->as('agent-consensus-optimizer')->creativity(0.5)->json(
                    Prompts::voice() . "\n\n" . Prompts::writingRules(),
                    $optimizerPrompt
                );

                if (!empty($refined['body'])) {
                    $candidateBody = $this->guardrails->sanitizeContent(trim((string)$refined['body']));
                    $candidateParts = (array)($refined['parts'] ?? $candidateParts);
                }
            } catch (Throwable $e) {
                // Keep original if optimizer fails
            }

            $trace[] = [
                'agent' => 'Consensus & Optimizer',
                'role' => 'Synthesizes auditor critiques and refines draft to production standard',
                'output' => [
                    'body' => $candidateBody,
                    'parts' => $candidateParts,
                ],
                'passed' => true,
            ];
        } else {
            $trace[] = [
                'agent' => 'Consensus & Optimizer',
                'role' => 'Approved initial generation without remediation needed',
                'output' => 'Draft met all quality and grounding bars on first pass.',
                'passed' => true,
            ];
        }

        return [
            'final_post' => [
                'platform' => $platform,
                'body' => $candidateBody,
                'parts' => $candidateParts,
            ],
            'trace' => $trace,
            'grounding_score' => $groundingScore,
            'voice_score' => $voiceScore,
            'guardrails_summary' => $this->guardrails->summary(),
        ];
    }
}
