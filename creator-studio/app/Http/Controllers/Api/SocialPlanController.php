<?php

namespace App\Http\Controllers\Api;

use App\Domains\Media\MediaFile;
use App\Domains\Social\Models\SocialPost;
use App\Domains\Social\Platforms;
use App\Domains\Social\Repurposer;
use App\Domains\Social\SocialScheduler;
use App\Http\Controllers\Controller;
use App\Models\Script;
use App\Services\Llm;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Posting queue, best-time scheduling, the week planner, media on posts and captions files.
 */
class SocialPlanController extends Controller
{
    public const MEDIA_MAX = 8;          // files per post
    public const IMAGE_MAX_MB = 20;
    public const VIDEO_MAX_MB = 500;

    /**
     * What's due now (or overdue) and what's coming in the next 24 hours.
     */
    public function due(): JsonResponse
    {
        return response()->json(SocialScheduler::due());
    }

    /**
     * Put a post in its next good, free slot.
     */
    public function autoSchedule(SocialPost $post): JsonResponse
    {
        if ($post->status === 'posted') {
            return response()->json([
                'error' => 'That post is already published.',
            ], 422);
        }
        $slot = SocialScheduler::nextSlot($post->platform, null, $post->id);
        $post->update([
            'scheduled_for' => $slot['at'],
            'status' => 'scheduled',
        ]);

        return response()->json([
            'post' => $post->fresh(),
            'reason' => $slot['reason'],
            'source' => $slot['source'],
            'message' => 'Scheduled for ' . $slot['at']->setTimezone(\App\Domains\Social\SocialAnalytics::tz())->format('D j M, H:i') . '.',
        ]);
    }

    /**
     * A week of posts from one brief: as many per network as her weekly targets say (one each on
     * the usual networks if none are set), each in its next good slot. She reviews them on the calendar.
     */
    public function planWeek(Request $request, Repurposer $repurposer): JsonResponse
    {
        $data = $request->validate([
            'brief' => 'required|string|min:3|max:20000',
            'script_id' => 'nullable|exists:scripts,id',
            'formats' => 'nullable|array',
            'formats.*' => 'string',
            'extra' => 'nullable|string|max:2000',
        ]);
        $cadence = array_filter(SocialScheduler::cadence());
        if (!$cadence) {
            $cadence = [
                'linkedin' => 1,
                'x' => 1,
                'instagram' => 1,
                'tiktok' => 1,
                'threads' => 1,
            ];
        }
        $targets = [];
        foreach ($cadence as $platform => $n) {
            $format = $data['formats'][$platform] ?? array_key_first(Platforms::FORMATS[$platform]);
            if (!Platforms::valid($platform, $format)) {
                $format = array_key_first(Platforms::FORMATS[$platform]);
            }
            for ($i = 0; $i < min(7, $n); $i++) {
                $targets[] = [
                    'platform' => $platform,
                    'format' => $format,
                ];
            }
        }
        $targets = array_slice($targets, 0, 15);

        $source = "This week's brief:\n" . trim($data['brief']);
        if (!empty($data['script_id'])) {
            $s = Script::find($data['script_id']);
            $source .= "\n\nThis week's video: \"{$s->title}\"\n" . mb_substr((string)($s->transcript ?: $s->body), 0, 8000);
        }
        $extra = trim('When a network appears more than once, give each post a different angle and hook; spread them across the week (teaser, value, behind the scenes, question). ' . ($data['extra'] ?? ''));

        try {
            $posts = $repurposer->run('Week plan', $source, $targets, $data['script_id'] ?? null, $extra, 3);
        } catch (RuntimeException $e) {
            return response()->json([
                'error' => $e->getMessage(),
            ], 422);
        } catch (Throwable $e) {
            return response()->json([
                'error' => $e->getMessage(),
            ], 500);
        }

        // Each one in its next free good slot, counting the ones just placed.
        $placed = collect();
        foreach ($posts as $p) {
            $slot = SocialScheduler::nextSlot($p->platform, null, $p->id, $placed);
            $p->update([
                'scheduled_for' => $slot['at'],
                'status' => 'scheduled',
                'notes' => $slot['reason'],
            ]);
            $placed->push($p);
        }

        return response()->json([
            'posts' => collect($posts)->map(fn ($p) => $p->fresh()->toArray() + [
                'checks' => SocialController::checks($p),
            ]),
            'message' => count($posts) . ' posts planned and scheduled. Review them on the calendar.',
        ]);
    }

    // ---------------------------------------------------------------- media

