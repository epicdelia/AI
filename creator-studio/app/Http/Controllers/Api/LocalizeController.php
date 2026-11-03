<?php

namespace App\Http\Controllers\Api;

use App\Domains\YouTube\YouTubeStudio;
use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\Llm;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Titles and descriptions in other languages (YouTube shows viewers the one in their
 * language; this pairs with YouTube's own audio dubbing). The AI translates, keeping her
 * glossary words as they are; she reviews each one; then they're pushed to the video's
 * `localizations` through the YouTube Data API.
 */
class LocalizeController extends Controller
{
    public const LANGS = ['es' => 'Spanish', 'pt' => 'Portuguese', 'fr' => 'French', 'de' => 'German', 'it' => 'Italian', 'nl' => 'Dutch',
        'ro' => 'Romanian', 'pl' => 'Polish', 'tr' => 'Turkish', 'hi' => 'Hindi', 'id' => 'Indonesian', 'ja' => 'Japanese', 'ko' => 'Korean', 'ar' => 'Arabic', 'zh-Hans' => 'Chinese (Simplified)'];

    private const API = 'https://www.googleapis.com';

    public function show(string $videoId): JsonResponse
    {
        return response()->json([
            'drafts' => DB::table('video_localizations')->where('video_id', $videoId)->orderBy('lang')->get(),
            'langs' => self::LANGS,
            'glossary' => Setting::get('translation_glossary', ''),
            'default_language' => Setting::get('default_language') ?: 'en',
        ]);
    }

    public function translate(Request $request, string $videoId, Llm $llm): JsonResponse
    {
        $data = $request->validate([
            'langs' => 'required|array|min:1|max:10',
            'langs.*' => 'in:' . implode(',', array_keys(self::LANGS)),
            'title' => 'required|string|max:100',
            'description' => 'nullable|string|max:5000',
            'glossary' => 'nullable|string|max:1000',
        ]);
        if (array_key_exists('glossary', $data)) {
            Setting::put('translation_glossary', (string)$data['glossary']);
        }
        $keep = trim((string)($data['glossary'] ?? Setting::get('translation_glossary', '')));
        foreach ($data['langs'] as $lang) {
            $system = 'You translate YouTube titles and descriptions. Keep the meaning, tone and hooks natural for native speakers. '
                . 'Keep URLs, timestamps (00:00), @handles, #hashtags and these words exactly as written: ' . ($keep ?: '(none)')
                . '. Answer with JSON only: {"title": "...", "description": "..."}. The title must stay under 100 characters.';
            $raw = $llm->as('translate')->chat($system, "Translate into " . self::LANGS[$lang] . ".\n\nTITLE:\n{$data['title']}\n\nDESCRIPTION:\n" . ($data['description'] ?? ''));
            $j = json_decode((string)preg_replace('/^```(?:json)?|```$/m', '', trim($raw)), true);
            if (!is_array($j) || empty($j['title'])) {
                continue;
            }
            DB::table('video_localizations')->updateOrInsert(['video_id' => $videoId, 'lang' => $lang], [
                'title' => mb_substr((string)$j['title'], 0, 100),
                'description' => mb_substr((string)($j['description'] ?? ''), 0, 5000),
                'pushed_at' => null, 'error' => null, 'updated_at' => now(), 'created_at' => now(),
            ]);
        }

        return $this->show($videoId);
    }

    public function update(Request $request, string $videoId, string $lang): JsonResponse
    {
        $data = $request->validate(['title' => 'required|string|max:100', 'description' => 'nullable|string|max:5000']);
        abort_unless(DB::table('video_localizations')->where('video_id', $videoId)->where('lang', $lang)->exists(), 404);
        DB::table('video_localizations')->where('video_id', $videoId)->where('lang', $lang)->update($data + ['pushed_at' => null, 'updated_at' => now()]);

        return $this->show($videoId);
    }

    public function destroy(string $videoId, string $lang): JsonResponse
    {
        DB::table('video_localizations')->where('video_id', $videoId)->where('lang', $lang)->delete();

        return $this->show($videoId);
    }

    /**
     * Send every draft to YouTube (the video keeps its own title, description and other languages).
     */
    public function push(string $videoId): JsonResponse
    {
        $drafts = DB::table('video_localizations')->where('video_id', $videoId)->get();
        abort_if($drafts->isEmpty(), 422, 'Translate at least one language first.');
        try {
            $token = YouTubeStudio::token();
            $r = Http::withToken($token)->timeout(20)->get(self::API . '/youtube/v3/videos', ['part' => 'snippet,localizations', 'id' => $videoId]);
            $v = $r->json('items.0') ?? throw new RuntimeException('That video wasn’t found on your channel.');
            $snippet = $v['snippet'];
            $localizations = (array)($v['localizations'] ?? []);
            foreach ($drafts as $d) {
                $localizations[$d->lang] = ['title' => $d->title, 'description' => (string)$d->description];
            }
            $put = Http::withToken($token)->timeout(20)->put(self::API . '/youtube/v3/videos?part=snippet,localizations', [
                'id' => $videoId,
                'snippet' => array_filter([
                    'title' => $snippet['title'],
                    'description' => $snippet['description'] ?? '',
                    'categoryId' => $snippet['categoryId'] ?? '28',
                    'tags' => $snippet['tags'] ?? null,
                    // YouTube needs the video's own language before it accepts translations.
                    'defaultLanguage' => $snippet['defaultLanguage'] ?? (Setting::get('default_language') ?: 'en'),
                ], fn ($x) => $x !== null),
                'localizations' => $localizations,
            ]);
            if (!$put->successful()) {
                throw new RuntimeException('YouTube didn’t accept the translations: ' . ($put->json('error.message') ?? 'HTTP ' . $put->status()));
            }
            DB::table('video_localizations')->where('video_id', $videoId)->update(['pushed_at' => now(), 'error' => null]);
        } catch (RuntimeException $e) {
            DB::table('video_localizations')->where('video_id', $videoId)->update(['error' => mb_substr($e->getMessage(), 0, 300)]);
            abort(422, $e->getMessage());
        }

        return response()->json(['ok' => true, 'message' => 'Sent to YouTube: ' . $drafts->count() . ' language(s).'] + $this->show($videoId)->getData(true));
    }
}
