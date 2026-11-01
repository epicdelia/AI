<?php

namespace App\Domains\Deals;

use App\Domains\Landing\Pricing;
use App\Models\Deal;
use App\Models\Setting;
use App\Services\Llm;
use App\Services\Prompts;
use Throwable;

/**
 * A proposal for a website lead or a brand: the problem in their words, scope, timeline,
 * investment and next steps. The price is never the model's: it's the deal's agreed fee, or
 * the matching package from her Packages & pricing. Saved on the deal so she can edit,
 * print or send it, and add a payment link.
 */
class ProposalWriter
{
    /** Booking project type => pricing plan. */
    public const PLAN_FOR_TYPE = ['mvp_build' => 'mvp', 'retainer' => 'retainer', 'brand_deal' => 'brand', 'consulting' => 'strategy'];

    public function __construct(private Llm $llm) {}

    public static function plan(Deal $deal): ?string
    {
        $first = is_array($deal->deliverables) ? ($deal->deliverables[0] ?? []) : [];
        if (! empty($first['plan']) && isset(Pricing::PLANS[$first['plan']])) {
            return $first['plan'];
        }
        if (! empty($first['project_type']) && isset(self::PLAN_FOR_TYPE[$first['project_type']])) {
            return self::PLAN_FOR_TYPE[$first['project_type']];
        }
        foreach (Pricing::PLANS as $key => $p) {
            if (! empty($first['package']) && in_array($first['package'], [$p['package'], $p['deposit'] ?? null], true)) {
                return $key;
            }
        }

        return null;
    }

    public function write(Deal $deal, ?string $planKey = null, ?string $extra = null): array
    {
        $planKey = $planKey && isset(Pricing::PLANS[$planKey]) ? $planKey : self::plan($deal);
        $plan = $planKey ? Pricing::PLANS[$planKey] : null;
        $amount = (float) $deal->rate > 0 ? (float) $deal->rate : ($planKey ? (float) Pricing::price($planKey) : 0.0);
        $currency = $deal->currency ?: 'USD';
        $terms = $deal->payment_terms ?: match ($planKey) {
            'mvp' => '50% to start, 50% on launch',
            'retainer' => 'Billed monthly, cancel anytime',
            default => 'Due before the start date',
        };

        $context = implode("\n", array_filter([
            "Client: {$deal->brand_name}",
            $deal->contact_name ? "Contact: {$deal->contact_name}" : null,
            $plan ? "Package: {$plan['name']} ({$plan['clarify']}). {$plan['desc']}" : null,
            $plan ? 'Included: '.implode('; ', array_map(fn ($b) => str_replace('{reach}', 'native cross-platform distribution', $b[0]), array_filter($plan['benefits'], fn ($b) => $b[1] !== 'absent'))) : null,
            $plan ? 'Package facts: '.implode('; ', array_map(fn ($m) => "{$m[0]}: {$m[1]} ({$m[2]})", $plan['metrics'])) : null,
            "Investment (fixed, do not change): {$currency} ".number_format($amount, 0)." · {$terms}",
            $deal->key_talking_points ? "What they asked for: {$deal->key_talking_points}" : null,
            $deal->notes ? 'Notes from the inquiry: '.mb_substr($deal->notes, 0, 2000) : null,
            $extra ? "Her notes for this proposal: {$extra}" : null,
        ]));

        $system = Prompts::profile()."\n\n".Prompts::writingRules()."\n\nWrite a one-page client proposal from ".Prompts::name().
            '. Plain, confident, specific to this client; no hype, no guarantees of results, no invented numbers, clients or case studies. '.
            'Only promise what the package includes. Return JSON with: "title", "summary" (2-3 sentences in their terms), '.
            '"goals" (3-5 short strings), "scope" (array of {"item","detail"}), "timeline" (array of {"when","milestone"}), '.
            '"deliverables" (strings), "assumptions" (strings: what she needs from them), "next_steps" (2-4 strings).';

        $doc = null;
        try {
            $doc = $this->llm->as('deal:proposal')->json($system, $context);
        } catch (Throwable) {
            // fall back below
        }
        if (empty($doc['title']) || empty($doc['scope'])) {
            $doc = self::template($deal, $plan);
            $doc['ai'] = false;
        } else {
            $doc['ai'] = true;
        }

        $doc['client'] = $deal->brand_name;
        $doc['plan'] = $planKey;
        $doc['investment'] = ['amount' => $amount, 'currency' => $currency, 'terms' => $terms];
        $doc['valid_until'] = now(Setting::get('timezone') ?: 'UTC')->addDays(14)->toDateString();
        $doc['generated_at'] = now()->toIso8601String();

        return self::clean($doc);
    }

