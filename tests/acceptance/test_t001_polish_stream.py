import json

import httpx
import pytest
from fastapi.testclient import TestClient

import app as flow


def sse(*deltas):  # role-only chunk and keep-alive comment first, as OpenAI-compatible streams send them
    data = "".join(f"data: {json.dumps({'choices': [{'delta': {'content': d}}]})}\n\n" for d in deltas)
    text = 'data: {"choices":[{"delta":{"role":"assistant"}}]}\n\n: ping\n\n' + data + "data: [DONE]\n\n"
    return httpx.Response(200, headers={"content-type": "text/event-stream"}, text=text)


@pytest.fixture
def gw(monkeypatch):
    monkeypatch.setenv("ASSEMBLYAI_API_KEY", "test-key")
    gw = {"calls": [], "post": lambda text: TestClient(flow.app).post("/api/polish", json={"text": text})}
    monkeypatch.setattr(flow, "HTTP_TRANSPORT", httpx.MockTransport(lambda req: gw["calls"].append(req) or gw["reply"]))
    return gw


@pytest.mark.parametrize("reply,expected", [
    (sse("**Launch", " update**", "\n\n- Friday"), "**Launch update**\n\n- Friday"),
    (sse("Caf\u00e9", "", " \u2713 <b>"), "Caf\u00e9 \u2713 <b>"),  # unicode, empty delta, HTML passed through as text
    (httpx.Response(200, json={"choices": [{"message": {"content": "  **Hi**\n- a\n "}}]}), "**Hi**\n- a"),
], ids=["b2-sse", "b2-sse-edge", "b3-json-fallback"])
def test_b1_b2_b3_requests_stream_and_returns_plain_text(gw, reply, expected):
    gw["reply"] = reply
    r = gw["post"]("  um so launch uh friday ")
    assert (r.status_code, r.text) == (200, expected)
    body, auth = json.loads(gw["calls"][0].content), gw["calls"][0].headers["authorization"]
    assert (body["stream"], body["model"], auth) == (True, flow.LLM_MODEL, "test-key")
    assert body["messages"] == [{"role": "system", "content": flow.SYSTEM_PROMPT},
                                {"role": "user", "content": "um so launch uh friday"}]


@pytest.mark.parametrize("status,body", [(401, '{"error":"Invalid API key"}'), (503, "upstream down")])
def test_b4_upstream_error_before_text_is_502(gw, status, body):
    gw["reply"] = httpx.Response(status, text=body)
    r = gw["post"]("hello")
    assert r.status_code == 502 and json.loads(gw["calls"][0].content)["stream"] is True
    assert str(status) in r.json()["detail"] and body in r.json()["detail"]


@pytest.mark.parametrize("text,key,status", [("", "k", 400), (" \n\t ", "k", 400), ("hi", "", 500), ("hi", "  ", 500)])
def test_b5_b6_rejected_without_upstream_call(gw, monkeypatch, text, key, status):
    monkeypatch.setenv("ASSEMBLYAI_API_KEY", key)
    r = gw["post"](text)
    assert (r.status_code, gw["calls"]) == (status, [])
    assert status == 400 or "ASSEMBLYAI_API_KEY" in r.json()["detail"]
