---
name: chloe-shih-edit
description: Plan and edit a personal "cinematic short" reel in the style of Chloe Shih (@chloe.shih), the ex-tech-PM creator: a real-life moment from work, career or your 30s turned into a short film, narrated like a diary entry over b-roll of your actual day. Use when the user says "edit like chloe shih", "chloe style", "cinematic short", "make my day into a short film", or wants a career/life-update reel from their own footage. Also measures reference reels locally with analyze_reel.py, so no API is needed.
---

# Edit like Chloe Shih

Chloe Shih (@chloe.shih, about 1M on Instagram) spent her 20s in product and BD at Google,
Meta, TikTok and Discord. She vlogged daily for about three years, went viral filming
her own Discord layoff in January 2024, and a week later posted her first
"cinematic short". She has posted almost daily since. Her edge is **real life, filmed
and cut like a movie**: her own career moments, not commentary on other people's.

Read `references/style-profile.md` first. **The editing specifics are not
calibrated yet.** Public sources describe her career and tools, not her cuts.
Run the calibration below before trusting the beat template.

## Why this fits Delia
Same lane: ex-big-tech, career and life as content, filmed by herself. The
difference from `covacut-edit`: Chloe's reels are *documentary* (a real event,
your real feelings). covacut's are *concept + VFX*. If the footage is your actual
day, use this skill.

## The method

### 1. Find the real moment
One true event with stakes: a layoff, a first day, a resignation, a hard
conversation, a "should I go back to tech" night. It has to have happened.
Her biggest video was a real layoff, filmed as it unfolded. **Never fake one.** This
audience can tell, and it's her whole brand.

### 2. Write the narration like a diary entry
- Line 1 is the moment, plainly: "I got laid off this morning." No warm-up.
- First person, present tense, honest, a little self-aware. Feelings named
  specifically ("I keep refreshing Slack like it'll come back"), not generally.
- One turn near the end: what this means for you now. End on a line that's
  hopeful or open, not a lesson.
- 30–60 s of voiceover. Record it close to the mic, quiet, after the footage is shot.

### 3. Shoot / pick the b-roll
Real moments from the day: hands on a laptop, a coffee, the commute, a window, a
face with no talking. Lots of short cutaways under the narration; the talking-head
shots are where the emotion lands. `HYPOTHESIS:` the cutaway-to-face ratio and shot
lengths aren't measured yet.

### 4. Cut: through `delia-vlog-editor`
Take Chloe's **storytelling structure**, not her look. Render with Delia's own
`delia-vlog-editor` skill (B-roll library, edit list, caption safe area, brand fonts
from `tech-unicorn-visual-brand`). Its rules win wherever they conflict with this file:
- **No music in the render.** Mix the voice to about -14 LUFS with ambience under
  it. Delia adds a licensed track in the Instagram editor, and lets it swell at the turn.
- **Her brand, not Chloe's signature look:** Fraunces italic titles, Inter captions,
  inside the safe area.
- **Footage beats library B-roll.** Shots filmed on the day always win a slot.
  Library clips fill gaps only if they don't misstate where or when something happened.
- **Voice:** her real voice memo first. Use her ElevenLabs clone only if she signs off,
  and then add Instagram's AI label.
- Cut to the narration: each phrase gets the shot that shows it, cutting on word
  boundaries.

### 5. Series episodes ("AI runs my life" and similar)
Every episode uses the same skeleton, so viewers know the format and watch for what changes:

| Beat | Length | What's on screen |
|---|---|---|
| Hook | 0-2 s | The most arresting real frame (e.g. a charge notification), plus one plain line |
| Rule card | 2-4 s | "Episode N. $budget. Rule: ...", as a kinetic title |
| Setup | ~5 s | The prompt being typed, screen-recorded |
| Clue / day | 15-25 s | Diary voiceover over b-roll from the day |
| Reveal | ~60-70% in | Live sound, real reaction, no voiceover over it |
| Turn | 5-8 s | What it got right or wrong about her, one honest line |
| Verdict card | 4-6 s | The prompt (2 s), spent / budget, "knew me: N/10", next-time fix |
| Ask | last 2 s | "Comment the rule for episode N+1" |

Put the full prompt in the caption and the pinned comment. Each episode also gets a
companion carousel (prompt, setup, receipt, fix) via `tech-unicorn-carousel`. Never
re-stage the reveal or invent a score: if the day was dull, the verdict says so.

### 6. Measure, then post
Run `scripts/analyze_reel.py` on your export and on 2–3 of her reels (see Calibration), and
compare shot length, cuts per 10 s, words per minute and loudness side by side.

## Calibration (no vidIQ needed)
1. The user saves or screen-records 5–10 of her recent reels (including
   instagram.com/reel/DF5YQKPJiJV) and uploads the files.
2. For each one: `python3 -I .claude/skills/chloe-shih-edit/scripts/analyze_reel.py <file> <out_dir>`.
   That gives a frame grid, cut times, shot-length stats, pauses, loudness and a rough
   word-timed transcript (pocketsphinx, offline).
3. Look at each grid. Note the hook shot, talking-head vs b-roll, and caption style.
4. Write medians and "n/10 reels do X" patterns into `references/style-profile.md`,
   set Status to `CALIBRATED <date>, n=<count>`, and replace each `HYPOTHESIS:` above.
