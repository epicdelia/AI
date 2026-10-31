<?php

namespace App\Http\Controllers\Api;

use App\Domains\Landing\Talk;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/** Her talks for the landing page's "On Stage": add, edit, feature, hide, reorder, photos. */
class TalksController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'talks' => Talk::orderByDesc('featured')->orderByRaw('date is null')->orderByDesc('date')->orderBy('position')->get(),
            'kinds' => Talk::KINDS,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $talk = Talk::create($this->validated($request) + ['position' => (int) Talk::max('position') + 1]);
        $this->onlyOneFeatured($talk);

        return response()->json(['talk' => $talk->fresh()], 201);
    }

    public function update(Request $request, Talk $talk): JsonResponse
    {
        $talk->update($this->validated($request, partial: true));
        $this->onlyOneFeatured($talk);

        return response()->json(['talk' => $talk->fresh()]);
    }

    public function destroy(Talk $talk): JsonResponse
    {
        $talk->delete();

        return response()->json(['ok' => true]);
    }

    /** A stage photo for the talk (JPEG, PNG or WebP, up to 8 MB). */
    public function uploadImage(Request $request, Talk $talk): JsonResponse
    {
        $file = $request->validate(['image' => 'required|file|mimes:jpg,jpeg,png,webp|max:8192'])['image'];
        $old = $talk->image;
        $path = $file->storeAs('talks', Str::uuid().'.'.strtolower($file->getClientOriginalExtension() ?: 'jpg'), 'local');
        $talk->update(['image' => $path]);
        if ($old && ! str_starts_with($old, '/')) {
            Storage::disk('local')->delete($old);
        }

        return response()->json(['talk' => $talk->fresh()]);
    }

    public function deleteImage(Talk $talk): JsonResponse
    {
        if ($talk->image && ! str_starts_with($talk->image, '/')) {
            Storage::disk('local')->delete($talk->image);
        }
        $talk->update(['image' => null]);

        return response()->json(['talk' => $talk->fresh()]);
    }

    private function validated(Request $request, bool $partial = false): array
    {
        $req = $partial ? 'sometimes' : 'required';

        return $request->validate([
            'event' => "{$req}|string|max:150",
            'title' => 'nullable|string|max:200',
            'kind' => "{$req}|string|in:".implode(',', array_keys(Talk::KINDS)),
            'description' => 'nullable|string|max:600',
            'date' => 'nullable|date',
            'time' => 'nullable|string|max:40',
            'venue' => 'nullable|string|max:150',
            'city' => 'nullable|string|max:100',
            'url' => 'nullable|url:http,https|max:500',
            'featured' => 'sometimes|boolean',
            'visible' => 'sometimes|boolean',
            'position' => 'sometimes|integer|min:0|max:10000',
        ]);
    }

    /** Featuring a talk takes the spotlight from the previous one. */
    private function onlyOneFeatured(Talk $talk): void
    {
        if ($talk->featured) {
            Talk::where('id', '!=', $talk->id)->where('featured', true)->update(['featured' => false]);
        }
    }
}