    public function uploadMedia(Request $request, SocialPost $post): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|extensions:jpg,jpeg,png,webp,gif,mp4,mov,m4v,webm|max:' . (self::VIDEO_MAX_MB * 1024),
        ]);
        if (count($post->media ?? []) >= self::MEDIA_MAX) {
            return response()->json([
                'error' => 'Up to ' . self::MEDIA_MAX . ' files per post.',
            ], 422);
        }
        $file = $request->file('file');
        $mime = (string)$file->getMimeType();
        $isImage = str_starts_with($mime, 'image/');
        if (!$isImage && !str_starts_with($mime, 'video/')) {
            return response()->json([
                'error' => 'Images or videos only.',
            ], 422);
        }
        if ($isImage && $file->getSize() > self::IMAGE_MAX_MB * 1048576) {
            return response()->json([
                'error' => 'Images up to ' . self::IMAGE_MAX_MB . ' MB.',
            ], 422);
        }
        $ext = strtolower($file->getClientOriginalExtension() ?: ($isImage ? 'jpg' : 'mp4'));
        // Stream to disk (videos can be large) rather than through memory.
        $path = Storage::disk('local')->putFileAs('media/social/' . now()->format('Y/m'), $file, Str::uuid() . '.' . $ext);
        $media = MediaFile::create([
            'kind' => 'social',
            'path' => $path,
            'mime' => $mime,
            'bytes' => $file->getSize(),
            'title' => mb_substr(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME), 0, 120),
        ]);

        return $this->attachMedia($post, $media);
    }

    /**
     * Reuse something she already made (a thumbnail mockup, a reference photo).
     */
    public function attachExisting(Request $request, SocialPost $post): JsonResponse
    {
        $media = MediaFile::findOrFail($request->validate([
            'media_id' => 'required|integer',
        ])['media_id']);
        if (count($post->media ?? []) >= self::MEDIA_MAX) {
            return response()->json([
                'error' => 'Up to ' . self::MEDIA_MAX . ' files per post.',
            ], 422);
        }

        return $this->attachMedia($post, $media);
    }

    public function removeMedia(SocialPost $post, int $media): JsonResponse
    {
        $post->update([
            'media' => array_values(array_filter($post->media ?? [], fn ($m) => $m['id'] !== $media)),
        ]);
        // Uploaded only for this post and not used elsewhere: delete the file too.
        $file = MediaFile::find($media);
        if ($file && $file->kind === 'social' && !SocialPost::where('media', 'like', '%"id":' . $media . ',%')->exists()) {
            $file->delete();
        }

        return response()->json([
            'post' => $post->fresh(),
            'message' => 'Removed.',
        ]);
    }

    /**
     * Alt text for an attached image, written by the vision model (she can edit it).
     */
    public function altText(Request $request, SocialPost $post, int $media, Llm $llm): JsonResponse
    {
        $items = $post->media ?? [];
        $i = collect($items)->search(fn ($m) => $m['id'] === $media);
        abort_if($i === false, 404);
        if ($request->has('alt')) {
            $items[$i]['alt'] = mb_substr(trim((string)$request->input('alt')), 0, 1000) ?: null;
            $post->update([
                'media' => $items,
            ]);

            return response()->json([
                'post' => $post->fresh(),
                'message' => 'Saved.',
            ]);
        }
        $file = MediaFile::findOrFail($media);
        if (!str_starts_with((string)$file->mime, 'image/')) {
            return response()->json([
                'error' => 'Alt text is for images.',
            ], 422);
        }

        try {
            $alt = trim($llm->as('alt_text')->see(
                'Write alt text for this image for someone using a screen reader: one or two plain sentences, what matters for the post, any readable text quoted. No "image of". Never guess who people are.',
                'Post: ' . mb_substr($post->asText(), 0, 600),
                [[
                    'mime' => $file->mime,
                    'data' => base64_encode((string)$file->contents()),
                ]],
                90
            ));
        } catch (Throwable $e) {
            return response()->json([
                'error' => $e->getMessage(),
            ], 502);
        }
        $items[$i]['alt'] = mb_substr($alt, 0, 1000);
        $post->update([
            'media' => $items,
        ]);

        return response()->json([
            'post' => $post->fresh(),
            'alt' => $items[$i]['alt'],
        ]);
    }

    // ---------------------------------------------------------------- captions

    /**
     * An .srt captions file from a Reel / TikTok / Short's spoken beats, timed at speaking pace.
     */
    public function srt(SocialPost $post): Response|JsonResponse
    {
        if ((Platforms::FORMATS[$post->platform][$post->format][1] ?? null) !== 'Beats' || !$post->parts) {
            return response()->json([
                'error' => 'Captions are for Reels, TikToks and Shorts with spoken beats.',
            ], 422);
        }
        $out = [];
        $t = 0.0;
        $n = 1;
        foreach ($post->parts as $beat) {
            $spoken = trim(preg_replace('/\s+/', ' ', preg_replace('/\[[^\]]*\]/u', ' ', $beat)));
            if ($spoken === '') {
                continue;
            }
            $dur = max(1.2, str_word_count($spoken) / 2.5); // about 150 words a minute
            $out[] = $n++ . "\n" . self::stamp($t) . ' --> ' . self::stamp($t + $dur) . "\n" . wordwrap($spoken, 42, "\n", true) . "\n";
            $t += $dur;
        }
        $name = Str::slug($post->title ?: 'captions') ?: 'captions';

        return response(implode("\n", $out), 200, [
            'Content-Type' => 'application/x-subrip; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$name}.srt\"",
        ]);
    }

    private function attachMedia(SocialPost $post, MediaFile $media): JsonResponse
    {
        $items = $post->media ?? [];
        if (!collect($items)->contains('id', $media->id)) {
            $items[] = [
                'id' => $media->id,
                'url' => $media->url,
                'mime' => $media->mime,
                'name' => $media->title ?: $media->kind,
                'alt' => null,
            ];
        }
        $post->update([
            'media' => $items,
        ]);

        return response()->json([
            'post' => $post->fresh(),
            'message' => 'Added.',
        ]);
    }

    private static function stamp(float $s): string
    {
        $ms = (int)round($s * 1000);

        return sprintf('%02d:%02d:%02d,%03d', intdiv($ms, 3600000), intdiv($ms, 60000) % 60, intdiv($ms, 1000) % 60, $ms % 1000);
    }
}
