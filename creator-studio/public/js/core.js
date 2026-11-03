// Shared helpers for the Creator Studio SPA: DOM building, API calls, toasts, modals,
// a small safe Markdown renderer and formatting utilities. No framework, no build step.

/**
 * Build a DOM element. h('button', { class: 'btn', onclick: fn }, 'Save')
 * Children may be strings, numbers, nodes, arrays or null/false (skipped).
 * Strings are always inserted as text, so user data can't inject HTML.
 */
export function h(tag, props = {}, ...children) {
  const el = document.createElement(tag);
  for (const [key, value] of Object.entries(props || {})) {
    if (value === null || value === undefined || value === false) continue;
    if (key === 'class') el.className = value;
    else if (key === 'style' && typeof value === 'object') Object.assign(el.style, value);
    else if (key === 'dataset') Object.assign(el.dataset, value);
    else if (key === 'html') el.innerHTML = value; // only for trusted, pre-escaped markup
    else if (key.startsWith('on') && typeof value === 'function') el.addEventListener(key.slice(2), value);
    else if (key === 'value') el.value = value;
    else if (value === true) el.setAttribute(key, '');
    else el.setAttribute(key, value);
  }
  append(el, children);
  return el;
}

function append(el, children) {
  for (const child of children.flat(Infinity)) {
    if (child === null || child === undefined || child === false) continue;
    el.append(child instanceof Node ? child : document.createTextNode(String(child)));
  }
}

// Native append()/prepend()/replaceChildren() turn an array argument into the text
// "[object HTMLButtonElement],[object ...]". Pages pass mapped arrays all the time (h() already
// flattens them), so make the native methods flatten arrays and skip null/false/undefined too.
for (const proto of [Element.prototype, DocumentFragment.prototype]) {
  for (const name of ['append', 'prepend', 'replaceChildren']) {
    const native = proto[name];
    if (native.__flattens) continue;
    const patched = function (...items) {
      return native.apply(this, items.flat(Infinity).filter((x) => x !== null && x !== undefined && x !== false));
    };
    patched.__flattens = true;
    proto[name] = patched;
  }
}

export const $ = (sel, root = document) => root.querySelector(sel);
export const $$ = (sel, root = document) => [...root.querySelectorAll(sel)];

export function clear(el) {
  while (el.firstChild) el.removeChild(el.firstChild);
  return el;
}

// ------------------------------------------------------------------
// API
// ------------------------------------------------------------------

let onUnauthorized = () => {};
export function setUnauthorizedHandler(fn) { onUnauthorized = fn; }

export class ApiError extends Error {
  constructor(message, status, data) {
    super(message);
    this.status = status;
    this.data = data;
  }
}

/** Call the JSON API. Throws ApiError with the server's message on failure. */
/** The landing page's stylesheet, added the first time a landing module loads (the studio skips it). */
export function ensureLandingCss() {
  if (document.querySelector('link[data-landing-css]')) return;
  document.head.append(h('link', { rel: 'stylesheet', href: '/css/landing.css?v=1', 'data-landing-css': '' }));
}

/** Public forms bots like: `_t` (ms the page was open) goes with them (BlockBots). */
const BOT_GUARDED = /^(auth\/register|booking|stripe\/checkout-session|review\/)/;

/** A field people never see (bots fill it in): send its value as `website` (BlockBots). */
export function botTrap() {
  return h('input', { type: 'text', name: 'website', tabindex: '-1', autocomplete: 'off', class: 'hp-field', 'aria-hidden': 'true' });
}

const apiMemoryCache = new Map();

/** Invalidate client-side API in-memory cache */
export function invalidateApiCache(prefix = null) {
  if (!prefix) {
    apiMemoryCache.clear();
  } else {
    for (const k of apiMemoryCache.keys()) {
      if (k.startsWith(prefix)) apiMemoryCache.delete(k);
    }
  }
}

