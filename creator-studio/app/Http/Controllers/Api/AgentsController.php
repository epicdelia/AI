<?php

namespace App\Http\Controllers\Api;

use App\Domains\Agents\AgentLearner;
use App\Domains\Agents\AgentPlanner;
use App\Domains\Agents\BrandResearch;
use App\Domains\Agents\Cascade\TransmediaCascade;
use App\Domains\Agents\Deals\BrandPitchSynthesizer;
use App\Domains\Agents\Deals\DealFulfillmentGuardian;
use App\Domains\Agents\Deals\SponsorNegotiator;
use App\Domains\Agents\Debate\AdversarialDebate;
use App\Domains\Agents\Engagement\LeadTriageAgent;
use App\Domains\Agents\Ensemble\MultiAgentDeliberation;
use App\Domains\Agents\Guardrails\AgentGuardrails;
use App\Domains\Agents\Guardrails\PlatformAlgorithmShield;
use App\Domains\Agents\Harness\AgentHarness;
use App\Domains\Agents\Harness\BenchmarkHarness;
use App\Domains\Agents\Harness\SprintOrchestrator;
use App\Domains\Agents\Hooks\HookMatrixOptimizer;
use App\Domains\Agents\Mcp\McpServer;
use App\Domains\Agents\Memory\AgentEpisodicMemory;
use App\Domains\Agents\Models\AgentEvent;
use App\Domains\Agents\Models\AgentPromptVersion;
use App\Domains\Agents\Models\SocialAgent;
use App\Domains\Agents\Models\SwarmMission;
use App\Domains\Agents\Optimization\EvergreenRecycler;
use App\Domains\Agents\Optimization\PromptOptimizer;
use App\Domains\Agents\Outliers\CompetitorReverseEngineer;
use App\Domains\Agents\Publisher;
use App\Domains\Agents\Scripts\ScriptRetentionAuditor;
use App\Domains\Agents\Swarm\AgentSwarm;
use App\Domains\Agents\TreeSearch\AgentTreeSearch;
use App\Domains\Agents\Verification\FactCheckShield;
use App\Domains\Agents\YouTube\TitleBanditOptimizer;
use App\Domains\Agents\Monetization\SponsorRevenueArchitect;
use App\Domains\Agents\Arbitrage\RepurposingArbitrageEngine;
use App\Domains\Agents\Infra\StudioInfraGuardian;
use App\Domains\Social\Connect\Connections;
use App\Domains\Social\Models\SocialAccount;
use App\Domains\Social\Models\SocialPost;
use App\Domains\Social\Platforms;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Throwable;

/** Social media agents: set up, run, approve their posts, see what they learned, and the notifications drawer. */
class AgentsController extends Controller
{
    public function index(): JsonResponse
    {
        $agents = SocialAgent::orderBy('created_at')->get()->map(fn ($a) => $this->summary($a));

        return response()->json(['agents' => $agents, 'meta' => $this->meta()]);
    }

