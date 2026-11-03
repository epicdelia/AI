import json
import tempfile
import unittest
from pathlib import Path
from src.bridge.hiring_evaluator import generate_trial_pack, evaluate_scorecard, RUBRIC_CRITERIA


class TestHiringEvaluator(unittest.TestCase):
    def test_rubric_criteria_structure(self):
        self.assertGreaterEqual(len(RUBRIC_CRITERIA), 6)
        ids = [c["id"] for c in RUBRIC_CRITERIA]
        self.assertIn("cold_open", ids)
        self.assertIn("followed_brief", ids)
        self.assertIn("feedback_round", ids)

    def test_generate_trial_pack(self):
        with tempfile.TemporaryDirectory() as tmpdir:
            pack = generate_trial_pack("Jordan Smith", role="editor", rate=150.0, output_dir=Path(tmpdir))
            self.assertEqual(pack["candidate"], "Jordan Smith")
            p = Path(pack["directory"])
            self.assertTrue((p / "instructions.md").exists())
            self.assertTrue((p / "trial_brief.md").exists())
            self.assertTrue((p / "scorecard.json").exists())
            self.assertTrue((p / "scorecard.md").exists())

            brief_content = (p / "trial_brief.md").read_text(encoding="utf-8")
            self.assertIn("Paid Trial", brief_content)
            self.assertIn("Jordan Smith", brief_content)

    def test_evaluate_candidate_hire(self):
        with tempfile.TemporaryDirectory() as tmpdir:
            pack = generate_trial_pack("Jordan Smith", output_dir=Path(tmpdir))
            scorecard_path = Path(pack["directory"]) / "scorecard.json"
            with open(scorecard_path, "r", encoding="utf-8") as f:
                data = json.load(f)

            # High scores
            for c in data["criteria"]:
                c["score"] = 5

            with open(scorecard_path, "w", encoding="utf-8") as f:
                json.dump(data, f)

            res = evaluate_scorecard(scorecard_path)
            self.assertEqual(res["decision"], "HIRE")
            self.assertEqual(res["weighted_average"], 5.0)

    def test_evaluate_candidate_reject_on_zero_1s_rule(self):
        with tempfile.TemporaryDirectory() as tmpdir:
            pack = generate_trial_pack("Jordan Smith", output_dir=Path(tmpdir))
            scorecard_path = Path(pack["directory"]) / "scorecard.json"
            with open(scorecard_path, "r", encoding="utf-8") as f:
                data = json.load(f)

            # Mostly 5s but 1 on followed_brief
            for c in data["criteria"]:
                c["score"] = 5 if c["id"] != "followed_brief" else 1

            with open(scorecard_path, "w", encoding="utf-8") as f:
                json.dump(data, f)

            res = evaluate_scorecard(scorecard_path)
            self.assertEqual(res["decision"], "REJECT")
            self.assertIn("Received 1 on 'Followed the Brief'", res["rationale"])


if __name__ == "__main__":
    unittest.main()

