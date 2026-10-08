---
name: covacut-edit
description: Plan and finish a vertical reel in the style of Cole Lee (@covacut) — cinematic creative-tech storytelling, one "impossible" AI/VFX moment in the first seconds, intimate voiceover, synced serif text, warm filmic grade. Use when the user says "edit like covacut", "cova style", "make this look like covacut", or asks for a cinematic AI-VFX reel about tech/art. Produces a beat sheet, AI shot prompts, a sound plan and an editor-ready brief, and can apply the finishing grade with ffmpeg.
---

# Edit like covacut

Cole Lee (@covacut, ~380K on Instagram) makes 60–90 second vertical reels about
creative technology. The look is easy to copy. The rest is not: she spends
**10–20 hours per video**, and most of that goes into story and planning, not
the timeline. A filter alone won't make a reel look like hers. Most of this
skill is a planning discipline; the grade is the last 5%.

Read `references/style-profile.md` before the first edit. Every claim there is
tagged with its source and a confidence level. **None of it comes from
frame-by-frame analysis of her latest reels yet.** See "Calibration" below.

## Inputs to ask for (only if missing)
1. The idea in one sentence: the tech/art thing, and why the viewer should care.
2. The raw footage: talking head, b-roll, screen recordings.
3. Voiceover: recorded already, or a script to record.
4. Which AI/VFX tools the user can actually run (Kling, Seedance, Runway,
   Higgsfield, After Effects…). Don't plan shots nobody can make.

## The method (do these in order)

### 1. Story before shots
- Write the arc as **personal stake → tension → reveal/turn → reflective close**.
  Her reels are commentary filtered through her own experience (e.g. CS degree → art),
  not explainers.
- First line of VO is an emotional second-person hook aimed at the viewer's
  ambition ("If you've ever…"). Draft 5 options and pick one; use
  `script-doctor` or `stacked-hooks` if they're available.
- State the video's **hypothesis**: what you're testing with this reel. She treats every
  video as an experiment. Write it in the brief so the result can be judged later.

### 2. One impossible shot, early
- In the first 0–3 s, plan **one** shot that can't exist without VFX/AI. For example,
  the creator steps from a real room into a generated world, or a desk object comes alive.
- Build it the way she reportedly does: take a real start frame from the footage,
  then generate a **3–4 s AI transition clip** from it (Seedance or Kling,
  image-to-video), and cut it in on a beat.
- Everything else stays grounded in real footage. AI is the spice, and a reel that's all AI
  stops looking like hers.

### 3. Beat sheet (target 60–90 s)
Produce a table: `t-start | t-end | shot | VO line | on-screen text | SFX/music | VFX/AI`.
Default structure (adjust after calibration):

| Window | Purpose | Typical treatment |
|---|---|---|
| 0–2 s | Identity + hook | Selfie-style close-up, direct to lens, hook text centered |
| 2–5 s | Impossible shot | AI transition from a real frame, hard sound hit |
| 5–45 s | Story | Real footage + practical props as chapter markers (handwritten notes, stop-motion on a desk, top-down montage) |
| 45–75 s | Turn | Bigger VFX moment, or a quiet beat — contrast with the run-up |
| last 5–10 s | Reflective close | Back to the face, slower, one line that lands. No "follow for more" |

### 4. Text
- Serif type, warm gold or cream, centered or lower-third. It appears **in sync with the VO**,
  one short phrase at a time, never a full-sentence caption block.
- Text should carry the story with the sound off.

### 5. Sound
- An intimate, close-mic, conversational VO is the spine.
- Give every AI/VFX transition 3–5 layered sounds (whoosh/riser + impact + texture + tail).
  Lay a continuous room-tone or soft static bed under AI cuts so they don't sound pasted in.
- Score: cinematic/ambient (her stated influences are cyberpunk and noir cinema).
  Duck it under the VO. `HYPOTHESIS:` the music choices haven't been verified from her reels.

### 6. Finish: the grade
Warm, high-contrast, sepia-leaning highlights, slight chromatic aberration,
film grain, soft vignette. Apply it with the bundled script **after** the edit is
locked:

```bash
.claude/skills/covacut-edit/scripts/cova_grade.sh in.mp4 out.mp4          # default strength
.claude/skills/covacut-edit/scripts/cova_grade.sh in.mp4 out.mp4 0.6      # lighter
```
It outputs 1080×1920, H.264, 30 fps, and copies the audio. A strength of 0–1 scales warmth, grain
and aberration. Run it on a 5 s test cut first, and check skin tones before grading the whole reel.

### 7. Deliverable
Fill `editing/edit_brief_template.md` (Reference videos = the calibrated covacut
reels; Avoid = meme SFX, stock whooshes on non-VFX cuts, full-caption blocks,
cold blue grades). Attach the beat sheet, the AI shot prompts (start-frame
timestamp + prompt + duration), and the sound plan.

## Calibration (pull her latest 10 reels)
The style profile is built from interviews and one third-party breakdown, not her
current feed. To ground it in her **latest 10 reels**, follow
`references/calibrate.md`. It uses vidIQ (`vidiq_ig_profile_reels` +
`vidiq_watch_shortform_content`, ~105 credits) and writes measured numbers
(cut rate, hook length, text style, VFX timing, duration) back into
`references/style-profile.md`. Re-run it every few months, because her style moves.

## Honesty rules
- Don't tell the user the output "edits exactly like covacut". This skill copies her
  **documented** method and an approximation of her look.
- This is for learning a style. Don't copy her concepts, scripts or footage, and don't
  present work as hers.
