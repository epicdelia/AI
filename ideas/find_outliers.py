"""Find breakout long-form YouTube videos on a watchlist of competitor channels (backwards-compatible wrapper).
Core implementation now lives in src.signals.outliers.
"""
from src.signals.outliers import (
    API,
    SHORTS_MAX_SECONDS,
    api_get,
    parse_duration,
    channel_uploads,
    recent_videos,
    score_channel,
    find_outliers,
    compact,
    COMPACT_FIELDS,
    sync_outliers_to_studio,
    main,
)

if __name__ == "__main__":
    main()
