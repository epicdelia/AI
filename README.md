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

Example output: `ideas/2026-09-26.md`, built from real Sandcastles data. A full run through the pipeline is in `videos/2026-09-27_what-ai-replaced-at-google/`: the script (v2), the independent review that shaped it, and the filled-in edit brief.

## Mentor and accountability

`MENTOR.md` holds the contract: **1 long-form video a week for 12 weeks (Sep 28 to Dec 20)**, a baseline, and blunt scoring rules. The Notion "YouTube Scoreboard" has one row per week, and every Sunday a check-in routine scores the week using `ideas/channel_stats.py` (zero tokens) plus two Notion page reads.

## Token budget

Rough figures, **low confidence** until measured on real runs:

| Step | How often | Where it's kept down |
|---|---|---|
| Finding breakouts | every other day | Python script, zero tokens. The model reads 10 compact lines. |
| Idea brief | every other day | 2 Sandcastles calls, a mid-tier model, a 4-day window instead of reading history, writes to Notion but never reads it |
| Script + review | only on greenlit ideas | linter first, one combined review call, 1,000-word voice excerpts |
| Full 4-agent panel | sponsored / high-effort only | about 3 to 4 times the default review cost |
| Video | never | the AI reads transcripts only, never frames |

**Measured so far (Sep 27 demo run):** the combined review, run as a separate Sonnet agent, used **about 80k tokens**. That's about 3x the guess above, and most of it is fixed agent start-up overhead, not the script itself. It's still one call per greenlit video, not per day. The full 4-agent panel would multiply that overhead by 4, which is another reason to keep it for sponsored videos only.

The brief is the only cost that repeats on a schedule, so its frequency is the biggest lever. Running it every other day halves it compared with daily.

## Setup still needed

1. ~~YouTube Data API key~~ done (environment variable `YOUTUBE_API_KEY`).
2. **Competitors**: Theo and Alberta Tech are verified. Madeline Zhang's two channel IDs haven't been API-checked yet. Add 10 to 20 more channels.
3. **Voice corpus**: transcripts in `voice/transcripts/`, then about 1,000 words in `voice/excerpts.md`. See `voice/README.md`.
4. **Routines** ("YouTube idea brief" and "YouTube mentor check-in"): attach this repo plus the Sandcastles and Notion connectors in the claude.ai Routines UI.

## Run

```
python -m unittest discover -s tests
YOUTUBE_API_KEY=... python ideas/find_outliers.py --out ideas/outliers.jsonl
python review/ai_tells.py path/to/draft.md
```

Stdlib only, no dependencies.

## Videos don't go in this repo

Git can't hold multi-GB footage. Raw footage and edits live in Google Drive (folder layout in `editing/workflow.md`). This repo holds only text: ideas, scripts, briefs.

---

# Flow: speak messy, get polished

Push-to-talk dictation: your mic streams to AssemblyAI Universal-Streaming (live
raw transcript on the left). When you stop, the transcript goes through
AssemblyAI's LLM Gateway, and clean Markdown appears on the right and is copied
to your clipboard.

## Run it

```bash
python3 -m venv .venv && source .venv/bin/activate
pip install -r requirements.txt
cp .env.example .env        # then paste your key into .env
uvicorn app:app --port 8000
```

Open http://localhost:8000 in Chrome, allow the mic, then **hold Space** (or
click **Start Speaking**), talk, and let go.

## Put it online (free, on Render)

`render.yaml` deploys Flow to Render's free tier, so you get an `https://` URL
(browsers only allow the mic on https or localhost).

1. Go to https://dashboard.render.com/blueprints → **New Blueprint Instance** → pick this repo.
2. When asked, paste your `ASSEMBLYAI_API_KEY`.
3. Wait for the build (~2 min) and open the `https://flow-dictation-….onrender.com` URL.

Anyone with the URL can use it on your AssemblyAI credit, so keep the link private. The free
tier sleeps after 15 idle minutes, so the first load after a break takes about a minute.
Open it once before you record.

## Choosing the LLM

`LLM_MODEL` in `.env` picks the model (default `gpt-5-mini`). To see the models
your account can use (for example, whether a Qwen model is offered):

```bash
curl -s https://llm-gateway.assemblyai.com/v1/models -H "authorization: $ASSEMBLYAI_API_KEY"
```

For the demo, pick the fastest model on that list. The **AI polish** badge shows
the real round-trip time, so you can compare models live.

## How it works

| Piece | Where |
|---|---|
| Mic → 16 kHz 16-bit PCM, 50 ms chunks | AudioWorklet in `static/index.html` |
| Short-lived streaming token (API key stays on the server) | `GET /api/token` in `app.py` |
| Live partial/final transcript | Browser ↔ `wss://streaming.assemblyai.com/v3/ws` |
| Close the turn the moment you release Space | `ForceEndpoint` message, then `Terminate` |
| Cleanup + Markdown formatting | `POST /api/polish` → LLM Gateway `/v1/chat/completions` |

The badges show measured times, not targets: **Final transcript** (release →
final text), **AI polish** (LLM round trip) and **Stop → polished** (the whole wait).

## Tests

```bash
pip install pytest ruff && ./scripts/check.sh          # backend, mocked HTTP
pip install playwright && python scripts/e2e_check.py  # real browser, fake mic + fake AssemblyAI socket
```
