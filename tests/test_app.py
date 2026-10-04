import json

import httpx
import pytest
from fastapi.testclient import TestClient

import app as flow


@pytest.fixture
def client(monkeypatch):
    monkeypatch.setenv("ASSEMBLYAI_API_KEY", "test-key")
    calls = []

    def handler(request: httpx.Request) -> httpx.Response:
        calls.append(request)
        return flow_responses[request.url.host](request)

    flow_responses = {}
    monkeypatch.setattr(flow, "HTTP_TRANSPORT", httpx.MockTransport(handler))
    c = TestClient(flow.app)
    c.calls, c.responses = calls, flow_responses
    return c


def test_index_serves_page(client):
    r = client.get("/")
    assert r.status_code == 200
    assert "Raw Speech" in r.text and "Polished AI Output" in r.text


def test_token_uses_server_side_key(client):
    client.responses["streaming.assemblyai.com"] = lambda req: httpx.Response(200, json={"token": "tmp-123"})
    r = client.get("/api/token")
    assert r.json() == {"token": "tmp-123", "speech_model": None}
    req = client.calls[0]
    assert req.url.path == "/v3/token"
    assert req.url.params["expires_in_seconds"] == "60"
    assert req.headers["authorization"] == "test-key"


def test_missing_key_is_a_clear_error(client, monkeypatch):
    monkeypatch.delenv("ASSEMBLYAI_API_KEY")
    r = client.get("/api/token")
    assert r.status_code == 500
    assert "ASSEMBLYAI_API_KEY" in r.json()["detail"]
    assert client.calls == []


def test_polish_sends_transcript_with_system_prompt(client):
    client.responses["llm-gateway.assemblyai.com"] = lambda req: httpx.Response(
        200, json={"choices": [{"message": {"content": "  **Update**\n\n- Ship Friday  "}}]}
    )
    r = client.post("/api/polish", json={"text": "um so like we ship uh friday"})
    assert r.status_code == 200
    assert r.text == "**Update**\n\n- Ship Friday"
    req = client.calls[0]
    assert req.url.path == "/v1/chat/completions"
    assert req.headers["authorization"] == "test-key"
    body = json.loads(req.content)
    assert body["messages"][0] == {"role": "system", "content": flow.SYSTEM_PROMPT}
    assert body["messages"][1] == {"role": "user", "content": "um so like we ship uh friday"}


@pytest.mark.parametrize("text", ["", "   "])
def test_polish_rejects_empty_transcript(client, text):
    r = client.post("/api/polish", json={"text": text})
    assert r.status_code == 400
    assert client.calls == []


def test_upstream_error_is_surfaced(client):
    client.responses["llm-gateway.assemblyai.com"] = lambda req: httpx.Response(401, json={"error": "Invalid API key"})
    r = client.post("/api/polish", json={"text": "hello"})
    assert r.status_code == 502
    assert "401" in r.json()["detail"] and "Invalid API key" in r.json()["detail"]


def test_malformed_llm_response_is_surfaced(client):
    client.responses["llm-gateway.assemblyai.com"] = lambda req: httpx.Response(200, json={"choices": []})
    r = client.post("/api/polish", json={"text": "hello"})
    assert r.status_code == 502
    assert "Unexpected" in r.json()["detail"]



DENIED = {"metadata": {"errors": ["Your account does not have access to this LLM Gateway model"]}, "code": 400}


def _gateway(allowed, listed):
    def reply(req):
        if req.url.path == "/v1/models":
            return httpx.Response(200, json={"data": [{"id": i} for i in listed]})
        model = json.loads(req.content)["model"]
        if model in allowed:
            return httpx.Response(200, json={"choices": [{"message": {"content": f"ok from {model}"}}]})
        return httpx.Response(400, json=DENIED)
    return reply


def test_falls_back_to_a_model_the_account_can_use(client, monkeypatch):
    monkeypatch.setattr(flow, "_working_model", None)
    client.responses["llm-gateway.assemblyai.com"] = _gateway(
        allowed={"gemini-2.5-flash", "big-model"}, listed=[flow.LLM_MODEL, "big-model", "gemini-2.5-flash"])
    r = client.post("/api/polish", json={"text": "hello"})
    assert r.status_code == 200 and r.text == "ok from gemini-2.5-flash"  # fast-sounding model tried first
    client.calls.clear()
    assert client.post("/api/polish", json={"text": "again"}).text == "ok from gemini-2.5-flash"
    assert len(client.calls) == 1  # remembered: no second denied attempt, no model listing


def test_clear_error_when_no_model_is_usable(client, monkeypatch):
    monkeypatch.setattr(flow, "_working_model", None)
    listed = [f"model-{n}" for n in range(20)]
    client.responses["llm-gateway.assemblyai.com"] = _gateway(allowed=set(), listed=listed)
    r = client.post("/api/polish", json={"text": "hello"})
    assert r.status_code == 502 and "can't use any LLM Gateway model" in r.json()["detail"]
    chat_calls = [c for c in client.calls if c.url.path.endswith("/chat/completions")]
    assert len(chat_calls) == 1 + flow.MAX_MODEL_TRIES  # bounded, not all 20


def _sse_response(body):
    return lambda req: httpx.Response(200, headers={"content-type": "text/event-stream"}, text=body)


def test_stream_cut_off_is_flagged(client):
    chunk = 'data: {"choices":[{"delta":{"content":"**Half a"}}]}\n\n'
    client.responses["llm-gateway.assemblyai.com"] = _sse_response(chunk)  # no [DONE]
    text = client.post("/api/polish", json={"text": "hello"}).text
    assert text.startswith("**Half a") and flow.STREAM_ERROR in text and "cut off" in text


