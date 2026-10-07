// Flow extension service worker: owns one dictation at a time.
// content script (any tab) --start/stop--> here --> offscreen document (mic + AssemblyAI socket)
// offscreen --partial/final--> here --> Flow server /api/polish --> content script inserts the text.

importScripts("site-style.js"); // styleFor(setting, url)

const DEFAULTS = { serverUrl: "https://flow-dictation.onrender.com", style: "site", lang: "en", dict: "" };

let active = null; // { tabId, frameId }

async function settings() {
  const s = await chrome.storage.sync.get(DEFAULTS);
  return { ...s, serverUrl: s.serverUrl.replace(/\/+$/, "") };
}

async function ensureOffscreen() {
  if (await chrome.offscreen.hasDocument()) return;
  await chrome.offscreen.createDocument({
    url: "offscreen.html",
    reasons: ["USER_MEDIA", "CLIPBOARD"],
    justification: "Capture the microphone for dictation and copy text when a page can't be typed into.",
  });
}

function tell(state, extra = {}) {
  if (!active) return;
  chrome.tabs.sendMessage(active.tabId, { type: "flow-state", state, ...extra }, { frameId: active.frameId }).catch(() => {});
}

function dictTerms(dict) {
  const seen = new Set();
  return dict.split(/[\n,]/).map((t) => t.trim()).filter((t) => t && t.length <= 50)
    .filter((t) => !seen.has(t.toLowerCase()) && seen.add(t.toLowerCase())).slice(0, 100);
}

async function start(tabId, frameId, url) {
  if (active) return;
  active = { tabId, frameId, url, startedAt: Date.now() };
  tell("connecting");
  try {
    const cfg = await settings();
    const res = await fetch(`${cfg.serverUrl}/api/token`);
    const tok = await res.json().catch(() => ({}));
    if (!res.ok) throw new Error(tok.detail || `Flow server error (${res.status}). Check the server URL in Flow's options.`);
    await ensureOffscreen();
    await chrome.runtime.sendMessage({ target: "offscreen", type: "start", token: tok, lang: cfg.lang, terms: dictTerms(cfg.dict) });
  } catch (err) {
    fail(err.message || String(err));
  }
}

function stop() {
  if (!active) return;
  tell("finishing");
  chrome.runtime.sendMessage({ target: "offscreen", type: "stop" }).catch(() => {});
}

function fail(message) {
  tell("error", { message });
  chrome.runtime.sendMessage({ target: "offscreen", type: "abort" }).catch(() => {});
  active = null;
}

// Markdown from the polish -> plain text that reads well when typed into Gmail, Slack, a form, etc.
function toPlain(md) {
  return md.split("\n").map((line) => line
    .replace(/^#{1,6}\s+/, "")
    .replace(/\*\*(.+?)\*\*/g, "$1")
    .replace(/(^|[^*])\*(?!\s)(.+?)\*(?!\*)/g, "$1$2")
    .replace(/^(\s*)[*•]\s+/, "$1- ")).join("\n").replace(/\n{3,}/g, "\n\n").trim();
}

async function finish(raw) {
  if (!raw.trim()) return fail("Didn't catch any speech. Hold the keys, speak, then let go.");
  tell("polishing");
  const cfg = await settings();
  const style = styleFor(cfg.style, active.url);
  let text = raw.trim(); let note = "";
  try {
    const res = await fetch(`${cfg.serverUrl}/api/polish`, {
      method: "POST", headers: { "Content-Type": "application/json" }, body: JSON.stringify({ text: raw, style }),
    });
    if (!res.ok) throw new Error((await res.json().catch(() => ({}))).detail || `polish failed (${res.status})`);
    const [body, problem] = (await res.text()).split("\u0000");
    if (problem !== undefined || !body.trim()) throw new Error(problem || "empty reply");
    text = toPlain(body);
  } catch (err) {
    note = `AI polish failed (${err.message}); inserted your raw words.`;
  }
  const target = active;
  active = null;
  const done = await chrome.tabs.sendMessage(target.tabId, { type: "flow-insert", text, note }, { frameId: target.frameId })
    .catch(() => ({ inserted: false }));
  if (!done || !done.inserted) {
    await ensureOffscreen();
    await chrome.runtime.sendMessage({ target: "offscreen", type: "copy", text }).catch(() => {});
    chrome.tabs.sendMessage(target.tabId, { type: "flow-state", state: "copied", note }, { frameId: target.frameId }).catch(() => {});
  }
}

chrome.runtime.onMessage.addListener((msg, sender, reply) => {
  if (msg.target === "offscreen") return; // not ours
  if (msg.type === "flow-start" && sender.tab) start(sender.tab.id, sender.frameId, sender.tab.url || sender.url || "");
  else if (msg.type === "flow-stop") stop();
  else if (msg.type === "partial") tell("listening", { text: msg.text });
  else if (msg.type === "ready") tell("listening", { text: "" });
  else if (msg.type === "final") finish(msg.text);
  else if (msg.type === "error") fail(msg.message);
  else if (msg.type === "get-settings") { settings().then(reply); return true; }
});

chrome.commands.onCommand.addListener(async (command, tab) => {
  if (command !== "toggle-dictation" || !tab) return;
  if (active) stop();
  else chrome.tabs.sendMessage(tab.id, { type: "flow-toggle-start" }).catch(() => {});
});

chrome.runtime.onInstalled.addListener((info) => {
  if (info.reason === "install") chrome.runtime.openOptionsPage();
});
