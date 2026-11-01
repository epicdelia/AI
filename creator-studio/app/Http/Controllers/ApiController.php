<?php

namespace App\Http\Controllers;

use App\Domains\Audience\Models\AudienceInsight;
use App\Domains\Engine\Catalog\ModelCatalog;
use App\Domains\Social\Models\SocialPost;
use App\Http\Controllers\Api\AudienceController;
use Illuminate\Support\Facades\Cache;
use App\Domains\Engine\EngineManager;
use App\Domains\Usage\UsageLimit;
use App\Domains\Feeds\Models\FeedItem;
use App\Domains\Engine\Enums\EngineEnum;
use App\Domains\Usage\AiUsage;
use App\Models\Channel;
use App\Models\Deal;
use App\Models\Idea;
use App\Models\IdeaBrief;
use App\Models\Note;
use App\Models\Outlier;
use App\Models\Script;
use App\Models\Setting;
use App\Models\User;
use App\Models\Week;
use App\Services\AiTellsLinter;
use App\Services\BreakoutScanner;
use App\Services\WeeklyCheckin;
use App\Services\Dictation;
use App\Services\Llm;
use App\Services\NextStep;
use App\Services\Prompts;
use App\Services\ScriptStructure;
use App\Services\YouTube;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Throwable;

class ApiController extends Controller
{
    // ==========================================
    // AUTHENTICATION & SETUP
    // ==========================================

    public function authStatus(): JsonResponse
    {
        $dbConnected = true;
        $hasUsers = false;
        try {
            $hasUsers = User::count() > 0;
        } catch (Throwable) {
            $dbConnected = false;
            $hasUsers = false;
        }
        $user = $dbConnected ? Auth::user() : null;

        $rawName = Setting::get('nickname') ?: trim(explode(' ', (string) (Setting::get('creator_name') ?: User::orderBy('id')->value('name')))[0]);
        if (empty($rawName) || strcasecmp($rawName, 'Dempsey') === 0) {
            $rawName = 'Delia';
        }

        return response()->json([
            'initialized' => $hasUsers,
            'authenticated' => (bool) $user,
            'db_connected' => $dbConnected,
            'tour_done' => $user ? Setting::has('tour_done') : null,
            // Login screen: her first name and whether love notes are on (nothing else before sign-in).
            'welcome' => $hasUsers && ! $user ? [
                'name' => $rawName,
                'accent' => Setting::get('accent', 'rose'),
                'love' => Setting::get('love_notes', '1') === '1',
                'google' => \App\Http\Controllers\GoogleLoginController::available(),
            ] : null,
            'love' => $user ? [
                'enabled' => Setting::get('love_notes', '1') === '1',
                'name' => $rawName,
                'from' => Setting::get('love_from', 'Dempsey'),
                'custom' => array_values(array_filter(array_map('trim', preg_split('/\R/', (string) Setting::get('love_custom', ''))))),
            ] : null,
            'prefs' => $user ? [
                'nickname' => Setting::get('nickname', ''),
                'accent' => Setting::get('accent', 'rose'),
                'text_size' => Setting::get('text_size', 'default'),
                'density' => Setting::get('density', 'comfortable'),
                'nav_hidden' => json_decode((string) Setting::get('nav_hidden', '[]'), true) ?: [],
                'dashboard_layout' => json_decode((string) Setting::get('dashboard_layout', ''), true) ?: null,
                'motto' => Setting::get('motto', ''),
                'start_page' => Setting::get('start_page', '/'),
                'nav_mode' => Setting::get('nav_mode', 'top'),
                'avatar' => (bool) Setting::get('avatar'),
            ] : null,
            'user' => $user ? [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ] : null,
        ]);
    }

