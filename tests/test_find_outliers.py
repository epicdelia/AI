import sys
import unittest
from datetime import datetime, timedelta, timezone
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parents[1] / "ideas"))
import find_outliers as fo  # noqa: E402

NOW = datetime(2026, 9, 26, tzinfo=timezone.utc)


def vid(i, days_ago, views, seconds=900):
    return {
        "id": f"v{i}",
        "title": f"Video {i}",
        "published_at": (NOW - timedelta(days=days_ago)).isoformat().replace("+00:00", "Z"),
        "views": views,
        "comments": 0,
        "seconds": seconds,
    }


class FakeAPI:
    def __init__(self, videos):
        self.videos = videos

    def __call__(self, endpoint, params):
        if endpoint == "channels":
            return {"items": [{"contentDetails": {"relatedPlaylists": {"uploads": "UU1"}},
                               "snippet": {"title": "Chan"}}]}
        if endpoint == "playlistItems":
            return {"items": [{"contentDetails": {"videoId": v["id"]}} for v in self.videos]}
        if endpoint == "videos":
            return {"items": [{
                "id": v["id"],
                "snippet": {"title": v["title"], "publishedAt": v["published_at"]},
                "statistics": {"viewCount": str(v["views"])},
                "contentDetails": {"duration": f"PT{v['seconds']}S"},
            } for v in self.videos]}
        raise AssertionError(endpoint)


class TestOutliers(unittest.TestCase):
    def setUp(self):
        baseline = [vid(i, 20 + i, 10_000) for i in range(6)]
        self.videos = baseline + [
            vid(100, 5, 80_000),                # 8x breakout
            vid(101, 5, 12_000),                # normal
            vid(102, 5, 900_000, seconds=45),   # a Short, must be ignored
            vid(103, 1, 500_000),               # too young to judge
        ]

    def test_finds_only_real_long_form_breakout(self):
        cfg = {"channels": ["x"], "min_outlier_score": 3, "min_views": 20000}
        out = fo.find_outliers(cfg, get=FakeAPI(self.videos), now=NOW)
        self.assertEqual([v["id"] for v in out], ["v100"])
        self.assertEqual(out[0]["outlier_score"], 8.0)

    def test_seen_videos_are_skipped(self):
        cfg = {"channels": ["x"]}
        out = fo.find_outliers(cfg, get=FakeAPI(self.videos), now=NOW, seen={"v100"})
        self.assertEqual(out, [])

    def test_thin_baseline_returns_nothing(self):
        out = fo.score_channel([vid(1, 30, 100), vid(2, 3, 10_000)], NOW, 14)
        self.assertEqual(out, [])

    def test_parse_duration(self):
        self.assertEqual(fo.parse_duration("PT1H2M3S"), 3723)
        self.assertEqual(fo.parse_duration("PT45S"), 45)
        self.assertEqual(fo.parse_duration("P1DT1S"), 86401)


if __name__ == "__main__":
    unittest.main()
