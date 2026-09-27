# Flow: speak messy, get polished

Push-to-talk dictation: your mic streams to AssemblyAI Universal-Streaming (live
raw transcript on the left). When you stop, the transcript goes through
AssemblyAI's LLM Gateway, and clean Markdown appears on the right and is copied
to your clipboard.

## Run it

```bash
python3 -m venv .venv && source .venv/bin/activate
pip install -r requirements.txt
cp .env.example .env        # then paste your key into .env
uvicorn app:app --port 8000
```

Open http://localhost:8000 in Chrome, allow the mic, then **hold Space** (or
click **Start Speaking**), talk, and let go.

## Choosing the LLM

`LLM_MODEL` in `.env` picks the model (default `gpt-5-mini`). To see the models
your account can use (for example, whether a Qwen model is offered):

```bash
curl -s https://llm-gateway.assemblyai.com/v1/models -H "authorization: $ASSEMBLYAI_API_KEY"
```

For the demo, pick the fastest model on that list. The **AI polish** badge shows
the real round-trip time, so you can compare models live.

## How it works

| Piece | Where |
|---|---|
| Mic → 16 kHz 16-bit PCM, 50 ms chunks | AudioWorklet in `static/index.html` |
| Short-lived streaming token (API key stays on the server) | `GET /api/token` in `app.py` |
| Live partial/final transcript | Browser ↔ `wss://streaming.assemblyai.com/v3/ws` |
| Close the turn the moment you release Space | `ForceEndpoint` message, then `Terminate` |
| Cleanup + Markdown formatting | `POST /api/polish` → LLM Gateway `/v1/chat/completions` |

The badges show measured times, not targets: **Final transcript** (release →
final text), **AI polish** (LLM round trip) and **Stop → polished** (the whole wait).

## Tests

```bash
pip install pytest ruff && ./scripts/check.sh          # backend, mocked HTTP
pip install playwright && python scripts/e2e_check.py  # real browser, fake mic + fake AssemblyAI socket
```
