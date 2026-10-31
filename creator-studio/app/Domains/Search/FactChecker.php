<?php

namespace App\Domains\Search;

use App\Services\Llm;
use App\Services\Prompts;
use RuntimeException;

/**
 * Checks a script's factual claims against live web results:
 * 1. the AI lists the checkable claims (preferring the script's "Claims list"),
 * 2. each claim is searched,
 * 3. the AI writes a verdict per claim, citing only the results it was given.
 */
class FactChecker
{
    public const MAX_CLAIMS = 8;

    public function __construct(private Llm $llm, private WebSearch $search) {}

    /** @return array<int, array{claim: string, query: string}> */
    public function claims(string $script): array
    {
        $data = $this->llm->as('fact_check')->json(<<<'TXT'
You extract factual claims from a YouTube script so they can be checked against the web.
- Use the "Claims list" section if there is one, plus any other specific, checkable statements of fact: numbers, dates, prices, names, releases, quotes, "studies show".
- Skip opinions, jokes, personal stories and [YOUR STORY] placeholders.
- Up to 8 claims, most important first. For each, write a short neutral search query that would confirm or refute it.
JSON: {"claims": [{"claim": "the statement, close to the script's wording", "query": "search query"}]}
TXT, mb_substr($script, 0, 60000));

        return collect($data['claims'] ?? [])
            ->filter(fn ($c) => is_array($c) && ! empty($c['claim']))
            ->take(self::MAX_CLAIMS)
            ->map(fn ($c) => ['claim' => mb_substr((string) $c['claim'], 0, 400), 'query' => mb_substr((string) ($c['query'] ?? $c['claim']), 0, 200)])
            ->values()->all();
    }

    /**
     * @param  callable(array $status): void  $onStatus  progress ({step, done, total, claim})
     * @param  callable(string): void  $onDelta  verdict text as it streams
     * @return array{report: string, claims: array, sources: int}
     */
    public function run(string $script, callable $onStatus, callable $onDelta): array
    {
        $onStatus(['step' => 'extracting']);
        $claims = $this->claims($script);
        if (! $claims) {
            $report = "## Fact-check\nNo checkable factual claims found. Nothing to verify.";
            $onDelta($report);

            return ['report' => $report, 'claims' => [], 'sources' => 0];
        }

        $evidence = [];
        $sources = 0;
        foreach ($claims as $i => $c) {
            $onStatus(['step' => 'searching', 'done' => $i, 'total' => count($claims), 'claim' => $c['claim']]);
            try {
                $result = $this->search->search($c['query'], 5);
                $sources += count($result['results']);
                $evidence[] = 'Claim '.($i + 1).": {$c['claim']}\nSearch: {$c['query']}\nResults:\n".WebSearch::forPrompt($result);
            } catch (RuntimeException $e) {
                $evidence[] = 'Claim '.($i + 1).": {$c['claim']}\nSearch failed: {$e->getMessage()}";
            }
        }

        $onStatus(['step' => 'judging', 'total' => count($claims)]);
        $name = Prompts::name();
        $system = <<<TXT
You fact-check {$name}'s YouTube script using ONLY the web results provided. Be strict and plain.

For each claim write:
### N. <the claim>
**Verdict:** VERIFIED | WRONG | UNSOURCED | UNCLEAR
<one or two sentences: what the sources say; for WRONG give the correct fact>
**Sources:** markdown links to the results you relied on (only URLs that appear in the results)

Rules:
- VERIFIED only if a result clearly supports it. WRONG only if a result clearly contradicts it.
- If results are missing, off-topic or disagree, use UNSOURCED or UNCLEAR. Never fill gaps from memory.
- Dates matter: prefer the most recent source when facts change over time, and say so.
End with "## Summary": counts per verdict and one line on what to fix before filming.
No em dashes.
TXT;

        $report = $this->llm->as('fact_check')->stream($system, [['role' => 'user', 'content' => implode("\n\n---\n\n", $evidence)]], $onDelta);

        return ['report' => $report, 'claims' => $claims, 'sources' => $sources];
    }
}