def test_in_band_stream_error_is_flagged(client):
    body = ('data: {"choices":[{"delta":{"content":"Hi"}}]}\n\n'
            'data: {"error":{"message":"overloaded"}}\n\ndata: [DONE]\n\n')
    client.responses["llm-gateway.assemblyai.com"] = _sse_response(body)
    text = client.post("/api/polish", json={"text": "hello"}).text
    assert text.startswith("Hi" + flow.STREAM_ERROR) and "overloaded" in text


def test_data_lines_without_space_are_parsed(client):
    body = 'data:{"choices":[{"delta":{"content":"tight"}}]}\n\ndata:[DONE]\n\n'
    client.responses["llm-gateway.assemblyai.com"] = _sse_response(body)
    assert client.post("/api/polish", json={"text": "hello"}).text == "tight"


@pytest.mark.parametrize("style,marker", [("message", "chat message"), ("email", "email body"), ("notes", "- [ ]")])
def test_style_changes_the_prompt(client, style, marker):
    client.responses["llm-gateway.assemblyai.com"] = lambda req: httpx.Response(
        200, json={"choices": [{"message": {"content": "ok"}}]})
    assert client.post("/api/polish", json={"text": "hello", "style": style}).status_code == 200
    prompt = json.loads(client.calls[-1].content)["messages"][0]["content"]
    assert marker in prompt and prompt.startswith(flow.CLEANUP_RULES) and prompt != flow.SYSTEM_PROMPT


def test_unknown_style_is_rejected_without_upstream_call(client):
    assert client.post("/api/polish", json={"text": "hello", "style": "poem"}).status_code == 422
    assert client.calls == []


def test_home_screen_manifest_and_icons_are_served(client):
    page = client.get("/").text
    assert 'rel="manifest"' in page and 'rel="apple-touch-icon"' in page
    manifest = client.get("/static/manifest.webmanifest").json()
    assert manifest["display"] == "standalone" and manifest["start_url"] == "/"
    for icon in manifest["icons"]:
        r = client.get(icon["src"])
        assert r.status_code == 200 and len(r.content) > 100
    assert client.get("/static/apple-touch-icon.png").headers["content-type"] == "image/png"


def test_speech_model_is_passed_to_the_page_when_configured(client, monkeypatch):
    monkeypatch.setenv("FLOW_SPEECH_MODEL", "universal-3-6-pro")
    client.responses["streaming.assemblyai.com"] = lambda req: httpx.Response(200, json={"token": "t"})
    assert client.get("/api/token").json() == {"token": "t", "speech_model": "universal-3-6-pro"}


def test_prompt_keeps_the_speakers_language():
    assert "same language the speaker used" in flow.SYSTEM_PROMPT


def test_health_reports_everything_ok(client, monkeypatch):
    monkeypatch.setattr(flow, "_working_model", None)
    client.responses["streaming.assemblyai.com"] = lambda req: httpx.Response(200, json={"token": "t"})
    client.responses["llm-gateway.assemblyai.com"] = _gateway(allowed={"gemini-2.5-flash"}, listed=["gemini-2.5-flash"])
    out = client.get("/api/health").json()
    assert out["key"]["ok"] and out["streaming"]["ok"]
    assert out["polish"] == {"ok": True, "model": "gemini-2.5-flash", "detail": "AI polish works with gemini-2.5-flash."}


def test_health_explains_missing_key_without_calling_out(client, monkeypatch):
    monkeypatch.delenv("ASSEMBLYAI_API_KEY")
    out = client.get("/api/health").json()
    assert out == {"key": {"ok": False, "detail": "ASSEMBLYAI_API_KEY is not set on the server."}}
    assert client.calls == []


def test_health_explains_no_model_access_and_bad_key(client, monkeypatch):
    monkeypatch.setattr(flow, "_working_model", None)
    client.responses["streaming.assemblyai.com"] = lambda req: httpx.Response(401, json={"error": "Invalid API key"})
    client.responses["llm-gateway.assemblyai.com"] = _gateway(allowed=set(), listed=["a", "b"])
    out = client.get("/api/health").json()
    assert not out["streaming"]["ok"] and "401" in out["streaming"]["detail"]
    assert not out["polish"]["ok"] and "billing" in out["polish"]["detail"]


@pytest.mark.parametrize("action,marker", [("shorter", "half as long"), ("formal", "more formal"),
                                           ("friendly", "warmer"), ("grammar", "Change nothing else")])
def test_rewrite_uses_the_rewrite_prompt(client, action, marker):
    client.responses["llm-gateway.assemblyai.com"] = lambda req: httpx.Response(
        200, json={"choices": [{"message": {"content": "ok"}}]})
    r = client.post("/api/polish", json={"text": "**Note**\n\n- a", "rewrite": action, "style": "notes"})
    assert r.status_code == 200
    prompt = json.loads(client.calls[-1].content)["messages"][0]["content"]
    assert marker in prompt and "Do not add new ideas" in prompt and prompt != flow.system_prompt("notes")


def test_unknown_rewrite_and_huge_text_are_rejected_without_upstream_call(client):
    assert client.post("/api/polish", json={"text": "hi", "rewrite": "pirate"}).status_code == 422
    assert client.post("/api/polish", json={"text": "x" * 20001}).status_code == 422
    assert client.calls == []


def test_prompt_follows_spoken_structure_and_number_style():
    for style in ("auto", "message", "email", "notes"):
        prompt = flow.system_prompt(style)
        assert "Follow spoken structure" in prompt and "new paragraph" in prompt and "3pm" in prompt
