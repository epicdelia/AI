<?php

namespace App\Http\Controllers\Api;

use App\Domains\Engine\Stream\StreamService;
use App\Domains\Social\AnalyticsImport;
use App\Domains\Social\Models\SocialPost;
use App\Domains\Social\Platforms;
use App\Domains\Social\SocialAnalytics;
use App\Domains\Social\SocialDashboard;
use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\Llm;
use App\Services\Prompts;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Analytics across platforms, best times, recycling winners, CSV import, bios and the weekly review.
 */
class SocialInsightsController extends Controller
{
    /**
     * What each profile field is called and its limit (third-party guides agree; Threads isn't published).
     */
    public const BIOS = [
        'instagram' => ['Bio', 150],
        'tiktok' => ['Bio', 80],
        'x' => ['Bio', 160],
        'threads' => ['Bio', 150],
        'linkedin' => ['Headline', 220],
        'youtube' => ['Channel description', 1000],
    ];

    public function analytics(Request $request): JsonResponse
    {
        $weeks = in_array((int)$request->query('weeks', 4), [4, 12], true) ? (int)$request->query('weeks', 4) : 4;

        return response()->json(SocialAnalytics::overview($weeks) + [
            'recycle' => SocialAnalytics::recyclable(),
            'review' => Setting::get('social_review'),
            'review_at' => Setting::get('social_review_at'),
        ]);
    }

    /**
     * KPIs, the insight ticker and daily series for the dashboard (all platforms, or one).
     */
    public function dashboard(Request $request): JsonResponse
    {
        $days = in_array((int)$request->query('days', 30), [7, 30, 90], true) ? (int)$request->query('days', 30) : 30;

        return response()->json(SocialDashboard::build($days, $request->query('platform') ?: null));
    }

    /**
     * Today's follower counts she typed in (YouTube is recorded automatically).
     */
    public function followers(Request $request): JsonResponse
    {
        $data = $request->validate([
            'counts' => 'required|array',
            'counts.*' => 'nullable|integer|min:0|max:2000000000',
        ]);
        $n = SocialDashboard::recordFollowers($data['counts']);

        return response()->json([
            'message' => "Saved {$n} follower count(s) for today.",
            'followers' => SocialDashboard::followers(),
        ]);
    }

    public function bestTimes(Request $request): JsonResponse
    {
        $platform = (string)$request->query('platform');
        abort_unless(isset(Platforms::PLATFORMS[$platform]), 404);

        return response()->json(SocialAnalytics::bestTimes($platform));
    }

    /**
     * A new draft from one of her proven posts: same idea, new hook and wording.
     */
    public function freshTake(SocialPost $post, Llm $llm): JsonResponse
    {
        $source = $post->asText();
        if (trim($source) === '') {
            return response()->json([
                'error' => 'That post has no text to work from.',
            ], 422);
        }
        $label = Platforms::PLATFORMS[$post->platform]['label'];
        $fmt = Platforms::FORMATS[$post->platform][$post->format];
        $e = $post->engagement;

        try {
            $data = $llm->as('fresh_take')->json(
                Prompts::freshTakeSystem(),
                "Platform: {$label}, format: {$fmt[0]}" . ($fmt[1] ? " (with {$fmt[1]})" : '') . "\n"
                . 'Posted ' . $post->posted_at?->diffForHumans() . ($e ? ", {$e['total']} engagements" : '') . "\n\nThe original:\n{$source}"
            );
        } catch (Throwable $err) {
            return response()->json([
                'error' => $err->getMessage(),
            ], 502);
        }
        $parts = $fmt[1] ? array_values(array_filter(array_map(fn ($x) => is_scalar($x) ? trim((string)$x) : '', (array)($data['parts'] ?? [])))) : [];
        $copy = SocialPost::create([
            'platform' => $post->platform,
            'format' => $post->format,
            'title' => mb_substr(trim((string)($data['title'] ?? '')) ?: 'Fresh take: ' . ($post->title ?: 'top post'), 0, 200),
            'body' => is_scalar($data['body'] ?? null) ? trim((string)$data['body']) : '',
            'parts' => array_slice($parts, 0, 25),
            'status' => 'draft',
            'source' => 'recycle',
            'source_post_id' => $post->id,
            'script_id' => $post->script_id,
            'notes' => is_scalar($data['why'] ?? null) ? mb_substr((string)$data['why'], 0, 500) : null,
        ]);

        return response()->json([
            'post' => $copy,
            'message' => 'Fresh take saved as a draft.',
        ]);
    }

