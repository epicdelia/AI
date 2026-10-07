"""End-to-end check of the Chrome extension: real Chromium with the unpacked extension loaded,
real Flow server, and a local fake AssemblyAI (token, live WebSocket, LLM Gateway SSE).

Holds Alt+Space in a textarea, an <input> and a contenteditable on a normal web page and checks the
polished text is typed in place as plain text; also checks the copy fallback when no field is focused
and the raw-words fallback when AI polish fails.

Run:  pip install websockets playwright && python scripts/extension_check.py   (CHROME_PATH optional)
Needs a Chromium that supports extensions in headless mode (Playwright's bundled Chromium does).
"""
import importlib
import json
import os
import sys
import tempfile
import threading
import time
from pathlib import Path

import uvicorn
from fastapi import FastAPI, WebSocket
from fastapi.responses import HTMLResponse, JSONResponse, StreamingResponse
from playwright.sync_api import sync_playwright

ROOT = Path(__file__).resolve().parents[1]
sys.path.insert(0, str(ROOT))
os.environ["ASSEMBLYAI_API_KEY"] = "fake-key"
FAKE_PORT, FLOW_PORT = 8791, 8792
os.environ["FLOW_STREAM_URL"] = f"ws://127.0.0.1:{FAKE_PORT}/v3/ws"
flow = importlib.import_module("app")  # after the env vars are set

SAID = "um so the launch is uh moving to friday and qa signs off thursday"
seen = {"polish_styles": [], "ws_urls": [], "fail_polish": False}
fake = FastAPI()


@fake.get("/v3/token")
def token():
    return {"token": "tmp-ext"}


@fake.get("/v1/models")
def models():
    return {"data": [{"id": flow.LLM_MODEL}]}


@fake.post("/v1/chat/completions")
async def chat(body: dict):
    seen["polish_styles"].append(body["messages"][0]["content"])
    if seen["fail_polish"]:
        return JSONResponse({"error": "overloaded"}, status_code=500)
    chunks = ["**Launch update**", "\n\n- Launch moves to **Friday**", "\n- QA signs off Thursday"]

    def sse():
        for c in chunks:
            yield f"data: {json.dumps({'choices': [{'delta': {'content': c}}]})}\n\n"
            time.sleep(0.05)
        yield "data: [DONE]\n\n"
    return StreamingResponse(sse(), media_type="text/event-stream")


@fake.websocket("/v3/ws")
async def stream(ws: WebSocket):
    seen["ws_urls"].append(str(ws.url))
    await ws.accept()
    await ws.send_json({"type": "Begin", "id": "s", "expires_at": 0})
    frames = 0
    while True:
        msg = await ws.receive()
        if msg.get("bytes") is not None:
            frames += 1
            if frames == 5:
                await ws.send_json({"type": "Turn", "turn_order": 0, "end_of_turn": False, "transcript": "um so the launch"})
        elif msg.get("text") is not None:
            m = json.loads(msg["text"])
            if m["type"] == "ForceEndpoint":
                await ws.send_json({"type": "Turn", "turn_order": 0, "end_of_turn": True, "transcript": SAID})
            elif m["type"] == "Terminate":
                await ws.send_json({"type": "Termination"})
                await ws.close()
                return
        else:
            return


@fake.get("/page", response_class=HTMLResponse)
def page():
    return """<!doctype html><title>Some site</title><body style="font:16px sans-serif">
      <textarea id="ta" rows="6" cols="60">Hi team, </textarea>
      <input id="inp" type="text" value="">
      <div id="ce" contenteditable="true" style="border:1px solid #999;min-height:60px">Note: </div>
      <button id="btn">not a field</button></body>"""


flow.STREAMING_TOKEN_URL = f"http://127.0.0.1:{FAKE_PORT}/v3/token"
flow.LLM_GATEWAY_URL = f"http://127.0.0.1:{FAKE_PORT}/v1/chat/completions"
flow.LLM_MODELS_URL = f"http://127.0.0.1:{FAKE_PORT}/v1/models"
servers = [uvicorn.Server(uvicorn.Config(a, host="127.0.0.1", port=p, log_level="warning"))
           for a, p in [(fake, FAKE_PORT), (flow.app, FLOW_PORT)]]
for srv in servers:
    threading.Thread(target=srv.run, daemon=True).start()
while not all(srv.started for srv in servers):
    time.sleep(0.05)


def dictate(page, selector, hold_ms=1200):
    if selector:
        page.click(selector)
        page.keyboard.press("End")
    page.keyboard.down("Alt")
    page.keyboard.down(" ")
    page.wait_for_timeout(hold_ms)
    page.keyboard.up(" ")
    page.keyboard.up("Alt")


