"""Full-path check: real browser -> real Flow server -> fake AssemblyAI that behaves like the live account.

Unlike e2e_check.py (which fakes Flow's own /api/* routes), this runs the real FastAPI app and
points it at a local fake AssemblyAI that:
  - refuses the default model with the same 400 the live account returned,
  - lists other models at /v1/models and accepts a "flash" one,
  - streams the polished reply slowly (SSE, one chunk every 120 ms).
So it exercises the model fallback, word-by-word rendering through the server, the remembered
model on the next take, styles, the dictionary, history, and an iPhone-sized run. It also simulates a
sleeping free-tier server (token takes 3 s) where the user lets go after 1 s: the take must not be lost.

Run:  python scripts/live_path_check.py   (needs playwright; CHROME_PATH optional)
"""
import importlib
import json
import os
import sys
import threading
import time
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
from pathlib import Path
from urllib.parse import parse_qs, urlparse

import uvicorn
from playwright.sync_api import sync_playwright

ROOT = Path(__file__).resolve().parents[1]
sys.path.insert(0, str(ROOT))
os.environ["ASSEMBLYAI_API_KEY"] = "fake-key"
flow = importlib.import_module("app")  # after the env var and sys.path are set

DENIED = {"metadata": {"errors": ["Your account does not have access to this LLM Gateway model"]},
          "message": "invalid request body", "code": 400}
CHUNKS = ["**Launch", " update**", "\n\n- Launch", " moves", " to", " **Friday**", "\n- QA", " signs", " off", " Thursday"]
seen = {"models": [], "prompts": [], "auth": set(), "token_delay": 0}


class FakeAssemblyAI(BaseHTTPRequestHandler):
    def log_message(self, *args):
        pass

    def _json(self, code, body):
        data = json.dumps(body).encode()
        self.send_response(code)
        self.send_header("content-type", "application/json")
        self.send_header("content-length", str(len(data)))
        self.end_headers()
        self.wfile.write(data)

    def do_GET(self):
        seen["auth"].add(self.headers.get("authorization"))
        if self.path.startswith("/v3/token"):
            time.sleep(seen["token_delay"])  # a sleeping Render server takes 30-60 s to answer the first request
            return self._json(200, {"token": "tmp-live"})
        if self.path == "/v1/models":
            return self._json(200, {"data": [{"id": i} for i in ["big-slow-model", "gemini-2.5-flash", "claude-x"]]})
        self._json(404, {})

    def do_POST(self):
        seen["auth"].add(self.headers.get("authorization"))
        body = json.loads(self.rfile.read(int(self.headers["content-length"])))
        seen["models"].append(body["model"])
        seen["prompts"].append(body["messages"][0]["content"])
        if body["model"] != "gemini-2.5-flash":
            return self._json(400, DENIED)
        self.send_response(200)
        self.send_header("content-type", "text/event-stream")
        self.end_headers()
        for chunk in CHUNKS:
            self.wfile.write(f"data: {json.dumps({'choices': [{'delta': {'content': chunk}}]})}\n\n".encode())
            self.wfile.flush()
            time.sleep(0.12)
        self.wfile.write(b"data: [DONE]\n\n")
        self.wfile.flush()


fake = ThreadingHTTPServer(("127.0.0.1", 0), FakeAssemblyAI)
threading.Thread(target=fake.serve_forever, daemon=True).start()
base = f"http://127.0.0.1:{fake.server_address[1]}"
flow.STREAMING_TOKEN_URL = f"{base}/v3/token"
flow.LLM_GATEWAY_URL = f"{base}/v1/chat/completions"
flow.LLM_MODELS_URL = f"{base}/v1/models"

server = uvicorn.Server(uvicorn.Config(flow.app, host="127.0.0.1", port=8790, log_level="warning"))
threading.Thread(target=server.run, daemon=True).start()
while not server.started:
    time.sleep(0.05)

ws_urls = []


def fake_stream(ws):
    ws_urls.append(ws.url)
    state = {"frames": 0}

    def on_message(msg):
        if isinstance(msg, (bytes, bytearray)):
            state["frames"] += 1
            if state["frames"] == 5:
                ws.send(json.dumps({"type": "Turn", "turn_order": 0, "end_of_turn": False,
                                    "transcript": "um so the launch", "words": []}))
            return
        m = json.loads(msg)
        if m["type"] == "ForceEndpoint":
            ws.send(json.dumps({"type": "Turn", "turn_order": 0, "end_of_turn": True,
                                "transcript": "um so the launch is uh moving to friday and qa signs off thursday",
                                "words": []}))
        elif m["type"] == "Terminate":
            ws.send(json.dumps({"type": "Termination"}))
            ws.close()

    ws.on_message(on_message)
    ws.send(json.dumps({"type": "Begin", "id": "s", "expires_at": 0}))


def take(page, style):
    """One dictation via the button; returns snapshots of the polished pane while it streams."""
    page.click(f'.styles button[data-style="{style}"]')
    page.click("#talk", timeout=5000)  # fails if anything covers the button (e.g. a layout wider than the screen)
    page.wait_for_function("document.getElementById('raw').innerText.includes('launch')", timeout=8000)
    page.wait_for_timeout(500)
    page.click("#talk", timeout=5000)
    snapshots = []
    deadline = time.time() + 10
    while time.time() < deadline:
        snapshots.append(page.inner_text("#polished"))
        if page.inner_text("#bStatus b") == "idle" and "Thursday" in snapshots[-1]:
            break
        page.wait_for_timeout(40)
    return snapshots


