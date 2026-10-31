<?php

namespace App\Domains\Social;

use App\Domains\Social\Models\SocialPost;
use App\Services\Llm;
use App\Services\Prompts;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * "Post score": how a draft is likely to do, before it goes out.
 *
 * With 8+ of her own posts with numbers on that network, the score is a prediction from her
 * history: the draft is compared with her past posts on simple features (hook length, length,
 * hashtags, question, number in the hook, format, time block, pillar, media) and the most
 * similar ones vote, weighted by similarity. The score is where that prediction lands among her
 * own posts (50 = her usual). With less history it's a rule-of-thumb score and says so.
 * Rules of thumb are general guidance, not her data; the checklist labels them that way.
 */
class PostScorer
{
    public const MIN_HISTORY = 8;

    private const K = 5;

    /** Rough length sweet spots per network (characters, rules of thumb). */
    private const SWEET = [
        'linkedin' => [600, 1800], 'instagram' => [150, 1200], 'x' => [70, 260], 'threads' => [80, 400], 'tiktok' => [40, 150], 'youtube' => [60, 600],
    ];

    public function __construct(private Llm $llm) {}

    /**
     * @param  array{platform: string, format?: string, body?: ?string, parts?: ?array, scheduled_for?: ?string, pillar?: ?string, media?: int, post_id?: ?int}  $draft
     */
    public function score(array $draft): array
    {
        $platform = $draft['platform'];
        $f = self::features($draft);
        $history = $this->history($platform, $draft['post_id'] ?? null);
        $checks = $this->checks($draft, $f);

        $data = null;
        if ($history->count() >= self::MIN_HISTORY) {
            $data = $this->predict($f, $history);
        }
        if ($data) {
            $score = $data['percentile'];
            $basis = 'your data';
        } else {
            // Rule-of-thumb score: start at 50, move with the checklist.
            $score = 50 + array_sum(array_map(fn ($c) => ['good' => 8, 'warn' => -8, 'bad' => -15][$c['status']] ?? 0, $checks));
            $basis = 'rules of thumb';
        }
        $score = (int) max(1, min(99, round($score)));
        // With her data, the words follow the predicted number vs her usual (what the sentence under it says).
        $ratio = $data && $data['usual'] > 0 ? $data['predicted'] / $data['usual'] : null;
        $label = $ratio !== null
            ? match (true) { $ratio >= 1.5 => 'Likely one of your best', $ratio >= 1.1 => 'Above your usual', $ratio >= 0.9 => 'Around your usual', $ratio >= 0.6 => 'Below your usual', default => 'Likely to underperform' }
            : match (true) { $score >= 75 => 'Looks strong', $score >= 58 => 'Looks good', $score >= 42 => 'Looks okay', default => 'Needs work' };

        return [
            'score' => $score,
            'label' => $label,
            'basis' => $basis,
            'history' => $history->count(),
            'needed' => self::MIN_HISTORY,
            'predicted' => $data['predicted'] ?? null,
            'usual' => $data['usual'] ?? null,
            'metric' => $data['metric'] ?? null,
            'similar' => $data['similar'] ?? [],
            'checks' => $checks,
        ];
    }

