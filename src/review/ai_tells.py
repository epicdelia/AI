"""Deterministic AI-tell linter for scripts and posts.
Runs before any LLM reviewer to catch machine clichés, robotic cadence, and banned phrases.
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


def lint(text: str) -> list[tuple[str, int, str, str]]:
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


HUMANIZE_REPLACEMENTS = [
    (re.compile(r"\bdelve into\b"), "explore"),
    (re.compile(r"\bdelve\b"), "dig"),
    (re.compile(r"\bgame-changer\b"), "big shift"),
    (re.compile(r"\bgame changer\b"), "big shift"),
    (re.compile(r"\btestament to\b"), "proof of"),
    (re.compile(r"\bseamlessly\b"), "smoothly"),
    (re.compile(r"\bIt is\b"), "It's"),
    (re.compile(r"\bit is\b"), "it's"),
    (re.compile(r"\bDo not\b"), "Don't"),
    (re.compile(r"\bdo not\b"), "don't"),
    (re.compile(r"\bCannot\b"), "Can't"),
    (re.compile(r"\bcannot\b"), "can't"),
    (re.compile(r"\bYou are\b"), "You're"),
    (re.compile(r"\byou are\b"), "you're"),
]


def humanize(text: str) -> str:
    """Auto-correct common AI tells: em dashes, formal contractions, buzzwords."""
    clean = text.replace("—", ", ").replace(" – ", ", ")
    for pattern, repl in HUMANIZE_REPLACEMENTS:
        clean = pattern.sub(repl, clean)
    return clean


def lint_paths(paths: list[str]) -> bool:
    has_hard = False
    for path in paths:
        with open(path, "r", encoding="utf-8") as f:
            findings = lint(f.read())
        for severity, lineno, name, match in findings:
            print(f"{path}:{lineno}: {severity} {name}: {match!r}")
            if severity == HARD:
                has_hard = True
        if not findings:
            print(f"{path}: clean (0 AI tells)")
    return has_hard


def main(paths: list[str]) -> int:
    return 1 if lint_paths(paths) else 0


if __name__ == "__main__":
    sys.exit(main(sys.argv[1:]))

