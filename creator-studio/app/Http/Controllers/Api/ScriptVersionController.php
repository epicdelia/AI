<?php

namespace App\Http\Controllers\Api;

use App\Domains\Copilot\ParagraphDiff;
use App\Http\Controllers\Controller;
use App\Models\Script;
use App\Models\ScriptVersion;
use App\Services\AiTellsLinter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Version history for a script: list, compare with the current text, save by hand, restore. */
class ScriptVersionController extends Controller
{
    public function index(Script $script): JsonResponse
    {
        return response()->json([
            'versions' => $script->versions()->latest('id')->get(['id', 'title', 'words', 'source', 'label', 'created_at']),
            'current_words' => $script->wordCount(),
        ]);
    }

    /** One version, with what changed between it and the current text. */
    public function show(Script $script, ScriptVersion $version): JsonResponse
    {
        return response()->json([
            'version' => $version,
            'hunks' => ParagraphDiff::hunks($version->body, (string) $script->body),
        ]);
    }

    public function store(Request $request, Script $script): JsonResponse
    {
        $data = $request->validate(['label' => 'nullable|string|max:120']);
        $version = ScriptVersion::keep($script, label: ($data['label'] ?? null) ?: 'Saved by hand');
        if (! $version) {
            return response()->json(['error' => 'There is nothing written to save yet.'], 422);
        }

        return response()->json(['version' => $version, 'message' => 'Version saved.']);
    }

    /** Put an old version back. The current text is kept as a version first, so this can be undone too. */
    public function restore(Script $script, ScriptVersion $version): JsonResponse
    {
        ScriptVersion::keep($script, source: 'restore', label: 'Before restoring the '.$version->created_at->format('M j, H:i').' version');
        $script->update(['body' => $version->body]);
        $findings = AiTellsLinter::lint($version->body);

        return response()->json([
            'script' => $script->fresh(),
            'findings' => $findings,
            'has_hard' => AiTellsLinter::hasHard($findings),
            'message' => 'Version restored. Your previous text is in the history.',
        ]);
    }

    public function destroy(Script $script, ScriptVersion $version): JsonResponse
    {
        $version->delete();

        return response()->json(['message' => 'Version deleted.']);
    }
}
