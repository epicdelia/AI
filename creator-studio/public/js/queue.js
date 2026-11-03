// Posting queue and reminders. She posts by hand, so a scheduled post only goes out if she
// knows it's time: a "due now" strip (dashboard and calendar), a desktop notification while the
// app is open (if she allows it), and the count in the tab title.
import { h, api, busy, toast, clear, link } from './core.js';
import { icon } from './icons.js';
import { PLATFORM_ICONS } from './social-tools.js';
const openInApp = async (...args) => (await import('./pages/social.js')).openInApp(...args);

const SEEN = 'studio-reminded';
const POLL_MS = 60000;
let timer = null;
let meta = null;
const baseTitle = () => document.title.replace(/^\(\d+\) /, '');

function seen() { try { return new Set(JSON.parse(localStorage.getItem(SEEN) || '[]')); } catch { return new Set(); } }
function remember(ids) { try { localStorage.setItem(SEEN, JSON.stringify([...ids].slice(-200))); } catch { /* not remembered */ } }
const timeOf = (iso) => new Date(iso).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });

async function poll() {
  try {
    meta ??= await api('GET', 'social/meta');
    const q = await api('GET', 'social/due');
    document.title = q.due.length ? `(${q.due.length}) ${baseTitle()}` : baseTitle();
    const done = seen();
    const fresh = q.due.filter((p) => !done.has(p.id));
    fresh.forEach((p) => done.add(p.id));
    const notify = (title, body, href, tag) => {
      if ('Notification' in window && Notification.permission === 'granted') {
        const n = new Notification(title, { body, tag, icon: '/favicon.ico' });
        n.onclick = () => { window.focus(); location.assign(href); };
      }
    };
    if (fresh.length === 1) {
      // One post: say which, and open it from the notification.
      const p = fresh[0];
      const title = `Time to post on ${meta.platforms[p.platform]?.label || p.platform}`;
      const body = p.title || String(p.body || p.parts?.[0] || '').slice(0, 120);
      notify(title, body, `/posts/${p.id}`, `post-${p.id}`);
      toast(`${title}: ${body}`, 'ok', 9000);
    } else if (fresh.length > 1) {
      // Several at once: one summary rather than a stack of toasts.
      const nets = [...new Set(fresh.map((p) => meta.platforms[p.platform]?.label || p.platform))].join(', ');
      notify(`${fresh.length} posts are due`, nets, '/', 'posts-due');
      toast(`${fresh.length} posts are due now (${nets}). They’re at the top of your dashboard.`, 'ok', 9000);
    }
    remember(done);
    window.dispatchEvent(new CustomEvent('studio:due', { detail: q }));
  } catch { /* offline or logged out: try again next minute */ }
}

/** Start the reminder poller (once, after login). */
export function startReminders() {
  if (timer) return;
  poll();
  timer = setInterval(poll, POLL_MS);
}
export function stopReminders() {
  clearInterval(timer);
  timer = null;
  document.title = baseTitle();
}

/** The "due now" strip: copy & open, mark posted, snooze. Hidden when nothing is due soon. */
export function dueStrip() {
  const box = h('div', { class: 'due-strip', hidden: true, 'aria-live': 'polite' });
  async function draw(q) {
    meta ??= await api('GET', 'social/meta').catch(() => null);
    if (!meta) return;
    q ??= await api('GET', 'social/due').catch(() => ({ due: [], upcoming: [] }));
    box.hidden = !q.due.length && !q.upcoming.length;
    if (box.hidden) return;
    const now = new Date(q.now || Date.now());
    const canAsk = 'Notification' in window && Notification.permission === 'default';
    clear(box).append(h('div', { class: 'card due-card' },
      h('div', { class: 'card-header' },
        h('h2', {}, q.due.length ? `Due now · ${q.due.length}` : 'Coming up today'),
        h('div', { class: 'row' },
          canAsk ? h('button', {
            class: 'btn ghost sm',
            onclick: async (e) => { const r = await Notification.requestPermission(); toast(r === 'granted' ? 'Reminders on: you’ll get a notification when a post is due.' : 'Notifications are blocked in this browser.'); e.target.closest('button')?.remove(); },
          }, icon('calendar', 14), 'Turn on reminders') : null,
          link('/calendar', { class: 'btn ghost sm' }, 'Calendar', icon('arrow', 14)))),
      h('ul', { class: 'due-list' }, q.due.map((p) => {
        const overdue = now - new Date(p.scheduled_for) > 3600000;
        const posted = h('button', {
          class: 'btn sm primary',
          onclick: () => busy(posted, 'Saving…', async () => {
            await api('PATCH', `social/posts/${p.id}`, { status: 'posted', posted_at: new Date().toISOString() });
            toast('Marked as posted. Add the link and numbers later from the post.');
            draw();
          }),
        }, icon('check', 14), 'Posted');
        const snooze = h('button', {
          class: 'btn ghost sm', title: 'Move it one hour later',
          onclick: () => busy(snooze, '…', async () => {
            await api('PATCH', `social/posts/${p.id}`, { scheduled_for: new Date(Date.now() + 3600000).toISOString(), status: 'scheduled' });
            draw();
          }),
        }, '+1 h');
        return h('li', { class: `due-item pf-${p.platform}${overdue ? ' overdue' : ''}` },
          icon(PLATFORM_ICONS[p.platform], 18),
          h('div', { class: 'due-main' },
            link(`/posts/${p.id}`, { class: 'clamp-1' }, p.title || String(p.body || p.parts?.[0] || 'Post').slice(0, 90)),
            h('span', { class: `small ${overdue ? 'error-text' : 'muted'}` }, `${meta.platforms[p.platform]?.label} · ${overdue ? 'was due' : 'due'} ${timeOf(p.scheduled_for)}${p.media?.length ? ` · ${p.media.length} file${p.media.length === 1 ? '' : 's'}` : ''}`)),
          h('div', { class: 'row due-actions' },
            h('button', { class: 'btn sm', onclick: () => openInApp(p, meta) }, icon('copy', 14), 'Copy & open'),
            posted, snooze));
      })),
      q.upcoming.length ? h('p', { class: 'muted small due-next' }, `Next: ${q.upcoming.slice(0, 3).map((p) => `${meta.platforms[p.platform]?.label} at ${timeOf(p.scheduled_for)}`).join(' · ')}${q.upcoming.length > 3 ? ` and ${q.upcoming.length - 3} more` : ''} in the next 24 hours.`) : null));
  }
  // Removes itself once the page that showed the strip is gone.
  const onDue = (e) => { if (!box.isConnected) { window.removeEventListener('studio:due', onDue); return; } draw(e.detail); };
  window.addEventListener('studio:due', onDue);
  draw();
  box.cleanup = () => window.removeEventListener('studio:due', onDue);
  return box;
}
