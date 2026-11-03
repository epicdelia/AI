"""Configuration and paths for Tech Unicorn Studio pipeline."""
import json
import os
from pathlib import Path

ROOT_DIR = Path(__file__).resolve().parents[2]
CONFIG_DIR = ROOT_DIR / "config"
CHANNELS_PATH = CONFIG_DIR / "channels.json"
SCOREBOARD_PATH = CONFIG_DIR / "scoreboard.json"
IDEAS_DIR = ROOT_DIR / "ideas"
BASELINE_PATH = IDEAS_DIR / "channel_baseline.json"

STUDIO_API_URL = os.getenv("STUDIO_API_URL", "http://localhost:8000/api")


def load_channels() -> list[dict]:
    if not CHANNELS_PATH.exists():
        return []
    with open(CHANNELS_PATH, "r", encoding="utf-8") as f:
        data = json.load(f)
        return data.get("channels", []) if isinstance(data, dict) else data


def load_scoreboard() -> list[dict]:
    if not SCOREBOARD_PATH.exists():
        return []
    with open(SCOREBOARD_PATH, "r", encoding="utf-8") as f:
        return json.load(f)


def load_baseline() -> dict:
    if not BASELINE_PATH.exists():
        return {}
    with open(BASELINE_PATH, "r", encoding="utf-8") as f:
        return json.load(f)

