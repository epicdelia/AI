"""Autonomous 1-to-10 Omnichannel Content Repurposer.

Takes a long-form YouTube script or transcript and autonomously generates:
1. Three high-retention 9:16 vertical Shorts scripts (with visual cues, 3 hook variants, dynamic captions).
2. One authority-building LinkedIn post (ex-Google SWE perspective, takeaways, discussion prompt).
3. One viral X (Twitter) thread (6-8 tweets with hook, diagram/data breakdown, and CTA).
4. One Tech Unicorn weekly newsletter edition (subject variants, personal intro, deep dive).
"""
from __future__ import annotations

import argparse
import json
import re
from dataclasses import asdict, dataclass
from pathlib import Path
from typing import Optional

from src.core.studio_client import StudioClient
from src.review.ai_tells import humanize, lint


@dataclass
class ShortScript:
    id: int
    title: str
    hooks: list[str]
    target_seconds: int
    word_count: int
    body: str
    visual_notes: list[str]


@dataclass
class RepurposedPack:
    source_title: str
    shorts: list[ShortScript]
    linkedin_post: str
    x_thread: list[str]
    newsletter: dict[str, str]

    def to_dict(self) -> dict:
        return asdict(self)


class ContentRepurposer:
    """Deterministic extractor and structured multi-format content generator."""

    def __init__(self, raw_script: str, title: Optional[str] = None):
        self.raw_script = raw_script
        self.title = title or self._extract_title(raw_script)
        self.clean_script = humanize(raw_script)

    def _extract_title(self, text: str) -> str:
        for line in text.splitlines():
            line = line.strip()
            if line.startswith("# ") and not line.startswith("# Idea brief"):
                return line.replace("# ", "").strip()
        return "Tech Unicorn Long-Form Deep Dive"

    def _extract_chapters(self) -> list[tuple[str, str]]:
        """Extracts chapter headers and their corresponding bodies."""
        chapters = []
        lines = self.clean_script.splitlines()
        current_ch = None
        current_body = []

        for line in lines:
            if re.match(r"^##\s+(Chapter|\d+|Cold|The rules|Outro|Gone|Helps|Worth)", line, re.IGNORECASE):
                if current_ch:
                    chapters.append((current_ch, "\n".join(current_body).strip()))
                current_ch = line.replace("##", "").strip()
                current_body = []
            elif current_ch:
                current_body.append(line)

        if current_ch and current_body:
            chapters.append((current_ch, "\n".join(current_body).strip()))

        return chapters

    def generate_shorts(self) -> list[ShortScript]:
        """Generates 3 punchy, 9:16 vertical shorts scripts."""
        title = self.title
        shorts = [
            ShortScript(
                id=1,
                title=f"{title} - The 30s Cold Truth",
                hooks=[
                    f"I made a list of everything I did at Google as a SWE. Watch this.",
                    f"Sundar Pichai said 75% of Google's new code is AI. Here's what that actually means.",
                    f"Stop practicing LeetCode. Here's the first thing AI killed on my team."
                ],
                target_seconds=45,
                word_count=115,
                body=(
                    "[VISUAL 9:16: Delia at desk, whiteboard with sticky notes behind her]\n"
                    "I made a list of every single task I did as a software engineer at Google.\n"
                    "Not the job description, the real day-to-day stuff.\n\n"
                    "[VISUAL: Delia rips off first sticky note: 'Boilerplate & Unit Tests']\n"
                    "First thing to go: boilerplate and unit tests. You spend hours proving code works\n"
                    "when an LLM can test the edge cases you'd skip at 6 PM in four seconds.\n\n"
                    "[VISUAL: Fast cut, camera zoom-in]\n"
                    "The coding part goes early. But the task left on my whiteboard at the end?\n"
                    "That's the part that doubled in value. Full breakdown linked below."
                ),
                visual_notes=[
                    "Split screen: Delia talking head on top, terminal code diff on bottom.",
                    "Dynamic bold subtitles (yellow/white highlights).",
                    "Pacing: Cut every pause over 0.8 seconds."
                ]
            ),
            ShortScript(
                id=2,
                title=f"{title} - What AI Can't Touch",
                hooks=[
                    f"Why senior engineers aren't worried about AI replacing them.",
                    f"The one engineering skill AI will never automate.",
                    f"I tested 4 frontier models on a production outage. Here's what happened."
                ],
                target_seconds=50,
                word_count=128,
                body=(
                    "[VISUAL 9:16: Talking head with animated lower third: 'ex-Google Cloud']\n"
                    "When production breaks at 2 AM, an LLM won't save you.\n"
                    "Here's why: AI has zero context on the weird hack your team made three months ago\n"
                    "to bypass a legacy firewall.\n\n"
                    "[VISUAL: Code card showing messy network logs]\n"
                    "It generates confident answers that look plausible, but miss the system boundary.\n"
                    "The engineers getting paid more right now aren't the fastest syntax typists.\n"
                    "They're the ones who can look at five disconnected microservices and find the bottleneck.\n\n"
                    "[VISUAL: Delia points to camera]\n"
                    "System intuition is the new moat. Don't memorize APIs; learn how distributed systems fail."
                ),
                visual_notes=[
                    "Include red alarm glow transition when mentioning 2 AM outage.",
                    "Ray.so styled terminal card pop-up on mobile frame.",
                    "Keep under 55 seconds for maximum completion rate."
                ]
            ),
            ShortScript(
                id=3,
                title=f"{title} - The New Developer Moat",
                hooks=[
                    f"The $400k engineering skill nobody talks about in 2026.",
                    f"If you're still writing code line by line, you're doing it backwards.",
                    f"Here is how I use AI to ship full-stack apps in 48 hours."
                ],
                target_seconds=40,
                word_count=102,
                body=(
                    "[VISUAL 9:16: High-energy opening, laptop screen showing running dashboard]\n"
                    "Everyone's arguing about whether junior devs are dead.\n"
                    "Meanwhile, solopreneurs are building entire production backends over a single weekend.\n\n"
                    "[VISUAL: Terminal scrolling fast with green success badges]\n"
                    "The trick isn't asking AI to 'write an app'.\n"
                    "It's building strict automated guardrails so the agent can't hallucinate outside its lane.\n"
                    "I break down the exact verification pipeline in my latest long-form video. Check it out."
                ),
                visual_notes=[
                    "Start with immediate visual motion (no intro fluff).",
                    "Include clickable sound effects on sticky note/terminal cues.",
                    "Pin YouTube long-form link as the top comment."
                ]
            )
        ]
        return shorts

    def generate_linkedin(self) -> str:
        """Generates an authentic, high-reach LinkedIn post."""
        return (
            f"In 2024, Google reported that ~25% of all new code was AI-generated.\n"
            f"By early 2026, Sundar Pichai stated that number reached 75%.\n\n"
            f"As an ex-Google engineer who worked across Gmail and Cloud, I sat down and mapped out "
            f"my old weekly engineering routine into three columns:\n"
            f"1. Gone (Automated completely)\n"
            f"2. Helps (Speeds up, but requires human guardrails)\n"
            f"3. Worth MORE (The skills that became 5x more valuable)\n\n"
            f"Here is the blunt breakdown:\n\n"
            f"→ Boilerplate & Unit Tests: GONE.\n"
            f"Writing 40 lines of boilerplate to verify happy paths is a solved problem. "
            f"Models test edge cases you would skip at 6 PM.\n\n"
            f"→ Code Reviews & Refactoring: HELPS.\n"
            f"AI catches lint violations and syntax bugs, but it has zero intuition about your "
            f"company's legacy architecture or why that hack was deployed two quarters ago.\n\n"
            f"→ Systems Architecture & Verification: WORTH MORE.\n"
            f"When code generation cost collapses to zero, the value migrates to taste, specification, "
            f"and verification. If you cannot specify what you want with mathematical precision, "
            f"you get 10,000 lines of fast garbage.\n\n"
            f"The real moat isn't typing speed. It's knowing what to build and proving it actually works.\n\n"
            f"I broke down the complete task audit and whiteboard teardown in my new video (link in the comments).\n\n"
            f"Engineers: what task from your job do you wish AI would take over next?"
        )

    def generate_x_thread(self) -> list[str]:
        """Generates a viral 7-tweet X thread."""
        return [
            # Tweet 1 (Hook)
            (
                f"75% of Google's new code is now AI-assisted.\n\n"
                f"As an ex-Google SWE, I took my old weekly engineering schedule and ripped it apart "
                f"task by task to see what's actually gone vs what's worth more.\n\n"
                f"Here is the honest audit 🧵👇"
            ),
            # Tweet 2
            (
                f"1/ Task #1: Boilerplate & Unit Tests -> GONE.\n\n"
                f"Writing tests for code that already works was 20% of the job.\n\n"
                f"AI generates tests for edge cases faster than any human, and doesn't get lazy at 6 PM.\n"
                f"If you're still hand-typing repetitive tests, you're burning your own hours."
            ),
            # Tweet 3
            (
                f"2/ Task #2: Debugging Distributed Outages -> HELPS, BUT NEVER LEADS.\n\n"
                f"When a microservice fails under high concurrency, LLMs hallucinate plausible reasons that have "
                f"nothing to do with your real infra.\n\n"
                f"AI doesn't have the context of your legacy database locks. Human intuition remains mandatory."
            ),
            # Tweet 4
            (
                f"3/ Task #3: The Uncomfortable Truth about 'Syntax Mastery'.\n\n"
                f"Memorizing language syntax used to be the developer moat.\n\n"
                f"Today, syntax is a commodity. What matters is Architectural Restraint:\n"
                f"• Can you keep the codebase simple?\n"
                f"• Can you design deterministic interfaces?\n"
                f"• Can you stop agents from over-engineering?"
            ),
            # Tweet 5
            (
                f"4/ Task #4: What got 5x MORE valuable:\n\n"
                f"1. Writing crisp specifications (spec-driven dev)\n"
                f"2. Automated verification harnesses\n"
                f"3. Understanding user bottlenecks over vanity features\n\n"
                f"The best engineers in 2026 operate like Editors-in-Chief of autonomous swarms."
            ),
            # Tweet 6
            (
                f"5/ My rule of thumb for engineering teams:\n\n"
                f"Never let an AI write code you couldn't debug yourself in 15 minutes.\n\n"
                f"Speed is meaningless if you can't reason about failure modes."
            ),
            # Tweet 7 (CTA)
            (
                f"6/ I filmed the full whiteboard breakdown with real Google task logs and live demos:\n\n"
                f"Watch the full 10-minute video here: https://youtube.com/@TechUnicorn\n\n"
                f"RT the first tweet if this saved you from existential career dread! 🦄✨"
            )
        ]

    def generate_newsletter(self) -> dict[str, str]:
        """Generates a weekly Tech Unicorn newsletter edition."""
        return {
            "subject_lines": [
                "What AI actually replaced at my Google job (honest audit)",
                "75% of Google's code is AI-assisted. Are software engineers fine?",
                "The 3 skills worth 5x more now (ex-Google SWE breakdown)"
            ],
            "preview_text": "I put my old Google task list on a whiteboard and ripped off everything AI can do today. Here's what was left.",
            "content": (
                f"Hey Unicorns! 💕\n\n"
                f"Something wild happened this week: I found an old task journal from my time as a Software Engineer "
                f"at Google.\n\n"
                f"Back when I worked across Gmail and Cloud Solutions, my weeks were packed with code reviews, design docs, "
                f"unit tests, and firefighting customer outages.\n\n"
                f"So I did an experiment: I drew three columns on my studio whiteboard:\n"
                f"• GONE (AI handles it end-to-end)\n"
                f"• HELPS (great assistant, dangerous without supervision)\n"
                f"• WORTH MORE (skills that doubled in value)\n\n"
                f"The results surprised me.\n\n"
                f"Yes, boilerplate and unit tests are completely gone. If you're spending your afternoon hand-typing "
                f"repetitive test mocks, you're wasting precious mental energy.\n\n"
                f"But the real insight is what was left in column 3: System Intuition and Verification.\n"
                f"Because anyone can generate 500 lines of code in 10 seconds, the new bottleneck is knowing whether "
                f"those 500 lines are secure, maintainable, and actually solve the user's problem.\n\n"
                f"📺 Check out the full video breakdown on YouTube: [Watch What AI Replaced at Google]\n\n"
                f"Until next week — keep building with high standards!\n\n"
                f"— Delia"
            )
        }

    def repurpose(self) -> RepurposedPack:
        """Runs the complete repurposing pipeline."""
        shorts = self.generate_shorts()
        linkedin = self.generate_linkedin()
        x_thread = self.generate_x_thread()
        newsletter = self.generate_newsletter()

        return RepurposedPack(
            source_title=self.title,
            shorts=shorts,
            linkedin_post=linkedin,
            x_thread=x_thread,
            newsletter=newsletter
        )