export async function api(method, path, body, options = {}) {
  const upper = (method || 'GET').toUpperCase();
  const cacheMs = options.cacheMs ?? (upper === 'GET' ? 15000 : 0);
  const cacheKey = path;

  if (upper === 'GET' && cacheMs > 0 && !options.fresh) {
    const hit = apiMemoryCache.get(cacheKey);
    if (hit && Date.now() - hit.time < cacheMs) {
      return hit.data;
    }
  }

  if (upper === 'POST' && body && typeof body === 'object' && BOT_GUARDED.test(path) && body._t === undefined) {
    body = { ...body, _t: Math.round(performance.now()) };
  }
  const opts = {
    method: upper,
    credentials: 'same-origin',
    headers: { Accept: 'application/json' },
  };
  if (body !== undefined) {
    opts.headers['Content-Type'] = 'application/json';
    opts.body = JSON.stringify(body);
  }

  let res;
  try {
    res = await fetch(`/api/${path}`, opts);
  } catch {
    throw new ApiError("Can't reach the server. Is `php artisan serve` still running?", 0, {});
  }

  let data = {};
  try { data = await res.json(); } catch { /* empty or non-JSON body */ }

  if (res.status === 401 && !path.startsWith('auth/')) {
    onUnauthorized();
  }
  // A sensitive action wants the password again (EnsureFreshPassword): ask, then retry once.
  if (res.status === 423 && data.password_confirm_required && !path.startsWith('auth/')) {
    if (await askPassword()) return api(method, path, body, options);
  }
  if (!res.ok) {
    throw new ApiError(errorMessage(data, res.status), res.status, data);
  }

  if (upper === 'GET' && cacheMs > 0) {
    apiMemoryCache.set(cacheKey, { time: Date.now(), data });
  } else if (upper !== 'GET') {
    invalidateApiCache();
  }

  return data;
}

/** Ask for the password and confirm it with the server; true once confirmed. */
function askPassword() {
  return modal('Confirm it’s you', (close) => {
    const input = h('input', { type: 'password', autocomplete: 'current-password', required: true });
    const err = h('p', { class: 'form-error', role: 'alert', hidden: true });
    const ok = h('button', { class: 'btn primary', type: 'submit' }, 'Continue');
    return h('form', { onsubmit: async (e) => {
      e.preventDefault();
      ok.disabled = true;
      try {
        await api('POST', 'auth/confirm-password', { password: input.value });
        close(true);
      } catch (ex) {
        err.textContent = ex.message;
        err.hidden = false;
        ok.disabled = false;
      }
    } },
    h('p', { class: 'muted' }, 'This needs your password again (it’s remembered for 15 minutes).'),
    field('Password', input), err,
    h('div', { class: 'row', style: { justifyContent: 'flex-end' } }, ok));
  }).then(Boolean);
}

/** onclick for a download link to a sensitive file: password first (if needed), then the download. */
export async function guardDownload(e) {
  const a = e.currentTarget;
  if (a.dataset.confirmed) { delete a.dataset.confirmed; return; }
  e.preventDefault();
  if (await freshPassword()) { a.dataset.confirmed = '1'; a.click(); }
}

/**
 * Run slow AI work in the background (RunAiTask) and wait for its answer, checking every 2 s:
 * the request never hangs, and a server time limit can't cut it off. Same result (or error) as
 * calling the endpoint directly.
 */
export async function runTask(kind, payload = {}) {
  let t = await api('POST', 'tasks', { kind, payload });
  const started = Date.now();
  while (t.status === 'queued' || t.status === 'running') {
    if (Date.now() - started > 15 * 60 * 1000) throw new ApiError('This is taking too long. Is the queue worker running?', 504, {});
    await new Promise((r) => setTimeout(r, 2000));
    t = await api('GET', `tasks/${t.id}`);
  }
  if (t.status === 'failed') throw new ApiError(errorMessage(t.result || {}, t.http_status || 500), t.http_status || 500, t.result || {});
  return t.result;
}

/** Before a download link to a sensitive file: make sure the password was typed recently. */
export async function freshPassword() {
  const { fresh } = await api('GET', 'auth/password-fresh');
  return fresh || askPassword();
}

/**
 * POST to a streaming endpoint (/api/stream/...) and read its Server-Sent Events.
 * onDelta(text) gets each chunk; resolves with the "done" payload; throws ApiError on
 * an "error" event or a normal JSON error response (validation runs before streaming).
 */
