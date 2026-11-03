// Where a landing-page visitor came from (?ref=, utm_* tags, the referring site), kept for this
// browser session so a later booking or checkout carries it, plus the funnel counts (visit, a
// book/buy click, a form started). No cookies and nothing personal: the server keeps a channel
// name, the tracked link, and counts per day.

const KEY = 'tu-attribution';
const SEEN = 'tu-funnel';
let memory = null;
let memorySeen = {};

function read(key) {
  try { return JSON.parse(sessionStorage.getItem(key) || 'null'); } catch { return null; }
}
function write(key, value) {
  try { sessionStorage.setItem(key, JSON.stringify(value)); } catch { /* private mode: memory only */ }
}

/** What brought this visitor, first touch in this session. */
export function attribution() {
  if (memory) return memory;
  const stored = read(KEY);
  const q = new URLSearchParams(location.search);
  const now = {};
  for (const k of ['ref', 'utm_source', 'utm_medium', 'utm_campaign', 'utm_content']) {
    const v = q.get(k);
    if (v) now[k] = v.slice(0, 300);
  }
  if (document.referrer) {
    try {
      const r = new URL(document.referrer);
      if (r.host !== location.host) now.referrer = `${r.protocol}//${r.host}${r.pathname}`.slice(0, 300);
    } catch { /* not a URL */ }
  }
  const tagged = Object.keys(now).length > 0;
  memory = stored && !(tagged && !stored.ref && !stored.utm_source && !stored.referrer) ? stored : { ...now, landing_path: location.pathname.slice(0, 200) };
  write(KEY, memory);
  return memory;
}

/** The item a visitor last opened from the content feed, sent with a form as context. */
export function rememberItem(key) {
  memory = { ...attribution(), last_item: key };
  write(KEY, memory);
}

/** Count a funnel step. "visit" and "form_start" count once per session, "cta" every click. */
export function countEvent(event) {
  const seen = read(SEEN) || memorySeen;
  if (event !== 'cta' && seen[event]) return;
  seen[event] = true;
  memorySeen = seen;
  write(SEEN, seen);
  try {
    const body = new Blob([JSON.stringify({ event, attribution: attribution() })], { type: 'application/json' });
    if (!navigator.sendBeacon?.('/api/landing/event', body)) {
      fetch('/api/landing/event', { method: 'POST', body, keepalive: true, headers: { 'Content-Type': 'application/json' } }).catch(() => {});
    }
  } catch { /* counting is best effort */ }
}

/** A downloadable .ics for a booked (or requested) intro call. */
export function icsFile({ start, minutes = 30, title, description = '' }) {
  const t = new Date(start);
  const fmt = (d) => d.toISOString().replace(/[-:]/g, '').replace(/\.\d{3}/, '');
  const esc = (s) => String(s).replace(/\\/g, '\\\\').replace(/;/g, '\\;').replace(/,/g, '\\,').replace(/\n/g, '\\n');
  return ['BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//Tech Unicorn//Intro call//EN', 'BEGIN:VEVENT',
    `UID:${fmt(t)}-${Math.random().toString(36).slice(2)}@techunicorn`, `DTSTAMP:${fmt(new Date())}`,
    `DTSTART:${fmt(t)}`, `DTEND:${fmt(new Date(t.getTime() + minutes * 60000))}`,
    `SUMMARY:${esc(title)}`, `DESCRIPTION:${esc(description)}`, 'END:VEVENT', 'END:VCALENDAR', ''].join('\r\n');
}

export function downloadIcs(filename, ics) {
  const url = URL.createObjectURL(new Blob([ics], { type: 'text/calendar;charset=utf-8' }));
  const a = document.createElement('a');
  a.href = url;
  a.download = filename;
  document.body.append(a);
  a.click();
  a.remove();
  setTimeout(() => URL.revokeObjectURL(url), 1000);
}
