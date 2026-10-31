<?php

namespace App\Domains\Copilot;

use App\Domains\Copilot\Models\CopilotSignal;
use App\Models\Setting;
use App\Services\Llm;
use Throwable;

/**
 * What the Copilot has learned about how she likes to work.
 *
 * Every Apply, Dismiss, Undo, 👍/👎 and feedback note is stored as a signal. Now and then
 * (daily, or "Learn now" in Settings) the AI turns new signals into a few short style rules,
 * merged with the existing ones. "Remember that..." goes straight in. The rules are plain
 * text she can edit in Settings, and every Copilot turn reads them.
 */
class CopilotPreferences
{
    public const MAX_RULES = 15;

    public function __construct(private Llm $llm) {}

    public static function record(string $kind, string $detail): void
    {
        try {
            CopilotSignal::create(['kind' => $kind, 'detail' => mb_substr($detail, 0, 1500)]);
        } catch (Throwable) {
            // Learning is a nice-to-have; never break the action that produced the signal.
        }
    }

    public static function rules(): string
    {
        return trim((string) Setting::get('copilot_style_rules', ''));
    }

    /** "Remember that I never say 'guys'": added as a rule right away. */
    public static function remember(string $rule): string
    {
        $rule = trim(preg_replace('/\s+/', ' ', $rule));
        if ($rule === '') {
            return 'Nothing to remember.';
        }
        $lines = array_filter(preg_split('/\R/', self::rules()) ?: [], fn ($l) => trim($l) !== '');
        $lines[] = '- '.ltrim($rule, '- ');
        Setting::put('copilot_style_rules', implode("\n", array_slice(array_values(array_unique($lines)), -self::MAX_RULES)));
        self::record('remember', $rule);
        CopilotSignal::where('kind', 'remember')->update(['learned' => true]);

        return "Remembered: {$rule}";
    }

    /** Turn unlearned signals into rules. Returns how many signals were used. */
    public function learn(int $minimum = 3): int
    {
        $signals = CopilotSignal::where('learned', false)->oldest()->take(60)->get();
        if ($signals->count() < $minimum) {
            return 0;
        }

        $list = $signals->map(fn ($s) => "- [{$s->kind}] {$s->detail}")->implode("\n");
        $current = self::rules() ?: '(none yet)';
        $out = $this->llm->as('copilot:learn')->json(<<<TXT
You keep a short list of a YouTube creator's working preferences for her AI copilot.
You get the current list and new signals: changes she applied, dismissed or undid, ratings and her comments.
Update the list:
- Each rule is one short imperative line ("Keep cold opens under 3 sentences", "Don't suggest list-style titles").
- Only add a rule when the signals show a pattern or she said it outright. Keep her explicit rules.
- Merge duplicates, drop rules the new signals contradict, at most 15 rules.
JSON: {"rules": ["..."]}
TXT, "Current rules:\n{$current}\n\nNew signals:\n{$list}");

        $rules = collect($out['rules'] ?? [])->filter(fn ($r) => is_string($r) && trim($r) !== '')
            ->map(fn ($r) => '- '.ltrim(trim($r), '- '))->take(self::MAX_RULES)->implode("\n");
        if ($rules !== '') {
            Setting::put('copilot_style_rules', $rules);
        }
        CopilotSignal::whereIn('id', $signals->pluck('id'))->update(['learned' => true]);
        Setting::put('copilot_learned_at', now()->toIso8601String());

        return $signals->count();
    }
}
