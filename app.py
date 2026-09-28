"""Flow: push-to-talk dictation -> AssemblyAI Universal-Streaming -> LLM Gateway polish.

The browser streams mic audio straight to AssemblyAI using a short-lived token
minted here, so the API key never leaves the server. After the user stops
talking, the raw transcript comes back here to be polished by the LLM Gateway.

Run:  uvicorn app:app --port 8000   then open http://localhost:8000
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
from pydantic import BaseModel

load_dotenv()

STREAMING_TOKEN_URL = "https://streaming.assemblyai.com/v3/token"
LLM_GATEWAY_URL = "https://llm-gateway.assemblyai.com/v1/chat/completions"
LLM_MODELS_URL = "https://llm-gateway.assemblyai.com/v1/models"
LLM_MODEL = os.getenv("LLM_MODEL", "gpt-5-mini")
FAST_HINTS = ("nano", "flash", "mini", "haiku", "lite", "small", "instant", "fast")
MAX_MODEL_TRIES = 8
_working_model: str | None = None  # the first model this key could use; remembered for later requests
STATIC_DIR = Path(__file__).parent / "static"
INDEX_HTML = STATIC_DIR / "index.html"
HTTP_TRANSPORT = None  # tests swap in an httpx.MockTransport

CLEANUP_RULES = """You turn raw dictated speech into clean written text.
- Remove filler words (um, uh, like, you know), stutters, repeated words and false starts.
- If the speaker corrects themselves ("Tuesday, no, Wednesday"), keep only the correction.
- Fix punctuation, capitalization and run-on sentences.
- Keep the speaker's meaning, facts, names, numbers and tone. Do not add new ideas or details.
- Do not guess at words that look misheard; keep them as spoken.
"""
STYLE_FORMATS = {
    "auto": "- Format as clean Markdown: a short bolded title line, then either a polished paragraph,\n"
            "  bullet points, or a short email, whichever fits what was said. Use headers only if there\n"
            "  are clearly separate topics.",
    "message": "- Format as a chat message (Slack, WhatsApp, iMessage): plain conversational text, no title,\n"
               "  no headers, short paragraphs. Use bullets only if the speaker lists several items.",
    "email": "- Format as an email body: a greeting line if the speaker named who it is for, short paragraphs,\n"
             "  then a simple sign-off such as \"Thanks,\". Do not invent a name, subject line or details.",
    "notes": "- Format as notes: a short bolded title line, then concise bullet points, one idea per bullet,\n"
             "  with action items marked \"- [ ]\".",
}
PolishStyle = Literal["auto", "message", "email", "notes"]


def system_prompt(style: str = "auto") -> str:
    return CLEANUP_RULES + STYLE_FORMATS[style] + "\nReturn only the text, with no preamble."


SYSTEM_PROMPT = system_prompt("auto")

app = FastAPI(title="Flow")
app.mount("/static", StaticFiles(directory=STATIC_DIR), name="static")  # manifest + icons for Add to Home Screen


class PolishRequest(BaseModel):
    text: str
    style: PolishStyle = "auto"


def _api_key() -> str:
    key = os.getenv("ASSEMBLYAI_API_KEY", "").strip()
    if not key:
        raise HTTPException(500, "ASSEMBLYAI_API_KEY is not set. Locally: copy .env.example to .env and add your key. "
                                 "On Render: add it under the service's Environment tab, then redeploy.")
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


STREAM_ERROR = "\x00"  # sent after the text when the reply was cut off or errored mid-stream; the page shows it


async def _sse_text(resp: httpx.Response, client: httpx.AsyncClient):
    """Turn an OpenAI-compatible SSE stream into plain text deltas, then close upstream.

    A reply that ends without [DONE], or carries an in-band error, ends with STREAM_ERROR + reason,
    so the page can say it was cut off instead of presenting half a note as finished."""
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


def _no_model_access(resp: httpx.Response) -> bool:
    return resp.status_code in (400, 403) and "access" in resp.text.lower()


def _speed_rank(model_id: str) -> int:
    return next((n for n, hint in enumerate(FAST_HINTS) if hint in model_id.lower()), len(FAST_HINTS))


async def _candidate_models(client: httpx.AsyncClient, key: str, tried: set[str]) -> list[str]:
    """Models the gateway lists, fastest-sounding first, so an account without the default still gets polish."""
    resp = await client.get(LLM_MODELS_URL, headers={"authorization": key})
    if resp.status_code != 200:
        return []
    try:
        ids = [m["id"] for m in resp.json()["data"] if isinstance(m.get("id"), str)]
    except (KeyError, TypeError, ValueError):
        return []
    return sorted((i for i in ids if i not in tried), key=_speed_rank)[:MAX_MODEL_TRIES]


async def _open_completion(client: httpx.AsyncClient, key: str, model: str, text: str,
                           prompt: str = SYSTEM_PROMPT) -> httpx.Response:
    request = client.build_request("POST", LLM_GATEWAY_URL, headers={"authorization": key}, json={
        "model": model, "stream": True,
        "messages": [{"role": "system", "content": prompt}, {"role": "user", "content": text}]})
    resp = await client.send(request, stream=True)
    if not (resp.status_code == 200 and resp.headers.get("content-type", "").startswith("text/event-stream")):
        await resp.aread()  # an error, or a plain JSON completion because the gateway ignored "stream"
    return resp


@app.post("/api/polish")
async def polish(req: PolishRequest) -> StreamingResponse:
    global _working_model
    text = req.text.strip()
    if not text:
        raise HTTPException(400, "Nothing to polish: the transcript is empty.")
    key = _api_key()
    prompt = system_prompt(req.style)
    client = _client()
    try:
        model = _working_model or LLM_MODEL
        resp = await _open_completion(client, key, model, text, prompt)
        if _no_model_access(resp):
            tried = {model}
            for model in await _candidate_models(client, key, tried):
                tried.add(model)
                resp = await _open_completion(client, key, model, text, prompt)
                if not _no_model_access(resp):
                    break
            else:
                raise HTTPException(502, "Your AssemblyAI account can't use any LLM Gateway model we tried "
                                         f"({', '.join(sorted(tried))}). LLM Gateway may need billing enabled on your "
                                         "AssemblyAI account (assemblyai.com/dashboard), or set LLM_MODEL to a model "
                                         "your plan includes.")
        if resp.status_code == 200:
            _working_model = model
        if resp.status_code == 200 and resp.headers.get("content-type", "").startswith("text/event-stream"):
            return StreamingResponse(_sse_text(resp, client), media_type="text/plain; charset=utf-8")  # closes client
    except BaseException:
        await client.aclose()
        raise
    await client.aclose()
    if resp.status_code != 200:
        raise _upstream_error("LLM Gateway request", resp)
    try:
        markdown = resp.json()["choices"][0]["message"]["content"].strip()
    except (KeyError, IndexError, TypeError, ValueError, AttributeError):
        raise HTTPException(502, f"Unexpected LLM Gateway response: {resp.text[:300]}")
    return StreamingResponse(iter([markdown]), media_type="text/plain; charset=utf-8")
