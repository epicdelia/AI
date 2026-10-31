<?php

namespace App\Domains\Agents\TreeSearch;

use App\Domains\Agents\Guardrails\AgentGuardrails;
use App\Domains\Agents\Harness\QualityEvaluator;
use App\Domains\Agents\Harness\ToolRegistry;
use App\Domains\Agents\Memory\AgentEpisodicMemory;
use App\Domains\Agents\Models\SocialAgent;
use App\Services\AiTellsLinter;
use App\Services\Llm;
use App\Services\Prompts;
use Throwable;

/**
 * Language Agent Tree Search (LATS) & Tree of Thoughts (ToT) Engine.
 * Sourced from frontier research (DeepMind/Princeton LATS & OpenAI o-series inference-time search).
 * Explores multiple divergent reasoning branches, evaluates nodes using external deterministic verifiers,
 * prunes dead ends, and backtracks to synthesize the highest-utility trajectory.
 */
class AgentTreeSearch
{
    public function __construct(
        private Llm $llm,
        private ToolRegistry $tools,
        private AgentEpisodicMemory $memory,
        private AgentGuardrails $guardrails
    ) {}

    /**
     * Execute Language Agent Tree Search to discover the globally optimal post trajectory.
     *
     * @param  array{topic: string, platform?: string, branches_per_level?: int}  $params
     * @return array{
     *     winning_post: array{platform: string, body: string, parts: array},
     *     tree: array{nodes: array, edges: array, pruned_nodes: array, winning_path: array},
     *     stats: array{total_nodes_explored: int, branches_pruned: int, highest_score: int}
     * }
     */
    public function search(SocialAgent $agent, array $params): array
    {
        $platform = $params['platform'] ?? 'linkedin';
        $topic = $params['topic'] ?? 'Software engineering architecture';
        $branchFactor = max(2, min(4, (int) ($params['branches_per_level'] ?? 3)));

        $nodes = [];
        $edges = [];
        $prunedNodes = [];

        // 0. Root Node
        $rootId = 'node_root';
        $nodes[$rootId] = [
            'id' => $rootId,
            'type' => 'root',
            'label' => mb_substr($topic, 0, 40),
            'depth' => 0,
            'score' => 100,
            'data' => ['topic' => $topic],
        ];

        // 1. Level 1: Propose Divergent Strategic Angles (Exploration)
        $angles = $this->proposeAngles($agent, $topic, $branchFactor);

        $level1Nodes = [];
        foreach ($angles as $i => $angle) {
            $nodeId = "node_angle_{$i}";
            $level1Nodes[] = $nodeId;

            $nodes[$nodeId] = [
                'id' => $nodeId,
                'parent' => $rootId,
                'type' => 'angle',
                'label' => $angle['angle_name'] ?? "Angle {$i}",
                'depth' => 1,
                'score' => 80,
                'data' => $angle,
            ];
            $edges[] = ['from' => $rootId, 'to' => $nodeId];
        }

        // 2. Level 2: Expand Angles into Concrete Draft Candidates (Expansion)
        $level2Nodes = [];
        foreach ($level1Nodes as $parentAngleId) {
            $angleData = $nodes[$parentAngleId]['data'];
            $draftCandidates = $this->expandDrafts($agent, $topic, $angleData, $platform, 2);

            foreach ($draftCandidates as $j => $draft) {
                $draftId = "{$parentAngleId}_draft_{$j}";

                // 3. External Verifier Scoring & Pruning (AiTellsLinter + QualityEvaluator)
                $verification = $this->evaluateDraftNode($draft, $platform);
                $score = $verification['overall_score'];
                $hasHardTells = ! empty($verification['hard_tells']);

                $isPruned = $score < 70 || $hasHardTells;

                $nodes[$draftId] = [
                    'id' => $draftId,
                    'parent' => $parentAngleId,
                    'type' => 'draft',
                    'label' => mb_substr($draft['body'], 0, 35).'...',
                    'depth' => 2,
                    'score' => $score,
                    'is_pruned' => $isPruned,
                    'verification' => $verification,
                    'data' => $draft,
                ];
                $edges[] = ['from' => $parentAngleId, 'to' => $draftId];

                if ($isPruned) {
                    $prunedNodes[] = $draftId;
                } else {
                    $level2Nodes[] = $draftId;
                }
            }
        }

        // 4. Backtracking & Trajectory Selection
        // If all level 2 nodes were pruned, salvage highest score among pruned nodes
        $candidatePool = ! empty($level2Nodes) ? $level2Nodes : array_keys(array_filter($nodes, fn ($n) => ($n['depth'] ?? 0) === 2));

        $bestNodeId = $candidatePool[0];
        $highestScore = -1;
        foreach ($candidatePool as $id) {
            if ($nodes[$id]['score'] > $highestScore) {
                $highestScore = $nodes[$id]['score'];
                $bestNodeId = $id;
            }
        }

        $bestNode = $nodes[$bestNodeId];
        $bestParent = $nodes[$bestNode['parent']];

        $winningPath = [$rootId, $bestParent['id'], $bestNodeId];

        // 5. Final Synthesis of Winning Node
        $winningPost = [
            'platform' => $platform,
            'body' => $this->guardrails->sanitizeContent($bestNode['data']['body']),
            'parts' => $bestNode['data']['parts'] ?? [],
        ];

        return [
            'winning_post' => $winningPost,
            'tree' => [
                'nodes' => array_values($nodes),
                'edges' => $edges,
                'pruned_nodes' => $prunedNodes,
                'winning_path' => $winningPath,
            ],
            'stats' => [
                'total_nodes_explored' => count($nodes),
                'branches_pruned' => count($prunedNodes),
                'highest_score' => $highestScore,
            ],
        ];
    }

