# Daily routine prompt

This is the prompt for a scheduled Claude Code Routine, which runs every morning in a fresh session on this repo. It needs the Sandcastles connector and a `YOUTUBE_API_KEY` environment secret. Run it on a mid-tier model (Sonnet). Ranking five ideas doesn't need the top model.

---

Write today's idea brief for Delia. Token budget is tight, so follow these steps exactly and read nothing else.

1. Run `python ideas/find_outliers.py --seen ideas/seen.json --out ideas/outliers.jsonl`. This costs zero tokens. If `YOUTUBE_API_KEY` is missing, skip it and note that at the top of the brief.
2. Make exactly **two** Sandcastles calls:
   - `get_personal_analytics` (Delia's own outliers)
   - `search_all_videos` with query "AI tools, coding, tech careers", `lookback_days: 3`, `min_outlier_score: 5`, `limit: 10`
   Don't call `top_topics`, `top_hooks` or `discover_channels`. They repeat the same videos with more text.
3. Read only `prompts/01_daily_ideas.md`, `ideas/outliers.jsonl` and `ideas/pitched.txt`. Don't open past briefs or any other file.
4. Write `ideas/<today>.md`. Append the 5 titles to `ideas/pitched.txt`, one line each.
5. Commit `ideas/` and push.
6. Deliver the brief via <DELIVERY: email / Notion / Slack, to be decided>.

Don't script anything. Scripting starts only when Delia replies with idea numbers.
