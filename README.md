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
| 1. Daily 5 ideas | `prompts/01_daily_ideas.md`, `ROUTINE.md` | Yes (scheduled Routine) |
| 2. Script | `prompts/02_script.md` | Yes, on your go-ahead |
| 3. Review | `review/ai_tells.py` + `prompts/03_review_panel.md` | Yes. The final read-aloud is you |
| 4. Substack | `prompts/04_substack.md` | Yes, after filming |
| 5. Editing | `editing/workflow.md`, `editing/edit_brief_template.md` | Process, not automation |
| 6. Hiring | `hiring/hiring_plan.md` | Process |

Example output: `ideas/2026-09-26.md`, built from real Sandcastles data.

## Setup still needed

1. **YouTube Data API key**, added as an environment secret `YOUTUBE_API_KEY`.
2. **Competitor watchlist**: edit `config/channels.json`. It currently holds a starter list.
3. **Voice corpus**: 10 to 20 transcripts in `voice/transcripts/`. See `voice/README.md`.
4. **Delivery channel** for the daily brief (email / Notion / Slack), then schedule `ROUTINE.md`.

## Run

```
python -m unittest discover -s tests
YOUTUBE_API_KEY=... python ideas/find_outliers.py --seen ideas/seen.json --out ideas/outliers.json
python review/ai_tells.py path/to/draft.md
```

Stdlib only, no dependencies.

## Videos don't go in this repo

Git can't hold multi-GB footage. Raw footage and edits live in Google Drive (folder layout in `editing/workflow.md`). This repo holds only text: ideas, scripts, briefs.
