<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Her affiliate links: /go/{slug} redirects (clicks counted per day), what each partner paid
 * (entered or imported from the partner's CSV, so earnings are real), and a weekly check
 * for links that broke.
 */
class AffiliateController extends Controller
{
    public const PLACEMENTS = ['video', 'post', 'newsletter', 'bio', 'other'];

    /**
     * Public: count the click and send the visitor on.
     */
    public function go(string $slug): RedirectResponse
    {
        $link = DB::table('affiliate_links')->where('slug', strtolower($slug))->where('active', true)->first();
        abort_unless($link, 404);
        DB::table('affiliate_links')->where('id', $link->id)->increment('clicks');
        DB::table('affiliate_clicks')->upsert([['link_id' => $link->id, 'day' => now()->toDateString(), 'count' => 1]], ['link_id', 'day'], ['count' => DB::raw('count + 1')]);

        return redirect()->away($link->url);
    }

    public function index(): JsonResponse
    {
        $since = now()->subDays(30)->toDateString();
        $clicks30 = DB::table('affiliate_clicks')->where('day', '>=', $since)->select('link_id', DB::raw('SUM(count) as n'))->groupBy('link_id')->pluck('n', 'link_id');
        $earned = DB::table('affiliate_earnings')->whereNotNull('link_id')->select('link_id', DB::raw('SUM(amount) as total'))->groupBy('link_id')->pluck('total', 'link_id');

        return response()->json([
            'links' => DB::table('affiliate_links')->orderByDesc('id')->get()->map(fn ($l) => (array)$l + [
                'short_url' => url('/go/' . $l->slug),
                'clicks_30d' => (int)($clicks30[$l->id] ?? 0),
                'earned' => (float)($earned[$l->id] ?? 0),
            ]),
            'earnings' => DB::table('affiliate_earnings')->leftJoin('affiliate_links', 'affiliate_links.id', '=', 'affiliate_earnings.link_id')
                ->orderByDesc('earned_on')->take(100)->get(['affiliate_earnings.*', 'affiliate_links.slug']),
            'totals' => [
                'earned_30d' => (float)DB::table('affiliate_earnings')->where('earned_on', '>=', $since)->sum('amount'),
                'earned_all' => (float)DB::table('affiliate_earnings')->sum('amount'),
                'clicks_30d' => (int)DB::table('affiliate_clicks')->where('day', '>=', $since)->sum('count'),
                'broken' => DB::table('affiliate_links')->where('active', true)->where('broken', true)->count(),
            ],
            'placements' => self::PLACEMENTS,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request, true);
        $data['slug'] = $this->uniqueSlug($data['slug'] ?? ($data['partner'] ?? parse_url($data['url'], PHP_URL_HOST) ?? 'link'));
        DB::table('affiliate_links')->insert($data + ['created_at' => now(), 'updated_at' => now()]);

        return $this->index();
    }

    public function update(Request $request, int $id): JsonResponse
    {
        abort_unless(DB::table('affiliate_links')->where('id', $id)->exists(), 404);
        $data = $this->validated($request, false);
        if (isset($data['slug'])) {
            $data['slug'] = $this->uniqueSlug($data['slug'], $id);
        }
        if (isset($data['url'])) {
            $data['broken'] = false;
            $data['last_status'] = null;
        }
        DB::table('affiliate_links')->where('id', $id)->update($data + ['updated_at' => now()]);

        return $this->index();
    }

    public function destroy(int $id): JsonResponse
    {
        DB::table('affiliate_links')->where('id', $id)->delete();

        return $this->index();
    }

    public function addEarning(Request $request): JsonResponse
    {
        $data = $request->validate([
            'link_id' => 'nullable|integer|exists:affiliate_links,id',
            'partner' => 'nullable|string|max:120',
            'amount' => 'required|numeric|min:-100000|max:1000000',
            'currency' => 'nullable|string|size:3',
            'earned_on' => 'required|date',
            'note' => 'nullable|string|max:200',
        ]);
        DB::table('affiliate_earnings')->insert($this->earningRow($data));

        return $this->index();
    }

    public function deleteEarning(int $id): JsonResponse
    {
        DB::table('affiliate_earnings')->where('id', $id)->delete();

        return $this->index();
    }

    /**
     * A partner's payout CSV: date, link slug or partner, amount[, currency, note]. Header
     * row optional. Rows that don't parse are reported, not guessed.
     */
    public function import(Request $request): JsonResponse
    {
        $request->validate(['file' => 'required|file|extensions:csv,txt|max:2048']);
        $rows = array_map('str_getcsv', preg_split('/\R/', trim((string)file_get_contents($request->file('file')->getRealPath()))));
        $slugs = DB::table('affiliate_links')->pluck('id', 'slug');
        $added = 0;
        $skipped = [];
        foreach ($rows as $i => $r) {
            if (count($r) < 3 || ($i === 0 && !is_numeric(str_replace([',', '$', '€', '£'], '', $r[2])))) {
                if ($i > 0) {
                    $skipped[] = $i + 1;
                }

                continue;
            }
            $date = rescue(fn () => \Carbon\CarbonImmutable::parse(trim($r[0]))->toDateString(), null, false);
            $amount = (float)str_replace([',', '$', '€', '£', ' '], '', $r[2]);
            if (!$date) {
                $skipped[] = $i + 1;

                continue;
            }
            $who = trim($r[1]);
            $linkId = $slugs[strtolower($who)] ?? null;
            DB::table('affiliate_earnings')->insert($this->earningRow([
                'link_id' => $linkId,
                'partner' => $linkId ? null : mb_substr($who, 0, 120),
                'amount' => $amount,
                'currency' => $r[3] ?? null,
                'earned_on' => $date,
                'note' => isset($r[4]) ? mb_substr($r[4], 0, 200) : 'CSV import',
            ]));
            $added++;
        }

        return response()->json(['added' => $added, 'skipped_rows' => array_slice($skipped, 0, 20)] + $this->index()->getData(true));
    }

    public function checkNow(): JsonResponse
    {
        $broken = self::checkLinks();

        return response()->json(['broken' => $broken] + $this->index()->getData(true));
    }

    /**
     * Weekly: fetch each active link; 4xx/5xx or no answer = broken. Emails her about newly
     * broken ones. Returns how many are broken now. Private/loopback hosts are never fetched.
     */
    public static function checkLinks(): int
    {
        $newly = [];
        foreach (DB::table('affiliate_links')->where('active', true)->get() as $l) {
            $status = self::probe($l->url);
            $isBroken = $status === 0 || $status >= 400;
            if ($isBroken && !$l->broken) {
                $newly[] = "/go/{$l->slug} → {$l->url} (" . ($status ?: 'no answer') . ')';
            }
            DB::table('affiliate_links')->where('id', $l->id)->update(['broken' => $isBroken, 'last_status' => $status ?: null, 'checked_at' => now()]);
        }
        if ($newly) {
            PortalController::tellAdmins('Affiliate links broke', "These links don’t work any more:\n\n" . implode("\n", $newly) . "\n\nFix them: " . url('/affiliates'));
        }

        return DB::table('affiliate_links')->where('active', true)->where('broken', true)->count();
    }

    private static function probe(string $url): int
    {
        $host = (string)parse_url($url, PHP_URL_HOST);
        $ip = $host !== '' ? gethostbyname($host) : '';
        if ($ip === '' || !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return 0;
        }
        try {
            $r = Http::timeout(10)->withHeaders(['User-Agent' => 'Mozilla/5.0 (link check)'])->withOptions(['allow_redirects' => ['max' => 5]])->head($url);
            if ($r->status() === 405 || $r->status() === 403) {
                $r = Http::timeout(10)->withHeaders(['User-Agent' => 'Mozilla/5.0 (link check)'])->get($url);
            }

            return $r->status();
        } catch (\Throwable) {
            return 0;
        }
    }

    private function earningRow(array $d): array
    {
        return [
            'link_id' => $d['link_id'] ?? null,
            'partner' => $d['partner'] ?? null,
            'amount' => round((float)$d['amount'], 2),
            'currency' => strtoupper($d['currency'] ?? 'USD') ?: 'USD',
            'earned_on' => $d['earned_on'],
            'note' => $d['note'] ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    private function validated(Request $request, bool $creating): array
    {
        return $request->validate([
            'url' => [$creating ? 'required' : 'sometimes', 'url:http,https', 'max:1000'],
            'slug' => ['nullable', 'string', 'max:60', 'regex:/^[a-zA-Z0-9-]+$/'],
            'partner' => 'nullable|string|max:120',
            'commission_note' => 'nullable|string|max:200',
            'placement' => 'nullable|in:' . implode(',', self::PLACEMENTS),
            'active' => 'sometimes|boolean',
        ]);
    }

    private function uniqueSlug(string $base, ?int $except = null): string
    {
        $slug = Str::slug($base) ?: 'link';
        $slug = substr($slug, 0, 50);
        $try = $slug;
        $n = 2;
        while (DB::table('affiliate_links')->where('slug', $try)->when($except, fn ($q) => $q->where('id', '!=', $except))->exists()) {
            $try = "{$slug}-{$n}";
            $n++;
        }

        return $try;
    }
}