    /** Hook lab: 5 rewrites of the first line, each scored, best first. */
    public function hooks(array $draft): array
    {
        $text = self::text($draft);
        [$hook, $rest] = self::splitHook($text);
        if (mb_strlen(trim($text)) < 20) {
            throw new \RuntimeException('Write a bit more first: the hook lab rewrites your opening line.');
        }
        $label = Platforms::PLATFORMS[$draft['platform']]['label'] ?? $draft['platform'];
        $fold = Platforms::PLATFORMS[$draft['platform']]['fold'] ?? null;
        $data = $this->llm->as('hooks')->creativity(0.9)->json(
            "You write opening lines (hooks) for ".Prompts::name()."'s {$label} posts.\n\n".Prompts::profile()."\n\n".Prompts::voice()."\n\n".Prompts::writingRules()."\n\n"
            ."Write 5 different hooks for the post below, each a different style: a concrete result or number, a bold claim, a short story opener, a question, a contrarian take. "
            .($fold ? "Each stays under {$fold} characters (the “see more” cut-off). " : 'Keep each short. ')
            .'Only claims the post itself supports; never invent numbers. Output JSON: {"hooks": [{"style": "result", "text": "..."}]}',
            "Current hook: {$hook}\n\nRest of the post:\n".mb_substr($rest, 0, 4000),
        );
        $out = [];
        foreach ((array) ($data['hooks'] ?? []) as $hk) {
            $line = trim((string) ($hk['text'] ?? ''), " \"\n");
            if ($line === '') {
                continue;
            }
            $variant = $draft;
            $variant['body'] = trim($line."\n".$rest);
            $variant['parts'] = $draft['parts'] ?? [];
            if (trim((string) ($draft['body'] ?? '')) === '' && ! empty($variant['parts'])) {
                $variant['parts'][0] = trim($line."\n".self::splitHook($variant['parts'][0])[1]);
                $variant['body'] = '';
            }
            $s = $this->score($variant);
            $out[] = ['style' => (string) ($hk['style'] ?? ''), 'text' => $line, 'score' => $s['score'], 'label' => $s['label']];
        }
        usort($out, fn ($a, $b) => $b['score'] <=> $a['score']);

        return ['current' => ['text' => $hook, 'score' => $this->score($draft)['score']], 'hooks' => $out];
    }

    // ------------------------------------------------------------ features & prediction

    public static function text(array $d): string
    {
        $body = trim((string) ($d['body'] ?? ''));
        $parts = array_values(array_filter(array_map('strval', (array) ($d['parts'] ?? []))));

        return trim($body !== '' ? $body."\n\n".implode("\n\n", $parts) : implode("\n\n", $parts));
    }

    /** [first line, the rest] */
    public static function splitHook(string $text): array
    {
        $text = trim($text);
        $pos = mb_strpos($text, "\n");

        return $pos === false ? [$text, ''] : [trim(mb_substr($text, 0, $pos)), trim(mb_substr($text, $pos + 1))];
    }

    public static function features(array $d): array
    {
        $text = self::text($d);
        [$hook] = self::splitHook($text);
        $at = ! empty($d['scheduled_for']) ? CarbonImmutable::parse($d['scheduled_for'])->setTimezone(SocialAnalytics::tz()) : null;
        $hour = $at ? ($at->hour < 6 ? $at->hour + 24 : $at->hour) : null;
        $block = null;
        foreach (SocialAnalytics::BLOCKS as $b => [$from, $to]) {
            if ($hour !== null && $hour >= $from && $hour < $to) {
                $block = $b;
            }
        }
        $lastLine = trim((string) array_slice(preg_split('/\R/', $text), -1)[0]);

        return [
            'len' => mb_strlen($text),
            'hook_len' => mb_strlen($hook),
            'hashtags' => preg_match_all('/(^|\s)#[\p{L}\p{N}_]+/u', $text),
            'question' => str_contains($lastLine, '?') || str_contains(mb_substr($text, -200), '?') ? 1 : 0,
            'number_hook' => preg_match('/\d/', $hook) ? 1 : 0,
            'emoji' => preg_match_all('/[\x{1F300}-\x{1FAFF}\x{2600}-\x{27BF}]/u', $text),
            'lines' => substr_count($text, "\n") + 1,
            'link' => preg_match('~https?://|www\.~i', $text) ? 1 : 0,
            'format' => $d['format'] ?? null,
            'block' => $block,
            'weekday' => $at?->dayOfWeekIso,
            'pillar' => $d['pillar'] ?? null,
            'media' => (int) ($d['media'] ?? 0) > 0 ? 1 : 0,
        ];
    }

    private function history(string $platform, ?int $except): Collection
    {
        return SocialPost::where('platform', $platform)->where('status', 'posted')->whereNotNull('posted_at')
            ->when($except, fn ($q) => $q->where('id', '!=', $except))->latest('posted_at')->limit(300)->get()
            ->filter(fn ($p) => $p->engagement !== null && trim($p->asText()) !== '')->values();
    }

