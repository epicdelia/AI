<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * White-label portal: what her clients and community members see instead of "Creator
 * Studio" (name, logo, accent colour, support email, a welcome line and footer). Her own
 * studio keeps its look. The public `branding` endpoint only exposes these display fields.
 */
class PortalBrandController extends Controller
{
    private const LOGO_DIR = 'portal-brand';

    public static function config(): array
    {
        $saved = json_decode((string)Setting::get('portal_brand', ''), true) ?: [];

        return [
            'enabled' => (bool)($saved['enabled'] ?? false),
            'name' => (string)($saved['name'] ?? ''),
            'tagline' => (string)($saved['tagline'] ?? ''),
            'accent' => preg_match('/^#[0-9a-fA-F]{6}$/', (string)($saved['accent'] ?? '')) ? $saved['accent'] : null,
            'support_email' => (string)($saved['support_email'] ?? ''),
            'welcome' => (string)($saved['welcome'] ?? ''),
            'footer' => (string)($saved['footer'] ?? ''),
            'logo' => (string)($saved['logo'] ?? ''),
        ];
    }

    /**
     * The "From" name on emails to clients and members, when branding is on.
     */
    public static function fromName(): ?string
    {
        $c = self::config();

        return $c['enabled'] && $c['name'] !== '' ? $c['name'] : null;
    }

    /**
     * Public: the portal's display branding (used before and after sign-in).
     */
    public function show(): JsonResponse
    {
        $c = self::config();
        if (!$c['enabled']) {
            return response()->json(['enabled' => false]);
        }

        return response()->json([
            'enabled' => true,
            'name' => $c['name'] ?: null,
            'tagline' => $c['tagline'] ?: null,
            'accent' => $c['accent'],
            'support_email' => $c['support_email'] ?: null,
            'welcome' => $c['welcome'] ?: null,
            'footer' => $c['footer'] ?: null,
            'logo_url' => $c['logo'] ? url('/api/branding/logo') . '?v=' . substr(md5($c['logo']), 0, 8) : null,
        ]);
    }

    public function logo(): Response
    {
        $c = self::config();
        abort_unless($c['enabled'] && $c['logo'] && Storage::disk('local')->exists($c['logo']), 404);
        $ext = strtolower(pathinfo($c['logo'], PATHINFO_EXTENSION));
        $types = ['png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'webp' => 'image/webp'];

        return response()->file(Storage::disk('local')->path($c['logo']), [
            'Content-Type' => $types[$ext] ?? 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }

    /**
     * Hers: the full settings.
     */
    public function edit(): JsonResponse
    {
        return response()->json(self::config() + [
            'logo_url' => self::config()['logo'] ? url('/api/branding/logo') : null,
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'enabled' => 'required|boolean',
            'name' => 'nullable|string|max:60',
            'tagline' => 'nullable|string|max:120',
            'accent' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'support_email' => 'nullable|email|max:200',
            'welcome' => 'nullable|string|max:300',
            'footer' => 'nullable|string|max:300',
        ]);
        Setting::put('portal_brand', json_encode(array_merge(self::config(), array_map(fn ($v) => $v ?? '', $data))));

        return $this->edit();
    }

    /**
     * PNG, JPG or WebP up to 1 MB (no SVG: it could carry script).
     */
    public function uploadLogo(Request $request): JsonResponse
    {
        $request->validate([
            'logo' => 'required|file|mimes:png,jpg,jpeg,webp|max:1024',
        ]);
        $c = self::config();
        if ($c['logo']) {
            Storage::disk('local')->delete($c['logo']);
        }
        $file = $request->file('logo');
        $path = $file->storeAs(self::LOGO_DIR, 'logo-' . bin2hex(random_bytes(4)) . '.' . strtolower($file->getClientOriginalExtension() ?: $file->extension()), 'local');
        $c['logo'] = $path;
        Setting::put('portal_brand', json_encode($c));

        return $this->edit();
    }

    public function deleteLogo(): JsonResponse
    {
        $c = self::config();
        if ($c['logo']) {
            Storage::disk('local')->delete($c['logo']);
        }
        $c['logo'] = '';
        Setting::put('portal_brand', json_encode($c));

        return $this->edit();
    }
}
