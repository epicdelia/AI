---
name: chloe-history-edit
description: Plan, generate and edit a "time traveller vlog" reel in the style of @chloe.vs.history. A modern narrator films handheld selfie vlogs inside a famous historical event (Black Death, Pompeii, Titanic), rendered entirely with AI video. Use when the user says "chloe style", "chloe vs history", "time travel vlog", "history POV reel", or "edit like chloe". Produces the script, a character sheet brief, per-scene AI prompts, an assembly with an ambient sound bed, captions and an AI disclosure.
---

# Edit like chloe.vs.history

**Read this first:** Chloe is not a real person. She's an AI character written and
produced by Jonathan Laramy (reported by Sky News, May 2026). Her bio credits the
generation engine ("Powered by PAI 2.0"). Every frame is generated, so making this
style is a **writing + generation + assembly** job. Cutting real footage isn't
part of it. Read `references/style-profile.md` for the sourced format and how
confident each claim is.

## When it fits, when it doesn't
- Fits: history, "what was it actually like", disaster/plague/era reels, explainers
  where a narrator can stand inside the event.
- Doesn't fit: talking-head footage you've already filmed. For that, use
  `covacut-edit` instead.
- If the user wants **themselves** as the time traveller: build the character sheet
  from their own photos (with their consent). The rest is unchanged.

## The method (in order)

### 1. Pick the event
Choose an event the audience **already knows the ending of**: Pompeii, the Titanic,
the Black Death, Salem. The drama comes from them knowing what she doesn't, or
what she knows and the people around her don't. Obscure events don't work for this.

### 2. Script first, scene by scene (45–70 s, 4–6 scenes of ~10–15 s)
- **Line 1 is the premise, as a flat statement:** "I travelled to London during the Black Death."
  The caption repeats it. No preamble.
- She reacts with modern slang ("like, are you kidding me right now"), the way a
  Gen-Z influencer reviews a restaurant. The joke is the clash between her voice and
  the era.
- Escalate from light to dark: street and novelty → a funny local character →
  the human cost → the iconic symbol (a red-cross door, the volcano) → the scale
  (mass grave, the ship going down). Comedy first, then the gut punch.
- Lines are short and clipped, one beat per scene.
- The ending circles back to the opening (same place, now changed) so it loops.
- **Check the history.** Real dates, real details (the "Lord have mercy" door
  marks, the plague doctor's beak). Comments will pounce on mistakes, and the
  algorithm rewards comments, but being wrong damages the account.

### 3. Character sheet (do this once, reuse every episode)
6–8 reference images of the same face, hair, tattoos and **modern outfit**
(crop top, jeans). The modern outfit is the visual anchor that makes the
time-travel premise obvious. Reuse the same description and seed in every prompt.
Inconsistent faces are the #1 thing that gives this format away.

### 4. Generate each scene separately
For each scene: first a keyframe image (character composited into the period
setting), then image-to-video for 10–15 s with native speech or lip-sync.
Prompt skeleton (fill it in per scene):

```
Style: cinematic vlog, historical fiction, high contrast, hyper-realistic.
Camera: handheld selfie, arm's length, wide angle, slight natural shake.
Character: <character sheet description, verbatim every time>.
Setting: <event, year, exact location, period-accurate details>.
Action: <what she does + what the locals do>.
Lighting/grade: <e.g. overcast, smoky, desaturated mud tones>.
Speech: "<this scene's lines>", direct to camera, energetic but <tone>.
```
Tools named in third-party guides (not confirmed by the creator): Nano Banana Pro
for keyframes; Veo 3.1, Kling 3.0 or Seedance 2.0 for video; ElevenLabs for voice
and ambient sound. Generate 2–3 takes per scene and keep the one where the face matches.

### 5. Assembly
- Hard cuts between scenes, with each cut landing on the end of a spoken line. No transitions.
- An ambient bed per scene (street crowd, bells, rain, the sea) under the dialogue,
  ducked about 12 dB. Silence or near-silence on the darkest beat.
- Captions: the premise line as big text for the first ~2 s, then short subtitle
  phrases. A period-flavoured serif is fine, but readability wins.
- Use the bundled script to stitch generated scenes and mix the ambient bed:

```bash
.claude/skills/chloe-history-edit/scripts/assemble.sh out.mp4 ambient.mp3 scene1.mp4 scene2.mp4 ...
```
It scales every scene to 1080×1920 at 30 fps, hard-cuts them in order, mixes the
ambient bed 12 dB under the dialogue, and normalises loudness to −14 LUFS. Pass `-` as the
ambient file to skip it.

### 6. Disclose
Label it as AI in the caption and turn on Instagram's AI label. Chloe's bio
credits the generation engine. Photorealistic AI people in real tragedies
without a label is how an account gets reported and loses trust.

## Calibration
Not calibrated yet: no reel was watched. See `references/calibrate.md`.
