# Daily routine prompt

This is the prompt for a scheduled Claude Code Routine, which runs every morning in a fresh session on this repo. It needs the Sandcastles connector and a `YOUTUBE_API_KEY` environment secret.

---

Run the daily idea brief for Delia's YouTube channel.

1. `python ideas/find_outliers.py --config config/channels.json --seen ideas/seen.json --out ideas/outliers.json`
   If `YOUTUBE_API_KEY` is missing, continue with short-form signals only and say so at the top of the brief.
2. Pull Sandcastles signals: `get_personal_analytics` (Delia's own outliers), plus `search_all_videos` and `top_topics` for AI/tech over the last 7 days with outlier score 5 or higher.
3. Follow `prompts/01_daily_ideas.md` exactly and write `ideas/<today>.md`.
4. Commit `ideas/` and push.
5. Deliver the brief to Delia via <DELIVERY: email / Notion / Slack, to be decided>.

Don't script anything. Scripting only starts when Delia replies with idea numbers. Then run `prompts/02_script.md`, followed by `prompts/03_review_panel.md`, and save to `scripts/<date>_<slug>/`.