    /** Keep only the fields the page knows, as the right types (also used when she edits it). */
    public static function clean(array $doc): array
    {
        $strings = fn ($v) => array_values(array_filter(array_map(fn ($x) => is_scalar($x) ? mb_substr(trim((string) $x), 0, 500) : null, (array) $v)));
        $pairs = fn ($v, $a, $b) => array_values(array_filter(array_map(fn ($x) => is_array($x) && ! empty($x[$a])
            ? [$a => mb_substr((string) $x[$a], 0, 200), $b => mb_substr((string) ($x[$b] ?? ''), 0, 600)] : null, (array) $v)));

        return [
            'title' => mb_substr((string) ($doc['title'] ?? 'Proposal'), 0, 200),
            'client' => mb_substr((string) ($doc['client'] ?? ''), 0, 150),
            'plan' => $doc['plan'] ?? null,
            'summary' => mb_substr((string) ($doc['summary'] ?? ''), 0, 1500),
            'goals' => $strings($doc['goals'] ?? []),
            'scope' => $pairs($doc['scope'] ?? [], 'item', 'detail'),
            'timeline' => $pairs($doc['timeline'] ?? [], 'when', 'milestone'),
            'deliverables' => $strings($doc['deliverables'] ?? []),
            'assumptions' => $strings($doc['assumptions'] ?? []),
            'next_steps' => $strings($doc['next_steps'] ?? []),
            'investment' => [
                'amount' => round((float) ($doc['investment']['amount'] ?? 0), 2),
                'currency' => mb_substr((string) ($doc['investment']['currency'] ?? 'USD'), 0, 3),
                'terms' => mb_substr((string) ($doc['investment']['terms'] ?? ''), 0, 200),
            ],
            'valid_until' => $doc['valid_until'] ?? null,
            'generated_at' => $doc['generated_at'] ?? now()->toIso8601String(),
            'ai' => (bool) ($doc['ai'] ?? false),
            'payment_url' => $doc['payment_url'] ?? null,
        ];
    }

    public static function markdown(array $p, ?string $from = null): string
    {
        $from ??= Prompts::name();
        $money = ($p['investment']['currency'] ?? 'USD').' '.number_format((float) ($p['investment']['amount'] ?? 0));
        $list = fn (array $xs) => implode("\n", array_map(fn ($x) => "- {$x}", $xs));
        $out = ["# {$p['title']}", "Prepared for **{$p['client']}** by {$from}".($p['valid_until'] ? " · valid until {$p['valid_until']}" : ''), '', $p['summary']];
        if ($p['goals']) {
            $out = [...$out, '', '## Goals', $list($p['goals'])];
        }
        if ($p['scope']) {
            $out = [...$out, '', '## Scope', implode("\n", array_map(fn ($s) => "- **{$s['item']}**".($s['detail'] ? ": {$s['detail']}" : ''), $p['scope']))];
        }
        if ($p['timeline']) {
            $out = [...$out, '', '## Timeline', implode("\n", array_map(fn ($t) => "- **{$t['when']}**: {$t['milestone']}", $p['timeline']))];
        }
        if ($p['deliverables']) {
            $out = [...$out, '', '## Deliverables', $list($p['deliverables'])];
        }
        $out = [...$out, '', '## Investment', "**{$money}**".($p['investment']['terms'] ? " · {$p['investment']['terms']}" : '')];
        if (! empty($p['payment_url'])) {
            $out[] = "Pay securely: {$p['payment_url']}";
        }
        if ($p['assumptions']) {
            $out = [...$out, '', '## What I need from you', $list($p['assumptions'])];
        }
        if ($p['next_steps']) {
            $out = [...$out, '', '## Next steps', $list($p['next_steps'])];
        }

        return implode("\n", $out)."\n";
    }

    private static function template(Deal $deal, ?array $plan): array
    {
        $included = $plan ? array_map(fn ($b) => str_replace('{reach}', 'Native cross-platform distribution', $b[0]), array_values(array_filter($plan['benefits'], fn ($b) => $b[1] !== 'absent'))) : [];

        return [
            'title' => ($plan['name'] ?? 'Project').' proposal for '.$deal->brand_name,
            'summary' => $plan ? "{$plan['desc']} This proposal sets out the scope, timeline and investment for {$deal->brand_name}." : "Scope, timeline and investment for working together with {$deal->brand_name}.",
            'goals' => array_slice($plan['perks'] ?? ['Agree the scope', 'Ship on a clear timeline'], 0, 4),
            'scope' => array_map(fn ($b) => ['item' => $b, 'detail' => ''], $included ?: ['Kick-off call to confirm goals']),
            'timeline' => array_map(fn ($m) => ['when' => $m[0], 'milestone' => "{$m[1]} ({$m[2]})"], $plan['metrics'] ?? []),
            'deliverables' => array_slice($included, 0, 6),
            'assumptions' => ['A single point of contact on your side', 'Feedback within 2 business days of each review'],
            'next_steps' => ['Reply to confirm the scope', 'Pay the invoice or deposit to book the start date', 'Kick-off call'],
        ];
    }
}
