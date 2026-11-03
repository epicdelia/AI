"""Parses script markdown to extract visual shot lists and generate structured edit briefs per editing/workflow.md."""
import re
from datetime import datetime, timedelta
from typing import Optional


def extract_visual_cues(script_body: str) -> list[dict]:
    cues = []
    lines = script_body.splitlines()
    word_count = 0

    for idx, line in enumerate(lines, 1):
        line_trim = line.strip()
        if line_trim.startswith("#"):
            continue

        matches = re.findall(r"\[(?:VISUAL|B-?ROLL|SCREEN|GRAPHIC|ON[- ]?SCREEN|SFX)\b([^\]]*)\]", line_trim, re.IGNORECASE)
        if matches:
            elapsed_sec = int(round(word_count / 150.0 * 60))
            mins = elapsed_sec // 60
            secs = elapsed_sec % 60
            time_str = f"{mins:02d}:{secs:02d}"
            for match in matches:
                cues.append({
                    "time": time_str,
                    "line": idx,
                    "text": match.strip() or line_trim
                })

        words = len(re.findall(r"\w+", line_trim))
        word_count += words

    return cues


def generate_edit_brief(
    title: str,
    script_body: str,
    hook: str = "",
    publish_date: Optional[str] = None,
    sponsor_name: Optional[str] = None
) -> str:
    words = len(script_body.split())
    target_min = round(words / 150.0, 1)
    ceiling_min = int(target_min * 1.25) + 1

    pub_dt = datetime.fromisoformat(publish_date) if publish_date else (datetime.now() + timedelta(days=7))
    rough_due = (pub_dt - timedelta(days=5)).strftime("%b %d, %Y (%a)")
    final_due = (pub_dt - timedelta(days=2)).strftime("%b %d, %Y (%a)")
    pub_str = pub_dt.strftime("%B %d, %Y (%A)")

    slug = re.sub(r"[^a-z0-9]+", "-", title.lower()).strip("-")
    folder_path = f"YouTube/{pub_dt.strftime('%Y-%m-%d')}_{slug}/"

    cues = extract_visual_cues(script_body)
    shot_list_lines = [f"- **{c['time']}** (Line {c['line']}): `{c['text']}`" for c in cues[:30]]
    shot_list_md = "\n".join(shot_list_lines) if shot_list_lines else "- Follow natural pacing on talking-head takes."

    sponsor_section = (
        f"Segment: Mid-Roll (around 02:30)\nBrand: {sponsor_name}\nDeliverable: 60s dedicated integration\nReview required: Yes"
        if sponsor_name else "No paid sponsor on this video."
    )

    return f"""# Edit brief: {title}

**Publish date:** {pub_str}
**Folder:** {folder_path}
**Target length:** {target_min} min (hard ceiling {ceiling_min} min)
**Rough cut due:** {rough_due}  **Final due:** {final_due}

## The one-sentence promise
{hook or title}

## Title + thumbnail (so the cold open matches)
Title: {title}
Thumbnail text: (source in 06_thumbnail/)
**The first 30 seconds must pay this off by showing:** High-energy hook and instant visual proof.

## Reference videos (pacing and style to copy)
1. Tech Unicorn style: Fast cuts, zoom-ins on reactions/code, terminal graphics, crisp audio.
2. Avoid: Overused meme sound effects, spinning 3D transitions, low-contrast subtitle fonts.

## Shot list
The `[VISUAL]` / `[B-ROLL]` notes extracted from `01_script/`:
{shot_list_md}

## Must cut
- Flubs and retakes (I say "again" or "take two" before a retake)
- Anything after I say "cut that"
- Pauses longer than 1.2 seconds on talking head

## Must keep
- Authentic reactions, terminal live coding executions, and raw bug fixes
- Personal stories and engineering insights

## Sponsor / disclosure
{sponsor_section}

## Deliverables & SLA (Strict 2-round review limit)
- [ ] Rough cut v1 (structure and pacing only, zero polish) due {rough_due}
- [ ] Polished v2 (graphics, zooms, sound, captions) due {final_due}
- [ ] Final 4K export in `05_final/`
- [ ] Captions file (`captions.srt`)
- [ ] 3 vertical Shorts (9:16) with burned-in dynamic captions
"""