export async function apiStream(path, body, { onDelta = () => {}, onMessage = () => {}, onStatus = () => {}, onEvent = () => {}, signal } = {}) {
  let res;
  try {
    res = await fetch(`/api/stream/${path}`, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { Accept: 'text/event-stream, application/json', 'Content-Type': 'application/json' },
      body: JSON.stringify(body || {}),
      signal,
    });
  } catch (err) {
    if (err.name === 'AbortError') throw err;
    throw new ApiError("Can't reach the server. Is `php artisan serve` still running?", 0, {});
  }

  if (!(res.headers.get('Content-Type') || '').includes('text/event-stream')) {
    let data = {};
    try { data = await res.json(); } catch { /* not JSON */ }
    if (res.status === 401) onUnauthorized();
    throw new ApiError(errorMessage(data, res.status), res.status, data);
  }

  const reader = res.body.getReader();
  const decoder = new TextDecoder('utf-8');
  let buffer = '';
  let result = null;
  let failure = null;

  const handle = (event, data) => {
    if (event === 'data') onDelta(JSON.parse(data));
    else if (event === 'message') onMessage(data);
    else if (event === 'status') onStatus(JSON.parse(data));
    else if (event === 'done') result = JSON.parse(data);
    else if (event === 'error') failure = new ApiError(JSON.parse(data), 502, { streamed: true }); // request was accepted, then the AI failed
    else if (event !== 'stop') { try { onEvent(event, JSON.parse(data)); } catch { onEvent(event, data); } } // tool / action / client / note ...
  };

  for (;;) {
    const { done, value } = await reader.read();
    if (done) break;
    buffer += decoder.decode(value, { stream: true });
    let idx;
    while ((idx = buffer.indexOf('\n\n')) !== -1) {
      const block = buffer.slice(0, idx);
      buffer = buffer.slice(idx + 2);
      let event = 'message';
      const lines = [];
      for (const line of block.split('\n')) {
        if (line.startsWith('event:')) event = line.slice(6).trim();
        else if (line.startsWith('data:')) lines.push(line.slice(5).replace(/^ /, ''));
      }
      if (lines.length) handle(event, lines.join('\n'));
    }
  }

  if (failure) throw failure;
  if (!result) throw new ApiError('The connection closed before the answer finished. Try again.', 0, {});
  return result;
}

/** Multipart upload with progress (fetch can't report upload progress). */
export function apiUpload(path, formData, { onProgress = () => {} } = {}) {
  return new Promise((resolve, reject) => {
    const xhr = new XMLHttpRequest();
    xhr.open('POST', `/api/${path}`);
    xhr.setRequestHeader('Accept', 'application/json');
    xhr.withCredentials = true;
    xhr.upload.onprogress = (e) => { if (e.lengthComputable) onProgress(e.loaded / e.total); };
    xhr.onerror = () => reject(new ApiError('Upload failed: the connection dropped.', 0, {}));
    xhr.onload = () => {
      let data = {};
      try { data = JSON.parse(xhr.responseText || '{}'); } catch { /* non-JSON */ }
      if (xhr.status === 401) onUnauthorized();
      if (xhr.status >= 200 && xhr.status < 300) resolve(data);
      else reject(new ApiError(xhr.status === 413 && !data.error ? 'That file is too big for the server.' : errorMessage(data, xhr.status), xhr.status, data));
    };
    xhr.send(formData);
  });
}

/** A Markdown block that re-renders as text streams in (at most once per frame). */
export function liveMarkdown(placeholder = 'Writing…') {
  let text = '';
  let queued = false;
  const el = h('div', { class: 'markdown-body live' }, h('p', { class: 'muted' }, h('span', { class: 'spinner' }), ' ', placeholder));
  const render = () => {
    queued = false;
    el.innerHTML = markdown(text) + '<span class="caret"></span>';
  };
  return {
    el,
    /** Replace the placeholder line before any text has arrived (e.g. "Searching claim 2 of 5…"). */
    status(message) {
      if (!text) el.replaceChildren(h('p', { class: 'muted' }, h('span', { class: 'spinner' }), ' ', message));
    },
    append(chunk) {
      text += chunk;
      if (!queued) { queued = true; requestAnimationFrame(render); }
    },
    get text() { return text; },
  };
}

function errorMessage(data, status) {
  if (data.errors) {
    const first = Object.values(data.errors)[0];
    if (Array.isArray(first) && first.length) return first[0];
  }
  if (data.error) return data.error;
  if (data.message) return data.message;
  if (status === 419) return 'Your session expired. Reload the page.';
  if (status >= 500) return `Server error (${status}). Check storage/logs/laravel.log.`;
  return `Request failed (${status}).`;
}

