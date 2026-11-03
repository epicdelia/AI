"""Find breakout long-form YouTube videos on a watchlist of competitor channels.
Scores videos where views / channel median views >= 3.0x multiplier.
Can synchronize automatically with Creator Studio & Mobile companion app.
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
from typing import Any, Callable, Optional
from src.core.studio_client import StudioClient

API = "https://www.googleapis.com/youtube/v3/"
SHORTS_MAX_SECONDS = 180


def api_get(endpoint: str, params: dict) -> dict:
    key = os.environ.get("YOUTUBE_API_KEY", "")
    params = dict(params, key=key)
    url = API + endpoint + "?" + urllib.parse.urlencode(params)
    with urllib.request.urlopen(url, timeout=30) as resp:
        return json.load(resp)


def parse_duration(iso: Optional[str]) -> int:
    m = re.fullmatch(r"P(?:(\d+)D)?T?(?:(\d+)H)?(?:(\d+)M)?(?:(\d+)S)?", iso or "")
    if not m:
        return 0
    d, h, mi, s = (int(x or 0) for x in m.groups())
    return d * 86400 + h * 3600 + mi * 60 + s


def channel_uploads(handle: str, get: Callable = api_get) -> tuple[Optional[str], Optional[str]]:
    key = "id" if re.fullmatch(r"UC[\w-]{22}", handle) else "forHandle"
    data = get("channels", {"part": "contentDetails,snippet", key: handle})
    items = data.get("items") or []
    if not items:
        return None, None
    ch = items[0]
    return ch["contentDetails"]["relatedPlaylists"]["uploads"], ch["snippet"]["title"]


def recent_videos(playlist_id: str, get: Callable = api_get, limit: int = 50) -> list[dict]:
    ids = [
        it["contentDetails"]["videoId"]
        for it in get(
            "playlistItems",
            {"part": "contentDetails", "playlistId": playlist_id, "maxResults": limit},
        ).get("items", [])
    ]
    if not ids:
        return []
    data = get("videos", {"part": "snippet,statistics,contentDetails", "id": ",".join(ids)})
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


def score_channel(
    videos: list[dict],
    now: datetime,
    lookback_days: int,
    min_age_days: int = 2,
    min_baseline: int = 5
) -> list[dict]:
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


def find_outliers(config: dict, get: Callable = api_get, now: Optional[datetime] = None, seen: Any = ()) -> list[dict]:
    now = now or datetime.now(UTC)
    lookback = config.get("lookback_days", 14)
    min_score = config.get("min_outlier_score", 3)
    min_views = config.get("min_views", 20000)
    results = []
    for handle in config.get("channels", []):
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


def compact(v: dict) -> dict:
    out = {k: v[k] for k in COMPACT_FIELDS}
    out["age_days"] = round(v["age_days"], 1)
    return out


def sync_outliers_to_studio(outliers: list[dict], client: Optional[StudioClient] = None) -> int:
    """Sync breakout videos to Creator Studio."""
    sc = client or StudioClient()
    synced = 0
    for o in outliers:
        payload = {
            "channel_title": o.get("channel", ""),
            "video_title": o.get("title", ""),
            "views": o.get("views", 0),
            "channel_median": o.get("channel_median", 0),
            "multiplier": o.get("outlier_score", 1.0),
            "video_url": o.get("url", f"https://youtu.be/{o.get('id', '')}"),
            "published_at": o.get("published_at", "")
        }
        if sc.sync_outlier(payload):
            synced += 1
    return synced


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--config", default="config/channels.json")
    ap.add_argument("--seen", help="JSON list of video ids already pitched; updated in place")
    ap.add_argument("--out", default="-")
    ap.add_argument("--top", type=int, default=10, help="max results written")
    ap.add_argument("--sync", action="store_true", help="Sync discovered outliers to Creator Studio API")
    args = ap.parse_args()

    with open(args.config, "r", encoding="utf-8") as f:
        config = json.load(f)
    seen = set()
    if args.seen and os.path.exists(args.seen):
        with open(args.seen, "r", encoding="utf-8") as f:
            seen = set(json.load(f))

    results = find_outliers(config, seen=seen)[: args.top]

    if args.sync:
        synced_count = sync_outliers_to_studio(results)
        print(f"Synced {synced_count}/{len(results)} breakout outliers to Creator Studio.")

    text = "\n".join(json.dumps(compact(v)) for v in results)
    if args.out == "-":
        print(text)
    else:
        with open(args.out, "w", encoding="utf-8") as f:
            f.write(text + "\n")
    if args.seen:
        with open(args.seen, "w", encoding="utf-8") as f:
            json.dump(sorted(seen | {v["id"] for v in results}), f, indent=0)


if __name__ == "__main__":
    main()