    private function predict(array $f, Collection $history): ?array
    {
        $rate = $history->filter(fn ($p) => ($p->engagement['rate'] ?? null) !== null)->count() >= $history->count() * 0.7;
        $value = fn ($p) => $rate ? ($p->engagement['rate'] ?? null) : ($p->engagement['total'] ?? null);
        $rows = $history->map(fn ($p) => ['post' => $p, 'v' => $value($p), 'f' => self::features([
            'body' => $p->body, 'parts' => $p->parts, 'format' => $p->format, 'pillar' => $p->pillar, 'media' => count($p->media ?? []),
            'scheduled_for' => $p->posted_at?->toIso8601String(),
        ])])->filter(fn ($r) => $r['v'] !== null)->values();
        if ($rows->count() < self::MIN_HISTORY) {
            return null;
        }
        // Scale numeric features by their spread in her history, so no one feature dominates.
        $num = ['len', 'hook_len', 'hashtags', 'emoji', 'lines'];
        $spread = [];
        foreach ($num as $k) {
            $vals = $rows->pluck("f.{$k}")->all();
            $spread[$k] = max(1, (max($vals) - min($vals)) ?: 1);
        }
        $dist = function (array $a, array $b) use ($num, $spread) {
            $d = 0;
            foreach ($num as $k) {
                $d += (($a[$k] - $b[$k]) / $spread[$k]) ** 2;
            }
            foreach (['question', 'number_hook', 'link', 'media'] as $k) {
                $d += $a[$k] === $b[$k] ? 0 : 0.35;
            }
            foreach (['format' => 0.6, 'block' => 0.4, 'pillar' => 0.5, 'weekday' => 0.15] as $k => $w) {
                if ($a[$k] !== null && $b[$k] !== null) {
                    $d += $a[$k] === $b[$k] ? 0 : $w;
                }
            }

            return sqrt($d);
        };
        $near = $rows->map(fn ($r) => $r + ['d' => $dist($f, $r['f'])])->sortBy('d')->take(self::K)->values();
        $wsum = $near->sum(fn ($r) => 1 / (0.15 + $r['d']));
        $predicted = $near->sum(fn ($r) => $r['v'] / (0.15 + $r['d'])) / $wsum;

        $sorted = $rows->pluck('v')->sort()->values();
        $below = $sorted->filter(fn ($v) => $v < $predicted)->count();
        $equal = $sorted->filter(fn ($v) => $v == $predicted)->count();
        $percentile = ($below + 0.5 * $equal) / $sorted->count() * 100;
        $n = $sorted->count();
        $usual = $n % 2 ? $sorted[intdiv($n, 2)] : ($sorted[$n / 2 - 1] + $sorted[$n / 2]) / 2;

        return [
            'percentile' => $percentile, 'predicted' => round($predicted, 2), 'usual' => round($usual, 2),
            'metric' => $rate ? 'engagement rate (%)' : 'engagements',
            'similar' => $near->take(3)->map(fn ($r) => [
                'id' => $r['post']->id, 'title' => mb_substr($r['post']->title ?: str_replace("\n", ' ', $r['post']->asText()), 0, 90),
                'value' => round($r['v'], 2), 'posted_at' => $r['post']->posted_at?->toIso8601String(),
            ])->all(),
        ];
    }

