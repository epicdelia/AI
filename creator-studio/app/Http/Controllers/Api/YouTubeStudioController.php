<?php

namespace App\Http\Controllers\Api;

use App\Domains\Media\MediaFile;
use App\Domains\Social\Models\SocialAccount;
use App\Domains\YouTube\AbTesting\BayesianAbOptimizer;
use App\Domains\YouTube\PreFlight\PreFlightSuite;
use App\Domains\YouTube\TitleTest;
use App\Domains\YouTube\VideoAbTest;
use App\Domains\YouTube\VideoCheck;
use App\Domains\YouTube\VideoUpload;
use App\Domains\YouTube\YouTubeStudio;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Throwable;

/**
 * Upload to YouTube, Pre-Flight Checks, and Bayesian Multi-Variant Testing.
 */
class YouTubeStudioController extends Controller
{
    /**
     * Browser → studio chunk size (stays under PHP's default post size).
     */
    public const RECEIVE_CHUNK = 4 * 1024 * 1024;

    public function index(): JsonResponse
    {
        $connected = SocialAccount::where('platform', 'youtube')->where('status', 'connected')->exists();

        return response()->json([
            'connected' => $connected,
            'uploads' => VideoUpload::latest()->limit(20)->get(),
            'tests' => TitleTest::latest()->limit(20)->get(),
            'ab_tests' => VideoAbTest::latest()->limit(20)->get(),
            'checks' => VideoCheck::with(['videoUpload', 'deal'])->latest()->limit(20)->get(),
            'thumbnails' => MediaFile::whereIn('kind', ['thumbnail', 'mockup'])->latest()->limit(24)->get(['id', 'title', 'kind', 'mime', 'created_at']),
            'chunk' => self::RECEIVE_CHUNK,
        ]);
    }

