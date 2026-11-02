<?php

namespace App\Domains\Deals;

use App\Domains\Landing\Attribution;
use App\Domains\Landing\Pricing;
use App\Domains\Search\WebSearch;
use App\Models\Deal;
use App\Services\Llm;
use App\Services\Prompts;
use Throwable;

/**
 * A one-page brief before an intro call: who they are (a live web search on the company, when
 * search is set up, with the links), what they asked for, likely needs, discovery questions,
 * objections with answers, the package to suggest (a real one, at her price), red flags and the
 * next step to ask for. Saved on the deal; the morning briefing makes them for the day's calls.
 */
class CallPrep
{
    private const FREE_MAIL = ['gmail.com', 'googlemail.com', 'yahoo.com', 'hotmail.com', 'outlook.com', 'live.com', 'icloud.com', 'me.com', 'proton.me', 'protonmail.com'];

    public function __construct(
        private Llm $llm,
        private WebSearch $search
    ) {
    }

    public function brief(Deal $deal): array
    {
        $domain = strtolower((string)substr(strrchr((string)$deal->contact_email, '@') ?: '', 1));
        $company = str_ends_with((string)$deal->brand_name, ' Inquiry') ? null : $deal->brand_name;
        $research = null;
        $sources = [];
        if (WebSearch::available() && ($company || ($domain && !in_array($domain, self::FREE_MAIL, true)))) {
            try {
                $q = trim(($company ?? '') . ' ' . ($domain && !in_array($domain, self::FREE_MAIL, true) ? $domain : '') . ' company');
                $found = $this->search->search($q, 5);
                $research = WebSearch::forPrompt($found);
                $sources = array_map(fn ($r) => [
                    'title' => $r['title'],
                    'url' => $r['link'],
                ], array_slice($found['results'] ?? [], 0, 5));
            } catch (Throwable) {
                // no research is fine: the brief says so
            }
        }

        $plans = collect(Pricing::PLANS)->map(fn ($p, $k) => "- {$k}: {$p['name']}, $" . number_format(Pricing::price($k)) . " {$p['per']}: {$p['desc']}")->implode("\n");
        $score = LeadScore::for($deal);
        $context = implode("\n", array_filter([
            "Lead: {$deal->brand_name}" . ($deal->contact_name ? " (contact {$deal->contact_name})" : '') . ($deal->contact_email ? ", {$deal->contact_email}" : ''),
            $deal->meeting_at ? 'Call: ' . $deal->meeting_at->toDayDateTimeString() . ' UTC' : null,
            $deal->source ? 'Came from: ' . Attribution::label($deal->source) . (!empty($deal->attribution['item']) ? " ({$deal->attribution['item']})" : '') : null,
            $deal->key_talking_points ? "Asked about: {$deal->key_talking_points}" : null,
            $deal->notes ? 'Their message and notes: ' . mb_substr($deal->notes, 0, 2500) : null,
            $score ? "Fit (rule of thumb): {$score['score']}/100, " . implode(', ', $score['reasons']) : null,
            "Her packages:\n{$plans}",
            $research ? "Web search about them (cite only these; never invent facts about the company):\n{$research}" : 'No web research available: say what to look up before the call.',
        ]));

        $system = Prompts::profile() . "\n\nYou prepare " . Prompts::name() . ' for a 30-minute intro call with a potential client. Be concrete and short; ' .
            'use only facts from the context, mark guesses as guesses. Suggest one of her packages by key (never a new price). Return JSON: ' .
            '{"summary": "2 sentences", "about_them": "what the research says, or what to check", "likely_needs": [..3], "questions": [..5 discovery questions], ' .
            '"objections": [{"objection": "...", "answer": "..."}], "package": {"plan": "key", "why": "..."}, "red_flags": [..], "next_step": "the one thing to ask for at the end"}';

        $brief = null;

        try {
            $brief = $this->llm->as('deal:call_prep')->json($system, $context);
        } catch (Throwable) {
            // fall back below
        }
        $plan = ProposalWriter::plan($deal);
        if (empty($brief['questions'])) {
            $brief = [
                'summary' => "Intro call with {$deal->brand_name}." . ($deal->key_talking_points ? " {$deal->key_talking_points}." : ''),
                'about_them' => $research ? 'See the links below.' : 'Look them up before the call: website, LinkedIn, recent funding or launches.',
                'likely_needs' => [],
                'questions' => ['What made you reach out now?', 'What does success look like in 3 months?', 'What have you tried already?', 'Who else is involved in the decision?', 'What budget and timeline are you working with?'],
                'objections' => [[
                    'objection' => 'The price is higher than expected',
                    'answer' => 'Walk through what is included and offer a smaller first step (a strategy session).',
                ]],
                'package' => [
                    'plan' => $plan ?? 'strategy',
                    'why' => 'Closest to what they asked for.',
                ],
                'red_flags' => [],
                'next_step' => 'Agree the scope and send the proposal within 24 hours.',
            ];
            $brief['ai'] = false;
        } else {
            $brief['ai'] = true;
        }
        $key = $brief['package']['plan'] ?? null;
        if (!$key || !isset(Pricing::PLANS[$key])) {
            $key = $plan;
        }
        $brief['package'] = $key ? [
            'plan' => $key,
            'name' => Pricing::PLANS[$key]['name'],
            'price' => Pricing::price($key),
            'why' => (string)($brief['package']['why'] ?? ''),
        ] : null;
        $brief['sources'] = $sources;
        $brief['researched'] = $research !== null;
        $brief['generated_at'] = now()->toIso8601String();

        $deal->update([
            'prep' => $brief,
            'prep_at' => now(),
        ]);

        return $brief;
    }
}