    /** Checklist: status good / warn / bad / info, with where each rule comes from. */
    private function checks(array $d, array $f): array
    {
        $p = $d['platform'];
        $fold = Platforms::PLATFORMS[$p]['fold'] ?? null;
        $out = [];
        if ($f['len'] === 0) {
            return [['status' => 'bad', 'text' => 'Nothing written yet.', 'source' => 'check']];
        }
        if ($fold) {
            $out[] = $f['hook_len'] <= $fold
                ? ['status' => 'good', 'text' => "The hook fits before “see more” ({$f['hook_len']}/{$fold} characters).", 'source' => 'platform']
                : ['status' => 'warn', 'text' => "The first line runs past “see more” ({$f['hook_len']}/{$fold}): shorten it so the hook shows in full.", 'source' => 'platform'];
        }
        if (isset(self::SWEET[$p]) && ! in_array($d['format'] ?? '', ['thread', 'carousel', 'story', 'reel', 'short', 'video', 'photo'], true)) {
            [$lo, $hi] = self::SWEET[$p];
            $out[] = $f['len'] < $lo ? ['status' => 'warn', 'text' => "Short for ".Platforms::PLATFORMS[$p]['label']." ({$f['len']} characters); posts of {$lo}–{$hi} tend to carry more.", 'source' => 'rule of thumb']
                : ($f['len'] > $hi ? ['status' => 'warn', 'text' => "Long ({$f['len']} characters); {$lo}–{$hi} usually reads better.", 'source' => 'rule of thumb']
                    : ['status' => 'good', 'text' => 'A good length for this network.', 'source' => 'rule of thumb']);
        }
        if (in_array($p, ['linkedin', 'threads', 'instagram'], true)) {
            $out[] = $f['question'] ? ['status' => 'good', 'text' => 'Ends with a question that invites replies.', 'source' => 'rule of thumb']
                : ['status' => 'info', 'text' => 'No question near the end; one often brings more comments.', 'source' => 'rule of thumb'];
        }
        if ($p === 'instagram' && $f['hashtags'] > 5) {
            $out[] = ['status' => 'warn', 'text' => "{$f['hashtags']} hashtags: Instagram announced a 5-hashtag cap.", 'source' => 'platform'];
        } elseif (in_array($p, ['linkedin', 'x', 'threads'], true) && $f['hashtags'] > 3) {
            $out[] = ['status' => 'warn', 'text' => "{$f['hashtags']} hashtags: more than 3 looks spammy here.", 'source' => 'rule of thumb'];
        }
        if ($p === 'linkedin' && $f['link']) {
            $out[] = ['status' => 'warn', 'text' => 'A link in a LinkedIn post is widely reported to cut its reach; put it in the first comment.', 'source' => 'rule of thumb'];
        }
        $text = mb_strtolower(self::text($d));
        $used = array_values(array_filter(\App\Http\Controllers\Api\BrandController::bannedWords(), fn ($w) => preg_match('/(^|[^\p{L}])'.preg_quote(mb_strtolower($w), '/').'($|[^\p{L}])/u', $text)));
        if ($used) {
            $out[] = ['status' => 'warn', 'text' => 'Uses words you avoid: '.implode(', ', $used).'.', 'source' => 'your brand kit'];
        }
        if ($f['number_hook']) {
            $out[] = ['status' => 'good', 'text' => 'A number in the hook makes it concrete.', 'source' => 'rule of thumb'];
        }
        // Time slot, from her own best times when she has them.
        if ($f['block'] !== null && ($bt = SocialAnalytics::bestTimes($p)) && ($bt['enough'] ?? false)) {
            // Time of day across the week (single day/time cells are too sparse to judge).
            $blockAvg = [];
            foreach (array_keys(SocialAnalytics::BLOCKS) as $b) {
                $vals = array_filter(array_map(fn ($day) => $day[$b]['median'], $bt['grid']), fn ($v) => $v !== null);
                if ($vals) {
                    $blockAvg[$b] = array_sum($vals) / count($vals);
                }
            }
            $top = $blockAvg ? max($blockAvg) : null;
            if (isset($blockAvg[$f['block']]) && $top > 0) {
                $ratio = $blockAvg[$f['block']] / $top;
                $out[] = $ratio >= 0.8 ? ['status' => 'good', 'text' => 'Scheduled in one of your strongest time slots.', 'source' => 'your data']
                    : ($ratio < 0.5 ? ['status' => 'warn', 'text' => 'This time of day has been weak for you; “Next good slot” finds a better one.', 'source' => 'your data']
                        : ['status' => 'info', 'text' => 'An average time of day for you.', 'source' => 'your data']);
            } else {
                $out[] = ['status' => 'info', 'text' => 'You haven’t posted at this time of day yet, so there’s no data on it.', 'source' => 'your data'];
            }
        } elseif (empty($d['scheduled_for'])) {
            $out[] = ['status' => 'info', 'text' => 'Not scheduled yet; the time is part of the score once it is.', 'source' => 'check'];
        }

        return $out;
    }
}
