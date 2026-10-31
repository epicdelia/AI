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
