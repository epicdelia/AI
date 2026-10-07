// Runs in every page: hold Option/Alt+Space to dictate into the field you're in; shows a small floating pill.
(() => {
  if (window.__flowDictation) return;
  window.__flowDictation = true;

  let target = null;      // the element that had focus when dictation started
  let savedRange = null;  // caret/selection inside a contenteditable
  let holding = false;
  let hideTimer = null;

  // --- floating pill (Shadow DOM so page CSS can't touch it) ---
  const host = document.createElement("div");
  host.style.cssText = "all:initial;position:fixed;left:50%;bottom:28px;transform:translateX(-50%);z-index:2147483647;pointer-events:none;";
  const root = host.attachShadow({ mode: "open" });
  root.innerHTML = `<style>
    .pill{display:none;align-items:center;gap:10px;max-width:min(560px,90vw);padding:10px 16px;border-radius:999px;
      background:#0f172a;color:#f1f5f9;font:500 14px/1.4 ui-sans-serif,system-ui,-apple-system,"Segoe UI",sans-serif;
      box-shadow:0 10px 30px rgba(0,0,0,.35);border:1px solid #334155}
    .pill.show{display:flex}
    .dot{flex:none;width:10px;height:10px;border-radius:50%;background:#38bdf8}
    .listening .dot{background:#f43f5e;animation:p 1s infinite}
    .error .dot{background:#f97316}
    .text{overflow:hidden;white-space:nowrap;text-overflow:ellipsis;direction:rtl;text-align:left}
    .text span{direction:ltr;unicode-bidi:plaintext}
    @keyframes p{50%{opacity:.25}}</style>
    <div class="pill" role="status" aria-live="polite"><span class="dot"></span><span class="text"><span></span></span></div>`;
  const pill = root.querySelector(".pill");
  const label = root.querySelector(".text span");
  const mount = () => { if (!host.isConnected) (document.body || document.documentElement).appendChild(host); };

  function show(state, text, autoHideMs) {
    mount();
    clearTimeout(hideTimer);
    pill.className = `pill show ${state}`;
    label.textContent = text;
    if (autoHideMs) hideTimer = setTimeout(() => { pill.className = "pill"; }, autoHideMs);
  }

  // --- where to type ---
  function deepActive() {
    let el = document.activeElement;
    while (el && el.shadowRoot && el.shadowRoot.activeElement) el = el.shadowRoot.activeElement;
    return el;
  }
  function editable(el) {
    if (!el) return false;
    if (el instanceof HTMLTextAreaElement) return !el.readOnly && !el.disabled;
    if (el instanceof HTMLInputElement) return /^(text|search|email|url|tel|)$/i.test(el.type) && !el.readOnly && !el.disabled;
    return el.isContentEditable;
  }
  let savedInputSel = null;  // [start, end] inside an input/textarea
  function remember() {
    target = deepActive();
    savedRange = null; savedInputSel = null;
    const sel = window.getSelection();
    if (target && target.isContentEditable && sel && sel.rangeCount) savedRange = sel.getRangeAt(0).cloneRange();
    if (target instanceof HTMLInputElement || target instanceof HTMLTextAreaElement) {
      try { savedInputSel = [target.selectionStart, target.selectionEnd]; } catch {}
    }
  }
  // Command mode: text selected in the field when dictation starts gets edited by what you say.
  function selectedText() {
    if (!editable(target)) return "";
    if (savedInputSel && savedInputSel[1] > savedInputSel[0]) return target.value.slice(savedInputSel[0], savedInputSel[1]);
    return savedRange && !savedRange.collapsed ? savedRange.toString() : "";
  }
  // Text before the caret, as the user sees it (browsers drop a trailing space at the end of a line).
  function textBefore(el) {
    if (el instanceof HTMLInputElement || el instanceof HTMLTextAreaElement) return el.value.slice(0, el.selectionStart ?? el.value.length);
    if (!savedRange) return "";
    const r = savedRange.cloneRange(); r.selectNodeContents(el); r.setEnd(savedRange.startContainer, savedRange.startOffset);
    return r.toString().replace(/ +$/, "");
  }
  // Fit the text to the field: one line for single-line inputs, and a space when joining onto earlier text.
  function shape(el, text) {
    if (el instanceof HTMLInputElement) {
      const lines = text.split(/\n+/).map((l) => l.replace(/^\s*[-*•]\s+/, "").trim()).filter(Boolean);
      // Joining several lines into one: end each with punctuation, but never glue a "." onto a link or email.
      const endsWithLink = (l) => /(https?:\/\/|www\.)\S+$|\S+@\S+\.\S+$/.test(l);
      text = lines.length < 2 ? (lines[0] || "")
        : lines.map((l) => (/[.!?:;,]$/.test(l) || endsWithLink(l) ? l : l + ".")).join(" ");
    }
    const before = textBefore(el);
    return before && !/\s$/.test(before) ? " " + text : text;
  }
  function insert(raw) {
    const el = target;
    if (!editable(el) || !el.isConnected) return false;
    const text = shape(el, raw);
    el.focus();
    if (savedInputSel) { try { el.setSelectionRange(savedInputSel[0], savedInputSel[1]); } catch {} }
    if (el.isContentEditable && savedRange) {
      const sel = window.getSelection(); sel.removeAllRanges(); sel.addRange(savedRange);
    }
    // execCommand keeps undo history and fires the events React/Slack/Gmail editors listen for.
    if (document.execCommand("insertText", false, text)) return true;
    if (el instanceof HTMLInputElement || el instanceof HTMLTextAreaElement) {
      const a = el.selectionStart ?? el.value.length, b = el.selectionEnd ?? a;
      el.setRangeText(text, a, b, "end");
      el.dispatchEvent(new InputEvent("input", { bubbles: true, inputType: "insertText", data: text }));
      return true;
    }
    return false;
  }

  // --- hold Option/Alt+Space ---
  const isCombo = (e) => e.code === "Space" && e.altKey && !e.ctrlKey && !e.metaKey;
  function begin() {
    remember();
    const selection = selectedText();
    show("listening", selection ? "Say how to change the selected text…"
      : editable(target) ? "Listening…" : "Listening… (no text box focused: the result will be copied)");
    chrome.runtime.sendMessage({ type: "flow-start", selection }).catch(() => show("error", "Flow was updated: reload this page.", 4000));
  }
  window.addEventListener("keydown", (e) => {
    if (!isCombo(e)) return;
    e.preventDefault(); e.stopImmediatePropagation();
    if (e.repeat || holding) return;
    holding = true;
    begin();
  }, true);
  window.addEventListener("keyup", (e) => {
    if (!holding || (e.code !== "Space" && e.key !== "Alt")) return;
    e.preventDefault(); e.stopImmediatePropagation();
    holding = false;
    chrome.runtime.sendMessage({ type: "flow-stop" }).catch(() => {});
  }, true);

  // --- messages from the extension ---
  chrome.runtime.onMessage.addListener((msg, _sender, reply) => {
    if (msg.type === "flow-toggle-start") { if (window === window.top || document.hasFocus()) begin(); }
    else if (msg.type === "flow-state") {
      if (msg.state === "connecting") show("listening", "Connecting…");
      else if (msg.state === "listening") show("listening", msg.text || "Listening…");
      else if (msg.state === "finishing") show("busy", "Finishing…");
      else if (msg.state === "polishing") show("busy", "Polishing…");
      else if (msg.state === "editing") show("busy", "Editing your selection…");
      else if (msg.state === "copied") show("done", `Copied: press ${navigator.platform.includes("Mac") ? "⌘" : "Ctrl+"}V to paste${msg.note ? ". " + msg.note : ""}`, 5000);
      else if (msg.state === "error") show("error", msg.message, 6000);
    } else if (msg.type === "flow-insert") {
      const inserted = insert(msg.text);
      if (inserted) show("done", msg.note || "Inserted ✓", msg.note ? 6000 : 1500);
      reply({ inserted });
    }
  });
})();
