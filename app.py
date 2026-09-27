"""Flow: push-to-talk dictation -> AssemblyAI Universal-Streaming -> LLM Gateway polish.

The browser streams mic audio straight to AssemblyAI using a short-lived token
minted here, so the API key never leaves the server. After the user stops
talking, the raw transcript comes back here to be polished by the LLM Gateway.

Run:  uvicorn app:app --port 8000   then open http://localhost:8000
"""

import os
import time
from pathlib import Path

import httpx
from dotenv import load_dotenv
from fastapi import FastAPI, HTTPException
from fastapi.responses import FileResponse
from pydantic import BaseModel

load_dotenv()

STREAMING_TOKEN_URL = "https://streaming.assemblyai.com/v3/token"
LLM_GATEWAY_URL = "https://llm-gateway.assemblyai.com/v1/chat/completions"
LLM_MODEL = os.getenv("LLM_MODEL", "gpt-5-mini")
INDEX_HTML = Path(__file__).parent / "static" / "index.html"
HTTP_TRANSPORT = None  # tests swap in an httpx.MockTransport

SYSTEM_PROMPT = """You turn raw dictated speech into clean written text.
- Remove filler words (um, uh, like, you know), stutters, repeated words and false starts.
- Fix punctuation, capitalization and run-on sentences.
- Keep the speaker's meaning, facts, names, numbers and tone. Do not add new ideas or details.
- Do not guess at words that look misheard; keep them as spoken.
- Format as clean Markdown: a short bolded title line, then either a polished paragraph,
  bullet points, or a short email, whichever fits what was said. Use headers only if there
  are clearly separate topics.
Return only the Markdown, with no preamble."""

app = FastAPI(title="Flow")


class PolishRequest(BaseModel):
    text: str


def _api_key() -> str:
    key = os.getenv("ASSEMBLYAI_API_KEY", "").strip()
    if not key:
        raise HTTPException(500, "ASSEMBLYAI_API_KEY is not set. Copy .env.example to .env and add your key.")
    return key


def _client() -> httpx.AsyncClient:
    return httpx.AsyncClient(transport=HTTP_TRANSPORT, timeout=httpx.Timeout(30.0, connect=5.0))


def _upstream_error(what: str, resp: httpx.Response) -> HTTPException:
    return HTTPException(502, f"{what} failed ({resp.status_code}): {resp.text[:300]}")


@app.get("/")
async def index() -> FileResponse:
    return FileResponse(INDEX_HTML)


@app.get("/api/token")
async def streaming_token() -> dict:
    async with _client() as client:
        resp = await client.get(
            STREAMING_TOKEN_URL,
            params={"expires_in_seconds": 60},
            headers={"authorization": _api_key()},
        )
    if resp.status_code != 200:
        raise _upstream_error("Streaming token request", resp)
    return {"token": resp.json()["token"]}


@app.post("/api/polish")
async def polish(req: PolishRequest) -> dict:
    text = req.text.strip()
    if not text:
        raise HTTPException(400, "Nothing to polish: the transcript is empty.")
    started = time.perf_counter()
    async with _client() as client:
        resp = await client.post(
            LLM_GATEWAY_URL,
            headers={"authorization": _api_key()},
            json={
                "model": LLM_MODEL,
                "messages": [
                    {"role": "system", "content": SYSTEM_PROMPT},
                    {"role": "user", "content": text},
                ],
            },
        )
    if resp.status_code != 200:
        raise _upstream_error("LLM Gateway request", resp)
    try:
        markdown = resp.json()["choices"][0]["message"]["content"].strip()
    except (KeyError, IndexError, TypeError, ValueError, AttributeError):
        raise HTTPException(502, f"Unexpected LLM Gateway response: {resp.text[:300]}")
    return {"markdown": markdown, "model": LLM_MODEL, "llm_ms": round((time.perf_counter() - started) * 1000)}
