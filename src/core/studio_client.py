"""HTTP client for communicating directly with the Creator Studio REST API."""
import os
from typing import Any, Optional
from src.core.config import STUDIO_API_URL


class StudioClient:
    def __init__(self, base_url: str = STUDIO_API_URL, token: Optional[str] = None):
        self.base_url = base_url.rstrip("/")
        self.token = token or os.getenv("STUDIO_API_TOKEN")
        self._session = None

    def _get_httpx(self):
        try:
            import httpx
            return httpx
        except ImportError:
            return None

    def _headers(self) -> dict[str, str]:
        headers = {"Accept": "application/json", "Content-Type": "application/json"}
        if self.token:
            headers["Authorization"] = f"Bearer {self.token}"
        return headers

    def _client(self):
        """Persistent keep-alive HTTP client session with connection pooling."""
        if self._session is None:
            httpx = self._get_httpx()
            if httpx:
                self._session = httpx.Client(timeout=5.0, headers=self._headers())
        return self._session

    def close(self):
        if self._session is not None:
            try:
                self._session.close()
            except Exception:
                pass
            self._session = None

    def __enter__(self):
        return self

    def __exit__(self, *args):
        self.close()

    def health(self) -> bool:
        client = self._client()
        if not client:
            return False
        try:
            res = client.get(f"{self.base_url}/auth/status")
            return res.status_code == 200
        except Exception:
            return False

    def sync_outlier(self, outlier_data: dict[str, Any]) -> bool:
        """Post a discovered breakout video to Creator Studio."""
        client = self._client()
        if not client:
            return False
        try:
            res = client.post(
                f"{self.base_url}/breakouts/scan",
                json=outlier_data
            )
            return res.status_code in (200, 201)
        except Exception:
            return False

    def save_idea(self, title: str, angle: str, hook: str) -> Optional[dict]:
        """Save a new greenlit idea to Creator Studio."""
        client = self._client()
        if not client:
            return None
        try:
            res = client.post(
                f"{self.base_url}/ideas",
                json={"title": title, "angle": angle, "hook": hook}
            )
            if res.status_code in (200, 201):
                return res.json().get("idea")
        except Exception:
            pass
        return None

    def save_note(self, text: str, source: str = "voice") -> Optional[dict]:
        """Save a voice dictation note to Creator Studio."""
        client = self._client()
        if not client:
            return None
        try:
            res = client.post(
                f"{self.base_url}/notes",
                json={"body": text, "source": source}
            )
            if res.status_code in (200, 201):
                return res.json().get("note")
        except Exception:
            pass
        return None

    def update_sunday_checkin(self, week_number: int, views_7d: int, status: str, verdict: str) -> bool:
        """Update Sunday mentor commitment scoreboard in Creator Studio."""
        httpx = self._get_httpx()
        if not httpx:
            return False
        try:
            with httpx.Client(timeout=5.0) as client:
                res = client.patch(
                    f"{self.base_url}/goals/{week_number}",
                    json={
                        "status": status,
                        "views_7d": views_7d,
                        "verdict": verdict
                    },
                    headers=self._headers()
                )
                return res.status_code == 200
        except Exception:
            return False
