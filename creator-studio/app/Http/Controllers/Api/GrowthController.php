<?php

namespace App\Http\Controllers\Api;

use App\Domains\Social\DailyBrief;
use App\Domains\Social\Platforms;
use App\Domains\Social\PostScorer;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Throwable;

/** Post score and hook lab, the daily brief, and follower goals. */
class GrowthController extends Controller
{
    public function score(Request $request, PostScorer $scorer): JsonResponse
    {
        return response()->json($scorer->score($this->draft($request)));
    }

    public function hooks(Request $request, PostScorer $scorer): JsonResponse
    {
        @set_time_limit(120);
        try {
            return response()->json($scorer->hooks($this->draft($request)));
        } catch (Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    /** "Ask your numbers": a plain-language answer from her own data only. */
    public function ask(Request $request, \App\Services\Llm $llm): JsonResponse
    {
        $q = $request->validate(['question' => ['required', 'string', 'min:3', 'max:500']])['question'];
        if (! \App\Services\Llm::configured()) {
            return response()->json(['error' => 'Add an AI key in Settings first.'], 422);
        }
        $pack = [
            'last_30_days' => \App\Domains\Social\SocialDashboard::build(30),
            'per_network_4_weeks' => \App\Domains\Social\SocialAnalytics::overview(4),
            'followers' => \App\Domains\Social\SocialDashboard::followers(),
            'goals' => DailyBrief::goals(),
            'best_times' => collect(array_keys(Platforms::PLATFORMS))->mapWithKeys(fn ($p) => [$p => \App\Domains\Social\SocialAnalytics::bestTimes($p)])->all(),
            'top_posts' => \App\Domains\Social\Models\SocialPost::where('status', 'posted')->get()->filter(fn ($p) => $p->engagement)->sortByDesc(fn ($p) => $p->engagement['total'])->take(15)
                ->map(fn ($p) => ['platform' => $p->platform, 'format' => $p->format, 'pillar' => $p->pillar, 'posted' => $p->posted_at?->toDateString(),
                    'text' => mb_substr(str_replace("\n", ' ', $p->asText()), 0, 160), 'metrics' => $p->metrics, 'engagement' => $p->engagement])->values(),
        ];
        try {
            $answer = $llm->as('ask-numbers')->chat(
                'You answer questions about '.\App\Services\Prompts::name().'’s social media results using ONLY the JSON data given. '
                .'Quote the actual numbers. If the data can’t answer it, say what’s missing and how she can get it (connect the network, import numbers, post more). '
                .'Short: 2 to 6 sentences or a few bullets, then one concrete suggestion. Markdown allowed. No invented benchmarks.',
                "Data:\n".mb_substr(json_encode($pack, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR), 0, 60000)."\n\nQuestion: {$q}",
            );
        } catch (Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json(['answer' => trim($answer)]);
    }

    public function brief(): JsonResponse
    {
        return response()->json(DailyBrief::build());
    }

    public function goals(): JsonResponse
    {
        return response()->json(['goals' => DailyBrief::goals()]);
    }

    public function saveGoals(Request $request): JsonResponse
    {
        $data = $request->validate([
            'goals' => ['present', 'array', 'max:6'],
            'goals.*.platform' => ['required', Rule::in(array_keys(Platforms::PLATFORMS))],
            'goals.*.target' => ['required', 'integer', 'min:1', 'max:1000000000'],
            'goals.*.by' => ['required', 'date', 'after:today'],
        ]);
        DailyBrief::saveGoals(collect($data['goals'])->mapWithKeys(fn ($g) => [$g['platform'] => ['target' => (int) $g['target'], 'by' => substr($g['by'], 0, 10)]])->all());

        return response()->json(['goals' => DailyBrief::goals()]);
    }

    private function draft(Request $request): array
    {
        return $request->validate([
            'platform' => ['required', Rule::in(array_keys(Platforms::PLATFORMS))],
            'format' => ['nullable', 'string', 'max:30'],
            'body' => ['nullable', 'string', 'max:30000'],
            'parts' => ['nullable', 'array', 'max:40'],
            'parts.*' => ['nullable', 'string', 'max:5000'],
            'scheduled_for' => ['nullable', 'date'],
            'pillar' => ['nullable', 'string', 'max:120'],
            'media' => ['nullable', 'integer', 'min:0'],
            'post_id' => ['nullable', 'integer'],
        ]);
    }
}
