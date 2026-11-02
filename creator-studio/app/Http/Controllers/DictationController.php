<?php

namespace App\Http\Controllers;

use App\Models\Idea;
use App\Models\Note;
use App\Services\Dictation;
use App\Services\Llm;
use Illuminate\Http\Request;
use Throwable;

/**
 * Flow: push-to-talk dictation, ported from app.py into the studio.
 */
class DictationController extends Controller
{
    public function show()
    {
        return view('dictate', [
            'notes' => Note::latest()->paginate(10),
            'styles' => array_keys(Dictation::STYLES),
        ]);
    }

    public function token(Dictation $dictation)
    {
        try {
            return response()->json($dictation->token());
        } catch (Throwable $e) {
            return response()->json([
                'detail' => $e->getMessage(),
            ], 502);
        }
    }

    public function polish(Request $request, Llm $llm)
    {
        $data = $request->validate([
            'text' => 'required|string|max:20000',
            'style' => 'nullable|in:' . implode(',', array_keys(Dictation::STYLES)),
            'rewrite' => 'nullable|in:' . implode(',', array_keys(Dictation::REWRITES)),
        ]);
        $prompt = !empty($data['rewrite']) ? Dictation::rewritePrompt($data['rewrite']) : Dictation::systemPrompt($data['style'] ?? 'auto');

        try {
            return response()->json([
                'text' => $llm->chat($prompt, trim($data['text']), 90),
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'detail' => $e->getMessage(),
            ], 502);
        }
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'raw' => 'nullable|string|max:20000',
            'polished' => 'required|string|max:40000',
            'style' => 'nullable|string|max:20',
        ]);
        $note = Note::create($data + [
            'style' => 'auto',
        ]);

        return response()->json([
            'id' => $note->id,
        ]);
    }

    /**
     * Turn a dictated note into an idea on her picked list.
     */
    public function toIdea(Note $note)
    {
        $lines = preg_split('/\R/', trim($note->polished));
        $title = trim(preg_replace('/^[#*\s]+|[*\s]+$/', '', $lines[0] ?? '')) ?: 'Dictated idea';
        Idea::create([
            'title' => mb_substr($title, 0, 200),
            'notes' => trim(implode("\n", array_slice($lines, 1))) ?: $note->polished,
            'status' => 'picked',
        ]);

        return redirect()->route('ideas.index')->with('status', 'Your dictated note is now an idea on the picked list.');
    }

    public function destroy(Note $note)
    {
        $note->delete();

        return back();
    }
}