/** Run an async action while a button shows a busy label; toasts errors. */
export async function busy(button, label, fn) {
  const original = button ? button.innerHTML : '';
  if (button) {
    button.disabled = true;
    button.innerHTML = '';
    button.append(h('span', { class: 'spinner' }), label);
  }
  try {
    return await fn();
  } catch (err) {
    toast(err.message || String(err), 'err');
    return undefined;
  } finally {
    if (button && button.isConnected) {
      button.disabled = false;
      button.innerHTML = original;
    }
  }
}

// ------------------------------------------------------------------
// Toasts and modals
// ------------------------------------------------------------------

export function toast(message, kind = 'ok', ms = 4200) {
  const box = document.getElementById('toast-container');
  const el = h('div', { class: `toast ${kind}`, role: kind === 'err' ? 'alert' : 'status' }, message);
  el.addEventListener('click', () => el.remove());
  box.append(el);
  setTimeout(() => el.remove(), kind === 'err' ? ms * 2 : ms);
}

/** Open a modal. `build(close)` returns the body node. Resolves with whatever close() is given. */
/** The close (x) mark for dialogs, as an SVG (core can't import icons.js without a cycle). */
function closeIcon() {
  const span = document.createElement('span');
  span.className = 'icon';
  span.setAttribute('aria-hidden', 'true');
  span.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M6 6l12 12M18 6 6 18"/></svg>';
  return span;
}

export function modal(title, build, { wide = false } = {}) {
  return new Promise((resolve) => {
    const overlay = h('div', { class: 'modal-overlay' });
    const close = (value) => {
      document.removeEventListener('keydown', onKey);
      window.removeEventListener('popstate', onNav);
      overlay.remove();
      resolve(value);
    };
    const onKey = (e) => { if (e.key === 'Escape') close(undefined); };
    const onNav = () => close(undefined); // a link inside the dialog moved to another page
    window.addEventListener('popstate', onNav);
    const dialog = h('div', { class: `modal${wide ? ' wide' : ''}`, role: 'dialog', 'aria-modal': 'true', 'aria-label': title },
      h('div', { class: 'modal-head' },
        h('h2', {}, title),
        h('button', { class: 'btn ghost sm', 'aria-label': 'Close', onclick: () => close(undefined) }, closeIcon())),
      build(close));
    overlay.append(dialog);
    overlay.addEventListener('mousedown', (e) => { if (e.target === overlay) close(undefined); });
    document.addEventListener('keydown', onKey);
    document.body.append(overlay);
    const focusable = dialog.querySelector('input, textarea, select');
    (focusable || dialog.querySelector('button')).focus();
  });
}

export function confirmDialog(message, { ok = 'Delete', danger = true } = {}) {
  return modal('Are you sure?', (close) => h('div', {},
    h('p', {}, message),
    h('div', { class: 'row', style: { justifyContent: 'flex-end', marginTop: '18px' } },
      h('button', { class: 'btn', onclick: () => close(false) }, 'Cancel'),
      h('button', { class: `btn ${danger ? 'danger-solid' : 'primary'}`, onclick: () => close(true) }, ok))))
    .then(Boolean);
}

// ------------------------------------------------------------------
// Markdown (small and safe: input is HTML-escaped before any markup is added)
// ------------------------------------------------------------------

export function escapeHtml(s) {
  return String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
}

