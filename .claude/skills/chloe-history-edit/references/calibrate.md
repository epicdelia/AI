# Calibrate against chloe.vs.history's latest reels

Same procedure as `.claude/skills/covacut-edit/references/calibrate.md`, with
`handle="chloe.vs.history"` (about 105 vidIQ credits for 10 reels). Also watch the
user's reference reel first: `https://www.instagram.com/reel/DF5YQKPJiJV/`.

Add these to the watch prompt:
> Scene count and length of each scene; is the narrator's face consistent across scenes;
> is the speech native or lip-synced; ambient sound vs music; caption font, size,
> position; where the tone turns from funny to dark; does the last shot mirror the first?

Then rewrite `style-profile.md` (Status → `CALIBRATED <date>, n=<count>`) and update the
scene template in `SKILL.md` to the measured medians.
