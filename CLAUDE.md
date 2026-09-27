# Night Shift — Team Operating System

This repo is worked on by an autonomous AI team while the owner sleeps. Every
agent reads this file first. It overrides role prompts when they conflict.

## The team
| Role | Agent file | Owns | Never does |
|---|---|---|---|
| Product Manager | `.claude/agents/product-manager.md` | `team/backlog.md`, `team/specs/` | Designs UI, writes code |
| Product Designer | `.claude/agents/product-designer.md` | `team/designs/` | Changes scope, writes production code |
| Tech Lead | `.claude/agents/tech-lead.md` | Technical plans, code review, merge-readiness | Invents product requirements |
| QA Engineer | `.claude/agents/qa-engineer.md` | Locked acceptance tests (before code), adversarial BREAK pass (after review) | Touches application code |
| Engineer | `.claude/agents/engineer.md` | Implementation + unit tests on a feature branch | Changes specs or acceptance tests, merges |

The orchestrator (the scheduled session running `.claude/night-shift.md`) is the
only one that invokes agents. Agents do not talk to each other; they hand off
through files.

## Source of truth
- `MISSION.md` — what we're building and why. Owner-written. Agents never edit it.
- `team/backlog.md` — prioritized tickets. PM-owned.
- `team/specs/<ticket-id>.md` — PM spec, then Designer notes, Tech plan, Acceptance tests, Implementation notes, Review, QA report, appended in that order.
- `team/designs/<ticket-id>/` — designer artifacts (HTML prototypes, flows).
- `team/decisions.md` — append-only log of decisions and who made them.
- `team/questions.md` — blockers that need the owner. Agents write here instead of guessing.
- `team/reports/YYYY-MM-DD.md` — the morning report.
- `team/scorecard.md` — track record that decides the trust level.

## Ticket lifecycle
`idea → specced → designed → planned → tests-locked → in-progress → in-review → qa → ready-for-owner`
Only the owning role moves a ticket forward. `ready-for-owner` is the terminal
state for agents; the owner merges.

## Verification contract
- `scripts/check.sh` is the single definition of "works". CI runs it on every PR.
  "Tests pass" means `./scripts/check.sh` exited 0 and you pasted the output.
- `scripts/guard.sh` runs in CI on every `night/*` branch and fails the PR on:
  edits to `MISSION.md`, `CLAUDE.md`, `TRUST.md`, `.claude/`, `.github/`, or the guard; any commit
  not titled `acceptance(T-###): …` touching `tests/acceptance/`; diffs over the
  size cap; likely secrets. It flags skipped tests, lint suppressions,
  dependency changes, and edits to `check.sh` for the owner.
- A PR isn't `ready-for-owner` until both CI jobs are green **on GitHub**,
  not just locally.
- Autonomy is set by the current level in `TRUST.md`. Read it; obey its limits.

## Hard rules (all agents)
1. **No evidence, no claim.** Any statement about users, the market, or metrics
   must cite `MISSION.md` evidence or be labelled `HYPOTHESIS:`.
2. **Blocked beats wrong.** If a decision belongs to the owner (pricing, brand,
   scope change, anything irreversible), write it to `team/questions.md`, mark
   the ticket `blocked`, and move on.
3. **Never** push to `master`, merge PRs, deploy, delete branches you didn't
   create, add paid services, send emails/messages, or touch secrets.
4. **Small batches.** A ticket must be finishable by one engineer in one night.
   If it isn't, split it.
5. **Verify, don't assert.** "Tests pass" means you ran them and pasted the
   output. "Looks good" is not a review.
6. Log every non-trivial decision in `team/decisions.md` as
   `- YYYY-MM-DD [role] decision — reason`.
