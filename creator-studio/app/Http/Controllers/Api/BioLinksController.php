<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

/**
 * Link in bio: her links on one page at /links, with clicks counted per day. The page is public
 * (it's meant for her bio), so it shows only her name, short bio and the links she adds. It's
 * reachable by others only when the studio itself is online (hosted or tunnelled).
 */
class BioLinksController extends Controller
{
    public function index(): JsonResponse
    {
        $links = DB::table('bio_links')->orderBy('position')->get();
        $since = now()->subDays(29)->toDateString();
        $daily = DB::table('bio_clicks')->where('day', '>=', $since)->selectRaw('day, sum(count) as clicks')->groupBy('day')->orderBy('day')->pluck('clicks', 'day');
        $series = [];
        for ($d = 29; $d >= 0; $d--) {
            $day = now()->subDays($d)->toDateString();
            $series[] = ['day' => $day, 'clicks' => (int) ($daily[$day] ?? 0)];
        }

        return response()->json([
            'links' => $links->map(fn ($l) => (array) $l + ['clicks_30d' => (int) DB::table('bio_clicks')->where('bio_link_id', $l->id)->where('day', '>=', $since)->sum('count')]),
            'title' => Setting::get('bio_title') ?: (Setting::get('nickname') ?: Setting::get('creator_name', '')),
            'text' => Setting::get('bio_text', ''),
            'url' => rtrim((string) config('app.url'), '/').'/links',
            'public' => ! preg_match('~//(localhost|127\.0\.0\.1)(:|/|$)~', (string) config('app.url')),
            'series' => $series,
        ]);
    }

    public function save(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:80'],
            'text' => ['nullable', 'string', 'max:300'],
            'links' => ['present', 'array', 'max:30'],
            'links.*.id' => ['nullable', 'integer'],
            'links.*.title' => ['required', 'string', 'max:80'],
            'links.*.url' => ['required', 'url:http,https', 'max:2000'],
            'links.*.icon' => ['nullable', 'string', 'max:20'],
            'links.*.active' => ['boolean'],
        ]);
        Setting::put('bio_title', $data['title'] ?? null);
        Setting::put('bio_text', $data['text'] ?? null);
        $keep = [];
        foreach ($data['links'] as $i => $l) {
            $row = ['title' => $l['title'], 'url' => $l['url'], 'icon' => $l['icon'] ?? null, 'position' => $i, 'active' => (bool) ($l['active'] ?? true), 'updated_at' => now()];
            if (! empty($l['id']) && DB::table('bio_links')->where('id', $l['id'])->exists()) {
                DB::table('bio_links')->where('id', $l['id'])->update($row);
                $keep[] = (int) $l['id'];
            } else {
                $keep[] = DB::table('bio_links')->insertGetId($row + ['created_at' => now(), 'clicks' => 0]);
            }
        }
        DB::table('bio_links')->whereNotIn('id', $keep ?: [0])->delete();

        return $this->index();
    }

    // ---- public ----

    public function page(): Response
    {
        $links = DB::table('bio_links')->where('active', true)->orderBy('position')->get();
        $title = e(Setting::get('bio_title') ?: (Setting::get('nickname') ?: Setting::get('creator_name', 'My links')));
        $text = e((string) Setting::get('bio_text', ''));
        $c1 = preg_match('/^#[0-9a-f]{6}$/i', (string) Setting::get('brand_color')) ? Setting::get('brand_color') : '#c93a70';
        $c2 = preg_match('/^#[0-9a-f]{6}$/i', (string) Setting::get('brand_color_2')) ? Setting::get('brand_color_2') : '#7b5cd6';
        $items = $links->map(fn ($l) => '<a class="l" href="/links/go/'.$l->id.'" rel="noopener">'.e($l->title).'</a>')->implode('');
        $initial = e(mb_strtoupper(mb_substr(html_entity_decode($title), 0, 1)));
        $html = <<<HTML
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>{$title}</title><meta name="description" content="{$text}"><meta name="robots" content="index,follow">
<style>
:root{--a:{$c1};--b:{$c2}}*{box-sizing:border-box}body{margin:0;min-height:100vh;font-family:system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;background:linear-gradient(160deg,var(--a),var(--b));color:#fff;display:flex;justify-content:center;padding:48px 16px}
main{width:100%;max-width:440px;text-align:center}.av{width:88px;height:88px;border-radius:50%;margin:0 auto 14px;display:grid;place-items:center;font-size:2.2rem;font-weight:800;background:rgba(255,255,255,.2);border:3px solid rgba(255,255,255,.6)}
h1{margin:0 0 6px;font-size:1.6rem}p{margin:0 0 26px;opacity:.9;line-height:1.5}.l{display:block;margin:0 0 12px;padding:16px 18px;border-radius:16px;background:#fff;color:#2b2230;font-weight:700;text-decoration:none;box-shadow:0 8px 24px rgba(0,0,0,.15);transition:transform .15s}.l:hover,.l:focus-visible{transform:translateY(-2px);outline:none}
footer{margin-top:28px;font-size:.75rem;opacity:.7}@media (prefers-reduced-motion:reduce){.l{transition:none}}
</style></head><body><main><div class="av" aria-hidden="true">{$initial}</div><h1>{$title}</h1><p>{$text}</p>{$items}<footer>Made with Creator Studio</footer></main></body></html>
HTML;

        return response($html, 200, ['Content-Type' => 'text/html; charset=utf-8', 'X-Frame-Options' => 'DENY']);
    }

    /** Count the click, then go. Only to links she saved (no open redirect). */
    public function go(int $link): RedirectResponse
    {
        $row = DB::table('bio_links')->where('id', $link)->where('active', true)->first();
        abort_unless($row, 404);
        DB::table('bio_links')->where('id', $link)->increment('clicks');
        DB::table('bio_clicks')->upsert([['bio_link_id' => $link, 'day' => now()->toDateString(), 'count' => 1]], ['bio_link_id', 'day'], ['count' => DB::raw('count + 1')]);

        return redirect()->away($row->url);
    }
}
