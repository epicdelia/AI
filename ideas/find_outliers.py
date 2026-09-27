"""Find breakout long-form YouTube videos on a watchlist of competitor channels.

Outlier score = a video's views / median views of that channel's older long-form
uploads. Young videos haven't finished accruing views, so the score is
conservative: a 5-day-old video already at 3x the channel median is a real
breakout.

Quota: uses channels.list, playlistItems.list and videos.list only (1 unit
each). About 3 units per channel, so 100 channels is ~300 of the free 10,000
daily units. search.list costs 100 units per call and is deliberately avoided.

Usage:
    YOUTUBE_API_KEY=... python ideas/find_outliers.py --config config/channels.json \
        --seen ideas/seen.json --out ideas/outliers.jsonl
"""

import argparse
import json
import os
import re
import statistics
import sys
import urllib.parse
import urllib.request
from datetime import UTC, datetime

API = "https://www.googleapis.com/youtube/v3/"
SHORTS_MAX_SECONDS = 180  # Shorts can run up to 3 minutes


def api_get(endpoint, params):
    params = dict(params, key=os.environ["YOUTUBE_API_KEY"])
    url = API + endpoint + "?" + urllib.parse.urlencode(params)
    with urllib.request.urlopen(url, timeout=30) as resp:
        return json.load(resp)


def parse_duration(iso):
    """ISO 8601 duration (PT1H2M3S) -> seconds."""
    m = re.fullmatch(r"P(?:(\d+)D)?T?(?:(\d+)H)?(?:(\d+)M)?(?:(\d+)S)?", iso or "")
    if not m:
        return 0
    d, h, mi, s = (int(x or 0) for x in m.groups())
    return d * 86400 + h * 3600 + mi * 60 + s


def channel_uploads(handle, get):
    data = get("channels", {"part": "contentDetails,snippet", "forHandle": handle})
    items = data.get("items") or []
    if not items:
        return None, None
    ch = items[0]
    return ch["contentDetails"]["relatedPlaylists"]["uploads"], ch["snippet"]["title"]


def recent_videos(playlist_id, get, limit=50):
    ids = [
        it["contentDetails"]["videoId"]
        for it in get(
            "playlistItems",
            {"part": "contentDetails", "playlistId": playlist_id, "maxResults": limit},
        ).get("items", [])
    ]
    if not ids:
        return []
    data = get(
        "videos",
        {"part": "snippet,statistics,contentDetails", "id": ",".join(ids)},
    )
    videos = []
    for v in data.get("items", []):
        videos.append(
            {
                "id": v["id"],
                "title": v["snippet"]["title"],
                "published_at": v["snippet"]["publishedAt"],
                "views": int(v["statistics"].get("viewCount", 0)),
                "comments": int(v["statistics"].get("commentCount", 0)),
                "seconds": parse_duration(v["contentDetails"]["duration"]),
            }
        )
    return videos


def score_channel(videos, now, lookback_days, min_age_days=2, min_baseline=5):
    """Return breakout candidates for one channel's recent uploads."""
    long_form = [v for v in videos if v["seconds"] > SHORTS_MAX_SECONDS]
    for v in long_form:
        published = datetime.fromisoformat(v["published_at"])
        v["age_days"] = (now - published).total_seconds() / 86400

    baseline = [v["views"] for v in long_form if v["age_days"] > lookback_days]
    if len(baseline) < min_baseline:
        return []
    median = statistics.median(baseline)
    if median <= 0:
        return []

    out = []
    for v in long_form:
        if min_age_days <= v["age_days"] <= lookback_days:
            out.append(dict(v, channel_median=median, outlier_score=round(v["views"] / median, 2)))
    return out


def find_outliers(config, get=api_get, now=None, seen=()):
    now = now or datetime.now(UTC)
    lookback = config.get("lookback_days", 14)
    min_score = config.get("min_outlier_score", 3)
    min_views = config.get("min_views", 20000)
    results = []
    for handle in config["channels"]:
        playlist, title = channel_uploads(handle, get)
        if not playlist:
            print(f"warn: channel not found: {handle}", file=sys.stderr)
            continue
        for v in score_channel(recent_videos(playlist, get), now, lookback):
            if v["id"] in seen or v["outlier_score"] < min_score or v["views"] < min_views:
                continue
            v.update(channel=title, handle=handle, url=f"https://youtu.be/{v['id']}")
            results.append(v)
    results.sort(key=lambda v: v["outlier_score"], reverse=True)
    return results


COMPACT_FIELDS = ("id", "title", "channel", "url", "views", "outlier_score")


def compact(v):
    out = {k: v[k] for k in COMPACT_FIELDS}
    out["age_days"] = round(v["age_days"], 1)
    return out


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--config", default="config/channels.json")
    ap.add_argument("--seen", help="JSON list of video ids already pitched; updated in place")
    ap.add_argument("--out", default="-")
    ap.add_argument("--top", type=int, default=10, help="max results written (keeps the LLM's input small)")
    args = ap.parse_args()

    with open(args.config) as f:
        config = json.load(f)
    seen = set()
    if args.seen and os.path.exists(args.seen):
        with open(args.seen) as f:
            seen = set(json.load(f))

    results = find_outliers(config, seen=seen)[: args.top]

    # One compact line per video: this file is what the LLM reads, so every
    # field here costs tokens every day.
    text = "\n".join(json.dumps(compact(v)) for v in results)
    if args.out == "-":
        print(text)
    else:
        with open(args.out, "w") as f:
            f.write(text + "\n")
    if args.seen:
        with open(args.seen, "w") as f:
            json.dump(sorted(seen | {v["id"] for v in results}), f, indent=0)


if __name__ == "__main__":
    main()
