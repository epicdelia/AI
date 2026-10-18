import unittest

import post_snapshots as ps
from test_find_outliers import NOW, FakeAPI, vid

CHANNEL = "UCxxxxxxxxxxxxxxxxxxxxxx"


def run(videos):
    return ps.due_snapshots(CHANNEL, get=FakeAPI(videos), now=NOW)


class TestPostSnapshots(unittest.TestCase):
    def setUp(self):
        # Five old Shorts at 2,000 views: the Shorts baseline median.
        self.old_shorts = [vid(i, 40 + i, 2_000, seconds=40) for i in range(5)]

    def test_emits_only_videos_inside_a_window_band(self):
        out = run(self.old_shorts + [
            vid(1, 0.5, 100, seconds=40),   # too young for 1d
            vid(2, 1.2, 900, seconds=40),   # 1d due
            vid(3, 7.0, 3_000, seconds=40),  # 7d due, on time
            vid(4, 8.9, 3_000, seconds=40),  # 7d due, late but inside grace
            vid(5, 9.1, 3_000, seconds=40),  # 7d missed: past grace
            vid(6, 28.5, 6_000, seconds=40),  # 28d due
        ])
        self.assertEqual([r["key"] for r in out], ["v2:1d", "v4:7d", "v3:7d", "v6:28d"])

    def test_score_is_against_same_format_baseline(self):
        out = run(self.old_shorts + [vid(9, 7.5, 6_000, seconds=40)])
        self.assertEqual(out[0]["score"], 3.0)
        self.assertEqual(out[0]["platform"], "YT Shorts")
        self.assertEqual(out[0]["late_days"], 0.5)
        self.assertEqual(out[0]["url"], "https://youtu.be/v9")

    def test_long_form_score_is_null_without_enough_history(self):
        # Mirrors the real channel: 2 old long-form uploads is too few to judge.
        out = run(self.old_shorts + [vid(20, 50, 10_000), vid(21, 60, 10_000), vid(22, 7.2, 6_000)])
        self.assertEqual(out[0]["platform"], "YouTube")
        self.assertIsNone(out[0]["score"])

    def test_nothing_due_is_empty(self):
        self.assertEqual(run(self.old_shorts), [])


if __name__ == "__main__":
    unittest.main()