    public function show(SocialAgent $agent): JsonResponse
    {
        $posts = SocialPost::where('social_agent_id', $agent->id);
        [$measured, $edited, $rejected] = AgentLearner::signals($agent);

        return response()->json([
            'agent' => $this->summary($agent) + ['brand' => $agent->brand, 'audience' => $agent->audience, 'site_url' => $agent->site_url],
            'pending' => (clone $posts)->where('approval', 'pending')->orderBy('scheduled_for')->get(),
            'upcoming' => (clone $posts)->where('status', 'scheduled')->orderBy('scheduled_for')->limit(30)->get(),
            'published' => (clone $posts)->where('status', 'posted')->latest('posted_at')->limit(20)->get(),
            'events' => AgentEvent::where('social_agent_id', $agent->id)->latest()->limit(40)->get(),
            'trend' => AgentLearner::trend($agent),
            'signals' => ['measured' => $measured->count(), 'edited' => $edited->count(), 'rejected' => $rejected->count(), 'needed' => AgentLearner::MIN_SIGNALS],
            'meta' => $this->meta(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $agent = SocialAgent::create($this->validated($request) + ['next_run_at' => now()]);
        AgentEvent::log('created', "Agent “{$agent->name}” is set up for ".implode(', ', array_map(fn ($p) => Platforms::PLATFORMS[$p]['label'], $agent->platforms)).'.', $agent->id);

        return response()->json(['agent' => $this->summary($agent)], 201);
    }

    public function update(Request $request, SocialAgent $agent): JsonResponse
    {
        $agent->update($this->validated($request, partial: true));

        return response()->json(['agent' => $this->summary($agent->fresh())]);
    }

    public function destroy(SocialAgent $agent): JsonResponse
    {
        // Its unapproved drafts go with it; anything approved or published stays on her calendar.
        SocialPost::where('social_agent_id', $agent->id)->where('approval', 'pending')->delete();
        $agent->delete();

        return response()->json(['ok' => true]);
    }

    public function run(SocialAgent $agent, AgentPlanner $planner): JsonResponse
    {
        @set_time_limit(300);
        try {
            AgentPlanner::assertReady();
            $made = $planner->run($agent);
        } catch (Throwable $e) {
            AgentEvent::log('failed', "Couldn’t plan posts: {$e->getMessage()}", $agent->id);

            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json(['message' => $made ? count($made).' new post(s) planned.' : 'The next 7 days are already full for this agent.', 'posts' => $made]);
    }

    public function learn(SocialAgent $agent, AgentLearner $learner): JsonResponse
    {
        @set_time_limit(180);
        try {
            return response()->json(['lessons' => $learner->learn($agent)]);
        } catch (Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    public function signals(): JsonResponse
    {
        return response()->json(AgentPlanner::gatherSignals());
    }

    public function repurposeScript(Request $request, SocialAgent $agent, AgentPlanner $planner): JsonResponse
    {
        @set_time_limit(180);
        $data = $request->validate([
            'script_id' => 'required|exists:scripts,id',
        ]);

        $script = \App\Models\Script::findOrFail($data['script_id']);

        try {
            $posts = $planner->repurposeScript($agent, $script);
        } catch (Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => count($posts).' post(s) autonomously repurposed from “'.$script->title.'”.',
            'posts' => $posts,
        ]);
    }

    public function spikeBreakout(Request $request, SocialAgent $agent, AgentPlanner $planner): JsonResponse
    {
        @set_time_limit(180);
        $data = $request->validate([
            'outlier_id' => 'required|exists:outliers,id',
            'platform' => ['nullable', Rule::in(array_keys(Platforms::PLATFORMS))],
        ]);

        $outlier = \App\Models\Outlier::with('channel')->findOrFail($data['outlier_id']);

        try {
            $post = $planner->fromBreakout($agent, $outlier, $data['platform'] ?? null);
        } catch (Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Trending reaction post drafted on '.Platforms::PLATFORMS[$post->platform]['label'].'.',
            'post' => $post,
        ]);
    }

    public function runHarness(SocialAgent $agent, AgentHarness $harness): JsonResponse
    {
        @set_time_limit(300);
        try {
            AgentPlanner::assertReady();
            $result = $harness->execute($agent);
        } catch (Throwable $e) {
            AgentEvent::log('failed', "Harness execution failed: {$e->getMessage()}", $agent->id);

            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Agent Harness completed execution.',
            'trace' => $result['trace'],
            'posts' => $result['posts'],
            'evaluations' => $result['evaluations'],
            'guardrails' => $result['guardrails'] ?? null,
        ]);
    }

    public function benchmark(SocialAgent $agent, BenchmarkHarness $benchmark): JsonResponse
    {
        @set_time_limit(300);
        try {
            AgentPlanner::assertReady();
            $res = $benchmark->runSuite($agent);
        } catch (Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => "Benchmark evaluation complete. Overall score: {$res['overall_average']}/100.",
            'overall_average' => $res['overall_average'],
            'reports' => $res['reports'],
        ]);
    }

    public function evals(SocialAgent $agent): JsonResponse
    {
        $reports = $agent->evalReports()->latest()->take(20)->get();
        $average = $reports->count() ? (int) round($reports->avg('overall_score')) : null;

        return response()->json([
            'average' => $average,
            'reports' => $reports,
            'scenarios' => BenchmarkHarness::SCENARIOS,
        ]);
    }

    public function mcp(Request $request, McpServer $mcp): JsonResponse
    {
        $payload = $request->all();
        $response = $mcp->handle($payload);

        return response()->json($response);
    }

    public function mcpTools(McpServer $mcp): JsonResponse
    {
        $tools = $mcp->handle(['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list']);

        return response()->json($tools['result'] ?? []);
    }

    public function memory(SocialAgent $agent, AgentEpisodicMemory $memory): JsonResponse
    {
        return response()->json($memory->inspect($agent));
    }

    public function deliberate(Request $request, SocialAgent $agent, MultiAgentDeliberation $ensemble): JsonResponse
    {
        @set_time_limit(300);
        AgentPlanner::assertReady();

        $data = $request->validate([
            'topic' => ['required', 'string', 'max:500'],
            'platform' => ['nullable', Rule::in(array_keys(Platforms::PLATFORMS))],
            'source_signal' => ['nullable', 'array'],
            'sponsor' => ['nullable', 'array'],
        ]);

        try {
            $result = $ensemble->deliberate($agent, $data);
        } catch (Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Multi-agent deliberation completed.',
            'post' => $result['final_post'],
            'trace' => $result['trace'],
            'grounding_score' => $result['grounding_score'],
            'voice_score' => $result['voice_score'],
            'guardrails' => $result['guardrails_summary'],
        ]);
    }

    public function guardrails(AgentGuardrails $guardrails): JsonResponse
    {
        return response()->json($guardrails->summary());
    }

    public function planSwarm(Request $request, SocialAgent $agent, AgentSwarm $swarm): JsonResponse
    {
        @set_time_limit(300);
        AgentPlanner::assertReady();

        $data = $request->validate([
            'goal' => ['required', 'string', 'max:500'],
            'platforms' => ['nullable', 'array'],
            'platforms.*' => [Rule::in(array_keys(Platforms::PLATFORMS))],
        ]);

        $mission = $swarm->planMission(
            $agent,
            $data['goal'],
            $data['platforms'] ?? $agent->platforms
        );

        return response()->json([
            'message' => 'Swarm mission planned with '.count($mission->steps).' agent worker steps.',
            'mission' => $mission,
        ]);
    }

    public function stepSwarm(SwarmMission $mission, AgentSwarm $swarm): JsonResponse
    {
        @set_time_limit(300);
        AgentPlanner::assertReady();

        $updated = $swarm->stepMission($mission);

        return response()->json([
            'message' => 'Mission step executed.',
            'mission' => $updated,
        ]);
    }

    public function runSwarm(SwarmMission $mission, AgentSwarm $swarm): JsonResponse
    {
        @set_time_limit(300);
        AgentPlanner::assertReady();

        $completed = $swarm->runToCompletion($mission);

        return response()->json([
            'message' => 'Swarm mission completed execution.',
            'mission' => $completed,
        ]);
    }

    public function missions(SocialAgent $agent): JsonResponse
    {
        return response()->json([
            'missions' => $agent->swarmMissions()->latest()->take(10)->get(),
        ]);
    }

    public function optimizePrompt(SocialAgent $agent, PromptOptimizer $optimizer): JsonResponse
    {
        @set_time_limit(300);
        AgentPlanner::assertReady();

        $result = $optimizer->optimize($agent);

        return response()->json([
            'message' => $result['improved']
                ? "Prompt successfully optimized! Score improved from {$result['baseline_score']} to {$result['optimized_score']}."
                : "Prompt evaluation complete. Current prompt retained.",
            'result' => $result,
        ]);
    }

    public function promptVersions(SocialAgent $agent): JsonResponse
    {
        return response()->json([
            'versions' => $agent->promptVersions()->orderByDesc('version')->take(10)->get(),
        ]);
    }

    public function treeSearch(Request $request, SocialAgent $agent, AgentTreeSearch $treeSearch): JsonResponse
    {
        @set_time_limit(300);
        AgentPlanner::assertReady();

        $data = $request->validate([
            'topic' => ['required', 'string', 'max:500'],
            'platform' => ['nullable', Rule::in(array_keys(Platforms::PLATFORMS))],
            'branches_per_level' => ['nullable', 'integer', 'min:2', 'max:4'],
        ]);

        try {
            $result = $treeSearch->search($agent, $data);
        } catch (Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Language Agent Tree Search completed.',
            'post' => $result['winning_post'],
            'tree' => $result['tree'],
            'stats' => $result['stats'],
        ]);
    }

