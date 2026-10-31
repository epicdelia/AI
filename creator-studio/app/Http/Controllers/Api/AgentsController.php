<?php

namespace App\Http\Controllers\Api;

use App\Domains\Agents\AgentLearner;
use App\Domains\Agents\AgentPlanner;
use App\Domains\Agents\BrandResearch;
use App\Domains\Agents\Models\AgentEvent;
use App\Domains\Agents\Models\SocialAgent;
use App\Domains\Agents\Publisher;
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