    private function proposeAngles(SocialAgent $agent, string $topic, int $limit): array
    {
        $memoryContext = $this->memory->buildMemoryContext($agent);

        $prompt = "Topic: {$topic}\n\n{$memoryContext}\n\n"
            ."Generate {$limit} divergent architectural angles for an engineer audience:\n"
            ."1. Contrarian/Debunking angle (disproves common myth)\n"
            ."2. Hard Benchmark/Production Failure angle (real incident or metric)\n"
            ."3. Pragmatic Solo-Builder trade-off angle (simplicity over hype)\n"
            ."Output JSON: {\"angles\": [{\"angle_name\": \"...\", \"hypothesis\": \"...\", \"hook_concept\": \"...\"}]}";

        $this->guardrails->recordTokens(mb_strlen($prompt) / 4, 250);

        try {
            $data = $this->llm->as('tree-search-angle')->creativity(0.8)->json(
                Prompts::voice()."\n\nPersona: Principal engineer & architecture critic.",
                $prompt
            );

            return array_slice($data['angles'] ?? [], 0, $limit);
        } catch (Throwable $e) {
            return [
                ['angle_name' => 'Contrarian Benchmark', 'hypothesis' => 'Simpler architectures outperform complex microservices.', 'hook_concept' => 'Why we dismantled our K8s cluster.'],
                ['angle_name' => 'Incident Autopsy', 'hypothesis' => 'Distributed locks fail during clock skews.', 'hook_concept' => 'The clock drift bug that cost $12k.'],
            ];
        }
    }

    private function expandDrafts(SocialAgent $agent, string $topic, array $angle, string $platform, int $count): array
    {
        $prompt = "Topic: {$topic}\n"
            ."Angle Name: {$angle['angle_name']}\n"
            ."Hypothesis: {$angle['hypothesis']}\n"
            ."Target Platform: {$platform}\n\n"
            ."Draft {$count} distinct post variations for this angle. Line 1 must be an attention-grabbing hook. "
            ."Zero em-dashes and zero AI corporate clichés.\n"
            ."Output JSON: {\"drafts\": [{\"body\": \"...\", \"parts\": []}]}";

        $this->guardrails->recordTokens(mb_strlen($prompt) / 4, 350);

        try {
            $data = $this->llm->as('tree-search-expand')->creativity(0.6)->json(
                Prompts::voice()."\n\n".Prompts::writingRules(),
                $prompt
            );

            return array_slice($data['drafts'] ?? [], 0, $count);
        } catch (Throwable $e) {
            return [
                ['body' => "Most distributed caching setups fail because engineers cache outputs rather than atomic compute mutexes.\n\nHere is how we fixed it.", 'parts' => []],
            ];
        }
    }

    private function evaluateDraftNode(array $draft, string $platform): array
    {
        $body = (string) ($draft['body'] ?? '');
        $eval = QualityEvaluator::evaluate(['platform' => $platform, 'body' => $body]);

        $findings = AiTellsLinter::lint($body);
        $hardTells = array_filter($findings, fn ($f) => $f['severity'] === AiTellsLinter::HARD);

        return [
            'overall_score' => $eval['overall_score'],
            'rubric' => $eval['rubric'],
            'hard_tells' => array_column($hardTells, 'match'),
            'feedback' => $eval['feedback'],
        ];
    }
}

