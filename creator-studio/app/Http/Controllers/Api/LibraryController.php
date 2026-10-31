<?php

namespace App\Http\Controllers\Api;

use App\Domains\Toolkit\DocumentImporter;
use App\Domains\Toolkit\Models\Document;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/** The Library: saved Toolkit, YouTube and chat outputs. */
class LibraryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $q = Document::query()->with('script:id,title');
        if ($search = trim((string) $request->query('q'))) {
            $q->where(fn ($w) => $w->where('title', 'like', "%{$search}%")->orWhere('content', 'like', "%{$search}%"));
        }
        if ($tool = $request->query('tool')) {
            $q->where('tool', $tool);
        }
        if ($request->boolean('favorite')) {
            $q->where('favorite', true);
        }

        return response()->json([
            'documents' => $q->orderByDesc('favorite')->latest('updated_at')->paginate(24),
            'tools' => Document::query()->whereNotNull('tool')->distinct()->orderBy('tool')->pluck('tool'),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $doc = Document::create($request->validate([
            'title' => 'required|string|max:200',
            'tool' => 'nullable|string|max:60',
            'content' => 'required|string|max:200000',
            'inputs' => 'nullable|array',
            'script_id' => 'nullable|exists:scripts,id',
            'favorite' => 'nullable|boolean',
        ]));

        return response()->json(['document' => $doc, 'message' => 'Saved to your Library.']);
    }

    public function show(Document $document): JsonResponse
    {
        return response()->json(['document' => $document->load('script:id,title')]);
    }

    /** Upload a PDF / Word / text / subtitle file into the Library (and Memory). */
    public function upload(Request $request, DocumentImporter $importer): JsonResponse
    {
        $data = $request->validate([
            'file' => 'required|file|max:'.(DocumentImporter::MAX_MB * 1024),
            'title' => 'nullable|string|max:200',
        ]);

        try {
            $doc = $importer->import($request->file('file'), $data['title'] ?? null);
        } catch (Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        $words = str_word_count($doc->content);

        return response()->json([
            'document' => $doc,
            'message' => "Added “{$doc->title}” to your Library (".number_format($words).' words). Your producer chat and Memory can use it now.',
        ]);
    }

    public function update(Request $request, Document $document): JsonResponse
    {
        $document->update($request->validate([
            'title' => 'sometimes|required|string|max:200',
            'content' => 'sometimes|required|string|max:200000',
            'favorite' => 'sometimes|boolean',
            'script_id' => 'sometimes|nullable|exists:scripts,id',
        ]));

        return response()->json(['document' => $document->load('script:id,title')]);
    }

    public function destroy(Document $document): JsonResponse
    {
        $document->delete();

        return response()->json(['message' => 'Deleted.']);
    }
}
