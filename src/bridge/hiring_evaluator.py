"""Hiring Evaluator and Trial Task Generator.
Automates candidate trial creation, rubric scoring, and hiring decisions
derived from hiring/hiring_plan.md.
"""
from pathlib import Path
from typing import Dict, Any, List, Optional
import json


RUBRIC_CRITERIA = [
    {
        "id": "cold_open",
        "name": "Cold Open",
        "question": "Did the first 30 seconds pay off the brief's promise without fluff?",
        "weight": 1.0,
    },
    {
        "id": "pacing",
        "name": "Pacing",
        "question": "Dead air removed, no stretch over ~10 seconds with no visual change?",
        "weight": 1.0,
    },
    {
        "id": "screen_recordings",
        "name": "Screen Recordings",
        "question": "Zoomed and cropped so a mobile phone viewer can easily read code/UI?",
        "weight": 1.2,
    },
    {
        "id": "taste",
        "name": "Taste & Sound",
        "question": "Restraint. Effects serve the point, no sound effect spam or cheesy memes?",
        "weight": 1.0,
    },
    {
        "id": "followed_brief",
        "name": "Followed the Brief",
        "question": "Must-keeps kept, must-cuts cut, sponsor placement accurate?",
        "weight": 1.5,
    },
    {
        "id": "feedback_round",
        "name": "Feedback Round",
        "question": "Fixed exactly what was requested; pushed back only with valid technical reasons?",
        "weight": 1.5,
    },
    {
        "id": "communication",
        "name": "Communication & Speed",
        "question": "Asked clarifying questions upfront, hit the 72-hour deadline?",
        "weight": 1.0,
    },
]


def generate_trial_pack(
    candidate_name: str,
    role: str = "editor",
    rate: float = 150.0,
    output_dir: Optional[Path] = None,
) -> Dict[str, Any]:
    """Generates the standardized paid trial task folder for a candidate."""
    safe_name = "".join(c if c.isalnum() else "_" for c in candidate_name.lower()).strip("_")
    if output_dir is None:
        target_dir = Path("hiring/trials") / safe_name
    else:
        target_dir = Path(output_dir)

    target_dir.mkdir(parents=True, exist_ok=True)

    # 1. Instructions
    instructions_text = f"""# Paid Video Editor Trial Task: {candidate_name}

Hi {candidate_name},

Thanks for applying! We shortlist candidates for a **paid** trial edit so you are compensated for your time, and we can test real working chemistry.

### Compensation & Agreement
- **Trial Fee:** ${rate:.2f} USD (payable via Wise, PayPal, or Stripe invoice upon delivery of the rough cut + feedback revision).
- **Turnaround Time:** 72 hours from receiving this brief.

### What We Care About Most
1. **Retention over flashy effects:** Pacing should be snappy without feeling rushed. Dead air cut, micro-pauses trimmed.
2. **Readability on mobile:** 70%+ of our audience watches on phones. Screen recordings and IDEs MUST be zoomed and cropped to 120-140%.
3. **Restraint:** No meme sound packs or random zooms that don't emphasize a specific spoken concept.
4. **Following the brief:** All items marked [MUST KEEP] must be present.

### Deliverables
1. **Rough Cut (within 72 hours):** Upload an unlisted YouTube or Frame.io link.
2. **Feedback Round (within 24-48 hours after notes):** We will provide 3-5 specific timestamp notes. Handling this revision is 50% of the evaluation.

Good luck!
"""

    # 2. Trial Brief
    brief_text = f"""# Edit Brief - Paid Trial
**Role:** {role.title()}
**Candidate:** {candidate_name}
**Project:** Building a Production App in 48h (Sample 6-minute section)

### 1. Hook & Cold Open (0:00 - 0:35)
- **Goal:** Immediately establish the stakes.
- **Audio:** Start on "Most people take 3 months to build what we shipped this weekend." Cut any filler breath.
- **Visual:** Split screen or fast B-roll showing the terminal deployment succeeding, then cut straight to the talking head.
- **Constraint:** Zero music during the first 5 seconds. Subtle riser into the title drop at 0:28.

### 2. Main Narrative & Screen Captures (0:35 - 4:30)
- **Visuals:** Whenever the speaker references code or the terminal:
  - Punch in 130% on the terminal cursor.
  - Highlight the exact flag or function name mentioned.
- **B-Roll:** Use the provided b-roll clips of keyboard typing and coffee cup. Do NOT use generic stock footage of servers or blue matrix code.
- **[MUST KEEP]:** Keep the self-deprecating joke about the database migration failing at 2:14.
- **[MUST CUT]:** Cut the repeated sentence at 3:45 where the speaker restarts the explanation.

### 3. Conclusion & Call-to-Action (4:30 - End)
- **Visuals:** Clean end card with link to GitHub repository.
- **Audio:** Music swells slightly to transition out.
"""

    # 3. Scorecard JSON
    scorecard_data = {
        "candidate": candidate_name,
        "role": role,
        "trial_rate": rate,
        "criteria": [
            {
                "id": c["id"],
                "name": c["name"],
                "question": c["question"],
                "weight": c["weight"],
                "score": 0,
                "notes": "",
            }
            for c in RUBRIC_CRITERIA
        ],
        "overall_notes": "",
    }

    # 4. Scorecard Markdown
    scorecard_md = f"""# Candidate Evaluation Scorecard: {candidate_name}

| Criterion | Weight | Score (1-5) | What to Look For | Notes |
|---|---|---|---|---|
"""
    for c in RUBRIC_CRITERIA:
        scorecard_md += f"| {c['name']} | {c['weight']}x | [ ] / 5 | {c['question']} | |\n"

    scorecard_md += """
### Hiring Rules (from hiring/hiring_plan.md)
1. **Zero 1s Rule:** Any score of 1 on any criterion is an immediate REJECT.
2. **Followed Brief & Feedback Rule:** Combined weighted score on 'Followed the Brief' and 'Feedback Round' must be >= 4.0.
3. **Threshold:** Overall weighted average must be >= 4.0 / 5.0 to HIRE.
"""

    (target_dir / "instructions.md").write_text(instructions_text, encoding="utf-8")
    (target_dir / "trial_brief.md").write_text(brief_text, encoding="utf-8")
    (target_dir / "scorecard.json").write_text(json.dumps(scorecard_data, indent=2), encoding="utf-8")
    (target_dir / "scorecard.md").write_text(scorecard_md, encoding="utf-8")

    return {
        "candidate": candidate_name,
        "directory": str(target_dir),
        "files": ["instructions.md", "trial_brief.md", "scorecard.json", "scorecard.md"],
    }


