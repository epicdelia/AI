"""Scaffolds the Google Drive or local video project folder hierarchy per editing/workflow.md.

Includes native Google Drive cloud storage provisioning, editor SLAs, and
Gemini AI Premium (2TB free cloud storage) integration metadata.
"""
from __future__ import annotations

import json
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
    "06_thumbnail",
    "07_repurposed"
]


def generate_drive_sync_guide(title: str, folder_name: str) -> str:
    """Generates a pragmatic, witty guide on utilizing Google Drive 2TB with Gemini."""
    return f"""# Google Drive + Gemini AI Cloud Storage Guide: {title}

## 💡 The Unfair Advantage: 2TB Free Storage with Your Gemini Account
Delia, you get **2TB of high-speed Google Cloud / Drive storage included free** with your Google One AI Premium (Gemini Advanced) account!

### Why this is a massive win for your content pipeline:
1. **Zero SaaS Tax ($0 vs $30–$50/mo):**
   - You don't need to pay for Frame.io Pro, Dropbox Business, or WeTransfer subscriptions just to send 4K ProRes files to editors.
   - 2TB gives you enough headroom to store **60+ complete long-form video archives** (raw footage, A-roll, screen recordings, B-roll, and final 4K masters) before ever needing to archive.

2. **Gemini 1M+ Context Window Native Ingestion (The AI Superpower):**
   - Because your footage lives in Google Drive, Gemini (Gemini 4 Argon / 1.5 Pro) can ingest full video and audio files **directly from Google Drive** without uploading gigabytes over the web twice!
   - You can point Gemini at a 20-minute raw take in Drive and ask: *"Find the 3 funniest flubs"*, *"Transcribe timestamps for B-roll cutaways"*, or *"Generate dynamic caption timings"*.

3. **Frictionless Freelance Editor Handoff:**
   - Editors can preview and scrub 4K video files directly in Chrome without waiting for 20GB downloads.
   - Using **Google Drive for Desktop**, your local `{folder_name}/02_raw` folder syncs to the cloud automatically while you sleep.

---

## 🚀 How to Sync in 3 Steps

### Step 1: Drag Footage into `02_raw/`
Put your raw camera takes, screen recordings, and audio files into `{folder_name}/02_raw/`.

### Step 2: Share Google Drive Link with Editor
Right-click the folder in Google Drive (or use the desktop sync folder) -> **Share** -> Set editor email to **Editor (can organize, add, & edit)**.

### Step 3: Editor Delivers into `04_edits/`
The editor uploads Rough Cut v1 and Polished v2 directly into `{folder_name}/04_edits/` for your review!
"""


def scaffold_project(
    base_dir: Path,
    title: str,
    script_body: str,
    hook: str = "",
    publish_date: Optional[str] = None,
    sponsor_name: Optional[str] = None,
    drive_folder_url: Optional[str] = None
) -> dict:
    pub_dt = datetime.fromisoformat(publish_date) if publish_date else (datetime.now() + timedelta(days=7))
    slug = re.sub(r"[^a-z0-9]+", "-", title.lower()).strip("-")
    folder_name = f"{pub_dt.strftime('%Y-%m-%d')}_{slug}"
    project_root = base_dir / folder_name

    # Create directories
    for sub in SUBFOLDERS:
        (project_root / sub).mkdir(parents=True, exist_ok=True)

    # 1. Write script to 01_script/final_script.md
    script_path = project_root / "01_script" / "final_script.md"
    with open(script_path, "w", encoding="utf-8") as f:
        f.write(f"# {title}\n\n{script_body}\n")

    # 2. Generate and write edit_brief.md
    brief_md = generate_edit_brief(title, script_body, hook, publish_date, sponsor_name)
    brief_path = project_root / "01_script" / "edit_brief.md"
    with open(brief_path, "w", encoding="utf-8") as f:
        f.write(brief_md)

    # 3. Write Google Drive & Gemini Cloud Storage Guide
    drive_guide_md = generate_drive_sync_guide(title, folder_name)
    drive_guide_path = project_root / "drive_sync.md"
    with open(drive_guide_path, "w", encoding="utf-8") as f:
        f.write(drive_guide_md)

    # 4. Generate cloud_manifest.json
    cloud_url = drive_folder_url or f"https://drive.google.com/drive/search?q={folder_name}"
    manifest = {
        "project_title": title,
        "folder_name": folder_name,
        "publish_date": pub_dt.strftime("%Y-%m-%d"),
        "cloud_provider": "Google Drive (Google One AI Premium / Gemini 2TB Tier)",
        "cloud_folder_url": cloud_url,
        "storage_quota_note": "2TB high-speed cloud storage included free with Gemini Advanced account",
        "subfolders": SUBFOLDERS,
        "sla": {
            "rough_cut_due": (pub_dt - timedelta(days=5)).strftime("%Y-%m-%d"),
            "final_cut_due": (pub_dt - timedelta(days=2)).strftime("%Y-%m-%d"),
            "max_revision_rounds": 2
        }
    }
    manifest_path = project_root / "cloud_manifest.json"
    with open(manifest_path, "w", encoding="utf-8") as f:
        json.dump(manifest, f, indent=2)

    slack_message = (
        f"**New Video Ready to Edit: {title}**\n"
        f"📁 Google Drive Cloud Folder: {cloud_url}\n"
        f"📑 Edit Brief: `{folder_name}/01_script/edit_brief.md`\n"
        f"⏱ Target Duration: ~{len(script_body.split()) / 150.0:.1f} mins\n"
        f"📅 Rough Cut Due: {(pub_dt - timedelta(days=5)).strftime('%b %d')} (48h turnaround)\n"
        f"💡 Storage: Hosted on Delia's Gemini Google Drive (2TB fast cloud storage)\n"
        f"⚡ SLA: Max 2 revision rounds per agreement. Let's make this viral! 🦄"
    )

    return {
        "folder_name": folder_name,
        "project_root": str(project_root),
        "brief_path": str(brief_path),
        "drive_guide_path": str(drive_guide_path),
        "manifest_path": str(manifest_path),
        "slack_message": slack_message,
        "subfolders": [str(project_root / s) for s in SUBFOLDERS]
    }
