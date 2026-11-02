<?php

namespace App\Domains\Agents;

use App\Domains\Agents\Models\AgentEvent;
use App\Domains\Agents\Models\SocialAgent;
use App\Domains\Social\Models\SocialPost;
use App\Services\Llm;
use App\Services\Prompts;
use RuntimeException;

/**
 * How an agent improves: it compares its best and worst posts by engagement rate (numbers
 * come from connected accounts or her imports), looks at how she rewrote its drafts and why
 * she turned some down, and distils that into a short list of lessons it follows next time.
 */
class AgentLearner
{
    public const MIN_SIGNALS = 4;

    public function __construct(
        private Llm $llm
    ) {
    }

    /**
     * Enough to learn from? [posts with numbers, edits, rejections].
     */
    public static function signals(SocialAgent $agent): array
    {
        $measured = SocialPost::where('social_agent_id', $agent->id)->where('status', 'posted')->get()
            ->filter(fn ($p) => ($p->engagement['total'] ?? null) !== null)->values();
        $edited = SocialPost::where('social_agent_id', $agent->id)->whereNotNull('agent_original')->where('approval', 'approved')->get()
            ->filter(fn ($p) => self::similarity($p->agent_original, trim($p->body . "\n\n" . implode("\n\n", $p->parts ?? []))) < 0.9)->values();
        $rejected = AgentEvent::where('social_agent_id', $agent->id)->where('type', 'rejected')->latest()->limit(15)->pluck('message');

        return [$measured, $edited, $rejected];
    }

    public function learn(SocialAgent $agent): array
    {
        [$measured, $edited, $rejected] = self::signals($agent);
        if ($measured->count() + $edited->count() + $rejected->count() < self::MIN_SIGNALS) {
            throw new RuntimeException('Not enough to learn from yet: it needs at least ' . self::MIN_SIGNALS . ' posts with numbers, edits or rejections (has ' . ($measured->count() + $edited->count() + $rejected->count()) . ').');
        }

        // Rank by engagement rate where impressions are known, else by total engagement, per network.
        $score = fn ($p) => $p->engagement['rate'] ?? ($p->engagement['total'] ?? 0) / 100;
        $sorted = $measured->sortByDesc($score)->values();
        $fmt = fn ($p) => "[{$p->platform}] rate " . ($p->engagement['rate'] ?? '?') . "%, {$p->engagement['total']} engagements: " . mb_substr(str_replace("\n", ' ', $p->asText()), 0, 400);
        $half = (int)ceil($sorted->count() / 2);
        $top = $sorted->take(min(5, $half))->map($fmt)->implode("\n");
        $bottom = $sorted->count() > 1 ? $sorted->reverse()->take(min(5, $sorted->count() - $half))->map($fmt)->implode("\n") : '';
        $edits = $edited->take(6)->map(fn ($p) => 'Agent wrote: ' . mb_substr(str_replace("\n", ' ', $p->agent_original), 0, 300) . "\nShe changed it to: " . mb_substr(str_replace("\n", ' ', $p->asText()), 0, 300))->implode("\n\n");
        $old = $agent->lessons ? implode("\n", array_map(fn ($l) => '- ' . $l['text'], $agent->lessons)) : '(none yet)';

        $data = $this->llm->as('agent-learn')->json(
            'You coach a social media agent that writes for ' . Prompts::name() . '. From the evidence, write the lessons it should follow from now on. '
            . 'Each lesson is one specific, actionable instruction (what to do or avoid, e.g. "Open LinkedIn posts with a concrete result, not a question"), with the evidence in a few words. '
            . 'Keep earlier lessons that the evidence still supports, change or drop the rest. 3 to 8 lessons. Don’t claim patterns from a single post; say "early signal" when evidence is thin. '
            . 'Output JSON: {"lessons": [{"text": "...", "evidence": "..."}], "summary": "one sentence on what changed"}',
            "Earlier lessons:\n{$old}\n\nBest performing posts:\n" . ($top ?: '(none)') . "\n\nWeakest posts:\n" . ($bottom ?: '(none)')
            . "\n\nHer edits to its drafts:\n" . ($edits ?: '(none)') . "\n\nPosts she rejected:\n" . ($rejected->implode("\n") ?: '(none)'),
        );

        $lessons = array_values(array_filter(array_map(fn ($l) => is_array($l) && trim((string)($l['text'] ?? '')) !== ''
            ? [
                'text' => mb_substr(trim($l['text']), 0, 300),
                'evidence' => mb_substr(trim((string)($l['evidence'] ?? '')), 0, 300),
            ] : null, (array)($data['lessons'] ?? []))));
        if (!$lessons) {
            throw new RuntimeException('The AI didn’t return any lessons. Try again.');
        }
        $lessons = array_slice($lessons, 0, 8);
        $agent->update([
            'lessons' => $lessons,
            'learned_at' => now(),
        ]);
        AgentEvent::log('learned', trim(($data['summary'] ?? '') ?: 'Updated what it has learned.') . ' (' . count($lessons) . ' lessons, from '
            . $measured->count() . ' measured posts, ' . $edited->count() . ' edits, ' . $rejected->count() . ' rejections)', $agent->id);

        return $lessons;
    }

    /**
     * 0..1 how alike two texts are (cheap word overlap).
     */
    public static function similarity(?string $a, ?string $b): float
    {
        $wa = array_count_values(str_word_count(mb_strtolower((string)$a), 1));
        $wb = array_count_values(str_word_count(mb_strtolower((string)$b), 1));
        if (!$wa && !$wb) {
            return 1.0;
        }
        $common = 0;
        foreach ($wa as $w => $n) {
            $common += min($n, $wb[$w] ?? 0);
        }

        return 2 * $common / (array_sum($wa) + array_sum($wb));
    }

    /**
     * Median engagement rate of an agent's posts, per 30-day window (oldest first) for the trend line.
     */
    public static function trend(SocialAgent $agent, int $windows = 4): array
    {
        $out = [];
        for ($i = $windows - 1; $i >= 0; $i--) {
            $from = now()->subDays(30 * ($i + 1));
            $to = now()->subDays(30 * $i);
            $rates = SocialPost::where('social_agent_id', $agent->id)->where('status', 'posted')->whereBetween('posted_at', [$from, $to])->get()
                ->map(fn ($p) => $p->engagement['rate'] ?? null)->filter(fn ($r) => $r !== null)->sort()->values();
            $n = $rates->count();
            $out[] = [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'posts' => $n,
                'median_rate' => $n ? round($n % 2 ? $rates[intdiv($n, 2)] : ($rates[$n / 2 - 1] + $rates[$n / 2]) / 2, 2) : null,
            ];
        }

        return $out;
    }
}
