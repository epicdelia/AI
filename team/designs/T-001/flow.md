# T-001 Flow: streamed polish

Entry point: the single page (`static/index.html`), a take already recorded.
No new screen, no new control. One new badge, and the Polished pane fills in
progressively instead of all at once.

1. **Release Space** (or click Stop). Status badge: `finalizing…`. Unchanged.
   The three polish badges (`First polished text`, `AI polish`, `Stop → polished`)
   and the Copy button reset to `–` / disabled right here, so nothing from the
   previous take is on screen.
2. **Polish request starts.** Status: `polishing…`. Polished pane shows the
   placeholder `Polishing…` (unchanged copy) until the first chunk arrives.
3. **First chunk arrives.** The placeholder is replaced by rendered text;
   `First polished text` badge fills (release → first text visible, ms, green).
   Each later chunk re-renders the whole accumulated Markdown (escaped, same
   renderer). Status stays `polishing…`; Copy stays disabled.
4. **Stream ends.** Full Markdown shown, `AI polish` and `Stop → polished`
   badges fill, Copy enables and auto-copies the full text (button reads
   `Copied ✓` / `Click to copy` as today), Status returns to `idle`.

End state: identical to today's done state, plus the `First polished text` badge.
The user starts the next take with Space; step 1 wipes the pane and badges.

Why 4 steps: they are the existing steps; streaming only splits "wait" into
"wait for first word" + "watch it write". No step was added for the user; none
requires input.

## Error branches
- **E1: polish fails before any text (e.g. 502).** Banner shows server
  `detail` verbatim. Pane returns to its empty placeholder. Status `idle`.
  `First polished text`, `AI polish`, `Stop → polished` stay `–`. Copy disabled.
  Space starts a new take (banner clears on start, as today).
- **E2: stream cut off after some text.** Partial text stays visible, dimmed
  (so it doesn't read as a finished note). Banner:
  `Polishing was cut off before it finished. The text below is incomplete. Hold Space to try again.`
  `First polished text` keeps its (measured) value; `AI polish` and
  `Stop → polished` stay `–` (nothing finished, so nothing to measure).
  Copy disabled. Status `idle`.
- **E3: gateway ignored `stream` (JSON fallback).** Looks like today: whole note
  appears in one step. `First polished text` = `Stop → polished`-ish (both
  measured; they will be nearly equal). No error.
