import unittest
from datetime import date

import channel_stats
from test_find_outliers import NOW, FakeAPI, vid


class TestChannelStats(unittest.TestCase):
    def test_only_uploads_since_date_oldest_first_with_format(self):
        videos = [vid(1, 20, 5_000), vid(2, 3, 9_000), vid(3, 1, 40_000, seconds=40)]
        out = channel_stats.uploads_since("UCxxxxxxxxxxxxxxxxxxxxxx", date(2026, 9, 20), get=FakeAPI(videos), now=NOW)
        self.assertEqual([v["id"] for v in out], ["v2", "v3"])
        self.assertEqual([v["long_form"] for v in out], [True, False])
        self.assertEqual(out[0]["url"], "https://youtu.be/v2")


if __name__ == "__main__":
    unittest.main()
