<?php

namespace App\Http\Controllers\Api;

use App\Domains\Media\MediaFile;
use App\Domains\Social\Models\SocialAccount;
use App\Domains\YouTube\TitleTest;
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

/** Upload to YouTube and title tests (My channel → YouTube studio). */
class YouTubeStudioController extends Controller
{
    /** Browser → studio chunk size (stays under PHP's default post size). */
    public const RECEIVE_CHUNK = 4 * 1024 * 1024;

    public function index(): JsonResponse
    {
        $connected = SocialAccount::where('platform', 'youtube')->where('status', 'connected')->exists();

        return response()->json([
            'connected' => $connected,
            'uploads' => VideoUpload::latest()->limit(20)->get(),
            'tests' => TitleTest::latest()->limit(20)->get(),
            'thumbnails' => MediaFile::whereIn('kind', ['thumbnail', 'mockup'])->latest()->limit(24)->get(['id', 'title', 'kind', 'mime', 'created_at']),
            'chunk' => self::RECEIVE_CHUNK,
        ]);
    }

    /** Her recent videos, for picking one to title-test. */
    public function videos(): JsonResponse
    {
        try {
            $token = YouTubeStudio::token();
            $ch = Http::withToken($token)->get('https://www.googleapis.com/youtube/v3/channels', ['part' => 'contentDetails', 'mine' => 'true'])->json('items.0');
            $playlist = $ch['contentDetails']['relatedPlaylists']['uploads'] ?? null;
            $items = $playlist ? Http::withToken($token)->get('https://www.googleapis.com/youtube/v3/playlistItems', ['part' => 'snippet', 'playlistId' => $playlist, 'maxResults' => 15])->json('items') ?? [] : [];

            return response()->json(['videos' => array_map(fn ($i) => ['id' => $i['snippet']['resourceId']['videoId'] ?? null, 'title' => $i['snippet']['title'] ?? '',
                'published_at' => $i['snippet']['publishedAt'] ?? null, 'thumb' => $i['snippet']['thumbnails']['default']['url'] ?? null], $items)]);
        } catch (Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

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
            'bytes' => ['required', 'integer', 'min:1', 'max:'.(256 * 1024 * 1024 * 1024)],
            'mime' => ['required', 'string', 'regex:/^video\//'],
        ]);
        if (! SocialAccount::where('platform', 'youtube')->where('status', 'connected')->exists()) {
            return response()->json(['error' => 'Connect YouTube first (Connections → YouTube).'], 422);
        }
        $path = 'youtube-uploads/'.Str::uuid().'.bin';
        Storage::disk('local')->put($path, '');
        $u = VideoUpload::create($data + ['path' => $path, 'status' => 'receiving']);

        return response()->json(['upload' => $u, 'chunk' => self::RECEIVE_CHUNK], 201);
    }

    /** One piece of the file from her browser, appended in order. */
    public function chunk(Request $request, VideoUpload $upload): JsonResponse
    {
        if ($upload->status !== 'receiving') {
            return response()->json(['error' => 'This upload isn’t waiting for data.'], 409);
        }
        $offset = (int) $request->query('offset', -1);
        if ($offset !== (int) $upload->received) {
            return response()->json(['error' => 'Out of order.', 'received' => $upload->received], 409);
        }
        $data = $request->getContent();
        if ($data === '' || strlen($data) > self::RECEIVE_CHUNK || $upload->received + strlen($data) > $upload->bytes) {
            return response()->json(['error' => 'Bad chunk size.'], 422);
        }
        $fh = fopen(Storage::disk('local')->path($upload->path), 'ab');
        fwrite($fh, $data);
        fclose($fh);
        $received = $upload->received + strlen($data);
        $upload->update(['received' => $received, 'status' => $received >= $upload->bytes ? 'queued' : 'receiving']);

        return response()->json(['upload' => $upload->fresh()]);
    }

    /** Send queued uploads to YouTube for a little while (the page calls this while it's open). */
    public function drain(): JsonResponse
    {
        @set_time_limit(90);
        YouTubeStudio::drainUploads(25);

        return response()->json(['uploads' => VideoUpload::latest()->limit(20)->get()]);
    }

    public function cancelUpload(VideoUpload $upload): JsonResponse
    {
        Storage::disk('local')->delete($upload->path);
        $upload->delete();

        return response()->json(['ok' => true]);
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
            return response()->json(['test' => YouTubeStudio::startTest($data['video_id'], $data['titles'], $data['hours'], $data['rounds'])], 201);
        } catch (Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    /** Stop early: the original title goes back. */
    public function stopTest(TitleTest $test): JsonResponse
    {
        if ($test->status === 'running') {
            try {
                YouTubeStudio::setTitle($test->video_id, $test->original_title, YouTubeStudio::token());
            } catch (Throwable $e) {
                return response()->json(['error' => $e->getMessage()], 422);
            }
            $test->update(['status' => 'stopped']);
        }

        return response()->json(['test' => $test->fresh()]);
    }
}
