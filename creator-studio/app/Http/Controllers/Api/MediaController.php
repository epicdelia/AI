<?php

namespace App\Http\Controllers\Api;

use App\Domains\Media\AudioCleaner;
use App\Domains\Media\BrollGenerator;
use App\Domains\Media\ClipProject;
use App\Domains\Media\VideoJob;
use App\Domains\Media\MediaFile;
use App\Domains\Media\ShortsClipper;
use App\Domains\Media\TextToSpeech;
use App\Domains\Media\ThumbnailGenerator;
use App\Http\Controllers\Controller;
use App\Models\Script;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/** Read-aloud, thumbnail mockups, her reference photos, and Shorts cut by Vizard. */
class MediaController extends Controller
{
    public function capabilities(TextToSpeech $tts): JsonResponse
    {
        $eleven = [];
        if (in_array('elevenlabs', TextToSpeech::providers(), true)) {
            try {
                $eleven = $tts->elevenVoices();
            } catch (Throwable) {
                $eleven = [];
            }
        }

        return response()->json([
            'tts' => ['providers' => TextToSpeech::providers(), 'default' => TextToSpeech::defaultProvider(),
                'voice' => \App\Models\Setting::get('tts_voice'), 'openai_voices' => TextToSpeech::OPENAI_VOICES, 'eleven_voices' => $eleven],
            'images' => ['providers' => ThumbnailGenerator::providers(), 'default' => ThumbnailGenerator::defaultProvider(),
                'photos' => MediaFile::where('kind', 'photo')->latest()->get()],
            'clips' => ShortsClipper::available(),
            'broll' => ['available' => BrollGenerator::available(), 'models' => collect(BrollGenerator::MODELS)->map(fn ($m, $k) => ['id' => $k, 'label' => $m[0], 'durations' => $m[4], 'default' => $m[5]])->values()],
            'cleanup' => AudioCleaner::available(),
        ]);
    }

    public function broll(Request $request): JsonResponse
    {
        $q = VideoJob::with('media')->latest();
        if ($sid = $request->query('script_id')) {
            $q->where('script_id', $sid);
        }

        return response()->json(['jobs' => $q->take(30)->get()]);
    }

