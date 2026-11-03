"""Delia's own recent long-form uploads, for the weekly mentor check-in (backwards-compatible wrapper).
Core implementation now lives in src.signals.channel_stats.
"""
from src.signals.channel_stats import (
    uploads_since,
    evaluate_week,
    main,
)

if __name__ == "__main__":
    main()
