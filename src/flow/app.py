"""Flow: push-to-talk dictation -> AssemblyAI Universal-Streaming -> LLM Gateway polish.
Includes Voice Intent Routing for Creator Studio synchronization.
"""
import json
import os
from pathlib import Path
from typing import Literal

import httpx
from dotenv import load_dotenv
from fastapi import FastAPI, HTTPException
from fastapi.responses import FileResponse, StreamingResponse
from fastapi.staticfiles import StaticFiles
from pydantic import BaseModel, Field

from src.flow.prompts import (
    CLEANUP_RULES,
    STYLE_FORMATS,
    PolishStyle,
    RewriteAction,
    rewrite_prompt,
    command_prompt,
    system_prompt,
)
from src.flow.router import VoiceIntentRouter

load_dotenv()

STREAMING_TOKEN_URL = "https://streaming.assemblyai.com/v3/token"
STREAM_URL = os.getenv("FLOW_STREAM_URL", "wss://streaming.assemblyai.com/v3/ws")
LLM_GATEWAY_URL = "https://llm-gateway.assemblyai.com/v1/chat/completions"
LLM_MODELS_URL = "https://llm-gateway.assemblyai.com/v1/models"
LLM_MODEL = os.getenv("LLM_MODEL", "gpt-5-mini")
FAST_HINTS = ("nano", "flash", "mini", "haiku", "lite", "small", "instant", "fast")
MAX_MODEL_TRIES = 8
_working_model: str | None = None
STATIC_DIR = Path(__file__).resolve().parents[2] / "static"
INDEX_HTML = STATIC_DIR / "index.html"
HTTP_TRANSPORT = None

SYSTEM_PROMPT = system_prompt("auto")

app = FastAPI(title="Flow")
if STATIC_DIR.exists():
    app.mount("/static", StaticFiles(directory=STATIC_DIR), name="static")

intent_router = VoiceIntentRouter()


class PolishRequest(BaseModel):
    text: str = Field(max_length=20000)
    style: PolishStyle = "auto"
    rewrite: RewriteAction | None = None
    instruction: str | None = Field(default=None, max_length=1000)
    snippets: list[str] = Field(default_factory=list, max_length=20)


def snippet_rules(cues: list[str]) -> str:
    cues = [c.strip()[:80] for c in cues if c.strip()]
    if not cues:
        return ""
    listed = "\n".join(f'  {n}. "{c}"' for n, c in enumerate(cues, 1))
    return (
        "\n- Snippets: when the speaker says one of these cue phrases (or a clear variant of it), write the "
        "placeholder [[SNIPPET n]] for it instead of the words, and nothing else for that cue:\n" + listed
    )


def _api_key() -> str:
    key = os.getenv("ASSEMBLYAI_API_KEY", "").strip()
    if not key:
        raise HTTPException(
            500,
            "ASSEMBLYAI_API_KEY is not set. Locally: copy .env.example to .env and add your key.",
        )
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
    return {
        "token": resp.json()["token"],
        "speech_model": os.getenv("FLOW_SPEECH_MODEL", "").strip() or None,
        "stream_url": STREAM_URL,
    }


STREAM_ERROR = "\x00"


async def _sse_text(resp: httpx.Response, client: httpx.AsyncClient):
    done = False
    try:
        async for line in resp.aiter_lines():
            if not line.startswith("data:"):
                continue
            data = line[len("data:"):].strip()
            if data == "[DONE]":
                done = True
                break
            try:
                event = json.loads(data)
            except ValueError:
                continue
            if event.get("error"):
                yield f"{STREAM_ERROR}The AI reply stopped with an error: {json.dumps(event['error'])[:300]}"
                return
            for choice in event.get("choices") or []:
                delta = (choice.get("delta") or {}).get("content")
                if delta:
                    yield delta
        if not done:
            yield f"{STREAM_ERROR}The AI reply was cut off before it finished."
    finally:
        await resp.aclose()
        await client.aclose()


@app.post("/api/polish")
async def polish(req: PolishRequest):
    if req.rewrite:
        prompt = rewrite_prompt(req.rewrite)
    elif req.instruction:
        prompt = command_prompt(req.instruction)
    else:
        prompt = system_prompt(req.style) + snippet_rules(req.snippets)

    body = {
        "model": LLM_MODEL,
        "messages": [
            {"role": "system", "content": prompt},
            {"role": "user", "content": req.text},
        ],
        "temperature": 0.2,
    }

    client = _client()
    try:
        resp = await client.post(
            LLM_GATEWAY_URL,
            json=body,
            headers={"authorization": _api_key()},
        )
        if resp.status_code != 200:
            raise _upstream_error("LLM Gateway request", resp)
        result_text = resp.json()["choices"][0]["message"]["content"]
        
        # Check intent routing
        intent_res = intent_router.route_dictation(result_text)

        return {
            "text": result_text,
            "intent": intent_res["intent"],
            "synced_to_studio": intent_res["synced"]
        }
    finally:
        await client.aclose()

