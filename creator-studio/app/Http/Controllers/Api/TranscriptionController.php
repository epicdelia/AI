<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Script;
use App\Services\Transcription;
use App\Services\YouTubeTranscript;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/** Upload a recording, get its transcript (optionally saved straight onto a script). */
class TranscriptionController extends Controller
{
    public function info(): JsonResponse
    {
        return response()->json([
            'engine' => Transcription::engine(),
            'upload_limit_mb' => Transcription::uploadLimitMb(),
            'whisper_max_mb' => Transcription::WHISPER_MAX_MB,
            'types' => Transcription::AUDIO_TYPES,
        ]);
    }

    public function store(Request $request, Transcription $transcription): JsonResponse
    {
        if (! $request->hasFile('file') && $request->server('CONTENT_LENGTH') > 0) {
            // PHP drops bodies over post_max_size without an error, so explain it.
            return response()->json(['error' => 'That file is bigger than the server accepts ('.Transcription::uploadLimitMb().' MB). Raise upload_max_filesize and post_max_size in php.ini, or upload the audio track only.'], 413);
        }

        $data = $request->validate([
            'file' => 'required|file|max:'.(Transcription::uploadLimitMb() * 1024),
            'script_id' => 'nullable|exists:scripts,id',
            'timestamps' => 'nullable|boolean',
        ]);

        try {
            $result = $transcription->transcribe($request->file('file'));
        } catch (Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        $transcript = $request->boolean('timestamps') && $result['segments']
            ? YouTubeTranscript::timestamped($result['segments'])
            : $result['text'];

        $script = null;
        if (! empty($data['script_id'])) {
            $script = Script::find($data['script_id']);
            $script->update(['transcript' => $transcript]);
        }

        return response()->json([
            'transcript' => $transcript,
            'words' => str_word_count($result['text']),
            'engine' => $result['engine'],
            'seconds' => round($result['duration_ms'] / 1000, 1),
            'script' => $script,
            'message' => 'Transcribed with '.($result['engine'] === 'openai' ? 'OpenAI Whisper' : 'AssemblyAI').'.',
        ]);
    }
}
