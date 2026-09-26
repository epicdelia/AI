# Stage 3: Review panel

The order matters. Cheap, deterministic checks run first. Reviewers work **in parallel and independently**. None of them sees another reviewer's notes, because that turns the panel into groupthink. Each returns **specific line edits**, never a rewritten script. Rewrites are how scripts slide back toward generic AI prose.

## Gate 0: Linter (not an LLM)

`python review/ai_tells.py draft.md`. Any HARD finding sends the draft back to the writer. WARN findings go to reviewers as hints.

## Reviewer A: Voice match

Inputs: the draft and 5 of Delia's real transcripts.
Task: flag every line Delia wouldn't say out loud. For each one, quote the line, say why (too formal, too polished, expert-on-stage energy, wrong humor lane), and offer a replacement in her rhythm, taken from how she phrases things in the transcripts.
Score 1 to 10: "Could her longtime viewers tell this wasn't written by her?" Anything under 8 fails.

## Reviewer B: Retention editor

Inputs: the draft and the source breakout video's title.
Task: mark every spot where a viewer would click away. Look for a slow cold open, a chapter with no hook, a missing open loop, a claim with no demo, a stretch longer than 90 seconds with no visual change. Check that the first 30 seconds pay off the title.
Output: timestamped cut or tighten list, plus the single weakest minute in the script.

## Reviewer C: Fact checker

Inputs: the draft and its claims list.
Task: for every claim, confirm the cited source actually says it, and that numbers, model names, prices and dates match. Mark each claim VERIFIED, WRONG (with the correction), or UNSOURCED. Any WRONG or UNSOURCED blocks the script. Her credibility as an ex-Google engineer is the asset here, and one bad number costs more than a flat hook.

## Reviewer D: Hostile commenter

Task: write the 5 top comments a smart, annoyed viewer would leave ("this is just an ad", "wrong, Opus 5 is cheaper", "clickbait, she never showed X"). The writer fixes whatever is legitimate.

## Merge

One agent merges the edits from A through D, resolves conflicts (fact checker beats voice, voice beats retention on wording, retention beats voice on structure), re-runs the linter, and outputs:
- the final script
- a changelog of what changed and which reviewer asked for it
- open questions for Delia, only where a stance or personal story is needed. Never invent her opinions or her anecdotes.

**Delia reads it out loud once before filming.** That's the real voice test. No reviewer panel replaces it.
