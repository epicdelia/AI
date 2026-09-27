# Stage 3: Review

The order is set by cost. Free checks run first, and an LLM only sees what they can't catch. Reviewers return **specific line edits**, never a rewritten script. Rewrites are how scripts slide back toward generic AI prose.

## Gate 0: Linter (free, not an LLM)

`python review/ai_tells.py draft.md`. Any HARD finding goes back to the writer before any reviewer spends a token. WARN findings get passed along as hints.

## Default: one combined review call

One agent reads **only** the draft, its claims list and `voice/excerpts.md`, then returns four short sections:

1. **Voice.** Quote every line Delia wouldn't say out loud, give the reason in 5 words or fewer, and offer a replacement in her rhythm. Score 1 to 10 on whether her longtime viewers could tell she didn't write it. Anything under 8 fails.
2. **Retention.** Name the weakest minute. List where a viewer would click away: a slow cold open, a chapter with no hook, a stretch longer than 90 seconds with no visual change. Check that the first 30 seconds pay off the title.
3. **Facts.** Mark each claim on the claims list VERIFIED, WRONG (with the correction) or UNSOURCED. Only look up claims whose source is a URL. Any WRONG or UNSOURCED claim blocks the script.
4. **Top 3 hostile comments** a smart, annoyed viewer would leave.

The writer applies the edits and re-runs the linter. That's it.

## Full panel: only for sponsored or high-effort videos

Run 4 separate reviewers (voice, retention, facts, hostile commenter) in parallel. None of them sees another's notes. Then one merge agent resolves conflicts: facts beat voice, voice beats retention on wording, retention beats voice on structure. This costs roughly 3 to 4 times the default, so save it for when a mistake is expensive: brand deals and multi-day builds.

## Always

- Open questions for Delia only where a stance or a personal story is needed. Never invent her opinions or her anecdotes.
- **Delia reads it out loud once before filming.** That's the real voice test, and it costs nothing.
