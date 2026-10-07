const DEFAULTS = { serverUrl: "https://flow-dictation.onrender.com", style: "message", lang: "en", dict: "" };
const $ = (id) => document.getElementById(id);
const status = (html) => { $("status").innerHTML = html; };
const esc = (s) => String(s).replace(/[&<>"']/g, (c) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c]));

chrome.storage.sync.get(DEFAULTS).then((s) => {
  for (const k of Object.keys(DEFAULTS)) $(k).value = s[k];
});

$("save").addEventListener("click", async () => {
  const v = Object.fromEntries(Object.keys(DEFAULTS).map((k) => [k, $(k).value.trim()]));
  v.serverUrl = (v.serverUrl || DEFAULTS.serverUrl).replace(/\/+$/, "");
  await chrome.storage.sync.set(v);
  status('<span class="ok">Saved ✓</span>');
});

// The offscreen document can't show a permission prompt, so the extension asks once here.
$("mic").addEventListener("click", async () => {
  try {
    const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
    stream.getTracks().forEach((t) => t.stop());
    status('<span class="ok">Microphone allowed ✓</span>');
  } catch (err) {
    status(`<span class="bad">Microphone blocked: ${esc(err.message || err)}. Allow it in Chrome's site settings for this extension.</span>`);
  }
});

$("check").addEventListener("click", async () => {
  const server = ($("serverUrl").value.trim() || DEFAULTS.serverUrl).replace(/\/+$/, "");
  status("Checking… (the first request can take up to a minute if the server was asleep)");
  try {
    const out = await (await fetch(`${server}/api/health`)).json();
    const rows = [["API key", out.key], ["Live transcription", out.streaming], ["AI polish", out.polish]].filter(([, c]) => c);
    status("<ul>" + rows.map(([n, c]) => `<li class="${c.ok ? "ok" : "bad"}">${c.ok ? "✓" : "✗"} ${esc(n)}: ${esc(c.detail || (c.ok ? "OK" : "Problem"))}</li>`).join("") + "</ul>");
  } catch (err) {
    status(`<span class="bad">Couldn't reach ${esc(server)}: ${esc(err.message || err)}</span>`);
  }
});
