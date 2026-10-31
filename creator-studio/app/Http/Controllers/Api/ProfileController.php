<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/** Her profile photo (the header avatar). Private: only served to her signed-in browser. */
class ProfileController extends Controller
{
    public function upload(Request $request): JsonResponse
    {
        $request->validate(['photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:6144']]);
        if ($old = Setting::get('avatar')) {
            Storage::disk('local')->delete($old);
        }
        Setting::put('avatar', $request->file('photo')->store('profile', 'local'));

        return response()->json(['ok' => true]);
    }

    public function show(): Response
    {
        $path = Setting::get('avatar');
        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path, null, ['Cache-Control' => 'private, max-age=3600']);
    }

    public function destroy(): JsonResponse
    {
        if ($old = Setting::get('avatar')) {
            Storage::disk('local')->delete($old);
        }
        Setting::put('avatar', null);

        return response()->json(['ok' => true]);
    }
}
