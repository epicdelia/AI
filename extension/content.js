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
  host.style.cssText = "all:initial;position:fixed;left:50%;bottom:28px;transform:translateX(-50%);z-index:2147483647;";
  const root = host.attachShadow({ mode: "open" });
  root.innerHTML = `<style>
    .pill{display:none;align-items:center;gap:10px;max-width:min(640px,92vw);padding:10px 16px;border-radius:999px;
      background:#0f172a;color:#f1f5f9;font:500 14px/1.4 ui-sans-serif,system-ui,-apple-system,"Segoe UI",sans-serif;
      box-shadow:0 10px 30px rgba(0,0,0,.35);border:1px solid #334155;pointer-events:none}
    .pill.show{display:flex}
    .pill.interactive{pointer-events:auto}
    .dot{flex:none;width:10px;height:10px;border-radius:50%;background:#38bdf8}
    .listening .dot{background:#f43f5e;animation:p 1s infinite}
    .error .dot{background:#f97316}
    .warn .dot{background:#eab308}
    .done .dot{background:#10b981}
    .text{overflow:hidden;white-space:nowrap;text-overflow:ellipsis;direction:rtl;text-align:left}
    .text span{direction:ltr;unicode-bidi:plaintext}
    .actions{display:none;align-items:center;gap:6px;margin-left:6px;flex:none}
    .interactive .actions{display:flex}
    .actions button{background:#1e293b;color:#38bdf8;border:1px solid #334155;border-radius:999px;padding:3px 9px;font:600 11px/1.2 inherit;cursor:pointer;transition:all .15s}
    .actions button:hover{background:#334155;color:#f8fafc;border-color:#64748b}
    @keyframes p{50%{opacity:.25}}</style>
    <div class="pill" role="status" aria-live="polite">
      <span class="dot"></span>
      <span class="text"><span></span></span>
      <div class="actions">
        <button id="act-idea" title="Save to Creator Studio Ideas">💡 Idea</button>
        <button id="act-note" title="Save to Creator Studio Notes">📝 Note</button>
        <button id="act-humanize" title="Purge robotic cliches and em-dashes">✨ Humanize</button>
      </div>
    </div>`;
  const pill = root.querySelector(".pill");
  const label = root.querySelector(".text span");
  const actIdea = root.querySelector("#act-idea");
  const actNote = root.querySelector("#act-note");
  const actHumanize = root.querySelector("#act-humanize");
  let activeTextForActions = "";

  const mount = () => { if (!host.isConnected) (document.body || document.documentElement).appendChild(host); };

  function show(state, text, autoHideMs, interactiveText = null) {
    mount();
    clearTimeout(hideTimer);
    activeTextForActions = interactiveText || "";
    pill.className = `pill show ${state}${interactiveText ? " interactive" : ""}`;
    label.textContent = text;
    if (autoHideMs) hideTimer = setTimeout(() => { pill.className = "pill"; activeTextForActions = ""; }, autoHideMs);
  }

  // --- AI-Tells Linter & Humanizer ---
  const AI_TELL_RULES = [
    { pattern: /\bdelve(?:\s+into)?\b/gi, name: "delve into", tip: "explore" },
    { pattern: /\btestament\s+to\b/gi, name: "testament to", tip: "proves" },
    { pattern: /\bin\s+today'?s\s+fast-paced\s+world\b/gi, name: "in today's fast-paced world", tip: "nowadays" },
    { pattern: /\bcrucial\s+to\s+remember\b/gi, name: "crucial to remember", tip: "remember" },
    { pattern: /\brich\s+tapestry\b/gi, name: "rich tapestry", tip: "mix" },
    { pattern: /\blet'?s\s+dive\s+in\b/gi, name: "let's dive in", tip: "let's start" },
    { pattern: /\bmoreover\b|\bfurthermore\b/gi, name: "moreover/furthermore", tip: "also" },
    { pattern: /\bbeacon\s+of\b/gi, name: "beacon of", tip: "model" },
    { pattern: /\bgame-?changer\b/gi, name: "game-changer", tip: "breakthrough" },
    { pattern: /\bnot\s+only\b[\s\S]{1,60}\bbut\s+also\b/gi, name: "not only ... but also", tip: "both / and" },
  ];

  function checkAiTells(text) {
    if (!text) return [];
    const found = [];
    for (const r of AI_TELL_RULES) {
      if (r.pattern.test(text)) found.push(`${r.name} (use "${r.tip}")`);
    }
    const emDashes = (text.match(/—|--|&mdash;/g) || []).length;
    if (emDashes >= 3) found.push(`${emDashes} em dashes`);
    return found;
  }

  function humanize(text) {
    let res = text.replace(/ — |—|--/g, ", ");
    res = res.replace(/\bdelve\s+into\b/gi, "explore");
    res = res.replace(/\btestament\s+to\b/gi, "proves");
    res = res.replace(/\bin\s+today'?s\s+fast-paced\s+world\b/gi, "nowadays");
    res = res.replace(/\bcrucial\s+to\s+remember\b/gi, "remember");
    res = res.replace(/\brich\s+tapestry\b/gi, "mix");
    res = res.replace(/\blet'?s\s+dive\s+in\b/gi, "let's begin");
    res = res.replace(/\bmoreover\b/gi, "also");
    res = res.replace(/\bfurthermore\b/gi, "also");
    return res;
  }

  actIdea.onclick = () => {
    if (!activeTextForActions) return;
    chrome.runtime.sendMessage({ type: "studio-save", kind: "idea", text: activeTextForActions }, (res) => {
      show("done", res && res.ok ? "Synced to Creator Studio Ideas ✓" : "Could not reach Studio", 3000);
    });
  };

  actNote.onclick = () => {
    if (!activeTextForActions) return;
    chrome.runtime.sendMessage({ type: "studio-save", kind: "note", text: activeTextForActions }, (res) => {
      show("done", res && res.ok ? "Synced to Creator Studio Notes ✓" : "Could not reach Studio", 3000);
    });
  };

  actHumanize.onclick = () => {
    if (!activeTextForActions) return;
    const cleaned = humanize(activeTextForActions);
    insert(cleaned);
    show("done", "Humanized ✓ (clichés purged)", 2500);
  };

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

  function runLintCheck() {
    remember();
    const sel = selectedText() || (target && target.value) || "";
    if (!sel.trim()) {
      show("warn", "Select text first to check for AI tells", 3000);
      return;
    }
    const tells = checkAiTells(sel);
    if (tells.length === 0) {
      show("done", "AI Tells: 100% Clean ✓ (Human tone verified)", 3500, sel);
    } else {
      show("warn", `AI Tell: ${tells[0]} (${tells.length} total)`, 8000, sel);
    }
  }

  // --- hold Option/Alt+Space or Option/Alt+Shift+L ---
  const isCombo = (e) => e.code === "Space" && e.altKey && !e.ctrlKey && !e.metaKey;
  const isLintCombo = (e) => (e.code === "KeyL" || e.key === "l" || e.key === "L") && e.altKey && e.shiftKey && !e.ctrlKey && !e.metaKey;

  function begin() {
    remember();
    const selection = selectedText();
    show("listening", selection ? "Say how to change the selected text…"
      : editable(target) ? "Listening…" : "Listening… (no text box focused: the result will be copied)");
    chrome.runtime.sendMessage({ type: "flow-start", selection }).catch(() => show("error", "Flow was updated: reload this page.", 4000));
  }
  // Hold = push-to-talk. A quick tap (< 300 ms) locks hands-free recording; the next tap stops it.
  const TAP_MS = 300;
  let pressedAt = 0;
  let handsFree = false;
  let swallowUp = false;
  const stopDictation = () => chrome.runtime.sendMessage({ type: "flow-stop" }).catch(() => {});
  window.addEventListener("keydown", (e) => {
    if (isLintCombo(e)) {
      e.preventDefault(); e.stopImmediatePropagation();
      runLintCheck();
      return;
    }
    if (!isCombo(e)) return;
    e.preventDefault(); e.stopImmediatePropagation();
    if (e.repeat || holding) return;
    if (handsFree) { handsFree = false; swallowUp = true; stopDictation(); return; }
    holding = true; pressedAt = performance.now();
    begin();
  }, true);
  window.addEventListener("keyup", (e) => {
    if (e.code !== "Space" && e.key !== "Alt") return;
    if (swallowUp) { e.preventDefault(); e.stopImmediatePropagation(); if (e.code === "Space") swallowUp = false; return; }
    if (!holding) return;
    e.preventDefault(); e.stopImmediatePropagation();
    holding = false;
    if (performance.now() - pressedAt < TAP_MS) {
      handsFree = true;
      show("listening", "Hands-free: speak, then tap Option/Alt+Space again to finish");
      return;
    }
    stopDictation();
  }, true);

  // --- messages from the extension ---
  chrome.runtime.onMessage.addListener((msg, _sender, reply) => {
    if (msg.type === "flow-toggle-start") { if (window === window.top || document.hasFocus()) begin(); }
    else if (msg.type === "flow-lint-selection") { runLintCheck(); }
    else if (msg.type === "flow-state") {
      if (msg.state === "connecting") show("listening", "Connecting…");
      else if (msg.state === "listening") { if (msg.text || !handsFree) show("listening", msg.text || "Listening…"); }
      else if (msg.state === "finishing") show("busy", "Finishing…");
      else if (msg.state === "polishing") show("busy", "Polishing…");
      else if (msg.state === "editing") show("busy", "Editing your selection…");
      else if (msg.state === "copied") show("done", `Copied: press ${navigator.platform.includes("Mac") ? "⌘" : "Ctrl+"}V to paste${msg.note ? ". " + msg.note : ""}`, 5000);
      else if (msg.state === "error") { handsFree = false; show("error", msg.message, 6000); }
    } else if (msg.type === "flow-insert") {
      const inserted = insert(msg.text);
      if (inserted) show("done", msg.note || "Inserted ✓", msg.note ? 6000 : 3500, msg.text);
      reply({ inserted });
    }
  });
})();
