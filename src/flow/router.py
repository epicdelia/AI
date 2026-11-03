"""Voice action intent router: classifies dictations and routes them directly to Creator Studio."""
import re
from typing import Optional
from src.core.studio_client import StudioClient


class VoiceIntentRouter:
    """Detects spoken intent prefixes and routes notes directly to Creator Studio."""

    INTENT_PREFIXES = [
        (r"^(?:video )?idea:\s*(.*)", "idea"),
        (r"^(?:studio )?note:\s*(.*)", "note"),
        (r"^script:\s*(.*)", "script"),
        (r"^to[- ]?do:\s*(.*)", "todo")
    ]

    def __init__(self, client: Optional[StudioClient] = None):
        self.client = client or StudioClient()

    def route_dictation(self, text: str) -> dict:
        text_clean = text.strip()
        matched_intent = "dictation"
        payload = text_clean

        for pattern, intent in self.INTENT_PREFIXES:
            match = re.match(pattern, text_clean, re.IGNORECASE)
            if match:
                matched_intent = intent
                payload = match.group(1).strip()
                break

        result = {
            "intent": matched_intent,
            "raw_text": text_clean,
            "payload": payload,
            "synced": False
        }

        if matched_intent == "idea":
            # Extract title and angle if present
            title = payload[:80]
            angle = payload
            hook = payload[:140]
            idea = self.client.save_idea(title=title, angle=angle, hook=hook)
            result["synced"] = bool(idea)
            result["record"] = idea
        elif matched_intent in ("note", "todo"):
            note = self.client.save_note(payload, source="voice_intent")
            result["synced"] = bool(note)
            result["record"] = note

        return result

