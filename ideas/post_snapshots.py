"""Due 1d / 7d / 28d view snapshots for Delia's own YouTube uploads (plan step A2).

Zero LLM tokens. Stateless on purpose: routines start in a fresh session, so
nothing survives between runs. Each run emits only the snapshots that are due
now: a video whose age has just crossed a window, within a grace period that
covers a missed day. Every row carries a `key` (<video id>:<window>d), and the
writer (a routine filling the Notion Posts DB) only fills empty fields, so a
row seen on two days in a row is written once.

`score` = views at the snapshot / median current views of the same format's
uploads older than 28 days + grace. It's conservative (older videos have had
longer to accrue views) and `null` when there are fewer than 5 such uploads.
Long-form will be `null` until there are 5 old long-form uploads to compare
against.

Usage:
    YOUTUBE_API_KEY=... python ideas/post_snapshots.py --channel UC8jn9cfrj46p59JKdq_J1Zw
"""

import argparse
import json
import statistics
from datetime import UTC, datetime

from find_outliers import SHORTS_MAX_SECONDS, api_get, channel_uploads, recent_videos

WINDOWS = (1, 7, 28)
GRACE_DAYS = 2
MIN_BASELINE = 5


def baselines(videos, now):
    """Median current views per format, from uploads past every window."""
    cutoff = max(WINDOWS) + GRACE_DAYS
    out = {}
    for long_form in (True, False):
        views = [
            v["views"] for v in videos
            if (v["seconds"] > SHORTS_MAX_SECONDS) == long_form and v["age_days"] > cutoff
        ]
        out[long_form] = statistics.median(views) if len(views) >= MIN_BASELINE else None
    return out


def due_snapshots(channel, get=api_get, now=None):
    now = now or datetime.now(UTC)
    playlist, _ = channel_uploads(channel, get)
    if not playlist:
        raise SystemExit(f"channel not found: {channel}")
    videos = recent_videos(playlist, get)
    for v in videos:
        v["age_days"] = (now - datetime.fromisoformat(v["published_at"])).total_seconds() / 86400
    medians = baselines(videos, now)

    out = []
    for v in videos:
        long_form = v["seconds"] > SHORTS_MAX_SECONDS
        median = medians[long_form]
        for window in WINDOWS:
            if not window <= v["age_days"] < window + GRACE_DAYS:
                continue
            out.append({
                "key": f"{v['id']}:{window}d",
                "platform": "YouTube" if long_form else "YT Shorts",
                "url": f"https://youtu.be/{v['id']}",
                "title": v["title"],
                "published": v["published_at"][:10],
                "window_days": window,
                "late_days": round(v["age_days"] - window, 1),
                "views": v["views"],
                "comments": v["comments"],
                "score": round(v["views"] / median, 2) if median else None,
            })
    return sorted(out, key=lambda r: (r["window_days"], r["published"]))


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--channel", required=True, help="channel ID (UC...) or handle")
    args = ap.parse_args()
    for row in due_snapshots(args.channel):
        print(json.dumps(row))


if __name__ == "__main__":
    main()
