# Calibrate against her latest 10 reels

Run this when `style-profile.md` says UNCALIBRATED, or about every 3 months.
Cost is about 5 + 10×10 = **105 vidIQ credits**. Check the balance first with `vidiq_balance`.

This is a deliberate, one-off exception to `editing/workflow.md` §5 ("AI never
watches the videos"): it's research, done rarely, not per-video editing.

1. `vidiq_ig_profile_reels(handle="covacut")` returns up to 12 reels with pinned
   ones first. Drop the pinned reels, sort the rest by timestamp descending, and keep 10.
   If fewer than 10 are unpinned, keep them all and record how many you got.
2. For each one, call `vidiq_watch_shortform_content(url="https://www.instagram.com/reel/<shortcode>/")`
   with this prompt (all 10 in parallel, then poll `vidiq_job_poll`):

   > Editing teardown, not a content summary. Give: total duration; number of cuts
   > and average shot length; exact hook (0–3 s): framing, first spoken line, first
   > text; every AI/VFX shot with timestamps and what makes it impossible; transition
   > types; on-screen text style (font class, color, position, sync to VO); color
   > grade; music genre/energy and when it changes; SFX on transitions; whether the
   > face is on camera and for how long; how it ends.

3. Save the raw outputs to `references/reels/<YYYY-MM-DD>_<shortcode>.md` (one
   file per reel, with plays/likes/comments from step 1).
4. Rewrite `style-profile.md`: change Status to `CALIBRATED <date>, n=<count>`, and add a
   "Measured" section with medians and ranges (duration, avg shot length, hook
   length, number of AI shots, VFX position). Label each pattern with how many of the 10
   reels show it ("7/10 open on face-cam"). Remove or downgrade any `low` claim
   the reels contradict.
5. Update the default beat-sheet table in `SKILL.md` to the measured medians.
6. Separate the top 3 reels by plays from the bottom 3. Note what differs. That's
   the part worth copying.

If vidIQ isn't available, the user can paste 10 reel URLs, or upload the files to
the Video_Editor (Higgsfield) MCP and run `video_analysis_create` on each.
