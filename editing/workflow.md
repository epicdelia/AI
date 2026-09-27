# Editing workflow

Most creator editing pain has nothing to do with editing skill. It comes from **ambiguous handoffs**: the editor doesn't know the intent, the feedback is vague ("make it pop"), and revisions loop. This workflow fixes the handoff.

## 1. One folder per video (Google Drive)

```
YouTube/
  2026-10-03_claude-ran-my-inbox/
    01_script/     final script (with [VISUAL] notes) + edit brief
    02_raw/        A-roll, screen recordings, phone clips. Upload straight from camera.
    03_assets/     logos, screenshots, music picks, reference videos
    04_edits/      v1.mp4, v2.mp4 ... (editor uploads here)
    05_final/      final export, captions .srt, chapters.txt
    06_thumbnail/  thumbnail source + 3 exports
```

Folder name is `YYYY-MM-DD_slug`, where the date is the **publish** date. Your raw footage is currently spread across several unnamed Drive folders, and that's the first thing to fix.

## 2. Handoff: the edit brief

Every video ships to the editor with `edit_brief_template.md` filled in. The script's `[VISUAL]` notes are the shot list. If you can't fill in the brief, the video isn't ready to edit.

## 3. Pipeline and turnaround

| Step | Owner | Target |
|---|---|---|
| Script locked, brief written | You + AI pipeline | Day 0 |
| Film, upload raw to `02_raw` | You | Day 1 |
| Rough cut v1 (structure and pacing only, no polish) | Editor | +48h |
| v1 feedback | You | +24h |
| Polished v2 (graphics, zooms, sound, captions) | Editor | +48h |
| v2 feedback (small changes only) | You | +24h |
| Final + thumbnail | Editor / designer | +24h |

**Two revision rounds, maximum.** A third round means the brief was bad. Fix the brief template, not the editor.

Reviewing the rough cut for structure before any polish is the most important rule here. Polishing a cut you'll restructure wastes the editor's hours and your money.

## 4. Feedback format

Use a tool with **timestamped comments on the video**, such as Frame.io. Drive comments don't pin to timecodes. Every note follows `timestamp, what, why`:

- Bad: "the middle drags"
- Good: "04:10 to 05:30 cut the second demo, it repeats the first one. Jump straight to the result."

## 5. AI never watches the videos

Having an AI analyze video frames costs far more than anything else in this pipeline. Editing tools (Premiere, Descript, CapCut) already do captions, filler-word removal and silence cuts built in, at no token cost. The AI only works on **text**:

- the script (it already exists)
- the final transcript, from YouTube auto-captions or the edit tool's export. Use it for Substack, chapters and the description.
- thumbnail concepts, via the `tech-unicorn-thumbnails` skill, from the title

Pacing judgment, knowing when a joke lands and story structure are what you pay the editor for.
