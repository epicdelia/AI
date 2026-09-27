# Trust Ladder

You don't trust the night shift because its prompts say "be careful." You trust
it the way you'd trust a new hire: its blast radius is limited, independent
checks verify its work, and it earns more autonomy with a measured track record.

## What makes the output trustworthy (layers, weakest → strongest)
| Layer | Enforced by | Can an agent bypass it? |
|---|---|---|
| Role prompts and `CLAUDE.md` rules | The model following instructions | Yes. Treat these as intent, not enforcement |
| Tech lead review | A second agent run | Partly: same model, correlated blind spots |
| QA acceptance tests written **before** code, from the spec only | `qa-engineer` + `scripts/guard.sh` lock | Only by faking a commit subject, which is visible in the PR |
| `scripts/check.sh` must pass | GitHub Actions `check` job | No, CI runs it, not the agent |
| Guard tripwires (protected paths, test tampering, size cap, secrets) | GitHub Actions `guard` job, loaded from **base** branch | No for this PR's code. Editing CI itself is a protected path |
| Nothing reaches `master` without you | GitHub branch protection (**you must turn this on**) | No |

The bottom two rows are the real guarantees. Everything above them is there to
cut down how often you have to say no.

## Required GitHub settings (one-time, owner does this)
Settings → Branches (or Rules → Rulesets) → add a rule for `master`:
- Require a pull request before merging. Set required approvals to **0** if
  night-shift PRs appear under your own account (GitHub won't let you approve
  your own PR). Your merge click is the review.
- Require status checks to pass: `check`, `guard`
- Block force pushes and deletions
- Don't allow bypassing the above (applies to admins too, if you can stand it)

Without this, every guarantee above is advisory.

## Levels
The orchestrator reads the current level from here and behaves accordingly.
Only the owner changes it.

**Current level: 0**

| Level | Agents may | Promote when (measured in `team/scorecard.md`) |
|---|---|---|
| 0 Probation | Tickets tagged `small` only: ≤ 200 lines, no new dependencies, no auth/payments/data-deletion code. 1 ticket per night. | 10 PRs, ≥ 80% merged without owner code changes, 0 reverts |
| 1 Junior | Up to 2 tickets/night, ≤ 400 lines, new deps allowed with a decision-log entry | 20 more PRs at ≥ 85%, 0 reverts, 0 guard violations |
| 2 Mid | Up to 3 tickets/night, ≤ 600 lines, may touch auth/data code (still flagged) | 30 more PRs at ≥ 90%; you've stopped finding bugs QA missed |
| 3 Senior | Auto-merge of `small` tickets with green CI (owner turns on auto-merge per PR) | Only if you're honestly reading fewer than half the diffs and nothing has bitten you |

**Demote immediately** (one level, or to 0 for anything touching data or security) on:
a revert of a night-shift PR, a guard HARD violation that reached a PR, or a
bug in production that QA's BREAK pass should have caught.