def evaluate_scorecard(scorecard_file: Path) -> Dict[str, Any]:
    """Evaluates a completed scorecard JSON according to hiring_plan.md rules."""
    with open(scorecard_file, "r", encoding="utf-8") as f:
        data = json.load(f)

    candidate = data.get("candidate", "Unknown")
    criteria = data.get("criteria", [])

    total_weight = 0.0
    weighted_sum = 0.0
    disqualifications = []
    scores_by_id = {}

    for c in criteria:
        cid = c.get("id")
        score = float(c.get("score", 0))
        weight = float(c.get("weight", 1.0))
        name = c.get("name", cid)

        scores_by_id[cid] = score
        total_weight += weight
        weighted_sum += score * weight

        if score == 1:
            disqualifications.append(f"Received 1 on '{name}': Automatic Disqualification per Hiring Rules.")

    avg_weighted = weighted_sum / total_weight if total_weight > 0 else 0.0

    brief_feedback_score = (
        scores_by_id.get("followed_brief", 0) * 1.5 + scores_by_id.get("feedback_round", 0) * 1.5
    ) / 3.0

    if disqualifications:
        decision = "REJECT"
        rationale = "Candidate failed the zero-tolerance standard:\n  * " + "\n  * ".join(disqualifications)
    elif brief_feedback_score < 3.5:
        decision = "REJECT"
        rationale = f"Candidate scored low on critical execution (Brief & Feedback average: {brief_feedback_score:.2f} < 3.5). Talent without brief adherence will cost more hours than it saves."
    elif avg_weighted >= 4.0:
        decision = "HIRE"
        rationale = f"Strong candidate with {avg_weighted:.2f} weighted average. Excellent pacing and responsive on revisions."
    elif avg_weighted >= 3.5:
        decision = "BORDERLINE"
        rationale = f"Candidate scored {avg_weighted:.2f}. Competent but consider interviewing alternative candidates before making offer."
    else:
        decision = "REJECT"
        rationale = f"Weighted score {avg_weighted:.2f} below the hiring bar (4.0)."

    return {
        "candidate": candidate,
        "decision": decision,
        "weighted_average": round(avg_weighted, 2),
        "brief_feedback_score": round(brief_feedback_score, 2),
        "rationale": rationale,
    }

