# Decisions

Append-only. `- YYYY-MM-DD [role] decision — reason`

- 2026-09-27 [pm] Seeded backlog from the owner's 2026-09-27 improvement list as T-001 streaming, T-002 personal dictionary, T-003 output modes, T-004 history — owner input; value to viewers is HYPOTHESIS (MISSION.md has no evidence).
- 2026-09-27 [pm] Parked the desktop app (owner item #5) without a ticket ID — owner marked it out of scope for agents; MISSION.md lists "a desktop app" as out of scope.
- 2026-09-27 [pm] Marked T-004 history `blocked` — MISSION.md "Out of scope" explicitly lists history, which conflicts with the owner's list; scope change is an owner decision (question added).
- 2026-09-27 [pm] Picked T-001 for tonight (level 0 allows 1 small ticket) — owner's #1 and directly serves the demo goal (perceived speed on camera). Judged it fits ≤ 200 lines incl. tests if narrowed: stream only the polish step, browser-measured latency badges, non-streamed JSON fallback if the gateway ignores `stream`, no new dependencies. If the tech plan estimates > 200 lines, T-001 goes back to PM to split; do not cut tests to fit. Fallback pick would be T-002 (clearly small).
- 2026-09-27 [pm] T-001 latency badges move to browser-measured times (first polished text, AI polish = request → last chunk, stop → polished) — with streaming the server can no longer return `llm_ms` in a JSON body, and MISSION.md requires honest, measured numbers.
- 2026-09-27 [pm] LLM Gateway streaming support taken from AssemblyAI docs/blog (`stream: true`, OpenAI-compatible SSE), not verified live — no API key in the agent environment; owner asked to confirm with one live take before merge.
- 2026-09-27 [designer] T-001: no new control; one new badge "First polished text" between Final transcript and AI polish; polish badges and Copy reset at release so no stale value from the previous take is ever shown — spec criterion "No badge shows a value that was not measured on this take".
- 2026-09-27 [designer] T-001: on a mid-stream cut-off, keep the partial text visible but dimmed (opacity .6, AA) with an explicit "incomplete" banner, rather than clearing it — clearing hides what happened on camera; dimming plus Copy disabled prevents it being mistaken for a finished note.
