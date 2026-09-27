# Questions for the owner

Agents write here instead of guessing. Owner answers inline and deletes resolved items.

Format: `- [T-###] [role] question — options considered — what is blocked`

- [T-004] [pm] Your improvement list includes "history of the last 10 dictations", but MISSION.md lists history as out of scope ("Features beyond the demo (accounts, history, a desktop app)"). Which one wins? — options: (a) keep it out of scope and cut T-004; (b) allow it as browser-only history (localStorage, no database) and update MISSION.md — blocks T-004.
- [T-001] [pm] (Non-blocking) Before merging T-001, please do one live take with your key to confirm the LLM Gateway actually streams (agents can only test against mocks; the SSE format is taken from AssemblyAI docs). Also: browser checks in `scripts/e2e_check.py` don't run in CI (Playwright isn't installed there). Do you want Playwright added to CI? That needs a `.github/` change and a dependency, which only you can make — blocks nothing tonight.
