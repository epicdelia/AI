<?php

namespace App\Http\Controllers\Api;

use App\Domains\Media\MediaFile;
use App\Domains\Media\ThumbnailGenerator;
use App\Http\Controllers\Controller;
use App\Models\Outlier;
use App\Services\Llm;
use App\Services\Prompts;
use App\Services\YouTubeTranscript;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * Thumbnail critic, built on the ported image-input path (MagicAI's
 * AiCompletionService::completeWithImage → Llm::see). Her thumbnail can be an upload, one of
 * her mockups, or a published video; up to 3 breakout thumbnails can be added for comparison.
 */
class VisionController extends Controller
{
    /** @var callable|null test hook: fn(string $videoId): string (image bytes) */
    public static $fetchFake = null;

    public function thumbnailReview(Request $request, Llm $llm): JsonResponse
    {
        $data = $request->validate([
            'image' => 'nullable|file|extensions:jpg,jpeg,png,webp,gif|max:10240',
            'media_id' => 'nullable|exists:media_files,id',
            'youtube_url' => 'nullable|string|max:500',
            'title' => 'nullable|string|max:200',
            'compare' => 'nullable|array|max:3',
            'compare.*' => 'string|max:500',
        ]);

        try {
            $mine = $this->subject($request, $data);
            $others = [];
            foreach ($data['compare'] ?? [] as $ref) {
                [$label, $img] = $this->reference($ref);
                $others[] = ['label' => $label, 'img' => $img];
            }
        } catch (RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        $images = [$mine];
        $legend = ['Image 1: HER thumbnail'.(! empty($data['title']) ? " (title: \"{$data['title']}\")" : '').'.'];
        foreach ($others as $i => $o) {
            $images[] = $o['img'];
            $legend[] = 'Image '.($i + 2).": a breakout video in her niche: {$o['label']}.";
        }

        $system = 'You are a YouTube packaging expert reviewing '.Prompts::name()."'s thumbnail.\n\n".Prompts::profile()."\n\n"
            ."Judge it the way a viewer sees it: tiny, on a phone, next to 20 other videos. Be specific and honest, and kind.\n\n"
            ."Return Markdown with these sections:\n"
            ."## First impression (1 second)\nWhat a scrolling viewer notices first, and whether they'd stop.\n"
            ."## Score\n\"N/10\" with one line why.\n"
            ."## What works\n## What to fix\nNumbered, concrete changes (crop, face, expression, text size or wording, contrast, clutter, colour).\n"
            ."## Title + thumbnail together\nDo they tell one story without repeating each other?".(! empty($data['title']) ? '' : ' (No title given: say what title it implies.)')."\n"
            .($others ? "## Against the breakouts\nWhat the breakout thumbnails do that hers doesn't (and the reverse). Never suggest copying them.\n" : '')
            .'Describe only what you can see. No em dashes.';

        try {
            $review = $llm->as('vision:thumbnail')->see($system, implode("\n", $legend), $images);
        } catch (Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 502);
        }

        return response()->json(['review' => $review, 'compared' => count($others)]);
    }

    /** @return array{mime: string, data: string} */
    private function subject(Request $request, array $data): array
    {
        if ($request->hasFile('image')) {
            return self::encode((string) file_get_contents($request->file('image')->getRealPath()));
        }
        if (! empty($data['media_id'])) {
            $m = MediaFile::find($data['media_id']);
            if (! in_array($m->kind, ['thumbnail', 'photo'], true)) {
                throw new RuntimeException('That file isn’t an image.');
            }

            return self::encode((string) $m->contents());
        }
        if (! empty($data['youtube_url'])) {
            return $this->reference($data['youtube_url'])[1];
        }
        throw new RuntimeException('Upload a thumbnail, pick a mockup, or give a YouTube link.');
    }

    /** A YouTube link/ID or a breakout id ("outlier:12") → [label, image]. */
    private function reference(string $ref): array
    {
        $label = $ref;
        if (preg_match('/^outlier:(\d+)$/', $ref, $m)) {
            $o = Outlier::find($m[1]) ?? throw new RuntimeException('That breakout no longer exists.');
            $ref = $o->video_id;
            $label = "\"{$o->title}\" ({$o->channel_title}, {$o->outlier_score}× its channel median)";
        }
        $id = YouTubeTranscript::videoId($ref) ?? throw new RuntimeException("Not a YouTube video: {$ref}");

        return [$label, self::encode($this->fetchThumbnail($id))];
    }

    private function fetchThumbnail(string $id): string
    {
        if (self::$fetchFake) {
            return (self::$fetchFake)($id);
        }
        foreach (['maxresdefault', 'sddefault', 'hqdefault'] as $size) {
            $resp = Http::timeout(15)->get("https://i.ytimg.com/vi/{$id}/{$size}.jpg");
            if ($resp->successful() && strlen($resp->body()) > 2000) { // YouTube serves a tiny grey placeholder for missing sizes
                return $resp->body();
            }
        }
        throw new RuntimeException("Couldn't download the thumbnail for {$id}.");
    }

    /** @return array{mime: string, data: string} */
    private static function encode(string $bytes): array
    {
        [, $mime] = ThumbnailGenerator::sniff($bytes);

        return ['mime' => $mime, 'data' => base64_encode($bytes)];
    }
}
