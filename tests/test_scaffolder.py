import json
import tempfile
import unittest
from pathlib import Path
from src.editing.scaffolder import scaffold_project, SUBFOLDERS


class TestScaffolder(unittest.TestCase):
    def test_scaffold_project_generates_cloud_files(self):
        with tempfile.TemporaryDirectory() as tmpdir:
            base_dir = Path(tmpdir)
            title = "Building Production App in 48h"
            script = "Quick intro to building production apps.\n[VISUAL: live terminal coding]\nHere is the code."

            res = scaffold_project(
                base_dir=base_dir,
                title=title,
                script_body=script,
                publish_date="2026-11-10"
            )

            root = Path(res["project_root"])
            self.assertTrue(root.exists())

            # Check subfolders
            for sub in SUBFOLDERS:
                self.assertTrue((root / sub).exists())

            # Check files
            self.assertTrue((root / "01_script" / "final_script.md").exists())
            self.assertTrue((root / "01_script" / "edit_brief.md").exists())
            self.assertTrue((root / "drive_sync.md").exists())
            self.assertTrue((root / "cloud_manifest.json").exists())

            # Verify manifest
            with open(root / "cloud_manifest.json", "r", encoding="utf-8") as f:
                manifest = json.load(f)
            self.assertEqual(manifest["project_title"], title)
            self.assertIn("Google Drive", manifest["cloud_provider"])
            self.assertIn("2TB", manifest["storage_quota_note"])
            self.assertIn("rough_cut_due", manifest["sla"])

            # Verify slack message
            self.assertIn("Google Drive", res["slack_message"])
            self.assertIn("Gemini", res["slack_message"])


if __name__ == "__main__":
    unittest.main()

