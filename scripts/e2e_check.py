"""Browser end-to-end check: real page in headless Chromium, fake mic, fake AssemblyAI socket.

Optional (needs `pip install playwright` and a Chromium). Run: python scripts/e2e_check.py
Set CHROME_PATH to use a specific Chromium binary.
"""
import json
import os
import subprocess
import sys
import time
from pathlib import Path

from playwright.sync_api import sync_playwright

ROOT = Path(__file__).resolve().parent.parent
server = subprocess.Popen(
    [sys.executable, "-m", "uvicorn", "app:app", "--port", "8765"],
    cwd=str(ROOT), env={**os.environ, "ASSEMBLYAI_API_KEY": "unused"},
    stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL,
)
time.sleep(2)
stats = {"url": None, "frames": 0, "sizes": set(), "control": []}
errors = []


def on_ws(ws):
    stats["url"] = ws.url

    def on_message(msg):
        if isinstance(msg, (bytes, bytearray)):
            stats["frames"] += 1
            stats["sizes"].add(len(msg))
            if stats["frames"] == 5:
                ws.send(json.dumps({"type": "Turn", "turn_order": 0, "end_of_turn": False, "turn_is_formatted": False,
                                    "transcript": "um so so like the launch", "end_of_turn_confidence": 0.1, "words": []}))
            return
        m = json.loads(msg)
        stats["control"].append(m["type"])
        if m["type"] == "ForceEndpoint":
            ws.send(json.dumps({"type": "Turn", "turn_order": 0, "end_of_turn": True, "turn_is_formatted": False,
                                "transcript": "um so so like the launch is uh moving to friday <b>x</b>",
                                "end_of_turn_confidence": 0.9, "words": []}))
        elif m["type"] == "Terminate":
            ws.send(json.dumps({"type": "Termination", "audio_duration_seconds": 1}))
            ws.close()

    ws.on_message(on_message)
    ws.send(json.dumps({"type": "Begin", "id": "s1", "expires_at": 0}))


polish_bodies = []


def polish(route):
    polish_bodies.append(json.loads(route.request.post_data))
    route.fulfill(json={"markdown": "**Launch update**\n\n- Launch moves to **Friday**\n- <script>alert(1)</script>",
                        "model": "gpt-5-mini", "llm_ms": 412})


try:
    with sync_playwright() as p:
        browser = p.chromium.launch(
            executable_path=os.getenv("CHROME_PATH") or None,
            args=["--use-fake-device-for-media-stream", "--use-fake-ui-for-media-stream"],
        )
        ctx = browser.new_context(permissions=["microphone", "clipboard-read", "clipboard-write"])
        page = ctx.new_page()
        page.on("pageerror", lambda e: errors.append(str(e)))
        page.on("console", lambda m: m.type == "error" and errors.append(m.text))
        page.route("**/api/token", lambda r: r.fulfill(json={"token": "tmp-abc"}))
        page.route("**/api/polish", polish)
        page.route_web_socket("wss://streaming.assemblyai.com/**", on_ws)
        page.goto("http://127.0.0.1:8765/")

        # 1. Hold Space ~1.5 s, then release.
        page.keyboard.down("Space")
        page.wait_for_function("document.getElementById('raw').innerText.includes('launch')", timeout=5000)
        partial_class = page.eval_on_selector("#raw span", "e => e.className")
        page.wait_for_timeout(600)
        page.keyboard.up("Space")
        page.wait_for_function("document.getElementById('polished').innerText.includes('Launch moves')", timeout=5000)
        page.wait_for_timeout(300)

        raw = page.inner_text("#raw")
        polished_html = page.inner_html("#polished")
        badges = {b: page.inner_text(f"#{b} b") for b in ["bStatus", "bFinal", "bLlm", "bTotal"]}
        clip = page.evaluate("navigator.clipboard.readText()")
        mic_live = page.evaluate("document.getElementById('talk').classList.contains('rec')")
        page.screenshot(path=str(ROOT / "e2e-screenshot.png"), full_page=True)

        # 2. Quick tap (release before connect) must not wedge the app.
        page.keyboard.down("Space"); page.keyboard.up("Space")
        page.wait_for_timeout(1500)
        status_after_tap = page.inner_text("#bStatus b")
        # 3. Button toggle works after the tap.
        page.click("#talk"); page.wait_for_timeout(800); page.click("#talk")
        page.wait_for_function("document.getElementById('bStatus').innerText.includes('idle')", timeout=6000)
        browser.close()
finally:
    server.terminate()

checks = {
    "ws url has 16k pcm + token": all(s in (stats["url"] or "") for s in ["sample_rate=16000", "encoding=pcm_s16le", "token=tmp-abc"]),
    "audio frames streamed": stats["frames"] >= 10,
    "frames are 50ms of 16-bit 16kHz (1600 bytes)": stats["sizes"] == {1600},
    "ForceEndpoint then Terminate sent": stats["control"][:2] == ["ForceEndpoint", "Terminate"],
    "partial styled as partial": partial_class == "partial",
    "raw keeps fillers": "um so so like" in raw,
    "raw escapes html": "<b>x</b>" in raw,
    "polish got final transcript": polish_bodies and "moving to friday" in polish_bodies[0]["text"],
    "markdown rendered": "<strong>Launch update</strong>" in polished_html and "<li>" in polished_html,
    "llm output html escaped": "<script>" not in polished_html,
    "auto-copied markdown": clip.startswith("**Launch update**"),
    "badges filled": badges["bLlm"] == "412 ms" and badges["bFinal"].endswith("ms") and badges["bTotal"].endswith("ms"),
    "mic off after stop": not mic_live,
    "quick tap recovers to idle": status_after_tap == "idle",
    "no page errors": not errors,
}
for k, v in checks.items():
    print(("PASS " if v else "FAIL ") + k)
print("frames:", stats["frames"], "sizes:", stats["sizes"], "control:", stats["control"])
print("badges:", badges, "errors:", errors)
sys.exit(0 if all(checks.values()) else 1)
