"""Deterministic AI-tell linter for scripts and posts (backwards-compatible wrapper).
Core implementation now lives in src.review.ai_tells.
"""
import sys
from src.review.ai_tells import (
    HARD,
    WARN,
    BANNED,
    RULES,
    SIGNATURE,
    lint,
    humanize,
    lint_paths,
    main,
)

if __name__ == "__main__":
    sys.exit(main(sys.argv[1:]))