    /**
     * Her recent videos, for picking one to title-test or A/B test.
     */
    public function videos(): JsonResponse
    {
        try {
            $token = YouTubeStudio::token();
            $ch = Http::withToken($token)->get('https://www.googleapis.com/youtube/v3/channels', [
                'part' => 'contentDetails',
                'mine' => 'true',
            ])->json('items.0');
            $playlist = $ch['contentDetails']['relatedPlaylists']['uploads'] ?? null;
            $items = $playlist ? Http::withToken($token)->get('https://www.googleapis.com/youtube/v3/playlistItems', [
                'part' => 'snippet',
                'playlistId' => $playlist,
                'maxResults' => 15,
            ])->json('items') ?? [] : [];

            return response()->json([
                'videos' => array_map(fn ($i) => [
                    'id' => $i['snippet']['resourceId']['videoId'] ?? null,
                    'title' => $i['snippet']['title'] ?? '',
                    'published_at' => $i['snippet']['publishedAt'] ?? null,
                    'thumb' => $i['snippet']['thumbnails']['default']['url'] ?? null,
                ], $items),
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    // ==========================================
    // Pre-Flight Quality Guard & Monetization
    // ==========================================

    public function preFlight(Request $request, PreFlightSuite $suite): JsonResponse
    {
        $data = $request->validate([
            'video_upload_id' => ['nullable', 'integer', 'exists:video_uploads,id'],
            'script_id' => ['nullable', 'integer', 'exists:scripts,id'],
            'file_path' => ['nullable', 'string'],
            'title' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:5000'],
            'body' => ['nullable', 'string'],
            'deal_id' => ['nullable', 'integer', 'exists:deals,id'],
            'resolution' => ['nullable', 'string'],
            'aspect_ratio' => ['nullable', 'string'],
            'fps' => ['nullable', 'numeric'],
        ]);

        try {
            $check = $suite->run($data);

            return response()->json([
                'check' => $check->load(['deal', 'videoUpload']),
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    public function getCheck(VideoCheck $check): JsonResponse
    {
        return response()->json([
            'check' => $check->load(['deal', 'videoUpload']),
        ]);
    }

    // ==========================================
    // Bayesian Multi-Variant Testing (A/B/C)
    // ==========================================

    public function abTests(): JsonResponse
    {
        return response()->json([
            'ab_tests' => VideoAbTest::latest()->limit(30)->get(),
        ]);
    }

    public function startAbTest(Request $request, BayesianAbOptimizer $optimizer): JsonResponse
    {
        $data = $request->validate([
            'video_id' => ['required', 'string', 'max:30'],
            'original_title' => ['required', 'string', 'max:100'],
            'original_thumbnail_media_id' => ['nullable', 'integer', 'exists:media_files,id'],
            'variants' => ['required', 'array', 'min:1', 'max:3'],
            'variants.*.title' => ['required', 'string', 'max:100'],
            'variants.*.thumbnail_media_id' => ['nullable', 'integer', 'exists:media_files,id'],
            'variants.*.thumbnail_url' => ['nullable', 'string'],
        ]);

        try {
            $test = $optimizer->startTest(
                $data['video_id'],
                $data['original_title'],
                $data['original_thumbnail_media_id'] ?? null,
                $data['variants']
            );

            return response()->json([
                'ab_test' => $test,
            ], 201);
        } catch (Throwable $e) {
            return response()->json([
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    public function updateAbTestPerformance(Request $request, VideoAbTest $abTest, BayesianAbOptimizer $optimizer): JsonResponse
    {
        $data = $request->validate([
            'observations' => ['required', 'array', 'min:1'],
            'observations.*.variant_id' => ['required', 'integer'],
            'observations.*.impressions' => ['required', 'integer', 'min:0'],
            'observations.*.clicks' => ['required', 'integer', 'min:0'],
            'observations.*.watch_time_sec' => ['required', 'integer', 'min:0'],
        ]);

        try {
            $updated = $optimizer->recordPerformance($abTest, $data['observations']);

            return response()->json([
                'ab_test' => $updated,
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    public function concludeAbTest(Request $request, VideoAbTest $abTest, BayesianAbOptimizer $optimizer): JsonResponse
    {
        $data = $request->validate([
            'winner_index' => ['nullable', 'integer'],
        ]);

        try {
            $concluded = $optimizer->conclude($abTest, $data['winner_index'] ?? null);

            return response()->json([
                'ab_test' => $concluded,
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    public function stopAbTest(VideoAbTest $abTest, BayesianAbOptimizer $optimizer): JsonResponse
    {
        try {
            $stopped = $optimizer->stop($abTest);

            return response()->json([
                'ab_test' => $stopped,
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    // ==========================================
    // Resumable Chunked Video Uploads
    // ==========================================

    public function startUpload(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:5000'],
            'tags' => ['nullable', 'array', 'max:30'],
            'tags.*' => ['string', 'max:60'],
            'privacy' => ['required', Rule::in(['private', 'unlisted', 'public', 'scheduled'])],
            'publish_at' => ['nullable', 'required_if:privacy,scheduled', 'date', 'after:now'],
            'script_id' => ['nullable', 'integer', 'exists:scripts,id'],
            'thumbnail_media_id' => ['nullable', 'integer', 'exists:media_files,id'],
            'bytes' => ['required', 'integer', 'min:1', 'max:' . (256 * 1024 * 1024 * 1024)],
            'mime' => ['required', 'string', 'regex:/^video\//'],
        ]);
        if (!SocialAccount::where('platform', 'youtube')->where('status', 'connected')->exists()) {
            return response()->json([
                'error' => 'Connect YouTube first (Connections → YouTube).',
            ], 422);
        }
        $path = 'youtube-uploads/' . Str::uuid() . '.bin';
        Storage::disk('local')->put($path, '');
        $u = VideoUpload::create($data + [
            'path' => $path,
            'status' => 'receiving',
        ]);

        return response()->json([
            'upload' => $u,
            'chunk' => self::RECEIVE_CHUNK,
        ], 201);
    }

    /**
     * One piece of the file from her browser, appended in order.
     */
    public function chunk(Request $request, VideoUpload $upload): JsonResponse
    {
        if ($upload->status !== 'receiving') {
            return response()->json([
                'error' => 'This upload isn’t waiting for data.',
            ], 409);
        }
        $offset = (int)$request->query('offset', -1);
        if ($offset !== (int)$upload->received) {
            return response()->json([
                'error' => 'Out of order.',
                'received' => $upload->received,
            ], 409);
        }
        $data = $request->getContent();
        if ($data === '' || strlen($data) > self::RECEIVE_CHUNK || $upload->received + strlen($data) > $upload->bytes) {
            return response()->json([
                'error' => 'Bad chunk size.',
            ], 422);
        }
        $fh = fopen(Storage::disk('local')->path($upload->path), 'ab');
        fwrite($fh, $data);
        fclose($fh);
        $received = $upload->received + strlen($data);
        $upload->update([
            'received' => $received,
            'status' => $received >= $upload->bytes ? 'queued' : 'receiving',
        ]);

        return response()->json([
            'upload' => $upload->fresh(),
        ]);
    }

    /**
     * Send queued uploads to YouTube for a little while (the page calls this while it's open).
     */
    public function drain(): JsonResponse
    {
        @set_time_limit(90);
        YouTubeStudio::drainUploads(25);

        return response()->json([
            'uploads' => VideoUpload::latest()->limit(20)->get(),
        ]);
    }

    public function cancelUpload(VideoUpload $upload): JsonResponse
    {
        Storage::disk('local')->delete($upload->path);
        $upload->delete();

        return response()->json([
            'ok' => true,
        ]);
    }

    public function startTest(Request $request): JsonResponse
    {
        $data = $request->validate([
            'video_id' => ['required', 'string', 'max:20'],
            'titles' => ['required', 'array', 'min:1', 'max:2'],
            'titles.*' => ['required', 'string', 'max:100'],
            'hours' => ['required', 'integer', Rule::in([12, 24, 48])],
            'rounds' => ['required', 'integer', 'min:1', 'max:3'],
        ]);

        try {
            return response()->json([
                'test' => YouTubeStudio::startTest($data['video_id'], $data['titles'], $data['hours'], $data['rounds']),
            ], 201);
        } catch (Throwable $e) {
            return response()->json([
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Stop early: the original title goes back.
     */
    public function stopTest(TitleTest $test): JsonResponse
    {
        if ($test->status === 'running') {
            try {
                YouTubeStudio::setTitle($test->video_id, $test->original_title, YouTubeStudio::token());
            } catch (Throwable $e) {
                return response()->json([
                    'error' => $e->getMessage(),
                ], 422);
            }
            $test->update([
                'status' => 'stopped',
            ]);
        }

        return response()->json([
            'test' => $test->fresh(),
        ]);
    }
}
