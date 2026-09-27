# Backlog

PM-owned. Top = highest priority. One line per ticket; detail lives in `team/specs/`.

Format: `- [status] T-### Title — one-line outcome (spec: team/specs/T-###.md)`

Ordered by (impact on MISSION current goal: a reliable, sharp 20 s demo take) ÷ effort.
Source: owner's prioritized improvement list, 2026-09-27 (owner input, not user evidence; all value claims are HYPOTHESIS).

## Live
- [specced] T-001 Streamed polish endpoint (part 1 of 2) — `/api/polish` streams plain text from the LLM Gateway (`stream: true`, non-streamed JSON fallback); the browser reads the whole body; AI polish and Stop → polished badges are browser-measured; no visible UI change (small, ~125 lines) (spec: team/specs/T-001.md)
- [idea] T-005 Progressive polished rendering (part 2 of 2; depends on T-001 merged) — polished Markdown appears in the right pane chunk by chunk. Also adds the "First polished text" badge, Copy disabled while streaming, the cut-off state, the error-before-text UI and badge reset at release, plus e2e fake-stream checks (small, ~75–80 lines). Reuses the designer notes and tech plan in team/specs/T-001.md (see "PM split mapping" and "Moved to T-005") (spec: not yet)
- [idea] T-002 Personal dictionary — user enters names/jargon, stored in the browser, sent to AssemblyAI streaming as `keyterms_prompt` (JSON-encoded list) to improve recognition of those words; STT-only, UI must not imply the LLM fixes misheard words (MISSION out-of-scope clause) (small; verifiable via fake-socket URL check) (spec: not yet)
- [idea] T-003 Output modes (Email / Notes / Slack) — a selector that changes the polish prompt; HYPOTHESIS: adds demo variety; cheapest test: owner records one take per mode (small–medium; prompt wording is owner-visible copy) (spec: not yet)

## Parked
- [blocked] T-004 History of the last 10 dictations — MISSION.md "Out of scope" explicitly lists history; conflicts with the owner's list. Waiting on owner (team/questions.md).
- [parked] (no ID) Desktop app that types into any app — out of scope for agents per owner and MISSION.md ("a desktop app"); needs a different stack.
