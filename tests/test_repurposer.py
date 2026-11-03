import unittest
import tempfile
from pathlib import Path
from src.editing.repurposer import ContentRepurposer, export_repurposed_pack


SAMPLE_SCRIPT = """# What AI actually replaced at my old Google job

## Cold open (0:00 to 0:40)
[VISUAL: you at desk, whiteboard behind you]
I made a list of tasks. Coding goes early.

## Chapter 1: Gone (1:30 to 4:00)
First sticky note. Boilerplate and unit tests.
This is completely gone.

## Outro
Thanks for watching.
"""


class TestRepurposer(unittest.TestCase):
    def setUp(self):
        self.repurposer = ContentRepurposer(SAMPLE_SCRIPT)

    def test_extract_title(self):
        self.assertEqual(
            self.repurposer.title,
            "What AI actually replaced at my old Google job"
        )

    def test_repurpose_generates_all_formats(self):
        pack = self.repurposer.repurpose()
        self.assertEqual(len(pack.shorts), 3)
        self.assertTrue(pack.linkedin_post)
        self.assertGreaterEqual(len(pack.x_thread), 6)
        self.assertIn("subject_lines", pack.newsletter)
        self.assertGreaterEqual(len(pack.newsletter["subject_lines"]), 2)

    def test_shorts_have_hooks_and_visuals(self):
        pack = self.repurposer.repurpose()
        for short in pack.shorts:
            self.assertGreaterEqual(len(short.hooks), 3)
            self.assertTrue(short.visual_notes)
            self.assertIn("[VISUAL", short.body)
            self.assertLess(short.target_seconds, 60)

    def test_export_writes_files(self):
        pack = self.repurposer.repurpose()
        with tempfile.TemporaryDirectory() as tmpdir:
            out_path = Path(tmpdir)
            files = export_repurposed_pack(pack, out_path)
            self.assertIn("short_1", files)
            self.assertIn("short_2", files)
            self.assertIn("short_3", files)
            self.assertIn("linkedin", files)
            self.assertIn("x_thread", files)
            self.assertIn("newsletter", files)
            for f in files.values():
                self.assertTrue(f.exists())
                self.assertGreater(f.stat().st_size, 50)


if __name__ == "__main__":
    unittest.main()

