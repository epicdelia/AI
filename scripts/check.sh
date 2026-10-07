#!/usr/bin/env bash
# The verification contract. CI and every agent run exactly this; "tests pass"
# means this script exited 0. The tech lead extends it as the stack grows;
# weakening it is flagged by scripts/guard.sh for owner review.
set -euo pipefail
cd "$(dirname "$0")/.."

branch="${GITHUB_HEAD_REF:-$(git rev-parse --abbrev-ref HEAD 2>/dev/null || echo unknown)}"
ran_tests=0

if [[ -f package.json ]]; then
  echo "==> node"
  if [[ -f package-lock.json ]]; then npm ci; else npm install; fi
  npm run --if-present lint
  npm run --if-present typecheck
  npm test
  ran_tests=1
fi

if [[ -f pyproject.toml || -f requirements.txt ]]; then
  echo "==> python"
  [[ -f requirements.txt ]] && pip install -q -r requirements.txt
  [[ -f pyproject.toml ]] && pip install -q -e ".[dev]" 2>/dev/null || true
  if command -v ruff >/dev/null; then ruff check .; fi
  pytest -q
  ran_tests=1
fi

if [[ -d tests/extension ]] && command -v node >/dev/null; then
  echo "==> extension (node)"
  for t in tests/extension/*.test.mjs; do node "$t"; done
fi

if [[ $ran_tests -eq 0 ]]; then
  if [[ "$branch" == night/T-* ]]; then
    echo "FAIL: no test suite configured. Night-shift code must ship with tests." >&2
    exit 1
  fi
  echo "NOTICE: no stack configured yet (no package.json / pyproject.toml / requirements.txt)."
fi
echo "==> check.sh passed"
