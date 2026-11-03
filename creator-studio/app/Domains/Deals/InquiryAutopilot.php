<?php

namespace App\Domains\Deals;

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DealRoomController;
use App\Models\Deal;
use App\Models\Setting;
use Illuminate\Support\Str;

/**
 * Brand inquiries from the website, triaged by her own rules (no AI guessing):
 * minimum budget, blocked words (categories she won't promote), minimum lead time.
 * The verdict is pursue / review / pass, with the reasons. Then, if she turned it on:
 * - pursue → a reply with her "starting from" price (only if she set one), her media kit
 *   link and the booking link;
 * - pass → a polite "not right now";
 * - review → nothing is sent; it waits in Needs you.
 * In "draft" mode every reply waits for her approval instead.
 */
class InquiryAutopilot
{
    public const BUDGETS = ['under_1k' => 1000, '1k_3k' => 3000, '3k_10k' => 10000, '10k_plus' => 25000];

    public static function rules(): array
    {
        $saved = json_decode((string)Setting::get('inquiry_rules', ''), true) ?: [];

        return [
            'enabled' => (bool)($saved['enabled'] ?? false),
            'mode' => in_array($saved['mode'] ?? 'draft', ['draft', 'send'], true) ? ($saved['mode'] ?? 'draft') : 'draft',
            'project_types' => array_values((array)($saved['project_types'] ?? ['brand_deal'])),
            'min_budget' => max(0, (int)($saved['min_budget'] ?? 0)),
            'blocked_words' => array_values(array_filter(array_map(fn ($w) => mb_strtolower(trim((string)$w)), (array)($saved['blocked_words'] ?? [])))),
            'min_lead_days' => max(0, (int)($saved['min_lead_days'] ?? 0)),
            'starting_price' => (string)($saved['starting_price'] ?? ''),
            'pursue_note' => (string)($saved['pursue_note'] ?? ''),
            'pass_note' => (string)($saved['pass_note'] ?? 'Thank you for thinking of me! I’m not able to take this one on right now, but I’ll keep your details in case that changes.'),
        ];
    }

    /**
     * @return array{score: int, verdict: string, reasons: array<int, string>}
     */
    public static function score(Deal $deal, array $rules): array
    {
        $score = 50;
        $reasons = [];
        $text = mb_strtolower(implode(' ', [$deal->brand_name, $deal->notes, $deal->inquiry_product_url, $deal->inquiry_deliverables]));
        foreach ($rules['blocked_words'] as $w) {
            if ($w !== '' && str_contains($text, $w)) {
                return ['score' => 0, 'verdict' => 'pass', 'reasons' => ["Mentions “{$w}” (on your blocked list)"]];
            }
        }
        $budget = self::BUDGETS[$deal->inquiry_budget] ?? null;
        if ($rules['min_budget'] > 0) {
            if ($budget === null) {
                $score -= 10;
                $reasons[] = 'No budget given';
            } elseif ($budget >= $rules['min_budget']) {
                $score += 30;
                $reasons[] = 'Budget meets your minimum';
            } else {
                $score -= 40;
                $reasons[] = 'Budget below your minimum';
            }
        }
        if ($rules['min_lead_days'] > 0 && $deal->inquiry_timeline) {
            $days = self::leadDays($deal->inquiry_timeline);
            if ($days !== null && $days < $rules['min_lead_days']) {
                $score -= 20;
                $reasons[] = "Needs it in {$days} days (you need {$rules['min_lead_days']})";
            } elseif ($days !== null) {
                $score += 10;
                $reasons[] = 'Enough lead time';
            }
        }
        if ($deal->inquiry_product_url) {
            $score += 10;
            $reasons[] = 'Product link given';
        }
        $score = max(0, min(100, $score));

        return ['score' => $score, 'verdict' => $score >= 70 ? 'pursue' : ($score < 40 ? 'pass' : 'review'), 'reasons' => $reasons];
    }

