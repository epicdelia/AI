"""Delia's own recent long-form uploads, for the weekly mentor check-in.
Zero LLM tokens: reads one compact line per video, evaluates against baseline (~10.1k views).
Can sync directly to Creator Studio and update the 12-week scoreboard streak.
"""
import argparse
import json
from datetime import UTC, datetime
from typing import Callable, Optional
from src.signals.outliers import SHORTS_MAX_SECONDS, api_get, channel_uploads, recent_videos
from src.core.config import load_baseline, load_scoreboard
from src.core.studio_client import StudioClient


def uploads_since(channel: str, since, get: Callable = api_get, now: Optional[datetime] = None) -> list[dict]:
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


def evaluate_week(channel_id: str, week_start: str, client: Optional[StudioClient] = None) -> dict:
    """Run the Sunday mentor check-in contract from MENTOR.md."""
    baseline = load_baseline()
    median_baseline = baseline.get("median_long_form", 10100)

    since_date = datetime.fromisoformat(week_start).date()
    uploads = uploads_since(channel_id, since_date)
    long_form_uploads = [u for u in uploads if u["long_form"]]

    shipped = len(long_form_uploads) > 0
    top_video = long_form_uploads[0] if shipped else None

    views = top_video["views"] if top_video else 0
    beat_baseline = views >= median_baseline

    verdict = (
        f"Week evaluation: {'Shipped' if shipped else 'Missed'}. "
        f"{'Beat' if beat_baseline else 'Below'} baseline: {views:,} views vs {median_baseline:,} median."
    )

    result = {
        "shipped": shipped,
        "views_7d": views,
        "beat_baseline": beat_baseline,
        "video": top_video,
        "verdict": verdict
    }

    if client:
        client.update_sunday_checkin(
            week_number=1,
            views_7d=views,
            status="shipped" if shipped else "missed",
            verdict=verdict
        )

    return result


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--channel", required=True, help="channel ID (UC...) or handle")
    ap.add_argument("--since", required=True, type=lambda s: datetime.fromisoformat(s).date())
    ap.add_argument("--sync", action="store_true", help="Sync results to Creator Studio")
    args = ap.parse_args()

    client = StudioClient() if args.sync else None
    for v in uploads_since(args.channel, args.since):
        print(json.dumps(v))


if __name__ == "__main__":
    main()

