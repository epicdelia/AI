"""Delia's own recent long-form uploads, for the weekly mentor check-in.

Zero LLM tokens: the check-in reads one compact line per video.

Usage:
    YOUTUBE_API_KEY=... python ideas/channel_stats.py --channel UC... --since 2026-09-28
"""

import argparse
import json
from datetime import UTC, datetime

from find_outliers import SHORTS_MAX_SECONDS, api_get, channel_uploads, recent_videos


def uploads_since(channel, since, get=api_get, now=None):
    now = now or datetime.now(UTC)
    playlist, _ = channel_uploads(channel, get)
    if not playlist:
        raise SystemExit(f"channel not found: {channel}")
    out = []
    for v in recent_videos(playlist, get):
        published = datetime.fromisoformat(v["published_at"])
        if published.date() < since:
            continue
        out.append({
            "id": v["id"],
            "title": v["title"],
            "published": published.date().isoformat(),
            "age_days": round((now - published).total_seconds() / 86400, 1),
            "views": v["views"],
            "long_form": v["seconds"] > SHORTS_MAX_SECONDS,
            "url": f"https://youtu.be/{v['id']}",
        })
    return sorted(out, key=lambda v: v["published"])


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--channel", required=True, help="channel ID (UC...) or handle")
    ap.add_argument("--since", required=True, type=lambda s: datetime.fromisoformat(s).date())
    args = ap.parse_args()
    for v in uploads_since(args.channel, args.since):
        print(json.dumps(v))


if __name__ == "__main__":
    main()
