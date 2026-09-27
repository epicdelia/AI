# Mission


## Product
Flow: a lightweight, production-quality voice dictation app ("Wispr Flow in ~50 lines") built on AssemblyAI Universal-Streaming and the LLM Gateway. You speak messily, and it shows the live raw transcript, then clean, formatted Markdown you can paste anywhere. It's the centrepiece of a video demo, so it must work reliably on camera and look sharp.

## Target user
Developers watching the demo video who want to copy it: they clone the repo, add an AssemblyAI key, run one command, and it works first time.

## Current goal (next 2–4 weeks)
The demo recording: hold Space, ramble for about 20 seconds, release, and see a polished Markdown note with visible, honest latency numbers. It must work with no errors across 10 takes in a row.

## Evidence we have
None yet. Everything about what viewers want is a HYPOTHESIS. The owner's spec (push-to-talk, raw vs polished split view, copy button, latency badge, dark high-contrast UI) is the requirement.

## Tech constraints
Python + FastAPI (`app.py`), a single static HTML page with no build step and no CDN scripts, dependencies pinned in `requirements.txt`. AssemblyAI only, for both STT and the LLM. Runs locally; no hosting. Don't add frameworks, databases or auth.

## Out of scope
`sudoku_solver.py`. Features beyond the demo (accounts, history, a desktop app). Any claim that the LLM corrects misheard words: it only fixes structure, punctuation and formatting.

## Nightly budget
Set by the current level in `TRUST.md` (level 0 = 1 small ticket per night).
Never merge to `master`. Never deploy. Never send external messages.
