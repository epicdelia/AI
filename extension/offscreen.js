// Offscreen document: the only place the extension touches the microphone and AssemblyAI's live socket.
let s = null; // current session

const send = (msg) => chrome.runtime.sendMessage(msg).catch(() => {});

function transcript() {
  return Object.keys(s.turns).map(Number).sort((a, b) => a - b).map((o) => s.turns[o]).join(" ").trim();
}

function teardown(x) {
  x.stream?.getTracks().forEach((t) => t.stop());
  x.ctx?.close().catch(() => {});
}

async function start({ token, lang, terms }) {
  if (s) teardown(s);
  const x = { turns: {}, pending: [], ready: false, openTurn: false, onFinal: null };
  s = x;
  try {
    x.stream = await navigator.mediaDevices.getUserMedia({ audio: { channelCount: 1, echoCancellation: true, noiseSuppression: true } });
  } catch (err) {
    s = null;
    return send({ type: "error", message: "Microphone blocked. Open Flow's options (click the Flow icon) and press \"Allow microphone\"." });
  }
  const params = new URLSearchParams({ sample_rate: 16000, encoding: "pcm_s16le", format_turns: "false", token: token.token });
  if (lang === "multi") { params.set("speech_model", "universal-streaming-multilingual"); params.set("language_detection", "true"); }
  else if (token.speech_model) params.set("speech_model", token.speech_model);
  if (terms.length) params.set("keyterms_prompt", JSON.stringify(terms));
  const ws = new WebSocket(`${token.stream_url || "wss://streaming.assemblyai.com/v3/ws"}?${params}`);
  ws.binaryType = "arraybuffer";
  x.ws = ws;
  ws.onmessage = (e) => {
    const m = JSON.parse(e.data);
    if (m.type === "Begin" && !x.ready) {
      x.ready = true; x.pending.forEach((c) => ws.send(c)); x.pending = []; send({ type: "ready" });
    } else if (m.type === "Turn") {
      x.turns[m.turn_order] = m.transcript; x.openTurn = !m.end_of_turn;
      if (m.end_of_turn && x.onFinal) x.onFinal();
      if (s === x) send({ type: "partial", text: transcript() });
    } else if (m.type === "Error") {
      send({ type: "error", message: `AssemblyAI: ${m.error}` });
    }
  };
  ws.onclose = (e) => {
    if (s === x && !x.ready) { teardown(x); s = null; send({ type: "error", message: "Couldn't connect to AssemblyAI streaming." }); }
  };
  x.ctx = new AudioContext();
  await x.ctx.audioWorklet.addModule(chrome.runtime.getURL("pcm-worklet.js"));
  const node = new AudioWorkletNode(x.ctx, "pcm16");
  node.port.onmessage = (e) => { if (x.ready && ws.readyState === WebSocket.OPEN) ws.send(e.data); else x.pending.push(e.data); };
  const mute = x.ctx.createGain(); mute.gain.value = 0;
  x.ctx.createMediaStreamSource(x.stream).connect(node).connect(mute).connect(x.ctx.destination);
}

async function stop() {
  const x = s;
  if (!x) return send({ type: "error", message: "Released too early. Hold the keys while you speak." });
  teardown(x);
  // Wait briefly for the connection if the user released very quickly.
  for (let i = 0; i < 40 && !x.ready; i++) await new Promise((r) => setTimeout(r, 100));
  if (!x.ready) { s = null; return send({ type: "error", message: "Streaming never connected." }); }
  const gotFinal = new Promise((r) => { x.onFinal = r; });
  x.ws.send(JSON.stringify({ type: "ForceEndpoint" }));
  await Promise.race([gotFinal, new Promise((r) => setTimeout(r, x.openTurn ? 2500 : 800))]);
  try { x.ws.send(JSON.stringify({ type: "Terminate" })); } catch {}
  const text = transcript();
  s = null;
  setTimeout(() => { try { x.ws.close(); } catch {} }, 500);
  send({ type: "final", text });
}

function copy(text) {
  const ta = document.getElementById("clip");
  ta.value = text; ta.select();
  document.execCommand("copy");
}

chrome.runtime.onMessage.addListener((msg) => {
  if (msg.target !== "offscreen") return;
  if (msg.type === "start") start(msg);
  else if (msg.type === "stop") stop();
  else if (msg.type === "abort") { if (s) { teardown(s); try { s.ws?.close(); } catch {} s = null; } }
  else if (msg.type === "copy") copy(msg.text);
});