    /**
     * Run on a new website inquiry.
     */
    public static function handle(Deal $deal, string $projectType): void
    {
        $rules = self::rules();
        if (!$rules['enabled'] || !in_array($projectType, $rules['project_types'], true)) {
            return;
        }
        $s = self::score($deal, $rules);
        $deal->forceFill(['fit_score' => $s['score'], 'fit_verdict' => $s['verdict'], 'fit_reasons' => $s['reasons']])->saveQuietly();
        if ($s['verdict'] === 'review') {
            return;
        }
        $body = $s['verdict'] === 'pursue' ? self::pursueBody($deal, $rules) : self::passBody($deal, $rules);
        if ($rules['mode'] === 'send') {
            self::send($deal, $body);
        } else {
            $deal->forceFill(['auto_reply' => 'draft', 'auto_reply_body' => $body])->saveQuietly();
        }
    }

    public static function send(Deal $deal, string $body): bool
    {
        $ok = $deal->contact_email ? AuthController::mail($deal->contact_email, 'Re: your inquiry', $body) : false;
        $deal->forceFill(['auto_reply' => $ok ? 'sent' : 'draft', 'auto_reply_body' => $body, 'last_contacted_at' => $ok ? now() : $deal->last_contacted_at])->saveQuietly();
        if ($ok && $deal->fit_verdict === 'pass') {
            $deal->forceFill(['stage' => 'lost', 'lost_reason' => 'Not a fit (autopilot)'])->save();
        }

        return $ok;
    }

    public static function pursueBody(Deal $deal, array $rules): string
    {
        if (!$deal->kit_token) {
            $deal->forceFill(['kit_token' => Str::random(32), 'kit_expires_at' => now()->addDays(DealRoomController::LINK_DAYS)])->saveQuietly();
        }
        $name = trim(explode(' ', (string)$deal->contact_name)[0]) ?: 'there';
        $lines = [
            "Hi {$name},",
            '',
            'Thanks for reaching out about a partnership: this sounds like a good fit.',
            $rules['starting_price'] !== '' ? "Sponsorships start from {$rules['starting_price']}; the final price depends on the deliverables." : null,
            '',
            'My media kit (audience, recent results, formats): ' . url('/kit/' . $deal->kit_token),
            'Book a 20-minute call to talk details: ' . url('/#book'),
            $rules['pursue_note'] !== '' ? "\n" . $rules['pursue_note'] : null,
            '',
            '— ' . \App\Services\Prompts::name(),
        ];

        return implode("\n", array_filter($lines, fn ($l) => $l !== null));
    }

    public static function passBody(Deal $deal, array $rules): string
    {
        $name = trim(explode(' ', (string)$deal->contact_name)[0]) ?: 'there';

        return "Hi {$name},\n\n{$rules['pass_note']}\n\n— " . \App\Services\Prompts::name();
    }

    /**
     * "in 2 weeks", "10 days", "next month", a date… → days from now (null if unclear).
     */
    public static function leadDays(string $timeline): ?int
    {
        $t = mb_strtolower($timeline);
        if (preg_match('/(\d+)\s*(day|week|month)/', $t, $m)) {
            return (int)$m[1] * ['day' => 1, 'week' => 7, 'month' => 30][$m[2]];
        }
        if (str_contains($t, 'asap') || str_contains($t, 'urgent')) {
            return 3;
        }
        if (str_contains($t, 'next month')) {
            return 30;
        }
        $date = rescue(fn () => \Carbon\CarbonImmutable::parse($timeline), null, false);

        return $date ? max(0, (int)now()->startOfDay()->diffInDays($date->startOfDay(), false)) : null;
    }

    public static function rateCardStale(): bool
    {
        $at = Setting::get('rate_card_reviewed_at');

        return !$at || \Carbon\CarbonImmutable::parse($at)->lt(now()->subDays(90));
    }
}
