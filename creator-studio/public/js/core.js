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
export async function api(method, path, body) {
  const opts = {
    method,
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
  if (!res.ok) {
    throw new ApiError(errorMessage(data, res.status), res.status, data);
  }
  return data;
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
export function modal(title, build, { wide = false } = {}) {
  return new Promise((resolve) => {
    const overlay = h('div', { class: 'modal-overlay' });
    const close = (value) => {
      document.removeEventListener('keydown', onKey);
      overlay.remove();
      resolve(value);
    };
    const onKey = (e) => { if (e.key === 'Escape') close(undefined); };
    const dialog = h('div', { class: `modal${wide ? ' wide' : ''}`, role: 'dialog', 'aria-modal': 'true', 'aria-label': title },
      h('div', { class: 'modal-head' },
        h('h2', {}, title),
        h('button', { class: 'btn ghost sm', 'aria-label': 'Close', onclick: () => close(undefined) }, '✕')),
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
      navigate(path);
    },
  }, ...children);
}
