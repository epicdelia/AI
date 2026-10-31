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
        $title = e(Setting::get('bio_title') ?: (Setting::get('nickname') ?: Setting::get('creator_name', 'Delia Lazarescu')));
        $text = e((string) Setting::get('bio_text', 'Ex-Google Engineer · Creator · Digital Nomad across 69+ countries'));
        $c1 = preg_match('/^#[0-9a-f]{6}$/i', (string) Setting::get('brand_color')) ? Setting::get('brand_color') : '#f06a9b';
        $c2 = preg_match('/^#[0-9a-f]{6}$/i', (string) Setting::get('brand_color_2')) ? Setting::get('brand_color_2') : '#a68cf2';
        $items = $links->map(fn ($l) => '<a class="l" href="/links/go/'.$l->id.'" rel="noopener"><span>'.e($l->title).'</span><span class="arr">↗</span></a>')->implode('');
        if (empty($items)) {
            $items = '<a class="l" href="/techunicorn" rel="noopener"><span>Explore Portfolio & Services</span><span class="arr">↗</span></a>'
                .'<a class="l" href="https://instagram.com/tech.unicorn" target="_blank" rel="noopener"><span>Instagram (@tech.unicorn)</span><span class="arr">↗</span></a>'
                .'<a class="l" href="https://youtube.com/@Tech.Unicorn" target="_blank" rel="noopener"><span>YouTube Channel</span><span class="arr">↗</span></a>'
                .'<a class="l" href="https://linkedin.com/in/delialazarescu" target="_blank" rel="noopener"><span>LinkedIn Profile</span><span class="arr">↗</span></a>'
                .'<a class="l" href="/mediakit" rel="noopener"><span>Press & Media Kit</span><span class="arr">↗</span></a>';
        }
        $initial = '🦄';
        $html = <<<HTML
<!doctype html>
<html lang="en" data-theme="dark">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>{$title} — @Tech.Unicorn</title>
  <meta name="description" content="{$text}">
  <meta name="robots" content="index,follow">
  <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>🦄</text></svg>">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,wght@1,500;1,600&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <style>
    :root {
      --bg: #17131a;
      --panel: #211b25;
      --panel-2: #2b2230;
      --ink: #f2eaf1;
      --muted: #a99cae;
      --line: #372d3c;
      --accent: {$c1};
      --violet: {$c2};
      --grad: linear-gradient(135deg, var(--accent) 0%, var(--violet) 100%);
      --serif: "Fraunces", Georgia, serif;
      --sans: "Inter", system-ui, sans-serif;
    }
    :root[data-theme="light"] {
      --bg: #fdfaf8;
      --panel: #ffffff;
      --panel-2: #f6edf2;
      --ink: #2b2230;
      --muted: #7f7485;
      --line: #ece3e8;
    }
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      min-height: 100vh; font-family: var(--sans); background: var(--bg); color: var(--ink);
      display: flex; flex-direction: column; align-items: center; justify-content: flex-start;
      padding: 48px 18px 40px; position: relative; overflow-x: hidden; transition: background 0.2s, color 0.2s;
    }
    body::before {
      content: ""; position: fixed; inset: 0; pointer-events: none; z-index: -1;
      background:
        radial-gradient(60% 50% at 85% 0%, rgba(166, 140, 242, 0.15), transparent 60%),
        radial-gradient(55% 45% at 10% 20%, rgba(240, 106, 155, 0.14), transparent 60%);
    }
    main { width: 100%; max-width: 460px; text-align: center; }
    .theme-btn { position: absolute; top: 16px; right: 16px; background: var(--panel); border: 1px solid var(--line); color: var(--muted); border-radius: 8px; width: 34px; height: 34px; cursor: pointer; display: grid; place-items: center; font-size: 0.9rem; }
    .av {
      width: 92px; height: 92px; border-radius: 50%; margin: 0 auto 14px; display: grid; place-items: center;
      font-size: 2.5rem; background: var(--panel); border: 2px solid var(--line); box-shadow: 0 8px 24px rgba(0,0,0,0.3);
    }
    .badge { display: inline-flex; align-items: center; gap: 4px; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; background: rgba(240,106,155,0.16); color: var(--accent); padding: 3px 10px; border-radius: 100px; margin-bottom: 8px; letter-spacing: 0.05em; }
    h1 { font-size: 1.55rem; font-weight: 700; letter-spacing: -0.02em; margin-bottom: 6px; }
    h1 em { font-family: var(--serif); font-style: italic; font-weight: 500; color: var(--accent); }
    p.bio { color: var(--muted); font-size: 0.95rem; line-height: 1.5; margin-bottom: 24px; padding-inline: 12px; }
    .links { display: flex; flex-direction: column; gap: 12px; }
    .l {
      display: flex; align-items: center; justify-content: space-between; padding: 16px 22px; border-radius: 16px;
      background: var(--panel); color: var(--ink); font-weight: 600; text-decoration: none; border: 1px solid var(--line);
      box-shadow: 0 4px 16px rgba(0,0,0,0.2); transition: all 0.2s cubic-bezier(.22,.61,.36,1);
    }
    .l:hover { transform: translateY(-3px); border-color: var(--accent); box-shadow: 0 8px 24px rgba(240,106,155,0.22); color: var(--ink); }
    .l .arr { color: var(--accent); font-weight: 700; transition: transform 0.2s; }
    .l:hover .arr { transform: translate(3px, -3px); }
    .featured-site { margin-top: 14px; padding: 14px 18px; border-radius: 14px; background: var(--panel-2); border: 1px dashed var(--line); font-size: 0.88rem; color: var(--muted); }
    .featured-site a { color: var(--accent); font-weight: 600; }
    footer { margin-top: 36px; font-size: 0.78rem; color: var(--muted); opacity: 0.8; }
  </style>
</head>
<body>
  <button class="theme-btn" id="theme-btn" aria-label="Toggle theme">◐</button>
  <main>
    <div class="av" aria-hidden="true">{$initial}</div>
    <div class="badge">Verified Creator</div>
    <h1>{$title}</h1>
    <p class="bio">{$text}</p>
    <div class="links">
      {$items}
    </div>
    <div class="featured-site">
      Looking for software development or keynotes? <br>
      <a href="/techunicorn">View Full Tech Unicorn Portfolio ↗</a>
    </div>
    <footer>© 2026 Tech Unicorn · Crafted with Creator Studio</footer>
  </main>
  <script>
    const btn = document.getElementById('theme-btn');
    btn?.addEventListener('click', () => {
      const cur = document.documentElement.getAttribute('data-theme') || 'dark';
      const next = cur === 'dark' ? 'light' : 'dark';
      document.documentElement.setAttribute('data-theme', next);
    });
  </script>
</body>
</html>
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
