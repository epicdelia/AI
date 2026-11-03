<?php

namespace App\Domains\Agents\Debate;

use App\Domains\Agents\Guardrails\AgentGuardrails;
use App\Domains\Agents\Memory\AgentEpisodicMemory;
use App\Domains\Agents\Models\SocialAgent;
use App\Services\Llm;
use App\Services\Prompts;
use Throwable;

/**
 * Adversarial Multi-Agent Red-Teaming & Debate Protocol.
 * Implements Minsky's Society of Mind and adversarial verification:
 * 1. Creator Advocate Agent: Proposes candidate thesis and hook.
 * 2. Red-Team Cynic Agent: Ruthlessly attacks assumptions, flags marketing fluff, and uncovers counter-arguments.
 * 3. Creator Defense Agent: Mounts technical defense and states boundary conditions.
 * 4. Arbiter Judge Agent: Synthesizes debate into an armored, bulletproof final draft.
 */
class AdversarialDebate
{
    public function __construct(
        private Llm $llm,
        private AgentEpisodicMemory $memory,
        private AgentGuardrails $guardrails
    ) {
    }

    /**
     * Conduct an adversarial red-team debate on a topic or draft.
     *
     * @param  array{topic: string, platform?: string, initial_draft?: string}  $params
     * @return array{
     *     armored_post: array{platform: string, body: string, parts: array},
     *     debate_transcript: array<int, array{round: int, agent: string, role: string, content: string}>,
     *     vulnerabilities_neutralized: array<int, string>,
     *     confidence_score: int
     * }
     */
    public function debate(SocialAgent $agent, array $params): array
    {
        $platform = $params['platform'] ?? 'linkedin';
        $topic = $params['topic'] ?? 'Modern software engineering practices';
        $initialDraft = $params['initial_draft'] ?? '';

        $transcript = [];
        $memoryContext = $this->memory->buildMemoryContext($agent, $platform);

        // Round 1: Creator Advocate (Thesis)
        if (empty($initialDraft)) {
            $genPrompt = "Topic: {$topic}\n\n{$memoryContext}\n\n"
                . "Draft a provocative practitioner take for {$platform}. Line 1 must be a sharp hook. "
                . 'Output JSON: {"body": "...", "parts": []}';

            $this->guardrails->recordTokens(mb_strlen($genPrompt) / 4, 250);

            try {
                $genRes = $this->llm->as('advocate-agent')->creativity(0.7)->json(
                    Prompts::voice() . "\n\nPersona: Confident practitioner for " . Prompts::name() . '.',
                    $genPrompt
                );
                $thesisBody = trim((string)($genRes['body'] ?? ''));
            } catch (Throwable $e) {
                $thesisBody = "Most engineering teams overcomplicate their stack by choosing distributed systems before validating product-market fit.\n\nHere is how we scaled to 50k users on a single VPS.";
            }
        } else {
            $thesisBody = $initialDraft;
        }

        $transcript[] = [
            'round' => 1,
            'agent' => 'Creator Advocate',
            'role' => 'Presents initial thesis and provocative practitioner hook',
            'content' => $thesisBody,
        ];

        // Round 2: Red-Team Cynic Agent (Attack)
        $attackPrompt = "Candidate Post:\n\"{$thesisBody}\"\n\n"
            . 'Act as a cynical, hyper-critical Senior Staff Engineer and skeptical audience member. '
            . "Ruthlessly attack this draft:\n"
            . "1. What claims sound exaggerated, clickbaity, or unsubstantiated?\n"
            . "2. What edge-cases or counter-arguments is the author glossing over?\n"
            . "3. Where does it sound arrogant or like generic advice?\n"
            . 'Output JSON: {"objections": ["..."], "counter_evidence": "..."}';

        $this->guardrails->recordTokens(mb_strlen($attackPrompt) / 4, 300);

        try {
            $attack = $this->llm->as('red-team-cynic')->creativity(0.3)->json(
                'You are an adversarial red-team code auditor and skeptical technical reviewer. Your job is to tear down weak arguments and uncover hidden flaws.',
                $attackPrompt
            );
            $objections = (array)($attack['objections'] ?? ['Lacks concrete boundary conditions for when this fails.']);
            $counterEvidence = (string)($attack['counter_evidence'] ?? 'High-availability requirements preclude single node designs.');
        } catch (Throwable $e) {
            $objections = ['Does not address multi-region failover or disaster recovery constraints.'];
            $counterEvidence = 'A single VPS is a single point of failure.';
        }

        $transcript[] = [
            'round' => 2,
            'agent' => 'Red-Team Cynic',
            'role' => 'Attacks unstated assumptions, exaggerations, and technical edge-cases',
            'content' => "Objections:\n- " . implode("\n- ", $objections) . "\n\nCounter-Evidence: {$counterEvidence}",
        ];

        // Round 3: Creator Defense Agent (Nuance & Rebuttal)
        $defensePrompt = "Candidate Thesis:\n\"{$thesisBody}\"\n\n"
            . "Red-Team Attacks:\n" . implode("\n- ", $objections) . "\n\n"
            . 'Provide a grounded, humble technical defense: concede valid constraints while reaffirming the core practitioner insight.';

        $this->guardrails->recordTokens(mb_strlen($defensePrompt) / 4, 250);

        try {
            $defense = $this->llm->as('advocate-defense')->creativity(0.5)->chat(
                Prompts::voice() . "\n\nPersona: Senior practitioner responding to critical review.",
                $defensePrompt
            );
        } catch (Throwable $e) {
            $defense = 'Agreed that HA requires replication, but 90% of solo projects die from operational overhead before ever needing multi-region.';
        }

        $transcript[] = [
            'round' => 3,
            'agent' => 'Creator Defense',
            'role' => 'Concedes valid edge-cases and contextualizes real-world trade-offs',
            'content' => $defense,
        ];

        // Round 4: Arbiter Judge Agent (Battle-Hardened Synthesis)
        $arbiterPrompt = "Original Thesis:\n\"{$thesisBody}\"\n\n"
            . "Red-Team Attacks:\n" . implode("\n- ", $objections) . "\n\n"
            . "Defense Arguments:\n\"{$defense}\"\n\n"
            . "Synthesize the complete debate into a final, armored post for {$platform}. "
            . "Incorporate the technical nuance and proactively neutralize the red-team's objections so a reader cannot poke holes in it. "
            . 'Keep line 1 punchy. Output JSON: {"body": "...", "parts": [], "confidence_score": 0-100}';

        $this->guardrails->recordTokens(mb_strlen($arbiterPrompt) / 4, 350);

        try {
            $arbiterRes = $this->llm->as('arbiter-judge')->creativity(0.4)->json(
                Prompts::voice() . "\n\n" . Prompts::writingRules() . "\n\nPersona: Arbiter of truth and clarity.",
                $arbiterPrompt
            );
            $armoredBody = $this->guardrails->sanitizeContent(trim((string)($arbiterRes['body'] ?? $thesisBody)));
            $armoredParts = (array)($arbiterRes['parts'] ?? []);
            $confidence = (int)($arbiterRes['confidence_score'] ?? 90);
        } catch (Throwable $e) {
            $armoredBody = $this->guardrails->sanitizeContent($thesisBody . "\n\n(Note: This applies to single-region services before multi-AZ clustering is justified.)");
            $armoredParts = [];
            $confidence = 85;
        }

        $transcript[] = [
            'round' => 4,
            'agent' => 'Arbiter Judge',
            'role' => 'Synthesizes debate arguments into an armored, bulletproof post',
            'content' => $armoredBody,
        ];

        return [
            'armored_post' => [
                'platform' => $platform,
                'body' => $armoredBody,
                'parts' => $armoredParts,
            ],
            'debate_transcript' => $transcript,
            'vulnerabilities_neutralized' => $objections,
            'confidence_score' => $confidence,
        ];
    }
}
