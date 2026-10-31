<?php

namespace App\Http\Controllers\Api;

use App\Domains\Social\DemoContent;
use App\Domains\Social\Models\SocialPost;
use App\Domains\Social\Platforms;
use App\Domains\Social\Repurposer;
use App\Domains\Toolkit\Models\Document;
use App\Http\Controllers\Controller;
use App\Models\Note;
use App\Models\Script;
use App\Models\Setting;
use App\Models\Week;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use RuntimeException;
use Throwable;

/** Social: posts for LinkedIn, Instagram, X and YouTube, the calendar, repurposing and her numbers. */
class SocialController extends Controller
{
    public function meta(): JsonResponse
    {
        return response()->json([
            'platforms' => Platforms::catalog(), 'statuses' => Platforms::STATUSES, 'progress' => self::progress(),
            'brand_color' => Setting::get('brand_color', '#d8487e'),
            'demo' => DemoContent::count(),
            'pillars' => Repurposer::pillars(),
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $q = SocialPost::query()->with('script:id,title');
        if ($p = $request->query('platform')) {
            $q->where('platform', $p);
        }
        if ($s = $request->query('status')) {
            $q->where('status', $s);
        }

        return response()->json([
            'posts' => $q->orderByRaw("CASE status WHEN 'scheduled' THEN 0 WHEN 'draft' THEN 1 WHEN 'idea' THEN 2 ELSE 3 END")
                ->orderByRaw('COALESCE(scheduled_for, posted_at, updated_at) DESC')->take(150)->get(),
            'counts' => SocialPost::query()->when($request->query('platform'), fn ($q, $p) => $q->where('platform', $p))
                ->selectRaw('status, count(*) as count')->groupBy('status')->pluck('count', 'status'),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request, true);
        $post = SocialPost::create($data + ['status' => 'draft', 'source' => 'manual']);

        return response()->json(['post' => $post, 'checks' => self::checks($post)]);
    }

    public function show(SocialPost $post): JsonResponse
    {
        return response()->json(['post' => $post->load('script:id,title'), 'checks' => self::checks($post)]);
    }

    public function update(Request $request, SocialPost $post): JsonResponse
    {
        $post->update($this->validated($request, false, $post));

        return response()->json(['post' => $post->fresh()->load('script:id,title'), 'checks' => self::checks($post)]);
    }

    public function destroy(SocialPost $post): JsonResponse
    {
        $post->delete();

        return response()->json(['message' => 'Post deleted.']);
    }

    /** A copy for another platform/format (the text is kept; she rewrites it there). */
    public function duplicate(Request $request, SocialPost $post): JsonResponse
    {
        $data = $request->validate(['platform' => ['required', Rule::in(array_keys(Platforms::PLATFORMS))], 'format' => 'required|string']);
        if (! Platforms::valid($data['platform'], $data['format'])) {
            return response()->json(['error' => 'That format doesn’t exist on that platform.'], 422);
        }
        $copy = $post->replicate(['status', 'scheduled_for', 'posted_at', 'url', 'metrics']);
        $copy->fill($data + ['status' => 'draft', 'source' => 'manual']);
        if (Platforms::FORMATS[$data['platform']][$data['format']][1] === null && $copy->parts) {
            $copy->body = trim(implode("\n\n", array_filter([$copy->body, implode("\n\n", $copy->parts)])));
            $copy->parts = [];
        }
        $copy->save();

        return response()->json(['post' => $copy->fresh()]);
    }

    /** Live checks while she types (nothing saved). */
    public function check(Request $request): JsonResponse
    {
        $data = $request->validate([
            'platform' => ['required', Rule::in(array_keys(Platforms::PLATFORMS))], 'format' => 'required|string',
            'body' => 'nullable|string|max:60000', 'parts' => 'nullable|array|max:40', 'parts.*' => 'nullable|string|max:30000',
        ]);
        if (! Platforms::valid($data['platform'], $data['format'])) {
            return response()->json(['error' => 'Unknown format.'], 422);
        }

        return response()->json(['checks' => Platforms::check($data['platform'], $data['format'], $data['body'] ?? '', $data['parts'] ?? [])]);
    }

    public function repurpose(Request $request, Repurposer $repurposer): JsonResponse
    {
        $data = $request->validate([
            'source' => 'required|in:script,transcript,document,note,text',
            'id' => 'nullable|integer',
            'text' => 'nullable|string|max:60000',
            'title' => 'nullable|string|max:200',
            'extra' => 'nullable|string|max:1000',
            'targets' => 'required|array|min:1|max:10',
            'targets.*.platform' => 'required|string',
            'targets.*.format' => 'required|string',
        ]);

        $scriptId = null;
        [$title, $text] = match ($data['source']) {
            'script', 'transcript' => (function () use ($data, &$scriptId) {
                $s = Script::findOrFail($data['id'] ?? 0);
                $scriptId = $s->id;
                $text = $data['source'] === 'transcript' ? ($s->transcript ?: $s->body) : $s->body;

                return [$s->title, (string) $text];
            })(),
            'document' => (fn ($d) => [$d->title, (string) $d->content])(Document::findOrFail($data['id'] ?? 0)),
            'note' => (fn ($n) => [mb_substr(strtok(trim($n->polished), "\n") ?: 'Dictated note', 0, 120), (string) $n->polished])(Note::findOrFail($data['id'] ?? 0)),
            'text' => [$data['title'] ?? 'My notes', (string) ($data['text'] ?? '')],
        };

        try {
            $posts = $repurposer->run($title, $text, $data['targets'], $scriptId, trim((string) ($data['extra'] ?? '')));
        } catch (RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }

        return response()->json([
            'posts' => collect($posts)->map(fn ($p) => $p->toArray() + ['checks' => self::checks($p)]),
            'message' => count($posts).' draft(s) ready.',
        ]);
    }

    /** Load example posts for every network (no-op if they're already there). */
    public function demo(): JsonResponse
    {
        $n = DemoContent::seed();

        return response()->json(['message' => $n ? "Added {$n} demo posts across your networks." : 'Demo posts are already loaded.', 'demo' => DemoContent::count()]);
    }

    public function removeDemo(): JsonResponse
    {
        $n = DemoContent::remove();

        return response()->json(['message' => "Removed {$n} demo post(s).", 'demo' => 0]);
    }

    /** What the dashboard composer shows: the model in use and whether an AI key is set. */
    public function composerMeta(): JsonResponse
    {
        $engine = app(\App\Domains\Engine\EngineManager::class)->current();

        return response()->json([
            'model' => Setting::get('llm_model') ?: $engine->defaultModel(),
            'engine' => $engine->label(),
            'ai_ready' => \App\Services\Llm::configured(),
            'targets' => ['linkedin.post', 'x.thread', 'instagram.carousel', 'tiktok.video', 'threads.post'],
        ]);
    }

    /**
     * The dashboard composer: a short prompt (plus optional images, described by a vision
     * model) into native drafts for each network she picked.
     */
    public function compose(Request $request, Repurposer $repurposer, \App\Services\Llm $llm): JsonResponse
    {
        $data = $request->validate([
            'prompt' => 'required|string|min:3|max:40000',
            'extra' => 'nullable|string|max:2000',
            'targets' => 'required|array|min:1|max:12',
            'targets.*.platform' => 'required|string',
            'targets.*.format' => 'required|string',
            'images' => 'nullable|array|max:4',
            'images.*' => 'string|max:7000000',
            'status' => 'nullable|in:draft,idea',
        ]);
        $prompt = trim($data['prompt']);
        $source = "What she wants to post about:\n{$prompt}";
        $note = null;
        $images = [];
        foreach ($data['images'] ?? [] as $uri) {
            if (preg_match('#^data:(image/(?:png|jpe?g|webp|gif));base64,(.+)$#s', $uri, $m)) {
                $images[] = ['mime' => $m[1], 'data' => $m[2]];
            }
        }
        if ($images) {
            try {
                $desc = $llm->as('compose_vision')->see('Describe each image in one or two plain sentences for someone writing a social post about it: what is shown, any readable text, the mood. No guesses about who people are.', 'Describe these images.', $images, 120);
                $source .= "\n\nImages she attached (to post with it):\n{$desc}";
            } catch (Throwable $e) {
                $note = 'The images weren’t described ('.$e->getMessage().'), so the drafts are written from your text only.';
            }
        }

        try {
            $posts = $repurposer->run(mb_substr(strtok($prompt, "\n") ?: 'Composer', 0, 80), $source, $data['targets'], null, trim((string) ($data['extra'] ?? '')), 3);
            if (($data['status'] ?? 'draft') === 'idea') {
                foreach ($posts as $p) {
                    $p->update(['status' => 'idea']);
                }
            }
        } catch (RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }

        return response()->json([
            'posts' => collect($posts)->map(fn ($p) => $p->toArray() + ['checks' => self::checks($p)]),
            'message' => count($posts).' draft(s) ready.',
            'note' => $note,
        ]);
    }

    /** Posts in a time range (by scheduled or posted time), unscheduled drafts, and her YouTube dates. */
    public function calendar(Request $request): JsonResponse
    {
        $data = $request->validate(['from' => 'required|date', 'to' => 'required|date|after:from']);
        $from = CarbonImmutable::parse($data['from'])->utc();
        $to = CarbonImmutable::parse($data['to'])->utc();

        $posts = SocialPost::query()
            ->where(fn ($q) => $q->whereBetween('scheduled_for', [$from, $to])->orWhereBetween('posted_at', [$from, $to]))
            ->get();
        $unscheduled = SocialPost::whereIn('status', ['draft', 'idea'])->whereNull('scheduled_for')->latest('updated_at')->take(40)->get();

        $youtube = collect();
        foreach (Week::with('script:id,title,status')->whereBetween('due_date', [$from->toDateString(), $to->toDateString()])->get() as $w) {
            $youtube->push(['kind' => 'due', 'date' => $w->due_date->toDateString(), 'title' => $w->video_title ?: "Week {$w->number} video", 'status' => $w->status, 'link' => $w->script_id ? "/scripts/{$w->script_id}" : '/goals']);
        }
        foreach (Script::whereBetween('published_on', [$from->toDateString(), $to->toDateString()])->get(['id', 'title', 'published_on']) as $s) {
            $youtube->push(['kind' => 'published', 'date' => $s->published_on->toDateString(), 'title' => $s->title, 'status' => 'published', 'link' => "/scripts/{$s->id}"]);
        }

        return response()->json(['posts' => $posts, 'unscheduled' => $unscheduled, 'youtube' => $youtube->values(), 'progress' => self::progress()]);
    }

    /** Her logged numbers for one platform: best posts and the last 30 days. */
    public function stats(Request $request): JsonResponse
    {
        $platform = $request->query('platform');
        $posted = SocialPost::where('status', 'posted')->when($platform, fn ($q, $p) => $q->where('platform', $p))
            ->where('posted_at', '>=', now()->subDays(90))->latest('posted_at')->get();
        $withNumbers = $posted->filter(fn ($p) => $p->engagement !== null);
        $rates = $withNumbers->pluck('engagement.rate')->filter(fn ($r) => $r !== null);
        $byFormat = $withNumbers->groupBy('format')->map(fn ($g) => ['posts' => $g->count(), 'avg_engagement' => (int) round($g->avg('engagement.total'))]);

        return response()->json([
            'posted_30d' => $posted->where('posted_at', '>=', now()->subDays(30))->count(),
            'logged' => $withNumbers->count(),
            'avg_rate' => $rates->isNotEmpty() ? round($rates->avg(), 2) : null,
            'by_format' => $byFormat,
            'top' => $withNumbers->sortByDesc('engagement.total')->take(5)->values(),
            'missing_numbers' => $posted->filter(fn ($p) => $p->engagement === null && $p->posted_at->lte(now()->subDay()))->count(),
        ]);
    }

    /** This week (Monday to Sunday, her timezone): posted or scheduled per platform against her targets. */
    public static function progress(): array
    {
        $tz = Setting::get('timezone', 'Europe/London');
        $start = CarbonImmutable::now($tz)->startOfWeek()->utc();
        $end = $start->addWeek();
        $out = [];
        foreach (array_keys(Platforms::PLATFORMS) as $p) {
            $target = (int) Setting::get("cadence_{$p}", '0');
            $q = SocialPost::where('platform', $p);
            $posted = (clone $q)->where('status', 'posted')->whereBetween('posted_at', [$start, $end])->count();
            $scheduled = (clone $q)->where('status', 'scheduled')->whereBetween('scheduled_for', [$start, $end])->count();
            $out[$p] = ['label' => Platforms::PLATFORMS[$p]['label'], 'target' => $target, 'posted' => $posted, 'scheduled' => $scheduled];
        }

        return $out;
    }

    public static function checks(SocialPost $post): array
    {
        return Platforms::check($post->platform, $post->format, $post->body, $post->parts ?? []);
    }

    private function validated(Request $request, bool $creating, ?SocialPost $post = null): array
    {
        $req = $creating ? 'required' : 'sometimes';
        $data = $request->validate([
            'platform' => [$req, Rule::in(array_keys(Platforms::PLATFORMS))],
            'format' => "{$req}|string|max:30",
            'title' => 'sometimes|nullable|string|max:200',
            'body' => 'sometimes|nullable|string|max:60000',
            'parts' => 'sometimes|nullable|array|max:40',
            'parts.*' => 'nullable|string|max:30000',
            'status' => ['sometimes', Rule::in(Platforms::STATUSES)],
            'scheduled_for' => 'sometimes|nullable|date',
            'posted_at' => 'sometimes|nullable|date',
            'url' => 'sometimes|nullable|url|max:500',
            'script_id' => 'sometimes|nullable|exists:scripts,id',
            'metrics' => 'sometimes|nullable|array',
            'metrics.*' => 'nullable|integer|min:0',
            'notes' => 'sometimes|nullable|string|max:5000',
            'pillar' => 'sometimes|nullable|string|max:80',
        ]);
        $platform = $data['platform'] ?? $post?->platform;
        $format = $data['format'] ?? $post?->format;
        if (! Platforms::valid((string) $platform, (string) $format)) {
            abort(response()->json(['error' => 'That format doesn’t exist on that platform.'], 422));
        }
        if (array_key_exists('parts', $data)) {
            $data['parts'] = array_values(array_map(fn ($p) => (string) $p, array_filter($data['parts'] ?? [], fn ($p) => $p !== null)));
        }
        if (array_key_exists('metrics', $data)) {
            $data['metrics'] = array_intersect_key($data['metrics'] ?? [], array_flip(SocialPost::METRICS)) ?: null;
        }
        foreach (['scheduled_for', 'posted_at'] as $k) {
            if (! empty($data[$k])) {
                $data[$k] = CarbonImmutable::parse($data[$k])->utc();
            }
        }

        return $data;
    }
}