    public function submitBroll(Request $request, BrollGenerator $gen): JsonResponse
    {
        $data = $request->validate([
            'prompt' => 'required|string|max:2000',
            'model' => 'nullable|string|max:40',
            'duration' => 'nullable|string|max:5',
            'aspect' => 'nullable|string|max:5',
            'media_id' => 'nullable|exists:media_files,id',
            'script_id' => 'nullable|exists:scripts,id',
        ]);
        try {
            $job = $gen->submit($data['prompt'], $data);
        } catch (Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json(['job' => $job, 'message' => 'Queued at fal.ai. Clips take 1–5 minutes; this page checks back.']);
    }

    public function refreshBroll(VideoJob $job, BrollGenerator $gen): JsonResponse
    {
        return response()->json(['job' => $gen->refresh($job)]);
    }

    public function destroyBroll(VideoJob $job): JsonResponse
    {
        $job->media?->delete();
        $job->delete();

        return response()->json(['message' => 'Removed.']);
    }

    public function cleanAudio(Request $request, AudioCleaner $cleaner): JsonResponse
    {
        $data = $request->validate([
            'file' => 'required|file|max:'.(AudioCleaner::uploadLimitMb() * 1024),
            'script_id' => 'nullable|exists:scripts,id',
        ]);
        try {
            $audio = $cleaner->clean($request->file('file'), $data['script_id'] ?? null);
        } catch (Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json(['audio' => $audio, 'message' => 'Background noise removed. Play it below or download the MP3.']);
    }

    public function index(Request $request): JsonResponse
    {
        $q = MediaFile::query()->latest();
        if ($kind = $request->query('kind')) {
            $q->where('kind', $kind);
        }
        if ($sid = $request->query('script_id')) {
            $q->where('script_id', $sid);
        }

        return response()->json(['media' => $q->take(60)->get()]);
    }

    /** Stream a private file to her (logged-in) browser. */
    public function file(Request $request, MediaFile $media): Response
    {
        $bytes = $media->contents();
        abort_if($bytes === null, 404);
        $name = str($media->title ?: $media->kind)->slug()->limit(60, '').'.'.pathinfo($media->path, PATHINFO_EXTENSION);

        return response($bytes, 200, [
            'Content-Type' => $media->mime,
            'Content-Length' => (string) strlen($bytes),
            'Content-Disposition' => ($request->boolean('download') ? 'attachment' : 'inline').'; filename="'.$name.'"',
            'Cache-Control' => 'private, max-age=86400',
            'Accept-Ranges' => 'none',
        ]);
    }

    public function destroy(MediaFile $media): JsonResponse
    {
        $media->delete();

        return response()->json(['message' => 'Deleted.']);
    }

    public function uploadPhoto(Request $request): JsonResponse
    {
        $request->validate(['photo' => 'required|file|max:10240']);
        $bytes = (string) file_get_contents($request->file('photo')->getRealPath());
        try {
            [$ext, $mime] = ThumbnailGenerator::sniff($bytes);
        } catch (Throwable) {
            return response()->json(['error' => 'Upload a JPG, PNG or WebP photo.'], 422);
        }
        if (MediaFile::where('kind', 'photo')->count() >= 8) {
            return response()->json(['error' => 'You have 8 photos already. Delete one first (the newest 4 are used).'], 422);
        }
        $photo = MediaFile::store('photo', $bytes, $ext, $mime, ['provider' => 'upload', 'title' => 'Reference photo']);

        return response()->json(['photo' => $photo, 'message' => 'Photo added. Tick “use my photos” to put you in the mockups.']);
    }

    public function speak(Request $request, TextToSpeech $tts): JsonResponse
    {
        $data = $request->validate([
            'text' => 'nullable|string|max:60000',
            'script_id' => 'nullable|exists:scripts,id',
            'provider' => 'nullable|in:openai,elevenlabs',
            'voice' => 'nullable|string|max:80',
            'speed' => 'nullable|numeric|min:0.25|max:4',
            'style' => 'nullable|string|max:300',
        ]);
        $script = ! empty($data['script_id']) ? Script::find($data['script_id']) : null;
        $text = trim((string) ($data['text'] ?? '')) ?: (string) $script?->body;
        if ($text === '') {
            return response()->json(['error' => 'Nothing to read.'], 422);
        }

        try {
            $clip = $tts->speak($text, array_filter([
                'provider' => $data['provider'] ?? null, 'voice' => $data['voice'] ?? null,
                'speed' => $data['speed'] ?? null, 'style' => $data['style'] ?? null,
                'script_id' => $script?->id, 'title' => $script ? ($data['text'] ?? null ? "{$script->title} (selection)" : $script->title) : null,
            ], fn ($v) => $v !== null));
        } catch (Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json(['audio' => $clip, 'message' => 'Ready to play: about '.gmdate('i:s', $clip->meta['est_seconds']).' long.']);
    }

    public function thumbnail(Request $request, ThumbnailGenerator $gen): JsonResponse
    {
        $data = $request->validate([
            'concept' => 'required|string|max:1500',
            'text' => 'nullable|string|max:60',
            'style' => 'nullable|string|max:80',
            'lighting' => 'nullable|string|max:80',
            'mood' => 'nullable|string|max:80',
            'use_photos' => 'nullable|boolean',
            'quality' => 'nullable|in:low,medium,high,auto',
            'provider' => 'nullable|in:openai,fal',
            'script_id' => 'nullable|exists:scripts,id',
        ]);

        try {
            $img = $gen->generate($data + ['use_photos' => $request->boolean('use_photos')]);
        } catch (Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json(['image' => $img, 'message' => 'Mockup ready.']);
    }

    public function clips(Request $request): JsonResponse
    {
        $q = ClipProject::query()->latest();
        if ($sid = $request->query('script_id')) {
            $q->where('script_id', $sid);
        }

        return response()->json(['projects' => $q->take(20)->get(), 'available' => ShortsClipper::available()]);
    }

    public function submitClips(Request $request, ShortsClipper $clipper): JsonResponse
    {
        $data = $request->validate([
            'url' => 'required|string|max:500',
            'script_id' => 'nullable|exists:scripts,id',
            'lang' => 'nullable|string|max:10',
            'length' => 'nullable|integer|in:0,1,2,3,4',
            'ratio' => 'nullable|integer|in:1,2,3,4',
            'max' => 'nullable|integer|min:1|max:20',
            'subtitles' => 'nullable|boolean',
            'headline' => 'nullable|boolean',
        ]);
        try {
            $project = $clipper->submit($data['url'], $data, $data['script_id'] ?? null);
        } catch (Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json(['project' => $project, 'message' => 'Sent to Vizard. Clips usually take a few minutes; they’ll appear here (the scheduler checks every 10 minutes).']);
    }

    public function refreshClips(ClipProject $project, ShortsClipper $clipper): JsonResponse
    {
        return response()->json(['project' => $clipper->refresh($project)]);
    }

    public function destroyClips(ClipProject $project): JsonResponse
    {
        $project->delete();

        return response()->json(['message' => 'Removed.']);
    }
}