    /**
     * CSV analytics import. Without `mapping` it previews (headers, sample rows, guessed columns).
     */
    public function import(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|max:10240',
            'platform' => ['required', Rule::in(array_keys(Platforms::PLATFORMS))],
            'mapping' => 'nullable|string|max:2000',
        ]);

        try {
            $csv = AnalyticsImport::read($request->file('file')->getRealPath());
        } catch (RuntimeException $e) {
            return response()->json([
                'error' => $e->getMessage(),
            ], 422);
        }
        if (!$request->filled('mapping')) {
            return response()->json([
                'headers' => $csv['headers'],
                'sample' => array_slice($csv['rows'], 0, 5),
                'rows' => count($csv['rows']),
                'guess' => AnalyticsImport::guess($csv['headers']),
                'fields' => AnalyticsImport::FIELDS,
            ]);
        }
        $map = json_decode($request->input('mapping'), true);
        if (!is_array($map) || (($map['date'] ?? null) === null && ($map['url'] ?? null) === null)) {
            return response()->json([
                'error' => 'Choose the column with the date or the post link, so each row can be matched.',
            ], 422);
        }
        $r = AnalyticsImport::import($request->input('platform'), $csv['rows'], $map, SocialAnalytics::tz());

        return response()->json($r + [
            'message' => "Imported: {$r['updated']} post(s) updated, {$r['created']} added, {$r['skipped']} row(s) skipped (no numbers, or no date and link).",
        ]);
    }

    public function bios(): JsonResponse
    {
        $out = [];
        foreach (self::BIOS as $p => [$what, $limit]) {
            $out[$p] = [
                'label' => $p === 'youtube' ? 'YouTube' : Platforms::PLATFORMS[$p]['label'],
                'what' => $what,
                'limit' => $limit,
                'text' => Setting::get("bio_{$p}", ''),
            ];
        }

        return response()->json([
            'bios' => $out,
        ]);
    }

    public function bioOptions(Request $request, Llm $llm): JsonResponse
    {
        $p = $request->validate([
            'platform' => ['required', Rule::in(array_keys(self::BIOS))],
        ])['platform'];
        [$what, $limit] = self::BIOS[$p];

        try {
            $data = $llm->as('bio')->json(Prompts::bioSystem($p === 'x' ? 'X' : ucfirst($p), $what, $limit), "Current {$what}: " . (Setting::get("bio_{$p}") ?: '(none yet)'));
        } catch (Throwable $e) {
            return response()->json([
                'error' => $e->getMessage(),
            ], 502);
        }
        $options = array_values(array_filter(array_map(fn ($o) => is_scalar($o) ? trim((string)$o) : '', (array)($data['options'] ?? []))));

        return response()->json([
            'options' => array_map(fn ($o) => [
                'text' => $o,
                'chars' => mb_strlen($o),
                'fits' => mb_strlen($o) <= $limit,
            ], array_slice($options, 0, 3)),
        ]);
    }

    public function saveBio(Request $request): JsonResponse
    {
        $data = $request->validate([
            'platform' => ['required', Rule::in(array_keys(self::BIOS))],
            'text' => 'nullable|string|max:3000',
        ]);
        Setting::put("bio_{$data['platform']}", trim((string)($data['text'] ?? '')) ?: null);

        return response()->json([
            'message' => 'Saved. Paste it into your profile.',
        ]);
    }

    /**
     * AI read of the last four weeks across platforms, streamed, and kept for next time.
     */
    public function review(Llm $llm, StreamService $sse): JsonResponse|StreamedResponse
    {
        $input = SocialAnalytics::reviewInput();
        if ($input === '') {
            return response()->json([
                'error' => 'Nothing posted in the last four weeks to review yet. Mark posts as posted (or import your analytics) first.',
            ], 422);
        }

        return $sse->respond(function (StreamService $sse) use ($llm, $input) {
            $review = $llm->as('social_review')->stream(Prompts::socialReviewSystem(), [[
                'role' => 'user',
                'content' => $input,
            ]], $sse->chunk(...));
            Setting::put('social_review', $review);
            Setting::put('social_review_at', now()->toIso8601String());

            return [
                'review' => $review,
            ];
        });
    }
}
