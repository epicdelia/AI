<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Llm;
use App\Services\Prompts;
use App\Services\YouTubeTranscript;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/** "AI YouTube": pull any video's transcript, then summarise, break it down or repurpose it. */
class YouTubeController extends Controller
{
    public function actions(): JsonResponse
    {
        return response()->json([
            'actions' => collect(Prompts::transcriptActions())->map(fn ($a, $k) => ['key' => $k, 'label' => $a['label']])->values(),
        ]);
    }

    public function transcript(Request $request, YouTubeTranscript $yt): JsonResponse
    {
        $data = $request->validate(['url' => 'required|string|max:500', 'timestamps' => 'nullable|boolean']);

        try {
            $t = $yt->fetch($data['url']);
        } catch (Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json([
            'video_id' => $t['video_id'],
            'title' => $t['title'],
            'language' => $t['language'],
            'transcript' => ! empty($data['timestamps']) ? YouTubeTranscript::timestamped($t['segments']) : $t['text'],
            'words' => str_word_count($t['text']),
        ]);
    }

    public function analyze(Request $request, YouTubeTranscript $yt, Llm $llm): JsonResponse
    {
        $actions = Prompts::transcriptActions();
        $data = $request->validate([
            'action' => 'required|in:'.implode(',', array_keys($actions)),
            'url' => 'nullable|string|max:500',
            'transcript' => 'nullable|string|max:150000',
            'title' => 'nullable|string|max:300',
        ]);

        $transcript = trim((string) ($data['transcript'] ?? ''));
        $title = $data['title'] ?? null;
        $url = $data['url'] ?? null;
        if ($transcript === '') {
            if (! $url) {
                return response()->json(['error' => 'Give a YouTube link or paste a transcript.'], 422);
            }
            try {
                $t = $yt->fetch($url);
            } catch (Throwable $e) {
                return response()->json(['error' => $e->getMessage(), 'needs_transcript' => true], 422);
            }
            $transcript = $data['action'] === 'chapters' ? YouTubeTranscript::timestamped($t['segments']) : $t['text'];
            $title ??= $t['title'];
        }

        $instruction = str_replace('{name}', Prompts::name(), $actions[$data['action']]['prompt']);
        $system = "You're a sharp YouTube strategist and editor working for ".Prompts::name().".\n\n".Prompts::profile()
            ."\n\n".Prompts::writingRules()."\n\nWork only from the transcript. Return Markdown, no preamble.";
        $user = ($title ? "Video title: {$title}\n" : '').($url ? "URL: {$url}\n" : '')
            ."\nTask: {$instruction}\n\nTranscript:\n".mb_substr($transcript, 0, 120000);

        try {
            $output = $llm->as('youtube:'.$data['action'])->chat($system, $user);
        } catch (Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 502);
        }

        return response()->json([
            'output' => $output,
            'title' => $title,
            'transcript' => $transcript,
            'action_label' => $actions[$data['action']]['label'],
        ]);
    }
}
