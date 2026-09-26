#!/usr/bin/env bash
# Tripwires for night-shift branches. CI runs the copy of this file from the
# BASE branch, so a PR cannot loosen the guard that judges it.
#
# Usage: guard.sh <base-ref> <head-branch-name>
# Exit 1 = hard violation (PR must not merge). Soft flags go to stdout and
# into $GITHUB_STEP_SUMMARY when set, for the owner to look at.
set -uo pipefail

base="${1:?base ref}"
branch="${2:?head branch name}"
# Line cap follows the trust level recorded in TRUST.md on the base branch.
level="$(git show "$base:TRUST.md" 2>/dev/null | sed -n 's/^\*\*Current level: \([0-9]\)\*\*$/\1/p')"
case "${level:-0}" in 0) default_cap=200 ;; 1) default_cap=400 ;; *) default_cap=600 ;; esac
max_lines="${GUARD_MAX_LINES:-$default_cap}"

fail=0
flags=()
hard() { echo "HARD: $*"; fail=1; }
soft() { echo "FLAG: $*"; flags+=("$*"); }

case "$branch" in
  night/*) ;;
  *) echo "guard: $branch is not a night-shift branch, skipping"; exit 0 ;;
esac

merge_base="$(git merge-base "$base" HEAD)"
changed="$(git diff --name-only "$merge_base"...HEAD)"

# 1. Protected paths: the rules, the mission, the agents, CI, and the guard itself.
while IFS= read -r f; do
  [[ -z "$f" ]] && continue
  case "$f" in
    MISSION.md|CLAUDE.md|TRUST.md|.claude/*|.github/*|scripts/guard.sh)
      hard "modifies protected path $f (owner-only)" ;;
  esac
done <<< "$changed"

# 2. State branches may only touch team/.
if [[ "$branch" =~ ^night/[0-9]{4}-[0-9]{2}-[0-9]{2}-state$ ]]; then
  while IFS= read -r f; do
    [[ -z "$f" || "$f" == team/* ]] && continue
    hard "state branch touches $f outside team/"
  done <<< "$changed"
fi

# 3. Acceptance tests are written by QA before implementation and then locked.
#    Only commits whose subject starts with "acceptance(" may touch them.
while IFS= read -r sha; do
  [[ -z "$sha" ]] && continue
  subject="$(git log -1 --format=%s "$sha")"
  [[ "$subject" == acceptance\(* ]] && continue
  touched="$(git diff-tree --no-commit-id --name-only -r "$sha" | grep '^tests/acceptance/' || true)"
  [[ -n "$touched" ]] && hard "commit ${sha:0:8} ('$subject') modifies locked acceptance tests: $(echo $touched)"
done < <(git rev-list "$merge_base"..HEAD)

# 4. Size cap (excluding lockfiles and team state).
lines="$(git diff --numstat "$merge_base"...HEAD -- . \
  ':(exclude)team/**' ':(exclude)*.lock' ':(exclude)package-lock.json' ':(exclude)pnpm-lock.yaml' \
  | awk '{a+=$1; d+=$2} END {print a+d+0}')"
(( lines > max_lines )) && hard "diff is $lines lines (cap $max_lines). Split the ticket."

# 5. Soft flags: things that are sometimes right but always worth a human look.
diff_added="$(git diff "$merge_base"...HEAD -U0 | grep '^+' | grep -v '^+++' || true)"
grep -qE '(\.skip\(|\bxit\(|\bxdescribe\(|\.only\(|pytest\.mark\.skip|pytest\.mark\.xfail|@unittest\.skip|t\.Skip\()' <<< "$diff_added" \
  && soft "adds skipped/focused/xfail tests"
grep -qE '(eslint-disable|# *noqa|# *type: *ignore|@ts-ignore|@ts-expect-error)' <<< "$diff_added" \
  && soft "adds lint/type suppressions"
grep -q '^scripts/check.sh$' <<< "$changed" && soft "changes scripts/check.sh (the verification contract)"
grep -qE '(^|/)(package\.json|requirements[^/]*\.txt|pyproject\.toml|go\.mod|Cargo\.toml)$' <<< "$changed" \
  && soft "changes dependency manifest"
deleted_tests="$(git diff --diff-filter=D --name-only "$merge_base"...HEAD | grep -E '(^|/)(tests?|__tests__)/|\.(test|spec)\.' || true)"
[[ -n "$deleted_tests" ]] && soft "deletes test files: $(echo $deleted_tests)"
grep -qiE '(api[_-]?key|secret|password|token)["'\'' ]*[:=] *["'\''][^"'\'' ]{12,}' <<< "$diff_added" \
  && hard "possible hard-coded secret in diff"

if [[ -n "${GITHUB_STEP_SUMMARY:-}" ]]; then
  {
    echo "## Night-shift guard"
    echo "- Trust level: ${level:-0 (default)}; diff size: $lines / $max_lines lines"
    if (( ${#flags[@]} )); then
      echo "### Needs owner attention"
      printf -- '- %s\n' "${flags[@]}"
    else
      echo "- No soft flags"
    fi
  } >> "$GITHUB_STEP_SUMMARY"
fi

(( fail )) && { echo "guard: FAILED"; exit 1; }
echo "guard: passed (${#flags[@]} flag(s))"
