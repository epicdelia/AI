# Backlog

PM-owned. Top = highest priority. One line per ticket; detail lives in `team/specs/`.

Format: `- [status] T-### Title — one-line outcome (spec: team/specs/T-###.md)`

Ordered by (impact on MISSION current goal: a reliable, sharp 20 s demo take) ÷ effort.
Source: owner's prioritized improvement list, 2026-09-27 (owner input, not user evidence; all value claims are HYPOTHESIS).

## Live
- [specced] T-001 Stream polished output token-by-token — polished Markdown appears progressively after release, with first-text and done latency badges; non-streamed fallback (small) (spec: team/specs/T-001.md)
- [idea] T-002 Personal dictionary — user enters names/jargon, stored in the browser, sent to AssemblyAI streaming as `keyterms_prompt` (JSON-encoded list) to improve recognition of those words; STT-only, UI must not imply the LLM fixes misheard words (MISSION out-of-scope clause) (small; verifiable via fake-socket URL check) (spec: not yet)
- [idea] T-003 Output modes (Email / Notes / Slack) — a selector that changes the polish prompt; HYPOTHESIS: adds demo variety; cheapest test: owner records one take per mode (small–medium; prompt wording is owner-visible copy) (spec: not yet)

## Parked
- [blocked] T-004 History of the last 10 dictations — MISSION.md "Out of scope" explicitly lists history; conflicts with the owner's list. Waiting on owner (team/questions.md).
- [parked] (no ID) Desktop app that types into any app — out of scope for agents per owner and MISSION.md ("a desktop app"); needs a different stack.
