# YouTube content pipeline

```
 trending + breakout signals          you pick           AI pipeline                    humans
┌──────────────────────────┐   ┌───────────────┐   ┌─────────────────────────┐   ┌──────────────────┐
│ YouTube API outliers     │   │ 5 ideas/day   │   │ script → linter →       │   │ you film         │
│ Sandcastles short-form   │──▶│ reply "2, 5"  │──▶│ 4 parallel reviewers →  │──▶│ editor (brief,   │
│ your own outlier reels   │   │               │   │ merge → you read aloud  │   │ 2 rounds max)    │
└──────────────────────────┘   └───────────────┘   └───────────┬─────────────┘   └──────────────────┘
                                                               └──▶ Substack post (from real transcript)
```

| Stage | File | Automated? |
|---|---|---|
| 1. Find breakouts | `ideas/find_outliers.py`, `config/channels.json` | Yes (YouTube Data API, ~3 quota units/channel) |
| 1. 5 ideas, every other day | `prompts/01_daily_ideas.md`, `ROUTINE.md` | Yes (scheduled Routine, delivered to Notion "YouTube Idea Briefs") |
| 2. Script | `prompts/02_script.md` | Yes, on your go-ahead |
| 3. Review | `review/ai_tells.py` + `prompts/03_review_panel.md` | Yes. The final read-aloud is you |
| 4. Substack | `prompts/04_substack.md` | Yes, after filming |
| 5. Editing | `editing/workflow.md`, `editing/edit_brief_template.md` | Process, not automation |
| 6. Hiring | `hiring/hiring_plan.md` | Process |

Example output: `ideas/2026-09-26.md`, built from real Sandcastles data.

## Token budget

Rough figures, **low confidence** until measured on real runs:

| Step | How often | Where it's kept down |
|---|---|---|
| Finding breakouts | every other day | Python script, zero tokens. The model reads 10 compact lines. |
| Idea brief | every other day | 2 Sandcastles calls, a mid-tier model, a 4-day window instead of reading history, writes to Notion but never reads it |
| Script + review | only on greenlit ideas | linter first, one combined review call, 1,000-word voice excerpts |
| Full 4-agent panel | sponsored / high-effort only | about 3 to 4 times the default review cost |
| Video | never | the AI reads transcripts only, never frames |

The brief is the only cost that repeats on a schedule, so its frequency is the biggest lever. Running it every other day halves it compared with daily.

## Setup still needed

1. **YouTube Data API key**, added as an environment secret `YOUTUBE_API_KEY`.
2. **Competitor handles**: confirm the three unverified guesses in `config/channels.json` and add more channels.
3. **Voice corpus**: transcripts in `voice/transcripts/`, then about 1,000 words in `voice/excerpts.md`. See `voice/README.md`.
4. **Merge this PR.** The routine runs on `master`.

## Run

```
python -m unittest discover -s tests
YOUTUBE_API_KEY=... python ideas/find_outliers.py --out ideas/outliers.jsonl
python review/ai_tells.py path/to/draft.md
```

Stdlib only, no dependencies.

## Videos don't go in this repo

Git can't hold multi-GB footage. Raw footage and edits live in Google Drive (folder layout in `editing/workflow.md`). This repo holds only text: ideas, scripts, briefs.
