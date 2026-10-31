<?php

namespace App\Http\Controllers;

use App\Domains\Engine\EngineManager;
use App\Domains\Engine\Enums\EngineEnum;
use App\Domains\Usage\AiUsage;
use App\Models\Channel;
use App\Models\Idea;
use App\Models\IdeaBrief;
use App\Models\Note;
use App\Models\Outlier;
use App\Models\Script;
use App\Models\Setting;
use App\Models\User;
use App\Models\Week;
use App\Services\AiTellsLinter;
use App\Services\Dictation;
use App\Services\Llm;
use App\Services\Prompts;
use App\Services\YouTube;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Throwable;

class ApiController extends Controller
{
    // ==========================================
    // AUTHENTICATION & SETUP
    // ==========================================

    public function authStatus(): JsonResponse
    {
        $hasUsers = User::count() > 0;
        $user = Auth::user();

        return response()->json([
            'initialized' => $hasUsers,
            'authenticated' => (bool) $user,
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

    public function login(Request $request): JsonResponse
    {
        $creds = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if (! Auth::attempt($creds, true)) {
            return response()->json(['error' => 'Incorrect email or password.'], 401);
        }

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
        $recentScripts = Script::latest('updated_at')->take(5)->get();
        $recentOutliers = Outlier::orderByDesc('outlier_score')->take(6)->get();
        $recentNotes = Note::latest()->take(4)->get();

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
            'picked_ideas' => $pickedIdeas,
            'recent_scripts' => $recentScripts,
            'recent_outliers' => $recentOutliers,
            'recent_notes' => $recentNotes,
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

    public function scanBreakouts(YouTube $yt): JsonResponse
    {
        $channels = Channel::where('active', true)->get();
        if ($channels->isEmpty()) {
            return response()->json(['error' => 'Please add active competitor channels first.'], 400);
        }

        @set_time_limit(300);

        try {
            $out = $yt->findOutliers($channels->pluck('handle')->all(), [
                'lookback_days' => Setting::get('lookback_days', '4'),
                'min_outlier_score' => Setting::get('min_outlier_score', '3'),
                'min_views' => Setting::get('min_views', '20000'),
            ], null, Outlier::pluck('video_id')->all());
        } catch (Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }

        $byHandle = $channels->keyBy('handle');
        foreach ($out['channels'] as $handle => $info) {
            $ch = $byHandle->get($handle);
            $ch?->update([
                'title' => $info['title'] ?? $ch->title,
                'last_error' => $info['error'],
                'last_scanned_at' => now(),
            ]);
        }

        $created = 0;
        foreach ($out['results'] as $v) {
            Outlier::create([
                'video_id' => $v['id'],
                'channel_id' => $byHandle->get($v['handle'])?->id,
                'channel_title' => $v['channel'],
                'title' => $v['title'],
                'url' => $v['url'],
                'views' => $v['views'],
                'channel_median' => (int) $v['channel_median'],
                'outlier_score' => $v['outlier_score'],
                'age_days' => round($v['age_days'], 1),
                'published_at' => CarbonImmutable::parse($v['published_at']),
            ]);
            $created++;
        }

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
            'signals' => $signals->map->compactLine()->implode("\n"),
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

        return response()->json([
            'message' => count($createdIdeas).' new idea(s) generated'.($signals->isEmpty() ? ' from your niche.' : ' from '.$signals->count().' breakout signal(s).'),
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
        $script->load('idea');
        $findings = AiTellsLinter::lint((string) $script->body);

        return response()->json([
            'script' => $script,
            'findings' => $findings,
            'has_hard' => AiTellsLinter::hasHard($findings),
            'word_count' => $script->wordCount(),
            'est_minutes' => $script->minutes(),
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

    public function checkinGoals(YouTube $yt, Llm $llm): JsonResponse
    {
        $weeks = Week::orderBy('number')->get();
        if ($weeks->isEmpty()) {
            return response()->json(['error' => 'Set up your weekly plan first.'], 400);
        }

        $channel = Setting::get('channel_id');
        if (! $channel) {
            return response()->json(['error' => 'Add your channel handle or ID in Settings.'], 400);
        }

        $tz = Setting::get('timezone', 'Europe/London');
        $today = CarbonImmutable::now($tz)->toDateString();

        try {
            $uploads = collect($yt->uploadsSince($channel, CarbonImmutable::parse($weeks->first()->start_date)->subDays(7)))
                ->where('long_form', true)->values();
        } catch (Throwable $e) {
            return response()->json(['error' => "Couldn't fetch YouTube uploads: {$e->getMessage()}"], 500);
        }

        $claimed = $weeks->pluck('youtube_url')->filter()->all();
        foreach ($weeks as $week) {
            $start = $week->start_date->toDateString();
            $due = $week->due_date->toDateString();

            $video = $uploads->first(function ($v) use ($start, $due, $tz, $claimed, $week) {
                $day = $v['published_at']->setTimezone($tz)->toDateString();

                return $day >= $start && $day <= $due && (! in_array($v['url'], $claimed, true) || $v['url'] === $week->youtube_url);
            });

            if ($video && in_array($week->status, ['pending', 'missed'], true)) {
                $week->fill([
                    'status' => 'shipped',
                    'video_title' => $week->video_title ?: $video['title'],
                    'youtube_url' => $video['url'],
                    'published_on' => $video['published'],
                ]);
                $claimed[] = $video['url'];
            } elseif (! $video && $week->status === 'pending' && $due < $today) {
                $week->status = 'missed';
            }

            if ($week->youtube_url && $week->views_7d === null) {
                $match = $uploads->firstWhere('url', $week->youtube_url);
                if ($match && $match['age_days'] >= 7) {
                    $week->views_7d = $match['views'];
                }
            }
            $week->save();
        }

        $verdict = null;
        $scoredWeek = $weeks->filter(fn ($w) => $w->status !== 'pending')->sortByDesc('number')->first();
        if ($scoredWeek && Llm::configured()) {
            $next = $weeks->firstWhere('number', $scoredWeek->number + 1);
            $board = $weeks->map(fn ($w) => "Week {$w->number} ({$w->start_date->format('M j')} to {$w->due_date->format('M j')}): {$w->status}"
                . ($w->video_title ? ", \"{$w->video_title}\"" : '')
                . ($w->views_7d !== null ? ", {$w->views_7d} views at 7 days" : '')
                . ($w->note ? ", her note: {$w->note}" : ''))->implode("\n");
            $nextLine = $next ? ($next->video_title ? "Next week's video: \"{$next->video_title}\"" : "Next week's video: NOT PICKED YET") : 'This was the final scheduled week.';
            $recent = $uploads->map(fn ($v) => "{$v['published']}: \"{$v['title']}\", {$v['views']} views after {$v['age_days']} days")->implode("\n");

            try {
                $verdict = $llm->as('mentor')->chat(Prompts::mentorSystem(), "Today: {$today}\nScoring week {$scoredWeek->number}.\n\nScoreboard:\n{$board}\n\n{$nextLine}\n\nRecent long-form uploads:\n" . ($recent ?: 'none'));
                $scoredWeek->update(['verdict' => $verdict]);
            } catch (Throwable $e) {
                // Ignore mentor LLM error if scoreboard updated
            }
        }

        return response()->json([
            'message' => 'Check-in complete. Scoreboard updated.',
            'verdict' => $verdict,
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
            'current_engine' => app(EngineManager::class)->current()->value,
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
