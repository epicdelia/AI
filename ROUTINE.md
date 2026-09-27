# Idea brief routine

Scheduled Claude Code Routine that runs every other day at 07:58 London, each time in a fresh session on `master`. It needs the Sandcastles and Notion connectors. `YOUTUBE_API_KEY` is optional; without it the brief runs on short-form data only. Run it on a mid-tier model (Sonnet).

**How it avoids repeat ideas without spending tokens:** each run only looks at the last 4 days of videos. Runs are 2 days apart, so a source video lands in at most two briefs. The routine never *reads* Notion or old briefs. Loading a Notion query tool alone costs about 13k tokens.

---

Write the YouTube idea brief for Delia. The token budget is tight, so follow these steps exactly, read nothing else, and don't commit or push.

1. Run `python ideas/find_outliers.py --out ideas/outliers.jsonl`. This costs zero tokens. If `YOUTUBE_API_KEY` is missing, skip this step and say so in one line at the top of the brief.
2. Make exactly **two** Sandcastles calls:
   - `get_personal_analytics`. Only use Delia's reels published in the last 4 days.
   - `search_all_videos` with query "AI tools, coding, tech careers", `lookback_days: 4`, `min_outlier_score: 5`, `limit: 10`.
   Don't call any other Sandcastles tool.
3. Read only `prompts/01_daily_ideas.md` and `ideas/outliers.jsonl`.
4. Load only the `notion-create-pages` tool. Create one page in data source `bee376dd-5e28-40e9-87f6-eb008fbd2afc` (the "YouTube Idea Briefs" database) with these properties:
   - Name: `<Mon DD>: <short 2-4 word label for each idea, separated by / >`
   - Date: today
   - Status: `New`
   The page content is the brief, in the format from the prompt.
5. Stop. Scripting starts only when Delia sets a brief's Status to Picked and asks for it.
