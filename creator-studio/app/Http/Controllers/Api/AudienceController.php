<?php

namespace App\Http\Controllers\Api;

use App\Domains\Audience\CommentMiner;
use App\Domains\Audience\Models\AudienceInsight;
use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\YouTube;
use App\Services\YouTubeTranscript;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use RuntimeException;
use Throwable;

/** My channel: her videos against her own median, and what her viewers ask for in the comments. */
class AudienceController extends Controller
{
    public const CACHE_MINUTES = 30;

    public function index(Request $request, YouTube $yt): JsonResponse
    {
        $channel = Setting::get('channel_id');
        $report = null;
        $error = null;

        if (! $channel || ! Setting::has('youtube_api_key')) {
            $error = ! $channel ? 'Add your channel handle or ID in Settings.' : 'Add a YouTube Data API key in Settings.';
        } else {
            $key = self::cacheKey($channel);
            if ($request->boolean('refresh')) {
                Cache::forget($key);
            }
            try {
                $report = Cache::remember($key, now()->addMinutes(self::CACHE_MINUTES), fn () => $yt->channelReport($channel) + ['fetched_at' => now()->toIso8601String()]);
            } catch (Throwable $e) {
                $error = $e->getMessage();
            }
        }

        return response()->json([
            'channel' => $channel,
            'report' => $report,
            'error' => $error,
            'insights' => AudienceInsight::latest('updated_at')->take(30)->get(),
        ]);
    }

    /** Read a video's comments (hers, or any video link) and keep what viewers asked for. */
    public function comments(Request $request, CommentMiner $miner): JsonResponse
    {
        $data = $request->validate(['url' => 'required|string|max:500', 'title' => 'nullable|string|max:250']);
        $videoId = YouTubeTranscript::videoId($data['url']);
        if (! $videoId) {
            return response()->json(['error' => 'That doesn’t look like a YouTube video link.'], 422);
        }
        if (! Setting::has('youtube_api_key')) {
            return response()->json(['error' => 'Reading comments needs a YouTube Data API key in Settings.'], 422);
        }

        $channel = Setting::get('channel_id');
        $report = $channel ? Cache::get(self::cacheKey($channel)) : null;
        $own = collect($report['videos'] ?? [])->contains('id', $videoId);

        try {
            $insight = $miner->mine($videoId, $data['title'] ?? null, $own);
        } catch (RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }

        $n = count($insight->items ?? []);

        return response()->json([
            'insight' => $insight,
            'message' => $n ? "Found {$n} thing(s) viewers want in {$insight->comment_count} comments." : 'Read the comments. Nothing worth a video stood out.',
        ]);
    }

    public function destroy(AudienceInsight $insight): JsonResponse
    {
        $insight->delete();

        return response()->json(['message' => 'Removed.']);
    }

    public static function cacheKey(string $channel): string
    {
        return 'audience:report:'.strtolower($channel);
    }
}
