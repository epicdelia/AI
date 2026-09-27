# Stage 2: Script draft

Write a YouTube long-form script for the idea Delia greenlit.

## Before writing, read these

- The idea block from `ideas/YYYY-MM-DD.md`
- The `delia-voice` skill (voice spec and hard rules)
- `voice/excerpts.md`: about 1,000 words of her real speech, picked once from the transcripts. Imitate the rhythm of how she actually talks, not a description of it. Don't load full transcripts, since the excerpts file carries the same signal at about a tenth of the tokens.
- The source breakout video's title and description (for the premise only)

## Structure (8 to 15 minutes, about 150 spoken words per minute)

1. **Cold open, 0:00 to 0:30.** Deliver on the title's promise right away: show the result, the wild thing, or the stakes. No "hey guys, welcome back." No channel intro before 0:30.
2. **The setup, to about 1:30.** Why it matters to *the viewer*, and the one question the video answers.
3. **Body in 3 to 5 chapters.** Each chapter opens with a mini-hook and closes with an open loop into the next ("but that's not the part that broke my brain"). Put a re-hook every 60 to 90 seconds.
4. **Payoff.** Answer the question from step 2. The viewer should be able to screenshot the takeaway.
5. **CTA, 15 seconds max,** native to her voice. The Substack plug goes here only if the essay goes deeper than the video.

## Script format

A two-column feel in markdown. Every beat carries a **[VISUAL]** note so the editor isn't guessing:

```
## Chapter 2: I let Claude run my inbox for a week
[VISUAL: screen rec of inbox, zoom on 400 unread count]
Okay so day one I was, like, weirdly confident...
[B-ROLL: coffee, laptop in cafe]
```

Also deliver:
- 3 title options and 3 thumbnail text options (tension, curiosity, outcome)
- Chapter timestamps
- A **claims list**: every factual claim in the script, each with its source URL. This feeds the fact checker.

## Hard rules

Follow every hard rule in the `delia-voice` skill. Zero em dashes. The script must pass `python review/ai_tells.py` with no HARD findings before it moves to review.
If a claim has no source, cut it or mark it `[VERIFY]`. Never smooth over a gap with confident wording.