def pill(page):
    return page.evaluate("""() => { for (const d of document.querySelectorAll('body > div')) {
        const p = d.shadowRoot && d.shadowRoot.querySelector('.pill'); if (p) return p.textContent; } return null; }""")


results, errors = {}, []
ext = str(ROOT / "extension")
try:
    with sync_playwright() as p, tempfile.TemporaryDirectory() as profile:
        ctx = p.chromium.launch_persistent_context(
            profile, headless=True, executable_path=os.getenv("CHROME_PATH") or None,
            args=[f"--disable-extensions-except={ext}", f"--load-extension={ext}",
                  "--use-fake-device-for-media-stream", "--use-fake-ui-for-media-stream"],
            permissions=["clipboard-read", "clipboard-write"])
        sw = ctx.service_workers[0] if ctx.service_workers else ctx.wait_for_event("serviceworker", timeout=10000)
        ext_id = sw.url.split("/")[2]

        opts = ctx.new_page()
        opts.goto(f"chrome-extension://{ext_id}/options.html")
        opts.fill("#serverUrl", f"http://127.0.0.1:{FLOW_PORT}")
        opts.select_option("#style", "message")
        opts.fill("#dict", "QA\nAssemblyAI")
        opts.click("#save")
        opts.click("#mic")
        opts.wait_for_selector("#status :text('Microphone allowed')", timeout=5000)  # extension pages forbid eval
        opts.click("#check")
        opts.wait_for_selector("#status li >> nth=2", timeout=10000)
        setup_rows = opts.locator("#status li").all_inner_texts()

        page = ctx.new_page()
        page.on("pageerror", lambda e: errors.append(str(e)))
        page.goto(f"http://127.0.0.1:{FAKE_PORT}/page")
        page.wait_for_timeout(500)

        page.click("#ta")
        page.keyboard.press("End")
        page.keyboard.down("Alt")
        page.keyboard.down(" ")
        page.wait_for_timeout(900)
        live_pill = pill(page)  # read while still holding
        page.keyboard.up(" ")
        page.keyboard.up("Alt")
        page.wait_for_function("document.getElementById('ta').value.includes('Thursday')", timeout=10000)
        ta = page.input_value("#ta")

        dictate(page, "#inp")
        page.wait_for_function("document.getElementById('inp').value.includes('Thursday')", timeout=10000)
        inp = page.input_value("#inp")

        dictate(page, "#ce")
        page.wait_for_function("document.getElementById('ce').innerText.includes('Thursday')", timeout=10000)
        ce = page.inner_text("#ce")

        page.click("#btn")  # focus something that isn't a text field: result should be copied instead
        dictate(page, None)
        page.wait_for_function("""() => { for (const d of document.querySelectorAll('body > div')) {
            const p = d.shadowRoot && d.shadowRoot.querySelector('.pill'); if (p && p.textContent.includes('Copied')) return true; }
            return false; }""", timeout=10000)
        copied = page.evaluate("navigator.clipboard.readText()")

        seen["fail_polish"] = True
        dictate(page, "#ta")
        page.wait_for_function("document.getElementById('ta').value.includes('qa signs off thursday')", timeout=10000)
        raw_fallback = page.input_value("#ta")
        fallback_pill = pill(page)
        ctx.close()
finally:
    for srv in servers:
        srv.should_exit = True

expected = "Launch update\n\n- Launch moves to Friday\n- QA signs off Thursday"
url = seen["ws_urls"][0] if seen["ws_urls"] else ""
checks = {
    "options: check setup reaches the Flow server": len(setup_rows) == 3 and all(r.startswith("✓") for r in setup_rows),
    "pill shows live words while holding": bool(live_pill) and ("launch" in live_pill or "Listening" in live_pill),
    "textarea: polished text typed at the cursor, plain text": ta == "Hi team, " + expected,
    "input: one tidy line": inp == "Launch update. Launch moves to Friday. QA signs off Thursday.",
    "contenteditable: polished text typed after existing text": ce.startswith("Note: ") and "QA signs off Thursday" in ce
    and "**" not in ce,
    "no text field focused: result copied to clipboard": copied == expected,
    "polish failure: raw words inserted, user told why": SAID in raw_fallback and "AI polish failed" in (fallback_pill or ""),
    "message style sent to the server": any("chat message" in p for p in seen["polish_styles"]),
    "dictionary + stream URL from server used": "keyterms_prompt=" in url and "tmp-ext" in url,
    "no page errors": not errors,
}
for k, v in checks.items():
    print(("PASS " if v else "FAIL ") + k)
print("textarea:", repr(ta))
print("input:", repr(inp))
print("contenteditable:", repr(ce))
print("live pill:", repr(live_pill), "| fallback pill:", repr(fallback_pill), "| errors:", errors)
sys.exit(0 if all(checks.values()) else 1)