function inline(text) {
  let s = escapeHtml(text);
  const codes = [];
  s = s.replace(/`([^`]+)`/g, (_, c) => { codes.push(c); return `\u0000${codes.length - 1}\u0000`; });
  s = s.replace(/\[(VISUAL|B-ROLL|VERIFY|YOUR STORY|EMBED VIDEO HERE)([^\]]*)\]/g, '<span class="cue">[$1$2]</span>');
  s = s.replace(/\[([^\]]+)\]\((https?:\/\/[^\s)]+)\)/g, '<a href="$2" target="_blank" rel="noopener">$1</a>');
  s = s.replace(/(^|[\s(])(https?:\/\/[^\s<)]+)/g, '$1<a href="$2" target="_blank" rel="noopener">$2</a>');
  s = s.replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>');
  s = s.replace(/(^|[^*\w])\*([^*\n]+)\*(?!\*)/g, '$1<em>$2</em>');
  s = s.replace(/(^|[^_\w])_([^_\n]+)_(?!\w)/g, '$1<em>$2</em>');
  s = s.replace(/~~([^~]+)~~/g, '<del>$1</del>');
  s = s.replace(/\u0000(\d+)\u0000/g, (_, i) => `<code>${codes[i]}</code>`);
  return s;
}

/** Markdown -> HTML string. Handles headings, lists, task lists, quotes, code, rules, paragraphs. */
export function markdown(src) {
  const lines = String(src ?? '').replace(/\r\n?/g, '\n').split('\n');
  const out = [];
  let para = [];
  let list = null; // { type: 'ul'|'ol', items: [] }
  let quote = [];
  let code = null;

  const flushPara = () => { if (para.length) { out.push(`<p>${para.map(inline).join('<br>')}</p>`); para = []; } };
  const flushList = () => {
    if (!list) return;
    const items = list.items.map((it) => {
      const task = it.match(/^\[([ xX])\]\s+(.*)$/);
      if (task) return `<li class="task"><input type="checkbox" disabled${task[1] !== ' ' ? ' checked' : ''}> ${inline(task[2])}</li>`;
      return `<li>${inline(it)}</li>`;
    });
    out.push(`<${list.type}>${items.join('')}</${list.type}>`);
    list = null;
  };
  const flushQuote = () => { if (quote.length) { out.push(`<blockquote>${quote.map(inline).join('<br>')}</blockquote>`); quote = []; } };
  const flushAll = () => { flushPara(); flushList(); flushQuote(); };

  for (const line of lines) {
    if (code !== null) {
      if (/^```/.test(line)) { out.push(`<pre><code>${escapeHtml(code.join('\n'))}</code></pre>`); code = null; }
      else code.push(line);
      continue;
    }
    if (/^```/.test(line)) { flushAll(); code = []; continue; }

    let m;
    if ((m = line.match(/^(#{1,6})\s+(.*)$/))) {
      flushAll();
      const level = Math.min(m[1].length + 1, 6); // page already has an h1
      out.push(`<h${level}>${inline(m[2])}</h${level}>`);
    } else if (/^\s*([-*_])\s*\1\s*\1[\s\-*_]*$/.test(line)) {
      flushAll(); out.push('<hr>');
    } else if ((m = line.match(/^\s*[-*+]\s+(.*)$/))) {
      flushPara(); flushQuote();
      if (!list || list.type !== 'ul') { flushList(); list = { type: 'ul', items: [] }; }
      list.items.push(m[1]);
    } else if ((m = line.match(/^\s*\d+[.)]\s+(.*)$/))) {
      flushPara(); flushQuote();
      if (!list || list.type !== 'ol') { flushList(); list = { type: 'ol', items: [] }; }
      list.items.push(m[1]);
    } else if ((m = line.match(/^>\s?(.*)$/))) {
      flushPara(); flushList(); quote.push(m[1]);
    } else if (line.trim() === '') {
      flushAll();
    } else if (list && /^\s{2,}\S/.test(line)) {
      list.items[list.items.length - 1] += ' ' + line.trim();
    } else {
      flushList(); flushQuote(); para.push(line);
    }
  }
  if (code !== null) out.push(`<pre><code>${escapeHtml(code.join('\n'))}</code></pre>`);
  flushAll();
  return out.join('\n');
}

export function mdBlock(src, emptyText = 'Nothing here yet.') {
  if (!src || !String(src).trim()) return h('p', { class: 'muted' }, emptyText);
  return h('div', { class: 'markdown-body', html: markdown(src) });
}

// ------------------------------------------------------------------
// Formatting
// ------------------------------------------------------------------

/** "2026-11-06T00:00:00.000000Z" or "2026-11-06" -> Date at local midnight. */
export function parseDay(value) {
  if (!value) return null;
  const [y, m, d] = String(value).slice(0, 10).split('-').map(Number);
  return new Date(y, m - 1, d);
}

export function isoDay(value) {
  return value ? String(value).slice(0, 10) : '';
}

export function fmtDay(value, opts = { month: 'short', day: 'numeric' }) {
  const d = parseDay(value);
  return d ? d.toLocaleDateString(undefined, opts) : '';
}

export function daysUntil(value) {
  const d = parseDay(value);
  if (!d) return null;
  const today = new Date();
  today.setHours(0, 0, 0, 0);
  return Math.round((d - today) / 86400000);
}

export function timeAgo(value) {
  if (!value) return 'never';
  const secs = (Date.now() - new Date(value).getTime()) / 1000;
  if (secs < 60) return 'just now';
  if (secs < 3600) return `${Math.floor(secs / 60)} min ago`;
  if (secs < 86400) return `${Math.floor(secs / 3600)} h ago`;
  const days = Math.floor(secs / 86400);
  return days === 1 ? 'yesterday' : `${days} days ago`;
}

export function compact(n) {
  const v = Number(n || 0);
  if (v >= 1e6) return `${(v / 1e6).toFixed(v >= 1e7 ? 0 : 1)}M`;
  if (v >= 1e3) return `${(v / 1e3).toFixed(v >= 1e4 ? 0 : 1)}K`;
  return String(v);
}

export function wordCount(text) {
  return (String(text || '').match(/[A-Za-z'’-]+/g) || []).length;
}

export function debounce(fn, ms) {
  let t;
  const wrapped = (...args) => { clearTimeout(t); t = setTimeout(() => fn(...args), ms); };
  wrapped.flush = (...args) => { clearTimeout(t); return fn(...args); };
  wrapped.cancel = () => clearTimeout(t);
  return wrapped;
}

export async function copyText(text, label = 'Copied to clipboard.') {
  try {
    await navigator.clipboard.writeText(text);
    toast(label);
  } catch {
    toast("Couldn't copy. Select the text and press Ctrl+C.", 'err');
  }
}

export function download(filename, text) {
  const url = URL.createObjectURL(new Blob([text], { type: 'text/markdown;charset=utf-8' }));
  const a = h('a', { href: url, download: filename });
  document.body.append(a);
  a.click();
  a.remove();
  setTimeout(() => URL.revokeObjectURL(url), 1000);
}

export function slug(s) {
  return String(s || 'untitled').toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '').slice(0, 60) || 'untitled';
}

// ------------------------------------------------------------------
// Small UI pieces shared by pages
// ------------------------------------------------------------------

export function pill(status, label) {
  return h('span', { class: `pill ${status}` }, label ?? status);
}

export function pageHead(title, desc, ...actions) {
  return h('div', { class: 'page-head' },
    h('div', {}, h('h1', {}, title), desc ? h('p', { class: 'desc' }, desc) : null),
    actions.length ? h('div', { class: 'row' }, actions) : null);
}

export function emptyState(icon, title, text, ...actions) {
  return h('div', { class: 'empty' },
    h('div', { class: 'empty-icon' }, icon),
    h('h3', {}, title),
    text ? h('p', { class: 'muted' }, text) : null,
    actions.length ? h('div', { class: 'row', style: { justifyContent: 'center' } }, actions) : null);
}

export function loading(text = 'Loading…') {
  return h('div', { class: 'loading' }, h('span', { class: 'spinner' }), text);
}

export function field(label, input, hint) {
  const id = input.id || `f-${Math.random().toString(36).slice(2, 9)}`;
  input.id = id;
  return h('div', { class: 'field' },
    h('label', { for: id }, label, hint ? h('span', { class: 'hint' }, ` ${hint}`) : null),
    input);
}

/** Read a form's named fields into a plain object. */
export function formData(form) {
  const data = {};
  for (const el of form.elements) {
    if (!el.name) continue;
    if (el.type === 'checkbox') data[el.name] = el.checked;
    else data[el.name] = el.value;
  }
  return data;
}

// ------------------------------------------------------------------
// Router (History API; Laravel's fallback route serves index.html for any path)
// ------------------------------------------------------------------

export function navigate(path, { replace = false } = {}) {
  if (path === location.pathname) {
    window.dispatchEvent(new Event('popstate'));
    return;
  }
  history[replace ? 'replaceState' : 'pushState']({}, '', path);
  window.dispatchEvent(new Event('popstate'));
}

/** <a> that routes inside the app without a page load. */
export function link(path, props, ...children) {
  return h('a', {
    href: path,
    ...props,
    onclick: (e) => {
      if (e.metaKey || e.ctrlKey || e.shiftKey || e.button !== 0) return;
      e.preventDefault();
      if (props && typeof props.onclick === 'function') {
        try { props.onclick(e); } catch (err) { console.error(err); }
      }
      navigate(path);
    },
  }, ...children);
}
