"""Deterministic AI-tell linter for scripts and posts.

Runs before any LLM reviewer. It's cheap, it never drifts, and it catches the
patterns LLM reviewers are worst at noticing in their own output.

Exit code 1 if any HARD rule fails, so it can gate the pipeline.

Usage:
    python review/ai_tells.py drafts/my-script.md
"""

import re
import sys

HARD, WARN = "HARD", "WARN"

BANNED = [
    "in today's fast-paced world", "game-changer", "game changer", "revolutionizing",
    "revolutionize", "delve", "navigate the landscape", "unprecedented", "harness the power",
    "it's no secret", "in the world of", "buckle up", "let's dive in", "dive into",
    "in conclusion", "tapestry", "testament to", "seamlessly", "elevate your",
    "unlock the power", "unleash", "a deep dive", "ever-evolving", "landscape of",
]

RULES = [
    (HARD, "em dash", re.compile("—")),
    (HARD, "spaced en dash used as em dash", re.compile(r"\s–\s")),
    (HARD, "banned phrase", re.compile(r"\b(" + "|".join(map(re.escape, BANNED)) + r")", re.IGNORECASE)),
    (HARD, "'leverage' as a verb", re.compile(r"\bleverag(e|es|ing|ed)\s+(the|your|our|this|these|AI|it)\b", re.IGNORECASE)),
    (WARN, "'not X, it's Y' reversal", re.compile(r"\b(isn't|is not|it's not|not) (just |only |about )?[^.,;!?]{1,40}[,;] (it's|it is|but)\b", re.IGNORECASE)),
    (WARN, "stock setup phrase", re.compile(r"\b(here's the (thing|kicker|catch)|let that sink in|the best part\?|spoiler( alert)?:|plot twist:|but here's where it gets)", re.IGNORECASE)),
    (WARN, "fake-suspense question", re.compile(r"\bThe (result|answer|catch|kicker|twist|verdict|secret)\?", re.IGNORECASE)),
    (WARN, "essay transition", re.compile(r"(^|[.!?]\s+)(Moreover|Furthermore|Additionally|Ultimately|Notably|Importantly),", re.MULTILINE)),
    (WARN, "one-word tricolon", re.compile(r"\b(\w+), (\w+),? and (\w+)[.!]", re.IGNORECASE)),
    (WARN, "missing contraction", re.compile(
        r"\b(I am|you are|we are|they are|do not|does not|did not|is not|are not|was not|it is|that is|"
        r"there is|cannot|will not|would not|should not|could not|have not|has not)\b",
        re.IGNORECASE,
    )),
]

SIGNATURE = [
    "absolutely insane", "absolutely crazy", "absolutely amazing", "obsessed with",
    "highly recommend", "don't judge me", "life-changing", "it's for science",
    "sketchy", "hello world, hello friends", "the world is your oyster",
]


def lint(text):
    findings = []
    for lineno, line in enumerate(text.splitlines(), 1):
        for severity, name, pattern in RULES:
            for m in pattern.finditer(line):
                findings.append((severity, lineno, name, m.group(0).strip()))

    lower = text.lower()
    sig = sum(lower.count(p) for p in SIGNATURE)
    if sig > 3:
        findings.append((WARN, 0, "signature phrases overused", f"{sig} found, cap is 3"))

    first = next((ln.strip() for ln in text.splitlines() if ln.strip() and not ln.startswith("#")), "")
    if re.match(r"So\b", first):
        findings.append((WARN, 1, "opens with 'So'", first[:40]))

    exclaims = text.count("!")
    words = max(len(text.split()), 1)
    if exclaims / words > 0.04:
        findings.append((WARN, 0, "exclamation density", f"{exclaims} in {words} words"))
    return findings


def main(paths):
    failed = False
    for path in paths:
        with open(path) as f:
            findings = lint(f.read())
        for severity, lineno, name, match in findings:
            print(f"{path}:{lineno}: {severity} {name}: {match!r}")
            failed |= severity == HARD
        if not findings:
            print(f"{path}: clean")
    return 1 if failed else 0


if __name__ == "__main__":
    sys.exit(main(sys.argv[1:]))
