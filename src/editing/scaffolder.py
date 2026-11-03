"""Scaffolds the Google Drive or local video project folder hierarchy per editing/workflow.md."""
import os
import re
from datetime import datetime, timedelta
from pathlib import Path
from typing import Optional
from src.editing.brief_generator import generate_edit_brief


SUBFOLDERS = [
    "01_script",
    "02_raw",
    "03_assets",
    "04_edits",
    "05_final",
    "06_thumbnail"
]


def scaffold_project(
    base_dir: Path,
    title: str,
    script_body: str,
    hook: str = "",
    publish_date: Optional[str] = None,
    sponsor_name: Optional[str] = None
) -> dict:
    pub_dt = datetime.fromisoformat(publish_date) if publish_date else (datetime.now() + timedelta(days=7))
    slug = re.sub(r"[^a-z0-9]+", "-", title.lower()).strip("-")
    folder_name = f"{pub_dt.strftime('%Y-%m-%d')}_{slug}"
    project_root = base_dir / folder_name

    # Create directories
    for sub in SUBFOLDERS:
        (project_root / sub).mkdir(parents=True, exist_ok=True)

    # Write script to 01_script/final_script.md
    script_path = project_root / "01_script" / "final_script.md"
    with open(script_path, "w", encoding="utf-8") as f:
        f.write(f"# {title}\n\n{script_body}\n")

    # Generate and write edit_brief.md
    brief_md = generate_edit_brief(title, script_body, hook, publish_date, sponsor_name)
    brief_path = project_root / "01_script" / "edit_brief.md"
    with open(brief_path, "w", encoding="utf-8") as f:
        f.write(brief_md)

    slack_message = (
        f"**New Video Ready to Edit: {title}**\n"
        f"[DIR] Drive Folder: `{folder_name}/`\n"
        f"[BRIEF] Edit Brief: `{folder_name}/01_script/edit_brief.md`\n"
        f"[PACING] Target Duration: ~{len(script_body.split()) / 150.0:.1f} mins\n"
        f"[DUE] Rough Cut Due: {(pub_dt - timedelta(days=5)).strftime('%b %d')} (48h turnaround)\n"
        f"[SLA] Reminder: Max 2 revision rounds per our SLA. Let's crush this one!"
    )

    return {
        "folder_name": folder_name,
        "project_root": str(project_root),
        "brief_path": str(brief_path),
        "slack_message": slack_message,
        "subfolders": [str(project_root / s) for s in SUBFOLDERS]
    }