    public function setup(Request $request): JsonResponse
    {
        if (User::count() > 0) {
            return response()->json(['error' => 'Application is already initialized.'], 403);
        }

        $data = $request->validate([
            'name' => 'required|string|max:100',
            'email' => 'required|email|max:200',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
        ]);

        if (! Setting::has('creator_name')) {
            Setting::put('creator_name', $data['name']);
        }

        Auth::login($user, true);
        $request->session()->regenerate();

        return response()->json([
            'message' => "Welcome, {$user->name}!",
            'user' => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email],
        ]);
    }

    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'email' => 'required|email|max:200|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $isFirst = User::count() === 0;

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
        ]);

        if ($isFirst || ! Setting::has('creator_name')) {
            Setting::put('creator_name', $data['name']);
        }

        Auth::login($user, true);
        $request->session()->regenerate();

        return response()->json([
            'message' => "Welcome to Creator Studio, {$user->name}!",
            'user' => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email],
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $creds = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);
        $remember = $request->boolean('remember', true);

        // Brute-force guard, after MagicAI's Helpers\RateLimiter (attempts per identifier,
        // cached with a decay window): 5 wrong passwords per email+IP locks login for 15 minutes.
        $key = 'login:'.strtolower($creds['email']).'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            $minutes = (int) ceil(RateLimiter::availableIn($key) / 60);

            return response()->json(['error' => "Too many attempts. Try again in {$minutes} minute(s)."], 429);
        }

        if (! Auth::attempt($creds, $remember)) {
            RateLimiter::hit($key, 15 * 60);

            return response()->json(['error' => 'Incorrect email or password.'], 401);
        }
        RateLimiter::clear($key);

        $request->session()->regenerate();
        $user = Auth::user();

        return response()->json([
            'message' => 'Logged in successfully.',
            'user' => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['message' => 'Logged out.']);
    }

    public function forgotPassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => 'required|email',
        ]);

        $email = strtolower(trim($data['email']));
        $user = User::where('email', $email)->first();
        if (! $user) {
            if (User::count() === 1) {
                $user = User::first();
            } else {
                return response()->json(['error' => 'No account found with this email address.'], 404);
            }
        }

        $code = (string) random_int(100000, 999999);

        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $user->email],
            [
                'token' => Hash::make($code),
                'created_at' => now(),
            ]
        );

        return response()->json([
            'ok' => true,
            'message' => "Reset code generated for {$user->email}.",
            'email' => $user->email,
            'code' => $code,
        ]);
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => 'required|email',
            'code' => 'required|string',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $email = strtolower(trim($data['email']));
        $record = DB::table('password_reset_tokens')->where('email', $email)->first();

        if (! $record && User::count() === 1) {
            $onlyUser = User::first();
            $record = DB::table('password_reset_tokens')->where('email', $onlyUser->email)->first();
            if ($record) {
                $email = $onlyUser->email;
            }
        }

        if (! $record) {
            return response()->json(['error' => 'No password reset requested for this account.'], 400);
        }

        if (CarbonImmutable::parse($record->created_at)->addMinutes(60)->isPast()) {
            return response()->json(['error' => 'The reset code has expired. Please request a new one.'], 400);
        }

        $code = trim($data['code']);
        if (! Hash::check($code, $record->token) && $code !== $record->token) {
            return response()->json(['error' => 'Invalid verification code. Please check and try again.'], 422);
        }

        $user = User::where('email', $email)->first();
        if (! $user) {
            return response()->json(['error' => 'User account not found.'], 404);
        }

        $user->forceFill([
            'password' => Hash::make($data['password']),
            'remember_token' => null,
        ])->save();

        DB::table('password_reset_tokens')->where('email', $email)->delete();

        Auth::login($user, true);
        $request->session()->regenerate();

        return response()->json([
            'ok' => true,
            'message' => "Password reset successfully! Welcome back, {$user->name}.",
            'user' => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email],
        ]);
    }

    // ==========================================
    // DASHBOARD
    // ==========================================

    public function dashboard(): JsonResponse
    {
        $weeks = Week::orderBy('number')->get();
        $currentWeek = Week::current();
        $shipped = $weeks->where('status', 'shipped')->count();
        $missed = $weeks->where('status', 'missed')->count();

        // Calculate streak
        $streak = 0;
        foreach ($weeks->where('status', '!=', 'pending')->sortByDesc('number') as $w) {
            if ($w->status !== 'shipped') {
                break;
            }
            $streak++;
        }

        $pickedIdeas = Idea::where('status', 'picked')->latest()->take(6)->get();
        $newIdeasCount = Idea::where('status', 'new')->count();
        $recentScripts = Script::with('deals:id,script_id,brand_name,rate')->latest('updated_at')->take(5)->get();
        $recentOutliers = Outlier::orderByDesc('outlier_score')->take(6)->get();
        $recentNotes = Note::latest()->take(4)->get();

        // Active sponsor deliverables & revenue summary for tight dashboard integration
        $activeDeliverables = [];
        $activeDeals = Deal::with('script:id,title')
            ->whereIn('stage', ['contracted', 'production', 'in_review', 'scheduled'])
            ->get();

        foreach ($activeDeals as $deal) {
            if (! is_array($deal->deliverables)) {
                continue;
            }
            foreach ($deal->deliverables as $deliv) {
                if (($deliv['status'] ?? 'pending') === 'done') {
                    continue;
                }
                $days = null;
                $dueLabel = null;
                if (! empty($deliv['due_date'])) {
                    $dueDate = rescue(fn () => CarbonImmutable::parse($deliv['due_date']), null);
                    if ($dueDate) {
                        $days = (int) now(Setting::get('timezone'))->startOfDay()->diffInDays($dueDate->startOfDay(), false);
                        $dueLabel = match (true) {
                            $days > 1 => "Due in {$days} days",
                            $days === 1 => 'Due tomorrow',
                            $days === 0 => 'Due today',
                            default => abs($days).'d past due',
                        };
                    }
                }
                $activeDeliverables[] = [
                    'deal_id' => $deal->id,
                    'brand_name' => $deal->brand_name,
                    'platform' => $deliv['platform'] ?? 'video',
                    'format' => $deliv['format'] ?? 'deliverable',
                    'rate' => $deal->rate,
                    'currency' => $deal->currency ?: 'USD',
                    'stage' => $deal->stage,
                    'due_date' => $deliv['due_date'] ?? null,
                    'days_until' => $days,
                    'due_label' => $dueLabel,
                    'script_id' => $deal->script_id,
                    'script_title' => $deal->script?->title,
                ];
            }
        }
        usort($activeDeliverables, function ($a, $b) {
            if ($a['days_until'] === null && $b['days_until'] === null) {
                return 0;
            }
            if ($a['days_until'] === null) {
                return 1;
            }
            if ($b['days_until'] === null) {
                return -1;
            }

            return $a['days_until'] <=> $b['days_until'];
        });

        // Pending AI agent drafts awaiting review
        $pendingAgentPosts = SocialPost::whereNotNull('social_agent_id')
            ->where('approval', 'pending')
            ->with('socialAgent:id,name')
            ->latest('created_at')
            ->take(5)
            ->get();

        $agentApprovals = [
            'total' => SocialPost::whereNotNull('social_agent_id')->where('approval', 'pending')->count(),
            'items' => $pendingAgentPosts->map(fn (SocialPost $p) => [
                'id' => $p->id,
                'platform' => $p->platform,
                'format' => $p->format,
                'title' => $p->title ?: mb_substr(strip_tags((string) $p->body), 0, 80),
                'scheduled_for' => $p->scheduled_for?->toIso8601String(),
                'agent_name' => $p->socialAgent?->name ?? 'Social Agent',
            ])->values(),
        ];

        $setupChecks = [
            ['key' => 'channel_id', 'label' => 'Your YouTube Channel', 'done' => Setting::has('channel_id')],
            ['key' => 'youtube_api_key', 'label' => 'YouTube Data API Key', 'done' => Setting::has('youtube_api_key')],
            ['key' => 'llm_api_key', 'label' => 'AI API Key (LLM)', 'done' => Llm::configured()],
            ['key' => 'channels', 'label' => 'Competitor Channels Added', 'done' => Channel::exists()],
            ['key' => 'goals', 'label' => '12-Week Video Plan', 'done' => $weeks->isNotEmpty()],
        ];

        return response()->json([
            'creator_name' => Setting::get('creator_name') ?: (Auth::user()?->name ?? 'Creator'),
            'current_week' => $currentWeek,
            'weeks' => $weeks,
            'stats' => [
                'shipped' => $shipped,
                'missed' => $missed,
                'streak' => $streak,
                'new_ideas' => $newIdeasCount,
                'picked_ideas' => $pickedIdeas->count(),
                'breakouts' => Outlier::count(),
            ],
            'setup_checks' => $setupChecks,
            'next_step' => NextStep::current(),
            'social' => \App\Http\Controllers\Api\SocialController::progress(),
            'picked_ideas' => $pickedIdeas,
            'recent_scripts' => $recentScripts,
            'recent_outliers' => $recentOutliers,
            'recent_notes' => $recentNotes,
            'active_deliverables' => array_slice($activeDeliverables, 0, 6),
            'agent_approvals' => $agentApprovals,
            'deals_summary' => [
                'active_count' => $activeDeals->count(),
                'confirmed_revenue' => $activeDeals->sum('rate'),
                'pipeline_total' => Deal::whereNotIn('stage', Deal::NOT_PIPELINE)->sum('rate'),
            ],
            'reach' => rescue(fn () => \App\Http\Controllers\LandingPageController::reach(), null),
            'hero' => rescue(fn () => \App\Domains\Landing\Hero::dashboard(), null),
            'marquee' => rescue(fn () => \App\Domains\Landing\Marquee::dashboard(), null),
            'partners' => rescue(fn () => \App\Domains\Landing\Partners::dashboard(), null),
            'pricing' => rescue(fn () => \App\Domains\Landing\Pricing::dashboard(), null),
            'about' => rescue(fn () => \App\Domains\Landing\About::publicSection(), null),
            'speaking' => rescue(fn () => \App\Http\Controllers\LandingPageController::speaking(), null),
            'journey' => \App\Domains\Landing\ClientJourney::summary(),
            'revenue' => rescue(fn () => \App\Http\Controllers\Api\PipelineController::revenue(), null),
            'follow_ups' => rescue(fn () => \App\Domains\Deals\FollowUps::list(5), []),
            'website' => rescue(fn () => \App\Http\Controllers\LandingPageController::summary(), null),
            'inbound' => [
                'new_inquiries' => Deal::where('stage', 'inquiry')->count(),
                'awaiting_payment' => Deal::whereNotIn('stage', ['inquiry', 'paid'])->where('payment_status', '!=', 'paid')->sum('rate'),
                'paid_this_month' => Deal::where('payment_status', 'paid')->where('paid_at', '>=', now()->startOfMonth())->sum('rate'),
                'latest' => Deal::where('stage', 'inquiry')->latest()->take(4)
                    ->get(['id', 'brand_name', 'contact_name', 'contact_email', 'rate', 'deliverables', 'created_at']),
                // Which landing-page services open inquiries asked about, most asked first.
                'by_service' => Deal::where('stage', 'inquiry')->get(['deliverables'])
                    ->map(fn (Deal $d) => $d->deliverables[0]['service'] ?? null)->filter()
                    ->countBy()->sortDesc()->map(fn ($n, $s) => ['service' => $s, 'count' => $n])->values(),
            ],
        ]);
    }

    // ==========================================
    // CHANNELS & BREAKOUTS
    // ==========================================

    public function getChannels(): JsonResponse
    {
        $channels = Channel::withCount('outliers')->orderBy('handle')->get();
        $outliers = Outlier::with('channel')->orderByDesc('created_at')->orderByDesc('outlier_score')->take(50)->get();

        return response()->json([
            'channels' => $channels,
            'outliers' => $outliers,
            'fresh_count' => Outlier::where('used', false)->count(),
        ]);
    }

    public function storeChannels(Request $request): JsonResponse
    {
        $request->validate(['handles' => 'required|string']);
        $added = 0;
        $items = preg_split('/[\s,]+/', $request->input('handles'), -1, PREG_SPLIT_NO_EMPTY);

        foreach ($items as $raw) {
            $handle = Channel::normalize($raw);
            if ($handle !== '' && ! Channel::where('handle', $handle)->exists()) {
                Channel::create(['handle' => $handle]);
                $added++;
            }
        }

        return response()->json([
            'message' => $added ? "Added {$added} competitor channel(s)." : "Channels already in list.",
            'channels' => Channel::withCount('outliers')->orderBy('handle')->get(),
        ]);
    }

    public function toggleChannel(Channel $channel): JsonResponse
    {
        $channel->update(['active' => ! $channel->active]);

        return response()->json(['channel' => $channel]);
    }

    public function deleteChannel(Channel $channel): JsonResponse
    {
        $channel->delete();

        return response()->json(['message' => "Channel {$channel->handle} deleted."]);
    }

    public function scanBreakouts(BreakoutScanner $scanner): JsonResponse
    {
        try {
            $created = $scanner->scan();
        } catch (Throwable $e) {
            return response()->json(['error' => $e->getMessage()], $e instanceof \RuntimeException && str_starts_with($e->getMessage(), 'Please add') ? 400 : 500);
        }
        Setting::put('last_run_scan', now()->toIso8601String());

        return response()->json([
            'message' => "Scan complete. Found {$created} new breakout video(s).",
            'channels' => Channel::withCount('outliers')->orderBy('handle')->get(),
            'outliers' => Outlier::with('channel')->orderByDesc('created_at')->orderByDesc('outlier_score')->take(50)->get(),
        ]);
    }

    public function deleteOutlier(Outlier $outlier): JsonResponse
    {
        $outlier->delete();

        return response()->json(['message' => 'Outlier dismissed.']);
    }

    // ==========================================
    // IDEAS
    // ==========================================

    public function getIdeas(Request $request): JsonResponse
    {
        $status = $request->query('status');
        $query = Idea::query();

        if ($status && in_array($status, Idea::STATUSES, true)) {
            $query->where('status', $status);
        }

        $ideas = $query->orderByRaw("CASE status WHEN 'picked' THEN 0 WHEN 'new' THEN 1 ELSE 2 END")
            ->latest()
            ->paginate(30);

        return response()->json([
            'ideas' => $ideas,
            'counts' => Idea::selectRaw('status, count(*) as count')->groupBy('status')->pluck('count', 'status'),
            'fresh_signals' => Outlier::where('used', false)->count(),
        ]);
    }

    public function generateIdeas(Request $request, Llm $llm): JsonResponse
    {
        $signals = Outlier::where('used', false)->orderByDesc('outlier_score')->take(10)->get();
        $extra = trim((string) $request->input('extra'));

        $userPrompt = $signals->isEmpty()
            ? "No breakout signals in database today.\n"
            : "Long-form breakout videos (doing 3x+ channel median):\n" . $signals->map->compactLine()->implode("\n") . "\n";

        // News & blog signals from her feeds: timely topics, cited the same way as breakouts.
        $news = FeedItem::with('feed:id,title')->where('used', false)
            ->whereHas('feed', fn ($q) => $q->where('active', true))
            ->where(fn ($q) => $q->whereNull('published_at')->orWhere('published_at', '>=', now()->subDays(14)))
            ->orderByDesc('published_at')->take(12)->get();
        if ($news->isNotEmpty()) {
            $userPrompt .= "\nNews and blog posts in her niche (timely topics; a news idea's why_now must cite one of these URLs):\n"
                . $news->map->compactLine()->implode("\n") . "\n";
        }

        // What her viewers (and competitors' viewers) asked for in the comments: demand that already exists.
        $asks = AudienceInsight::where('used', false)->where('updated_at', '>=', now()->subDays(30))->latest('updated_at')->take(4)->get();
        $askLines = $asks->flatMap->compactLines();
        if ($askLines->isNotEmpty()) {
            $userPrompt .= "\nWhat viewers asked for in comments (her_video=true means her own audience, the strongest signal; cite the url as source_url and the need in why_now):\n"
                .$askLines->implode("\n")."\n";
        }

        // Her own recent winners and duds, if My channel was opened recently (no extra API call).
        $channel = Setting::get('channel_id');
        $report = $channel ? Cache::get(AudienceController::cacheKey($channel)) : null;
        $own = collect($report['videos'] ?? [])->where('early', false)->whereNotNull('score')->sortByDesc('score');
        if ($own->count() >= 3) {
            $fmt = fn ($v) => "- \"{$v['title']}\": {$v['score']}x her median";
            $userPrompt .= "\nHer own videos against her median (lean toward what her audience already rewards):\n"
                .$own->take(3)->map($fmt)->implode("\n")."\n"
                .($own->count() >= 5 ? $own->reverse()->take(2)->map($fmt)->implode("\n")."\n" : '');
        }

        if ($extra !== '') {
            $userPrompt .= "\nPersonal notes/direction from creator:\n{$extra}\n";
        }

        $recent = Idea::latest()->take(15)->pluck('title');
        if ($recent->isNotEmpty()) {
            $userPrompt .= "\nAvoid duplicate premises with recent ideas:\n- " . $recent->implode("\n- ");
        }

        try {
            $data = $llm->as('ideas')->json(Prompts::ideasSystem(), $userPrompt);
        } catch (Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }

        $brief = IdeaBrief::create([
            'summary' => $data['summary'] ?? null,
            'signals' => $signals->toBase()->map->compactLine()->merge($news->toBase()->map->compactLine())->merge($askLines)->implode("\n"),
        ]);

        $createdIdeas = [];
        foreach (array_slice($data['ideas'] ?? [], 0, 5) as $i) {
            if (empty($i['title'])) {
                continue;
            }
            $idea = $brief->ideas()->create(collect($i)->only([
                'title', 'thumbnail_text', 'premise', 'why_now', 'source_url', 'angle', 'format', 'effort', 'shelf_life', 'risk',
            ])->map(fn ($v) => is_scalar($v) ? mb_substr((string) $v, 0, 2000) : null)->all());

            $createdIdeas[] = $idea;
        }

        Outlier::whereIn('id', $signals->pluck('id'))->update(['used' => true]);
        FeedItem::whereIn('id', $news->pluck('id'))->update(['used' => true]);
        AudienceInsight::whereIn('id', $asks->pluck('id'))->update(['used' => true]);

        $sources = array_filter([
            $signals->isNotEmpty() ? $signals->count().' breakout signal(s)' : null,
            $news->isNotEmpty() ? $news->count().' news item(s)' : null,
            $askLines->isNotEmpty() ? $askLines->count().' viewer request(s)' : null,
        ]);

        return response()->json([
            'message' => count($createdIdeas).' new idea(s) generated'.($sources ? ' from '.implode(' and ', $sources).'.' : ' from your niche.'),
            'summary' => $data['summary'] ?? '',
            'created' => $createdIdeas,
        ]);
    }

    public function storeIdea(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title' => 'required|string|max:200',
            'premise' => 'nullable|string|max:2000',
            'notes' => 'nullable|string|max:5000',
            'source_url' => 'nullable|url|max:500',
        ]);

        $idea = Idea::create($data + ['status' => 'picked']);

        return response()->json(['idea' => $idea, 'message' => 'Idea added to Picked list.']);
    }

    public function updateIdea(Request $request, Idea $idea): JsonResponse
    {
        $data = $request->validate([
            'status' => 'sometimes|in:' . implode(',', Idea::STATUSES),
            'title' => 'sometimes|required|string|max:200',
            'thumbnail_text' => 'sometimes|nullable|string|max:200',
            'premise' => 'sometimes|nullable|string|max:2000',
            'angle' => 'sometimes|nullable|string|max:2000',
            'format' => 'sometimes|nullable|string|max:100',
            'effort' => 'sometimes|nullable|string|max:100',
            'shelf_life' => 'sometimes|nullable|string|max:100',
            'risk' => 'sometimes|nullable|string|max:2000',
            'source_url' => 'sometimes|nullable|url|max:500',
            'notes' => 'sometimes|nullable|string|max:5000',
        ]);

        $idea->update($data);

        return response()->json(['idea' => $idea]);
    }

    public function deleteIdea(Idea $idea): JsonResponse
    {
        $idea->delete();

        return response()->json(['message' => 'Idea deleted.']);
    }

    // ==========================================
    // SCRIPTS & AI WORKFLOW
    // ==========================================

    public function getScripts(): JsonResponse
    {
        $scripts = Script::with('idea')->latest('updated_at')->paginate(30);

        return response()->json(['scripts' => $scripts]);
    }

    public function createScript(Request $request, Llm $llm): JsonResponse
    {
        $data = $request->validate([
            'idea_id' => 'nullable|exists:ideas,id',
            'title' => 'nullable|string|max:200',
            'blank' => 'nullable|boolean',
        ]);

        $idea = isset($data['idea_id']) ? Idea::find($data['idea_id']) : null;
        $title = $data['title'] ?? $idea?->title ?? 'Untitled Script';
        $body = '';

        if ($idea && ! ($data['blank'] ?? false)) {
            try {
                $body = $llm->as('script')->chat(Prompts::scriptSystem(), "The greenlit video idea:\n\n" . $idea->asMarkdown());
            } catch (Throwable $e) {
                return response()->json(['error' => 'AI draft generation error: ' . $e->getMessage()], 500);
            }
        }

        $script = Script::create([
            'idea_id' => $idea?->id,
            'title' => $title,
            'body' => $body,
        ]);

        $idea?->update(['status' => 'scripted']);

        return response()->json([
            'script' => $script,
            'message' => $body ? 'Script drafted with full formatting!' : 'Blank script created.',
        ]);
    }

    public function getScript(Script $script): JsonResponse
    {
        $script->load(['idea', 'deals']);
        $findings = AiTellsLinter::lint((string) $script->body);

        return response()->json([
            'script' => $script,
            'findings' => $findings,
            'has_hard' => AiTellsLinter::hasHard($findings),
            'word_count' => $script->wordCount(),
            'est_minutes' => $script->minutes(),
            'structure' => ScriptStructure::analyse((string) $script->body),
        ]);
    }

    public function attachDeal(Request $request, Script $script): JsonResponse
    {
        $data = $request->validate([
            'deal_id' => 'required|exists:deals,id',
            'inject_segment' => 'nullable|boolean',
        ]);

        $deal = Deal::findOrFail($data['deal_id']);
        $deal->update([
            'script_id' => $script->id,
            'stage' => in_array($deal->stage, ['inquiry', 'pitching', 'negotiating', 'contracted']) ? 'production' : $deal->stage,
        ]);

        if ($data['inject_segment'] ?? true) {
            $coupon = $deal->coupon_code ? "Use promo code: `{$deal->coupon_code}`" : '';
            $link = $deal->tracking_url ? "Link: {$deal->tracking_url}" : '';
            $points = $deal->key_talking_points ? trim($deal->key_talking_points) : 'Highlight key benefits & developer experience.';

            $segment = "\n\n## [SPONSORED SEGMENT: {$deal->brand_name}]\n"
                . "[VISUAL: {$deal->brand_name} logo & dashboard screencast]\n"
                . "(Sponsor integration: ~60-90s)\n"
                . "*Disclosure: \"This video is sponsored by {$deal->brand_name}.\"*\n\n"
                . "Key points to cover:\n"
                . "{$points}\n\n"
                . "Call to action:\n"
                . trim("{$link}\n{$coupon}")."\n";

            $script->body = trim((string) $script->body).$segment;
            $script->save();
        }

        return response()->json([
            'message' => "Attached {$deal->brand_name} sponsorship to script.",
            'script' => $script->fresh(['idea', 'deals']),
            'deal' => $deal,
        ]);
    }

    public function updateScript(Request $request, Script $script): JsonResponse
    {
        $data = $request->validate([
            'title' => 'sometimes|required|string|max:200',
            'body' => 'sometimes|nullable|string',
            'transcript' => 'sometimes|nullable|string',
            'substack' => 'sometimes|nullable|string',
            'edit_brief' => 'sometimes|nullable|string',
            'publish_kit' => 'sometimes|nullable|string',
            'status' => 'sometimes|in:' . implode(',', Script::STATUSES),
            'youtube_url' => 'sometimes|nullable|url|max:300',
            'published_on' => 'sometimes|nullable|date',
        ]);

        $script->update($data);

        return response()->json(['script' => $script, 'message' => 'Script saved.']);
    }

    public function deleteScript(Script $script): JsonResponse
    {
        $script->delete();

        return response()->json(['message' => 'Script deleted.']);
    }

    public function reviewScript(Script $script, Llm $llm): JsonResponse
    {
        $findings = AiTellsLinter::lint((string) $script->body);
        if (AiTellsLinter::hasHard($findings)) {
            return response()->json([
                'error' => 'Please resolve HARD linter errors (like banned AI cliches or em-dashes) before running AI review.',
                'findings' => $findings,
            ], 422);
        }

        $warns = collect($findings)->map(fn ($f) => "line {$f['line']}: {$f['rule']}: \"{$f['match']}\"")->implode("\n");
        $userPrompt = "Draft script:\n\n{$script->body}" . ($warns ? "\n\nLinter warnings (hints):\n{$warns}" : '');

        try {
            $review = $llm->as('review')->chat(Prompts::reviewSystem(), $userPrompt);
        } catch (Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }

        $script->update([
            'review' => $review,
            'status' => $script->status === 'draft' ? 'reviewed' : $script->status,
        ]);

        return response()->json([
            'review' => $review,
            'script' => $script,
            'message' => 'AI Review completed across Voice, Retention, Facts & Hostile Comments.',
        ]);
    }

    public function substackScript(Script $script, Llm $llm): JsonResponse
    {
        $source = $script->transcript
            ? "Real recording transcript:\n\n{$script->transcript}\n\nPlanned script:\n\n{$script->body}"
            : "Planned script:\n\n{$script->body}";

        try {
            $post = $llm->as('substack')->chat(Prompts::substackSystem(), "Video title: {$script->title}\n\n{$source}");
        } catch (Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }

        $script->update(['substack' => $post]);

        return response()->json(['substack' => $post, 'message' => 'Substack article generated.']);
    }

    public function editBriefScript(Script $script, Llm $llm): JsonResponse
    {
        try {
            $brief = $llm->as('edit_brief')->chat(Prompts::editBriefSystem(), "Title: {$script->title}\n\n{$script->body}");
        } catch (Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }

        $script->update(['edit_brief' => $brief]);

        return response()->json(['edit_brief' => $brief, 'message' => 'Editor brief generated.']);
    }

    public function lintText(Request $request): JsonResponse
    {
        $text = (string) $request->input('text', '');
        $findings = AiTellsLinter::lint($text);

        return response()->json([
            'findings' => $findings,
            'has_hard' => AiTellsLinter::hasHard($findings),
            'word_count' => str_word_count(strip_tags($text)),
            'structure' => ScriptStructure::analyse($text),
        ]);
    }

    // ==========================================
    // GOALS / WEEKLY MENTOR
    // ==========================================

    public function getGoals(): JsonResponse
    {
        $weeks = Week::with('script')->orderBy('number')->get();

        return response()->json([
            'weeks' => $weeks,
            'current' => Week::current(),
            'commitment' => Setting::get('commitment', '1 long-form video per week'),
            'baseline_views' => Setting::get('baseline_views'),
        ]);
    }

    public function planGoals(Request $request): JsonResponse
    {
        $data = $request->validate([
            'start' => 'required|date',
            'count' => 'required|integer|min:1|max:52',
            'replace' => 'nullable|boolean',
        ]);

        if (Week::exists() && ! ($data['replace'] ?? false)) {
            return response()->json(['error' => 'You already have an existing plan. Choose replace to reset.'], 400);
        }

        Week::query()->delete();
        $start = CarbonImmutable::parse($data['start']);

        for ($i = 0; $i < $data['count']; $i++) {
            Week::create([
                'number' => $i + 1,
                'start_date' => $start->addWeeks($i),
                'due_date' => $start->addWeeks($i)->addDays(6),
            ]);
        }

        return response()->json([
            'message' => "Created {$data['count']}-week accountability plan.",
            'weeks' => Week::orderBy('number')->get(),
        ]);
    }

    public function updateGoal(Request $request, Week $week): JsonResponse
    {
        $data = $request->validate([
            'status' => 'sometimes|in:' . implode(',', Week::STATUSES),
            'script_id' => 'sometimes|nullable|exists:scripts,id',
            'video_title' => 'sometimes|nullable|string|max:200',
            'youtube_url' => 'sometimes|nullable|url|max:300',
            'published_on' => 'sometimes|nullable|date',
            'views_7d' => 'sometimes|nullable|integer|min:0',
            'note' => 'sometimes|nullable|string|max:2000',
        ]);

        if (! empty($data['script_id']) && empty($data['video_title'])) {
            $data['video_title'] = Script::find($data['script_id'])->title;
        }

        $week->update($data);

        return response()->json(['week' => $week, 'message' => "Week {$week->number} updated."]);
    }

    public function checkinGoals(WeeklyCheckin $checkin): JsonResponse
    {
        try {
            $result = $checkin->run();
        } catch (\RuntimeException $e) {
            $status = str_starts_with($e->getMessage(), "Couldn't fetch") ? 500 : 400;

            return response()->json(['error' => $e->getMessage()], $status);
        }
        Setting::put('last_run_checkin', now()->toIso8601String());

        return response()->json([
            'message' => 'Check-in complete. Scoreboard updated.',
            'verdict' => $result['verdict'],
            'weeks' => Week::with('script')->orderBy('number')->get(),
        ]);
    }

    // ==========================================
    // FLOW DICTATION
    // ==========================================

    public function dictationToken(Dictation $dictation): JsonResponse
    {
        try {
            return response()->json($dictation->token());
        } catch (Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 502);
        }
    }

    public function polishDictation(Request $request, Llm $llm): JsonResponse
    {
        $data = $request->validate([
            'text' => 'required|string|max:20000',
            'style' => 'nullable|in:' . implode(',', array_keys(Dictation::STYLES)),
            'rewrite' => 'nullable|in:' . implode(',', array_keys(Dictation::REWRITES)),
        ]);

        $prompt = ! empty($data['rewrite'])
            ? Dictation::rewritePrompt($data['rewrite'])
            : Dictation::systemPrompt($data['style'] ?? 'auto');

        try {
            $cleaned = $llm->as('dictation')->chat($prompt, trim($data['text']), 90);

            return response()->json(['polished' => $cleaned]);
        } catch (Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 502);
        }
    }

    public function getNotes(): JsonResponse
    {
        return response()->json(['notes' => Note::latest()->take(30)->get()]);
    }

    public function saveNote(Request $request): JsonResponse
    {
        $data = $request->validate([
            'raw' => 'nullable|string|max:20000',
            'polished' => 'required|string|max:40000',
            'style' => 'nullable|string|max:20',
        ]);

        $note = Note::create(['style' => $data['style'] ?? 'auto'] + $data);

        return response()->json(['note' => $note]);
    }

    public function noteToIdea(Note $note): JsonResponse
    {
        $lines = preg_split('/\R/', trim($note->polished));
        $title = trim(preg_replace('/^[#*\s]+|[*\s]+$/', '', $lines[0] ?? '')) ?: 'Dictated idea';

        $idea = Idea::create([
            'title' => mb_substr($title, 0, 200),
            'notes' => trim(implode("\n", array_slice($lines, 1))) ?: $note->polished,
            'status' => 'picked',
        ]);

        return response()->json([
            'idea' => $idea,
            'message' => 'Dictation converted to a Picked Idea!',
        ]);
    }

    public function deleteNote(Note $note): JsonResponse
    {
        $note->delete();

        return response()->json(['message' => 'Note deleted.']);
    }

    // ==========================================
    // SETTINGS & DIAGNOSTICS
    // ==========================================

    public function getSettings(): JsonResponse
    {
        $settings = [];
        foreach (array_keys(Setting::DEFAULTS) as $k) {
            $settings[$k] = Setting::get($k, '');
        }

        $secrets = [];
        foreach (Setting::SECRET as $k) {
            $secrets[$k] = Setting::has($k);
        }

        return response()->json([
            'settings' => $settings,
            'secrets' => $secrets,
            'engines' => EngineEnum::catalog(),
            'models' => ModelCatalog::forSettings(),
            'current_engine' => app(EngineManager::class)->current()->value,
            'limits' => UsageLimit::summary(),
        ]);
    }

    public function usage(Request $request): JsonResponse
    {
        $days = max(1, min(365, (int) $request->query('days', 30)));

        return response()->json(AiUsage::summary($days));
    }

    public function updateSettings(Request $request): JsonResponse
    {
        $data = $request->all();

        foreach (array_keys(Setting::DEFAULTS) as $k) {
            if (array_key_exists($k, $data)) {
                $val = $data[$k];
                if ($k === 'channel_id' && $val) {
                    $val = Channel::normalize((string) $val);
                }
                Setting::put($k, $val !== '' ? (string) $val : null);
            }
        }

        foreach (Setting::SECRET as $k) {
            if (! empty($data['clear_' . $k])) {
                Setting::put($k, null);
            } elseif (! empty($data[$k])) {
                Setting::put($k, trim((string) $data[$k]));
            }
        }

        return response()->json(['message' => 'Settings saved successfully.']);
    }

    public function testConnections(YouTube $yt, Llm $llm): JsonResponse
    {
        $results = [];

        // YouTube check
        try {
            if (! Setting::has('youtube_api_key')) {
                throw new \RuntimeException('No YouTube API key saved.');
            }
            $channel = Setting::get('channel_id');
            if ($channel) {
                [, $title] = $yt->channelUploads($channel);
                $results['youtube'] = [
                    'ok' => (bool) $title,
                    'message' => $title ? "Connected. Channel found: {$title}" : "Key valid, but channel '{$channel}' not found.",
                ];
            } else {
                $yt->channelUploads('YouTube');
                $results['youtube'] = ['ok' => true, 'message' => 'Key is valid. (Set your channel handle in settings)'];
            }
        } catch (Throwable $e) {
            $results['youtube'] = ['ok' => false, 'message' => $e->getMessage()];
        }

        // LLM check
        try {
            $engine = app(EngineManager::class)->current();
            $model = Setting::get('llm_model') ?: $engine->defaultModel();
            $reply = $llm->as('test')->chat('Reply with the single word OK.', 'Say OK.', 30);
            $results['llm'] = [
                'ok' => true,
                'message' => "Connected to {$engine->label()}, model '{$model}'. Test response: '".mb_substr($reply, 0, 40)."'",
            ];
        } catch (Throwable $e) {
            $results['llm'] = ['ok' => false, 'message' => $e->getMessage()];
        }

        // AssemblyAI check
        try {
            app(Dictation::class)->token();
            $results['assemblyai'] = ['ok' => true, 'message' => 'Connected. Real-time streaming tokens functional.'];
        } catch (Throwable $e) {
            $results['assemblyai'] = ['ok' => false, 'message' => $e->getMessage()];
        }

        return response()->json(['checks' => $results]);
    }
}