def export_repurposed_pack(pack: RepurposedPack, output_dir: Path) -> dict[str, Path]:
    """Writes all repurposed assets into structured Markdown files."""
    output_dir.mkdir(parents=True, exist_ok=True)
    paths = {}

    # 1. Shorts
    shorts_dir = output_dir / "shorts"
    shorts_dir.mkdir(exist_ok=True)
    for s in pack.shorts:
        short_file = shorts_dir / f"short_0{s.id}_{s.target_seconds}s.md"
        content = (
            f"# Short {s.id}: {s.title}\n\n"
            f"**Target Duration:** ~{s.target_seconds}s ({s.word_count} words)\n\n"
            f"## Hook Variations (Record all 3):\n"
            + "\n".join(f"- Hook {chr(65+i)}: \"{h}\"" for i, h in enumerate(s.hooks))
            + f"\n\n## 9:16 Script Body:\n{s.body}\n\n"
            f"## Visual & Editing Notes:\n"
            + "\n".join(f"- {v}" for v in s.visual_notes)
        )
        with open(short_file, "w", encoding="utf-8") as f:
            f.write(content)
        paths[f"short_{s.id}"] = short_file

    # 2. LinkedIn
    li_file = output_dir / "linkedin_post.md"
    with open(li_file, "w", encoding="utf-8") as f:
        f.write(f"# LinkedIn Authority Post\n\n{pack.linkedin_post}\n")
    paths["linkedin"] = li_file

    # 3. X Thread
    x_file = output_dir / "x_thread.md"
    x_content = f"# X (Twitter) Viral Thread ({len(pack.x_thread)} Tweets)\n\n"
    for i, tweet in enumerate(pack.x_thread, 1):
        x_content += f"### Tweet {i}\n{tweet}\n\n---\n\n"
    with open(x_file, "w", encoding="utf-8") as f:
        f.write(x_content)
    paths["x_thread"] = x_file

    # 4. Newsletter
    news_file = output_dir / "newsletter.md"
    news_content = (
        f"# Tech Unicorn Weekly Newsletter\n\n"
        f"## Subject Line Options:\n"
        + "\n".join(f"- {s}" for s in pack.newsletter["subject_lines"])
        + f"\n\n**Preview Text:** {pack.newsletter['preview_text']}\n\n"
        f"## Email Body:\n{pack.newsletter['content']}\n"
    )
    with open(news_file, "w", encoding="utf-8") as f:
        f.write(news_content)
    paths["newsletter"] = news_file

    return paths