def cold_take(page, label, hold_ms=1000):
    """Server asleep: the token takes 3 s, and the user lets go after hold_ms of speaking."""
    seen["token_delay"] = 3
    page.reload()
    page.click(".styles button[data-style=\"notes\"]")
    if label == "desktop":
        page.click("h1")
        page.keyboard.down("Space")
        page.wait_for_timeout(hold_ms)
        page.keyboard.up("Space")
    else:
        page.click("#talk", timeout=5000)
        page.wait_for_timeout(hold_ms)
        page.click("#talk", timeout=5000)
    seen["token_delay"] = 0
    page.wait_for_function("document.getElementById('polished').innerText.includes('Thursday')"
                           " || document.getElementById('error').innerText.trim()", timeout=20000)
    return page.inner_text("#polished"), page.inner_text("#error").strip()


results = {}
errors = []
try:
    with sync_playwright() as p:
        browser = p.chromium.launch(executable_path=os.getenv("CHROME_PATH") or None,
                                    args=["--use-fake-device-for-media-stream", "--use-fake-ui-for-media-stream"])
        for label, ctx_args in [("desktop", {}), ("iphone", p.devices["iPhone 13"])]:
            ctx = browser.new_context(**ctx_args, permissions=["microphone", "clipboard-read", "clipboard-write"])
            page = ctx.new_page()
            page.on("pageerror", lambda e: errors.append(str(e)))
            page.route_web_socket("wss://streaming.assemblyai.com/**", fake_stream)
            page.goto("http://127.0.0.1:8790/")
            page.click("details.dict summary")
            page.fill("#dict", "QA\nFriday")
            page.click("h1")
            seen["models"].clear()
            first = take(page, "notes")
            models_first_take = list(seen["models"])
            seen["models"].clear()
            second = take(page, "email")
            models_second_take = list(seen["models"])
            final = page.inner_text("#polished")
            second_take_prompt = seen["prompts"][-1]
            flow._working_model = None  # make the setup check rediscover the model from scratch
            health = page.request.get("http://127.0.0.1:8790/api/health").json()
            partial_views = {s for s in first if s.strip() and "Thursday" not in s and "Polishing" not in s}
            badges = {b: page.inner_text(f"#{b} b") for b in ["bFinal", "bFirst", "bLlm", "bTotal"]}
            ms = {k: int(v.split()[0]) for k, v in badges.items() if v.endswith("ms")}
            results[label] = {
                "fallback: default refused, then flash model used": models_first_take[0] == flow.LLM_MODEL
                and models_first_take[-1] == "gemini-2.5-flash",
                "second take goes straight to the remembered model": models_second_take == ["gemini-2.5-flash"],
                "text rendered progressively (>=3 partial views)": len(partial_views) >= 3,
                "final note complete": "Launch update" in final and "Thursday" in final,
                "first words arrive before polish finishes": ms.get("bFirst", 1e9) < ms.get("bTotal", 0),
                "email style prompt sent on 2nd take": "email body" in second_take_prompt,
                "dictionary in stream URL": json.loads(parse_qs(urlparse(ws_urls[-1]).query)["keyterms_prompt"][0])
                == ["QA", "Friday"],
                "history has both notes": page.locator("#historyList li").count() == 2,
                "page never wider than the screen": page.evaluate(
                    "document.documentElement.scrollWidth <= innerWidth && innerWidth === screen.width || innerWidth > 800"),
                "no error banner": not page.inner_text("#error").strip(),
                "setup check finds the working model": health.get("polish") == {
                    "ok": True, "model": "gemini-2.5-flash", "detail": "AI polish works with gemini-2.5-flash."}
                and health["streaming"]["ok"] and health["key"]["ok"],
            }
            cold_note, cold_error = cold_take(page, label)
            results[label]["server asleep, released after 1 s: take still polished"] = "Thursday" in cold_note
            results[label]["server asleep: no error banner"] = cold_error == ""
            long_note, long_error = cold_take(page, label, hold_ms=2200)  # >1.5 s queued: sent in a burst, then Terminate
            results[label]["server asleep, 2 s of queued speech: take still polished"] = "Thursday" in long_note and not long_error
            print(label, "badges:", badges, "partial views:", len(partial_views), "| cold-start error:", repr(cold_error))
            # A fresh page for the next context; the remembered model is server-side, so reset it.
            flow._working_model = None
            ctx.close()
        browser.close()
finally:
    server.should_exit = True
    fake.shutdown()

ok = True
for label, checks in results.items():
    for name, passed in checks.items():
        ok &= bool(passed)
        print(("PASS " if passed else "FAIL ") + f"[{label}] {name}")
print("key only sent server-side:", seen["auth"] == {"fake-key"}, "| page errors:", errors)
ok &= seen["auth"] == {"fake-key"} and not errors
sys.exit(0 if ok else 1)
