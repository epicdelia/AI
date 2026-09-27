import unittest

import ai_tells


def names(text):
    return {name for _, _, name, _ in ai_tells.lint(text)}


class TestAITells(unittest.TestCase):
    def test_em_dash_is_hard_fail(self):
        f = ai_tells.lint("I tried it — wild.")
        self.assertEqual(f[0][0], ai_tells.HARD)

    def test_banned_phrase(self):
        self.assertIn("banned phrase", names("Let's dive in to Claude."))

    def test_reversal_pattern(self):
        self.assertIn("'not X, it's Y' reversal", names("It's not a chatbot, it's a router."))

    def test_leverage_verb_but_not_noun(self):
        self.assertIn("'leverage' as a verb", names("You can leverage AI here."))
        self.assertNotIn("'leverage' as a verb", names("I had zero leverage in that negotiation."))

    def test_missing_contraction(self):
        self.assertIn("missing contraction", names("I am testing this."))

    def test_clean_human_text(self):
        text = "Okay I did something kind of sketchy with Claude last night. It worked?? I'm shook."
        self.assertEqual(ai_tells.lint(text), [])


if __name__ == "__main__":
    unittest.main()
