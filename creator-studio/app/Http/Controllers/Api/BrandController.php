<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

/**
 * Brand kit: colours, font, logo, words to avoid and her usual call to action.
 */
class BrandController extends Controller
{
    public const FONTS = [
        'system' => 'System',
        'Poppins' => 'Poppins',
        'Montserrat' => 'Montserrat',
        'Playfair Display' => 'Playfair Display',
        'DM Sans' => 'DM Sans',
        'Space Grotesk' => 'Space Grotesk',
    ];

    public function show(): JsonResponse
    {
        return response()->json([
            'brand' => self::kit(),
            'fonts' => self::FONTS,
        ]);
    }

    public static function kit(): array
    {
        return [
            'color' => Setting::get('brand_color', '#d8487e'),
            'color_2' => Setting::get('brand_color_2', '#7b5cd6'),
            'font' => Setting::get('brand_font', 'system'),
            'banned_words' => Setting::get('brand_banned_words', ''),
            'cta' => Setting::get('brand_cta', ''),
            'hashtags' => Setting::get('brand_hashtags', ''),
            'logo' => (bool)Setting::get('brand_logo'),
        ];
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'color_2' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'font' => ['required', Rule::in(array_keys(self::FONTS))],
            'banned_words' => ['nullable', 'string', 'max:2000'],
            'cta' => ['nullable', 'string', 'max:300'],
            'hashtags' => ['nullable', 'string', 'max:500'],
        ]);
        Setting::put('brand_color', $data['color']);
        Setting::put('brand_color_2', $data['color_2']);
        Setting::put('brand_font', $data['font']);
        Setting::put('brand_banned_words', $data['banned_words'] ?? null);
        Setting::put('brand_cta', $data['cta'] ?? null);
        Setting::put('brand_hashtags', $data['hashtags'] ?? null);

        return response()->json([
            'brand' => self::kit(),
        ]);
    }

    public function uploadLogo(Request $request): JsonResponse
    {
        $request->validate([
            'logo' => ['required', 'file', 'mimes:png,jpg,jpeg,webp,svg', 'max:4096'],
        ]);
        if ($old = Setting::get('brand_logo')) {
            Storage::disk('local')->delete($old);
        }
        Setting::put('brand_logo', $request->file('logo')->store('brand', 'local'));

        return response()->json([
            'brand' => self::kit(),
        ]);
    }

    public function logo(): Response
    {
        $path = Setting::get('brand_logo');
        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path, null, [
            'Cache-Control' => 'private, max-age=3600',
            'Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'",
        ]);
    }

    public function deleteLogo(): JsonResponse
    {
        if ($old = Setting::get('brand_logo')) {
            Storage::disk('local')->delete($old);
        }
        Setting::put('brand_logo', null);

        return response()->json([
            'brand' => self::kit(),
        ]);
    }

    /**
     * Words she never wants in her posts, as a list.
     */
    public static function bannedWords(): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/[,\n]+/', (string)Setting::get('brand_banned_words', '')))));
    }
}
