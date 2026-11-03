<?php

namespace App\Http\Controllers\Api;

use App\Domains\Agents\Models\AgentEvent;
use App\Domains\Community\Community;
use App\Domains\Community\Models\CommunityItem;
use App\Domains\Community\Models\CommunityMembership;
use App\Domains\Community\Models\CommunityPoint;
use App\Domains\Community\Models\CommunityVote;
use App\Domains\Billing\BillingPortal;
use App\Domains\Community\ProBilling;
use App\Domains\Crm\Models\CrmContact;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * The Community Hub. Everything shown is real: what she publishes (kits, blueprints,
 * dispatches, perks), members' questions and showcase projects (projects wait for her
 * approval), one vote per member, points and levels, and an optional paid Pro tier.
 * Her side (community/admin/*) manages it all.
 */
class CommunityController extends Controller
{
    // ==========================================================================================
    // Members
    // ==========================================================================================

    public function hub(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $pro = Community::isPro($user);
        $voted = CommunityVote::where('user_id', $user->id)->pluck('item_id')->all();
        $items = CommunityItem::with('user:id,name')
            ->where(fn ($q) => $q->where('status', 'published')
                ->orWhere(fn ($q) => $q->where('user_id', $user->id)->whereIn('type', ['showcase', 'question'])))
            ->orderByDesc('featured')->latest('published_at')->latest('id')
            ->take(400)->get();
        $by = fn (string $type) => $items->where('type', $type)->values()->map(fn (CommunityItem $i) => $i->toMember($pro, $voted))->all();
        $questions = $items->where('type', 'question')->sortByDesc(fn ($i) => [$i->answered_at ? 0 : 1, $i->upvotes])->values()
            ->map(fn (CommunityItem $i) => $i->toMember($pro, $voted))->all();
        $points = Community::points($user);
        $membership = Community::membership($user);
        $proConfig = Community::proConfig();

        return response()->json([
            'kits' => $by('kit'),
            'blueprints' => $by('blueprint'),
            'dispatches' => $by('dispatch'),
            'perks' => $by('perk'),
            'showcase' => $by('showcase'),
            'ama' => $questions,
            'leaderboard' => Community::leaderboard(10, 30),
            'me' => Community::level($points) + [
                'rank' => $this->rank($user, $points),
            ],
            'pro' => [
                'available' => Community::proAvailable(),
                'active' => $pro,
                'name' => $proConfig['name'],
                'amount' => $proConfig['amount'],
                'currency' => strtoupper($proConfig['currency']),
                'interval' => $proConfig['interval'],
                'pitch' => $proConfig['pitch'],
                'perks' => $proConfig['perks'],
                'status' => $membership?->status,
                'renews_at' => $membership?->current_period_end?->toIso8601String(),
                'can_manage' => (bool)($user->stripe_customer_id && StripePaymentController::configured()),
            ],
            'hire' => Community::hireCard($user, $points),
            'digest' => (bool)($user->community_digest ?? true),
            'member' => [
                'name' => $user->name,
                'role' => $user->role()->label(),
                'joined' => $user->created_at?->format('M Y'),
            ],
        ]);
    }

    public function blueprints(Request $request): JsonResponse
    {
        $pro = Community::isPro($request->user());

        return response()->json([
            'blueprints' => CommunityItem::published()->where('type', 'blueprint')->latest('published_at')->get()
                ->map(fn (CommunityItem $i) => $i->toMember($pro))->all(),
        ]);
    }

    /**
     * A docker-compose + README starter for one of her published blueprints.
     */
    public function scaffoldBlueprint(Request $request): JsonResponse
    {
        $data = $request->validate([
            'blueprint_id' => 'required',
            'project_name' => 'nullable|string|max:50',
            'database' => 'nullable|string|in:postgres,sqlite',
            'cache' => 'nullable|string|in:redis,memory',
        ]);
        $bp = CommunityItem::published()->where('type', 'blueprint')->find((int)$data['blueprint_id']);
        abort_unless($bp, 404, 'That blueprint isn’t available.');
        abort_if($bp->tier === 'pro' && !Community::isPro($request->user()), 403, 'This blueprint is for Pro members.');

        $name = preg_replace('/[^a-zA-Z0-9_\-]/', '', $data['project_name'] ?? '') ?: 'my-blueprint-app';
        $db = $data['database'] ?? 'postgres';
        $cache = $data['cache'] ?? 'redis';
        $dbService = $db === 'postgres'
            ? "  db:\n    image: pgvector/pgvector:pg16\n    environment:\n      - POSTGRES_DB={$name}\n      - POSTGRES_USER=app\n      - POSTGRES_PASSWORD=change-me\n"
            : '';
        $cacheService = $cache === 'redis' ? "  cache:\n    image: redis:7-alpine\n" : '';
        $depends = array_filter([$db === 'postgres' ? 'db' : null, $cache === 'redis' ? 'cache' : null]);
        $compose = "services:\n  app:\n    build: .\n    ports:\n      - \"8000:8000\"\n    environment:\n      - APP_NAME={$name}\n      - DB_TYPE={$db}\n      - CACHE_DRIVER={$cache}\n"
            . ($depends ? "    depends_on:\n" . implode('', array_map(fn ($d) => "      - {$d}\n", $depends)) : '')
            . $dbService . $cacheService;
        $components = implode("\n", array_map(fn ($c) => "- {$c}", (array)($bp->meta['components'] ?? [])));
        $readme = "# {$name}\n\nStarter from the “{$bp->title}” blueprint.\n\n- Database: {$db}\n- Cache: {$cache}\n"
            . ($components ? "\n## Components\n{$components}\n" : '')
            . "\n## Quickstart\n```bash\ndocker compose up -d\n```\n";
        $env = "APP_NAME={$name}\nDB_TYPE={$db}\nCACHE_DRIVER={$cache}\n";

        return response()->json([
            'ok' => true,
            'project_name' => $name,
            'blueprint_id' => $bp->id,
            'files' => [
                'docker-compose.yml' => $compose,
                'README.md' => $readme,
                '.env.example' => $env,
            ],
            'message' => 'Starter files ready.',
        ]);
    }

    public function showcase(Request $request): JsonResponse
    {
        $user = $request->user();
        $voted = CommunityVote::where('user_id', $user->id)->pluck('item_id')->all();
        $list = CommunityItem::with('user:id,name')->published()->where('type', 'showcase')
            ->orderByDesc('featured')->orderByDesc('upvotes')->take(100)->get()
            ->map(fn (CommunityItem $i) => $i->toMember(true, $voted))->all();

        return response()->json([
            'showcase' => $list,
        ]);
    }

    /**
     * A member's project goes to her for approval before anyone else sees it.
     */
    public function submitShowcase(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title' => 'required|string|min:5|max:100',
            'desc' => 'nullable|string|min:10|max:500',
            'description' => 'nullable|string|min:10|max:500',
            'repo_url' => 'required|url:http,https|max:500',
            'demo_url' => 'nullable|url:http,https|max:500',
            'tags' => 'nullable|string|max:200',
        ]);
        $user = $request->user();
        $pending = CommunityItem::where('user_id', $user->id)->where('type', 'showcase')->where('status', 'pending')->count();
        abort_if($pending >= 3, 422, 'You have 3 projects waiting for review already.');

        $item = CommunityItem::create([
            'type' => 'showcase',
            'title' => trim($data['title']),
            'body' => trim((string)($data['description'] ?? $data['desc'] ?? '')),
            'url' => $data['repo_url'],
            'url2' => $data['demo_url'] ?? null,
            'tags' => self::tags($data['tags'] ?? ''),
            'status' => $user->hasAbility(Role::STUDIO) ? 'published' : 'pending',
            'published_at' => $user->hasAbility(Role::STUDIO) ? now() : null,
            'user_id' => $user->id,
        ]);
        AgentEvent::log('community', "Showcase submitted by {$user->name}: \"{$item->title}\" (waiting for review).");

        return response()->json([
            'ok' => true,
            'message' => $item->status === 'pending' ? 'Thanks! Your project will appear once it’s reviewed.' : 'Project published.',
            'item' => $item->load('user:id,name')->toMember(true),
        ], 201);
    }

    public function ask(Request $request): JsonResponse
    {
        $data = $request->validate([
            'question' => 'required|string|min:10|max:500',
        ]);
        $user = $request->user();
        $item = CommunityItem::create([
            'type' => 'question',
            'title' => trim($data['question']),
            'status' => 'published',
            'published_at' => now(),
            'user_id' => $user->id,
        ]);
        Community::award($user, 'asked', $item);
        AgentEvent::log('community', "New question from {$user->name}: \"{$item->title}\"");

        return response()->json([
            'ok' => true,
            'message' => 'Question posted. You’ll get an email when it’s answered.',
            'item' => $item->load('user:id,name')->toMember(true),
        ]);
    }

    /**
     * One vote per member per item; voting again takes it back.
     */
    public function vote(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $item = CommunityItem::published()->whereIn('type', ['question', 'showcase'])->findOrFail($id);
        abort_if($item->user_id === $user->id, 422, 'You can’t vote for your own post.');

        $existing = CommunityVote::where('user_id', $user->id)->where('item_id', $item->id)->first();
        if ($existing) {
            DB::transaction(function () use ($existing, $item) {
                $existing->delete();
                $item->decrement('upvotes');
                CommunityPoint::where('item_id', $item->id)->where('reason', 'upvoted')->latest('id')->limit(1)->delete();
            });
            $voted = false;
        } else {
            try {
                DB::transaction(function () use ($user, $item) {
                    CommunityVote::create(['user_id' => $user->id, 'item_id' => $item->id]);
                    $item->increment('upvotes');
                });
                Community::award($item->user, 'upvoted', $item);
            } catch (UniqueConstraintViolationException) {
                // a double click: already counted
            }
            $voted = true;
        }

        return response()->json([
            'ok' => true,
            'voted' => $voted,
            'upvotes' => (int)$item->fresh()->upvotes,
        ]);
    }

    /**
     * Old clients: POST upvote {id} and showcase/{id}/upvote.
     */
    public function upvote(Request $request): JsonResponse
    {
        $data = $request->validate([
            'id' => 'required|integer',
        ]);

        return $this->vote($request, (int)$data['id']);
    }

    public function upvoteShowcase(Request $request, int $id): JsonResponse
    {
        return $this->vote($request, $id);
    }

    public function digest(Request $request): JsonResponse
    {
        $data = $request->validate([
            'on' => 'required|boolean',
        ]);
        $request->user()->forceFill(['community_digest' => (bool)$data['on']])->save();

        return response()->json([
            'ok' => true,
            'digest' => (bool)$data['on'],
        ]);
    }

    public function proCheckout(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless(Community::proAvailable(), 404, 'The Pro tier isn’t open right now.');
        abort_if(Community::isPro($user), 422, 'You’re already a Pro member.');
        $session = ProBilling::checkout($user);
        abort_unless($session, 503, 'Online payment isn’t set up yet. Please try again later.');

        return response()->json([
            'url' => $session['url'],
        ]);
    }

    public function proManage(Request $request): JsonResponse
    {
        $url = BillingPortal::url($request->user()->stripe_customer_id, url('/community'));
        abort_unless($url, 422, 'There’s no subscription to manage yet.');

        return response()->json([
            'url' => $url,
        ]);
    }

    // ==========================================================================================
    // Her side
    // ==========================================================================================

    public function adminIndex(Request $request): JsonResponse
    {
        $items = CommunityItem::with('user:id,name,email')->latest('id')->take(500)->get();
        $memberIds = User::where('role', Role::Member->value)->pluck('id');
        $pointsBy = CommunityPoint::whereIn('user_id', $memberIds)->groupBy('user_id')
            ->select('user_id', DB::raw('SUM(points) as total'))->pluck('total', 'user_id');
        $hire = Community::hireConfig();
        $warm = User::whereIn('id', $pointsBy->filter(fn ($p) => $p >= $hire['min_points'])->keys())
            ->get(['id', 'name', 'email', 'created_at'])
            ->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'points' => (int)$pointsBy[$u->id],
                'level' => Community::level((int)$pointsBy[$u->id])['name'],
                'questions' => $items->where('type', 'question')->where('user_id', $u->id)->count(),
                'in_crm' => CrmContact::where('user_id', $u->id)->orWhere('email', $u->email)->exists(),
            ])->sortByDesc('points')->values();

        return response()->json([
            'items' => $items->map(fn (CommunityItem $i) => $i->toAdmin())->values(),
            'counts' => [
                'pending' => $items->where('status', 'pending')->count(),
                'unanswered' => $items->where('type', 'question')->where('status', 'published')->whereNull('answered_at')->count(),
                'members' => $memberIds->count(),
                'pro' => CommunityMembership::whereIn('status', ['active', 'comped'])->count(),
            ],
            'leaderboard' => Community::leaderboard(20),
            'warm_leads' => $warm,
            'pro' => Community::proConfig(),
            'hire' => $hire,
            'stripe' => StripePaymentController::configured(),
            'types' => CommunityItem::TYPES,
        ]);
    }

    public function adminStore(Request $request): JsonResponse
    {
        $data = $this->validateItem($request, true);
        $item = CommunityItem::create($data + [
            'user_id' => $request->user()->id,
            'status' => 'published',
            'published_at' => now(),
        ]);

        return response()->json([
            'item' => $item->load('user:id,name,email')->toAdmin(),
        ], 201);
    }

    public function adminUpdate(Request $request, int $id): JsonResponse
    {
        $item = CommunityItem::findOrFail($id);
        $data = $this->validateItem($request, false);
        $wasPending = $item->status === 'pending';
        $wasFeatured = $item->featured;
        $wasAnswered = $item->answered_at !== null;

        if (array_key_exists('answer', $data)) {
            $data['answer'] = trim((string)$data['answer']) ?: null;
            $data['answered_at'] = $data['answer'] ? ($item->answered_at ?? now()) : null;
        }
        if (($data['status'] ?? null) === 'published' && !$item->published_at) {
            $data['published_at'] = now();
        }
        $item->update($data);

        if ($wasPending && $item->status === 'published' && $item->type === 'showcase') {
            Community::award($item->user, 'showcase_approved', $item);
        }
        if (!$wasFeatured && $item->featured) {
            Community::award($item->user, 'featured', $item);
        }
        if (!$wasAnswered && $item->answered_at && $item->type === 'question') {
            Community::award($item->user, 'answered', $item);
            Community::notifyAnswered($item);
        }

        return response()->json([
            'item' => $item->fresh('user:id,name,email')->toAdmin(),
        ]);
    }

    public function adminDestroy(int $id): JsonResponse
    {
        CommunityItem::findOrFail($id)->delete();

        return response()->json([
            'ok' => true,
        ]);
    }

    public function adminSettings(Request $request): JsonResponse
    {
        $data = $request->validate([
            'pro' => 'sometimes|array',
            'pro.enabled' => 'boolean',
            'pro.name' => 'nullable|string|max:40',
            'pro.amount' => 'nullable|numeric|min:0|max:10000',
            'pro.currency' => 'nullable|string|size:3',
            'pro.interval' => 'nullable|in:month,year',
            'pro.pitch' => 'nullable|string|max:500',
            'pro.perks' => 'nullable|array|max:10',
            'pro.perks.*' => 'string|max:120',
            'hire' => 'sometimes|array',
            'hire.enabled' => 'boolean',
            'hire.min_points' => 'nullable|integer|min:0|max:10000',
            'hire.headline' => 'nullable|string|max:120',
            'hire.body' => 'nullable|string|max:400',
            'hire.cta_label' => 'nullable|string|max:40',
            'hire.cta_url' => ['nullable', 'string', 'max:300', 'regex:~^(/|https://)~'],
        ]);
        if (isset($data['pro'])) {
            Setting::put('community_pro', json_encode(array_merge(Community::proConfig(), array_filter($data['pro'], fn ($v) => $v !== null))));
        }
        if (isset($data['hire'])) {
            Setting::put('community_hire', json_encode(array_merge(Community::hireConfig(), array_filter($data['hire'], fn ($v) => $v !== null))));
        }

        return response()->json([
            'pro' => Community::proConfig(),
            'hire' => Community::hireConfig(),
        ]);
    }

    /**
     * Give (or take back) Pro without payment: a thank-you, a client perk.
     */
    public function adminComp(Request $request, int $user): JsonResponse
    {
        $data = $request->validate([
            'on' => 'required|boolean',
        ]);
        $member = User::findOrFail($user);
        if ($data['on']) {
            CommunityMembership::updateOrCreate(['user_id' => $member->id], ['tier' => 'pro', 'status' => 'comped']);
        } else {
            CommunityMembership::where('user_id', $member->id)->where('status', 'comped')->delete();
        }

        return response()->json([
            'ok' => true,
            'pro' => Community::isPro($member),
        ]);
    }

    /**
     * An engaged member becomes a lead in her CRM.
     */
    public function adminToCrm(int $user): JsonResponse
    {
        $member = User::findOrFail($user);
        $contact = CrmContact::firstOrCreate(['email' => $member->email], [
            'name' => $member->name,
            'source' => 'community',
            'stage' => 'lead',
            'user_id' => $member->id,
            'notes' => 'Engaged community member (' . Community::points($member) . ' points).',
        ]);

        return response()->json([
            'ok' => true,
            'contact_id' => $contact->id,
        ]);
    }

    // ==========================================================================================

    private function validateItem(Request $request, bool $creating): array
    {
        $req = $creating ? 'required' : 'sometimes';
        $data = $request->validate([
            'type' => [$req, 'in:' . implode(',', CommunityItem::TYPES)],
            'title' => [$req, 'string', 'max:200'],
            'body' => 'nullable|string|max:20000',
            'answer' => 'nullable|string|max:10000',
            'url' => 'nullable|url:http,https|max:500',
            'url2' => 'nullable|url:http,https|max:500',
            'code' => 'nullable|string|max:200',
            'tags' => 'nullable',
            'meta' => 'nullable|array',
            'tier' => 'sometimes|in:free,pro',
            'status' => 'sometimes|in:published,pending,hidden',
            'featured' => 'sometimes|boolean',
        ]);
        if (array_key_exists('tags', $data)) {
            $data['tags'] = is_array($data['tags']) ? array_slice(array_map('strval', $data['tags']), 0, 12) : self::tags((string)$data['tags']);
        }

        return $data;
    }

    /**
     * @return array<int, string>
     */
    private static function tags(string $raw): array
    {
        return array_values(array_slice(array_filter(array_map(fn ($t) => trim(ltrim(trim($t), '#')), explode(',', $raw))), 0, 8));
    }

    private function rank(User $user, int $points): ?int
    {
        if ($points <= 0) {
            return null;
        }

        return 1 + CommunityPoint::query()->groupBy('user_id')->havingRaw('SUM(points) > ?', [$points])
            ->select('user_id')->get()->count();
    }
}