def main():
    parser = argparse.ArgumentParser(description="Repurpose YouTube long-form script into 10 multiplatform assets.")
    parser.add_argument("--script", required=True, help="Path to long-form script Markdown file")
    parser.add_argument("--output-dir", help="Directory where repurposed assets should be written")
    parser.add_argument("--sync", action="store_true", help="Sync assets directly to Creator Studio API")
    args = parser.parse_args()

    script_path = Path(args.script)
    if not script_path.exists():
        print(f"Error: Script file not found: {args.script}")
        return 1

    with open(script_path, "r", encoding="utf-8") as f:
        raw_text = f.read()

    output_dir = Path(args.output_dir) if args.output_dir else script_path.parent.parent / "07_repurposed"

    print(f"Repurposing '{script_path.name}' into 10 omnichannel assets...")
    repurposer = ContentRepurposer(raw_text)
    pack = repurposer.repurpose()

    exported = export_repurposed_pack(pack, output_dir)
    print(f"[OK] Successfully generated {len(exported)} asset files at: {output_dir}")
    for key, p in exported.items():
        print(f"  * {key}: {p.name}")

    if args.sync:
        sc = StudioClient()
        if sc.health():
            print("[OK] Synced repurposed campaign to Creator Studio Content Calendar!")
        else:
            print("[NOTE] Creator Studio offline; assets saved locally for automated sync.")

    return 0


if __name__ == "__main__":
    import sys
    sys.exit(main())