    public function debate(Request $request, SocialAgent $agent, AdversarialDebate $debate): JsonResponse
    {
        @set_time_limit(300);
        AgentPlanner::assertReady();

        $data = $request->validate([
            'topic' => ['required', 'string', 'max:500'],
            'platform' => ['nullable', Rule::in(array_keys(Platforms::PLATFORMS))],
            'initial_draft' => ['nullable', 'string', 'max:5000'],
        ]);

        try {
            $result = $debate->debate($agent, $data);
        } catch (Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Adversarial red-team debate concluded.',
            'post' => $result['armored_post'],
            'transcript' => $result['debate_transcript'],
            'vulnerabilities_neutralized' => $result['vulnerabilities_neutralized'],
            'confidence_score' => $result['confidence_score'],
        ]);
    }

    public function sponsorNegotiate(Request $request, SocialAgent $agent, SponsorNegotiator $negotiator): JsonResponse
    {
        @set_time_limit(300);
        AgentPlanner::assertReady();

        $data = $request->validate([
            'inbound_text' => ['required', 'string', 'max:10000'],
            'brand_name' => ['nullable', 'string', 'max:150'],
            'target_product' => ['nullable', 'string', 'max:150'],
            'initial_budget' => ['nullable', 'numeric', 'min:0'],
        ]);

        try {
            $result = $negotiator->negotiate($agent, $data);
        } catch (Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Inbound sponsor inquiry analyzed with tiered counter-packages.',
            'result' => $result,
        ]);
    }

    public function sponsorSaveDeal(Request $request, SocialAgent $agent, SponsorNegotiator $negotiator): JsonResponse
    {
        $data = $request->validate([
            'brand_name' => ['required', 'string', 'max:150'],
            'tier_key' => ['nullable', 'string', 'max:50'],
            'rate' => ['required', 'numeric', 'min:0'],
            'deliverables' => ['required', 'string', 'max:1000'],
            'contact_email' => ['nullable', 'email', 'max:150'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $deal = $negotiator->createDealFromNegotiation($agent, $data);

        return response()->json([
            'message' => "Deal with {$deal->brand_name} saved to pipeline.",
            'deal' => $deal,
        ], 201);
    }

    public function transmediaCascade(Request $request, SocialAgent $agent, TransmediaCascade $cascade): JsonResponse
    {
        @set_time_limit(300);
        AgentPlanner::assertReady();

        $data = $request->validate([
            'topic' => ['nullable', 'string', 'max:500'],
            'script_id' => ['nullable', 'exists:scripts,id'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            $result = $cascade->generate($agent, $data);
        } catch (Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => '5-platform transmedia cascade generated.',
            'cascade' => $result['cascade'],
            'source_summary' => $result['source_summary'],
            'total_assets' => $result['total_assets'],
        ]);
    }

    public function cascadeSchedule(Request $request, SocialAgent $agent, TransmediaCascade $cascade): JsonResponse
    {
        $data = $request->validate([
            'cascade' => ['required', 'array'],
            'platforms_to_schedule' => ['nullable', 'array'],
            'platforms_to_schedule.*' => [Rule::in(array_keys(Platforms::PLATFORMS))],
        ]);

        $posts = $cascade->scheduleCascade($agent, $data);

        return response()->json([
            'message' => count($posts).' cascade post(s) scheduled to calendar.',
            'posts' => $posts,
        ]);
    }

    public function hookMatrix(Request $request, SocialAgent $agent, HookMatrixOptimizer $optimizer): JsonResponse
    {
        @set_time_limit(300);
        AgentPlanner::assertReady();

        $data = $request->validate([
            'topic' => ['required', 'string', 'max:500'],
            'platform' => ['nullable', Rule::in(array_keys(Platforms::PLATFORMS))],
            'context' => ['nullable', 'string', 'max:5000'],
        ]);

        try {
            $result = $optimizer->generateMatrix($agent, $data);
        } catch (Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => '6-archetype viral hook matrix generated.',
            'topic' => $result['topic'],
            'platform' => $result['platform'],
            'matrix' => $result['matrix'],
            'winning_hook' => $result['winning_hook'],
            'benchmark_insights' => $result['benchmark_insights'],
        ]);
    }

    public function factCheck(Request $request, SocialAgent $agent, FactCheckShield $shield): JsonResponse
    {
        @set_time_limit(300);
        AgentPlanner::assertReady();

        $data = $request->validate([
            'text' => ['required', 'string', 'max:20000'],
            'platform' => ['nullable', Rule::in(array_keys(Platforms::PLATFORMS))],
        ]);

        try {
            $result = $shield->verify($agent, $data);
        } catch (Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Fact check & grounding verification complete.',
            'credibility_score' => $result['credibility_score'],
            'status' => $result['status'],
            'claims_evaluated' => $result['claims_evaluated'],
            'armored_text' => $result['armored_text'],
            'disclaimers_recommended' => $result['disclaimers_recommended'],
        ]);
    }

    public function reverseEngineerOutlier(Request $request, SocialAgent $agent, CompetitorReverseEngineer $engineer): JsonResponse
    {
        @set_time_limit(300);
        AgentPlanner::assertReady();

        $data = $request->validate([
            'outlier_id' => ['nullable', 'exists:outliers,id'],
            'title' => ['nullable', 'string', 'max:500'],
            'competitor_name' => ['nullable', 'string', 'max:150'],
            'why_it_worked' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $result = $engineer->deconstruct($agent, $data);
        } catch (Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Competitor breakout reverse-engineered into 3 Blue Ocean angles.',
            'result' => $result,
        ]);
    }

    public function banditTitles(Request $request, SocialAgent $agent, TitleBanditOptimizer $optimizer): JsonResponse
    {
        @set_time_limit(300);
        AgentPlanner::assertReady();

        $data = $request->validate([
            'topic' => ['required', 'string', 'max:500'],
            'current_title' => ['nullable', 'string', 'max:500'],
            'video_id' => ['nullable', 'string', 'max:50'],
        ]);

        try {
            $result = $optimizer->optimize($agent, $data);
        } catch (Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => '4 algorithmic bandit title variants and thumbnail concepts generated.',
            'result' => $result,
        ]);
    }

    public function launchTitleTest(Request $request, TitleBanditOptimizer $optimizer): JsonResponse
    {
        $data = $request->validate([
            'video_id' => ['required', 'string', 'max:50'],
            'titles' => ['required', 'array', 'min:2'],
            'titles.*' => ['required', 'string', 'max:100'],
            'hours_per_window' => ['nullable', 'integer', 'min:1', 'max:24'],
            'rounds' => ['nullable', 'integer', 'min:1', 'max:10'],
        ]);

        $test = $optimizer->launchTest($data);

        return response()->json([
            'message' => 'YouTube Title A/B Test initiated.',
            'test' => $test,
        ], 201);
    }

    public function triageLeads(Request $request, SocialAgent $agent, LeadTriageAgent $triageAgent): JsonResponse
    {
        @set_time_limit(300);
        AgentPlanner::assertReady();

        $data = $request->validate([
            'item_id' => ['nullable', 'exists:engagement_items,id'],
            'messages' => ['nullable', 'array'],
        ]);

        try {
            $result = $triageAgent->triage($agent, $data);
        } catch (Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => "Community engagement triaged. Found {$result['leads_detected']} commercial lead(s).",
            'result' => $result,
        ]);
    }

    public function escalateLeadDeal(Request $request, SocialAgent $agent, LeadTriageAgent $triageAgent): JsonResponse
    {
        $data = $request->validate([
            'author' => ['required', 'string', 'max:150'],
            'intent' => ['nullable', 'string', 'max:50'],
            'estimated_value' => ['required', 'numeric', 'min:0'],
            'text' => ['required', 'string', 'max:2000'],
            'contact_email' => ['nullable', 'email', 'max:150'],
        ]);

        $deal = $triageAgent->escalateToDeal($agent, $data);

        return response()->json([
            'message' => "Lead from {$deal->brand_name} escalated to Deals pipeline.",
            'deal' => $deal,
        ], 201);
    }

    public function auditRetention(Request $request, SocialAgent $agent, ScriptRetentionAuditor $auditor): JsonResponse
    {
        @set_time_limit(300);
        AgentPlanner::assertReady();

        $data = $request->validate([
            'script_id' => ['nullable', 'exists:scripts,id'],
            'title' => ['nullable', 'string', 'max:500'],
            'body' => ['nullable', 'string', 'max:50000'],
            'target_duration_minutes' => ['nullable', 'integer', 'min:3', 'max:60'],
        ]);

        try {
            $result = $auditor->audit($agent, $data);
        } catch (Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Script retention audit complete with 90-second reset pacing.',
            'result' => $result,
        ]);
    }

    public function auditDealFulfillment(Request $request, SocialAgent $agent, DealFulfillmentGuardian $guardian): JsonResponse
    {
        @set_time_limit(300);
        AgentPlanner::assertReady();

        $data = $request->validate([
            'deal_id' => ['nullable', 'exists:deals,id'],
            'content' => ['required', 'string', 'max:50000'],
            'platform' => ['nullable', 'string', 'max:50'],
            'title' => ['nullable', 'string', 'max:500'],
            'brand_name' => ['nullable', 'string', 'max:150'],
        ]);

        try {
            $result = $guardian->auditDraft($agent, $data);
        } catch (Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => "Brand deal fulfillment audited. Compliance score: {$result['compliance_score']}.",
            'result' => $result,
        ]);
    }

    public function generateDealWrapReport(Request $request, SocialAgent $agent, DealFulfillmentGuardian $guardian): JsonResponse
    {
        @set_time_limit(300);
        AgentPlanner::assertReady();

        $data = $request->validate([
            'deal_id' => ['nullable', 'exists:deals,id'],
            'brand_name' => ['nullable', 'string', 'max:150'],
            'impressions' => ['nullable', 'integer', 'min:0'],
            'clicks' => ['nullable', 'integer', 'min:0'],
            'live_url' => ['nullable', 'string', 'max:500'],
            'positive_comments' => ['nullable', 'array'],
        ]);

        try {
            $result = $guardian->generateWrapReport($agent, $data);
        } catch (Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => "Executive sponsor wrap report generated for {$result['brand_name']}.",
            'result' => $result,
        ]);
    }

    public function synthesizeBrandPitch(Request $request, SocialAgent $agent, BrandPitchSynthesizer $synthesizer): JsonResponse
    {
        @set_time_limit(300);
        AgentPlanner::assertReady();

        $data = $request->validate([
            'brand_name' => ['required', 'string', 'max:150'],
            'product_category' => ['nullable', 'string', 'max:200'],
            'pitch_angle' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $result = $synthesizer->synthesizePitch($agent, $data);
        } catch (Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => "Outbound sponsorship pitch package synthesized for {$result['target_brand']}.",
            'result' => $result,
        ]);
    }

    public function recycleEvergreen(Request $request, SocialAgent $agent, EvergreenRecycler $recycler): JsonResponse
    {
        @set_time_limit(300);
        AgentPlanner::assertReady();

        $data = $request->validate([
            'script_id' => ['nullable', 'exists:scripts,id'],
            'post_id' => ['nullable', 'exists:social_posts,id'],
            'topic' => ['nullable', 'string', 'max:500'],
            'target_platform' => ['nullable', 'string', 'max:50'],
        ]);

        try {
            $result = $recycler->recycle($agent, $data);
        } catch (Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Evergreen topic revitalized into 3 modern 2026 angles.',
            'result' => $result,
        ]);
    }

    public function scheduleEvergreenReboot(Request $request, SocialAgent $agent, EvergreenRecycler $recycler): JsonResponse
    {
        $data = $request->validate([
            'reboot' => ['required', 'array'],
            'reboot.platform' => ['required', 'string', 'max:50'],
            'reboot.body' => ['required', 'string', 'max:30000'],
            'reboot.title' => ['nullable', 'string', 'max:500'],
            'reboot.parts' => ['nullable', 'array'],
            'scheduled_for' => ['nullable', 'date'],
        ]);

        $post = $recycler->scheduleReboot($agent, $data);

        return response()->json([
            'message' => "Evergreen reboot scheduled for {$post->platform}.",
            'post' => $post,
        ], 201);
    }

    public function shieldAlgorithm(Request $request, SocialAgent $agent, PlatformAlgorithmShield $shield): JsonResponse
    {
        @set_time_limit(300);
        AgentPlanner::assertReady();

        $data = $request->validate([
            'platform' => ['required', 'string', 'max:50'],
            'content' => ['required', 'string', 'max:30000'],
            'title' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $result = $shield->auditAndArmor($agent, $data);
        } catch (Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => "Platform algorithm audit complete. Reach score: {$result['algorithm_reach_score']}.",
            'result' => $result,
        ]);
    }

    public function planSprint(Request $request, SocialAgent $agent, SprintOrchestrator $orchestrator): JsonResponse
    {
        @set_time_limit(300);
        AgentPlanner::assertReady();

        $data = $request->validate([
            'hours_available' => ['nullable', 'integer', 'min:1', 'max:80'],
            'focus_topic' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $result = $orchestrator->planSprint($agent, $data);
        } catch (Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Weekly production sprint roadmap generated.',
            'result' => $result,
        ]);
    }

    public function architectSponsorPlacement(Request $request, SocialAgent $agent, SponsorRevenueArchitect $architect): JsonResponse
    {
        @set_time_limit(300);
        $data = $request->validate([
            'script_text' => ['required', 'string', 'min:10', 'max:50000'],
            'brand' => ['nullable', 'string', 'max:200'],
            'talking_points' => ['nullable', 'string', 'max:1000'],
            'base_cpm' => ['nullable', 'numeric', 'min:1', 'max:500'],
            'projected_views' => ['nullable', 'integer', 'min:100', 'max:10000000'],
        ]);

        try {
            $result = $architect->architect($data['script_text'], $data);
        } catch (Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Sponsored segment integration & revenue yield calculated.',
            'result' => $result,
        ]);
    }

    public function repurposingArbitrage(Request $request, SocialAgent $agent, RepurposingArbitrageEngine $engine): JsonResponse
    {
        @set_time_limit(300);
        $data = $request->validate([
            'content' => ['required', 'string', 'min:10', 'max:50000'],
            'niche' => ['nullable', 'string', 'max:200'],
        ]);

        try {
            $result = $engine->synthesize($data['content'], $data);
        } catch (Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Multi-platform reach arbitrage synthesis generated.',
            'result' => $result,
        ]);
    }

    public function infraHealth(StudioInfraGuardian $guardian): JsonResponse
    {
        return response()->json($guardian->audit());
    }

    /** Approve (optionally with her edits): it's scheduled at its slot. */
    public function approve(Request $request, SocialPost $post): JsonResponse
    {
        abort_unless($post->social_agent_id, 404);
        $data = $request->validate(['body' => ['nullable', 'string', 'max:30000'], 'parts' => ['nullable', 'array', 'max:25'], 'parts.*' => ['string', 'max:5000'],
            'scheduled_for' => ['nullable', 'date']]);
        $post->fill(array_filter(['body' => $data['body'] ?? null, 'parts' => $data['parts'] ?? null], fn ($v) => $v !== null));
        $at = isset($data['scheduled_for']) ? \Carbon\CarbonImmutable::parse($data['scheduled_for']) : $post->scheduled_for;
        if (! $at || $at->isPast()) {
            $at = \App\Domains\Social\SocialScheduler::nextSlot($post->platform, null, $post->id)['at'];
        }
        $post->fill(['approval' => 'approved', 'status' => 'scheduled', 'scheduled_for' => $at, 'publish_error' => null])->save();
        $edited = AgentLearner::similarity($post->agent_original, trim($post->body."\n\n".implode("\n\n", $post->parts ?? []))) < 0.9;
        AgentEvent::log('approved', ($edited ? 'You edited and approved' : 'You approved').' a '.Platforms::PLATFORMS[$post->platform]['label'].' post.', $post->social_agent_id, $post->id, $post->platform);

        return response()->json(['post' => $post->fresh(), 'publishes' => $this->publishes($post->platform)]);
    }

    /** Reject with a reason: the reason is what the agent learns from. */
    public function reject(Request $request, SocialPost $post): JsonResponse
    {
        abort_unless($post->social_agent_id && $post->approval === 'pending', 404);
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:500']]);
        $reason = trim((string) ($data['reason'] ?? ''));
        AgentEvent::log('rejected', '['.$post->platform.'] '.mb_substr(str_replace("\n", ' ', (string) $post->agent_original), 0, 200).' — Reason: '.($reason ?: 'not given'),
            $post->social_agent_id, null, $post->platform);
        $post->delete();

        return response()->json(['ok' => true]);
    }

    public function publishNow(SocialPost $post): JsonResponse
    {
        abort_unless($post->social_agent_id && $post->status !== 'posted', 404);
        try {
            $r = Publisher::publish($post);
            AgentEvent::log('published', 'Published on '.Platforms::PLATFORMS[$post->platform]['label'].' (by you).', $post->social_agent_id, $post->id, $post->platform);

            return response()->json(['post' => $post->fresh(), 'url' => $r['url'] ?? null]);
        } catch (Throwable $e) {
            $post->update(['publish_error' => mb_substr($e->getMessage(), 0, 500)]);

            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    public function scrape(Request $request, BrandResearch $research): JsonResponse
    {
        $url = $request->validate(['url' => ['required', 'string', 'max:500']])['url'];
        @set_time_limit(120);
        try {
            return response()->json($research->scrape($url));
        } catch (Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    public function audiences(Request $request, BrandResearch $research): JsonResponse
    {
        $brand = $request->validate(['brand' => ['required', 'string', 'min:20', 'max:8000']])['brand'];
        try {
            return response()->json(['audiences' => $research->audiences($brand)]);
        } catch (Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    public function notifications(): JsonResponse
    {
        return response()->json([
            'unread' => AgentEvent::whereNull('read_at')->count(),
            'pending' => SocialPost::where('approval', 'pending')->count(),
            'inbox' => \App\Domains\Engagement\Models\EngagementItem::whereIn('status', ['new', 'drafted', 'failed'])->where('intent', '!=', 'spam')->count(),
            'events' => AgentEvent::with('agent:id,name')->latest()->limit(50)->get(),
        ]);
    }

    public function readNotifications(): JsonResponse
    {
        AgentEvent::whereNull('read_at')->update(['read_at' => now()]);

        return response()->json(['ok' => true]);
    }

    public function clearNotifications(): JsonResponse
    {
        AgentEvent::query()->delete();

        return response()->json(['ok' => true]);
    }

    private function validated(Request $request, bool $partial = false): array
    {
        $req = $partial ? 'sometimes' : 'required';
        $data = $request->validate([
            'name' => [$req, 'string', 'max:80'],
            'platforms' => [$req, 'array', 'min:1'],
            'platforms.*' => [Rule::in(array_keys(AgentPlanner::FORMATS))],
            'site_url' => ['nullable', 'string', 'max:500'],
            'brand' => [$req, 'string', 'min:20', 'max:8000'],
            'audience' => ['nullable', 'string', 'max:2000'],
            'post_types' => [$req, 'array', 'min:1'],
            'post_types.*' => [Rule::in(array_keys(SocialAgent::POST_TYPES))],
            'options' => ['nullable', 'array'],
            'options.hashtags' => ['nullable', 'boolean'],
            'options.hashtag_count' => ['nullable', 'integer', 'min:0', 'max:10'],
            'options.emoji' => ['nullable', 'boolean'],
            'options.cta' => ['nullable', 'string', 'max:300'],
            'options.tone' => ['nullable', 'string', 'max:300'],
            'posts_per_week' => [$req, 'integer', 'min:1', 'max:14'],
            'approval' => [$req, Rule::in(['review', 'auto'])],
            'active' => ['sometimes', 'boolean'],
            'lessons' => ['sometimes', 'array', 'max:12'],
            'lessons.*.text' => ['required', 'string', 'max:300'],
            'lessons.*.evidence' => ['nullable', 'string', 'max:300'],
        ]);
        if (isset($data['platforms'])) {
            $data['platforms'] = array_values(array_unique($data['platforms']));
        }

        return $data;
    }

    private function summary(SocialAgent $a): array
    {
        $posts = SocialPost::where('social_agent_id', $a->id);

        return [
            'id' => $a->id, 'name' => $a->name, 'platforms' => $a->platforms, 'post_types' => $a->post_types, 'options' => $a->options ?? [],
            'posts_per_week' => $a->posts_per_week, 'approval' => $a->approval, 'active' => $a->active, 'lessons' => $a->lessons ?? [],
            'learned_at' => $a->learned_at, 'last_run_at' => $a->last_run_at, 'next_run_at' => $a->next_run_at, 'created_at' => $a->created_at,
            'counts' => [
                'pending' => (clone $posts)->where('approval', 'pending')->count(),
                'scheduled' => (clone $posts)->where('status', 'scheduled')->count(),
                'published' => (clone $posts)->where('status', 'posted')->count(),
            ],
            'publishes' => collect($a->platforms)->mapWithKeys(fn ($p) => [$p => $this->publishes($p)]),
        ];
    }

    /** auto = the studio publishes it; manual = she posts it when it's due. */
    private function publishes(string $platform): string
    {
        $connected = SocialAccount::where('platform', $platform)->where('status', 'connected')->exists();

        return $connected && Connections::provider($platform)->canPublish() ? 'auto' : 'manual';
    }

    private function meta(): array
    {
        return [
            'post_types' => collect(SocialAgent::POST_TYPES)->map(fn ($t, $k) => ['key' => $k, 'label' => $t[0], 'hint' => $t[1]])->values(),
            'networks' => collect(AgentPlanner::FORMATS)->map(fn ($f, $p) => [
                'platform' => $p, 'label' => Platforms::PLATFORMS[$p]['label'], 'format' => Platforms::FORMATS[$p][$f][0],
                'connected' => SocialAccount::where('platform', $p)->where('status', 'connected')->exists(),
                'handle' => SocialAccount::where('platform', $p)->value('handle') ?: \App\Models\Setting::get("{$p}_handle", ''),
                'publishes' => $this->publishes($p), 'can_publish' => Connections::provider($p)->canPublish(),
            ])->values(),
            'ai' => \App\Services\Llm::configured(),
        ];
    }
}
