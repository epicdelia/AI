// The notifications drawer: what her agents and automations did, and what's waiting for her
// (posts to approve, comments and DMs to answer). A bell in the sidebar with an unread count.
import { h, api, clear, toast, link, timeAgo, confirmDialog } from './core.js';
import { icon } from './icons.js';
import { PLATFORM_ICONS } from './pages/social.js';

const POLL_MS = 90000;
const TYPE_ICON = { drafted: 'bot', approved: 'check', rejected: 'x', published: 'external', failed: 'x', learned: 'sparkle', created: 'bot', comment: 'chat', dm: 'chat' };
const buttons = new Set();
let timer = null;
let last = null;

async function refresh() {
  try {
    last = await api('GET', 'agents/notifications');
    for (const b of buttons) {
      if (!b.isConnected) { buttons.delete(b); continue; }
      b.update(last);
    }
  } catch { /* offline or signed out */ }
}

/** A bell button with an unread badge; several can exist (sidebar and phone bar). */
export function notificationsButton({ withLabel = false } = {}) {
  const badge = h('span', { class: 'nf-badge', hidden: true });
  const btn = h('button', {
    class: withLabel ? 'search-launch nf-launch' : 'btn ghost sm nf-launch', title: 'Notifications', 'aria-label': 'Notifications',
    onclick: () => openDrawer(),
  }, h('span', { class: 'nf-bell' }, icon('bell', 16), badge), withLabel ? h('span', { class: 'nav-label' }, 'Notifications') : null);
  btn.update = (d) => {
    const n = d.unread + d.pending;
    badge.hidden = !n;
    badge.textContent = n > 99 ? '99+' : String(n);
    btn.setAttribute('aria-label', n ? `Notifications, ${n} new` : 'Notifications');
  };
  buttons.add(btn);
  if (last) btn.update(last);
  if (!timer) { refresh(); timer = setInterval(refresh, POLL_MS); }
  return btn;
}

export function stopNotifications() {
  clearInterval(timer);
  timer = null;
  buttons.clear();
}

/** Re-read counts now (after approving, replying, ...). */
export const refreshNotifications = refresh;

async function openDrawer() {
  document.querySelector('.nf-drawer-wrap')?.remove();
  const list = h('div', { class: 'nf-list' }, h('p', { class: 'muted small nf-empty' }, 'Loading…'));
  const close = () => { wrap.classList.remove('open'); setTimeout(() => wrap.remove(), 220); document.removeEventListener('keydown', onKey); };
  const onKey = (e) => { if (e.key === 'Escape') close(); };
  const wrap = h('div', { class: 'nf-drawer-wrap' },
    h('div', { class: 'nf-backdrop', onclick: close }),
    h('aside', { class: 'nf-drawer', role: 'dialog', 'aria-label': 'Notifications' },
      h('div', { class: 'nf-head' },
        h('h2', {}, 'Notifications'),
        h('button', {
          class: 'btn ghost sm', onclick: async () => {
            if (!(await confirmDialog('Clear all notifications?', { ok: 'Clear all' }))) return;
            await api('DELETE', 'agents/notifications');
            await draw();
          },
        }, 'Clear all'),
        h('button', { class: 'btn ghost sm', 'aria-label': 'Close', onclick: close }, icon('x', 16))),
      list));
  document.body.append(wrap);
  document.addEventListener('keydown', onKey);
  requestAnimationFrame(() => wrap.classList.add('open'));
  wrap.querySelector('.nf-head button:last-child').focus();

  async function draw() {
    const d = await api('GET', 'agents/notifications').catch((e) => { toast(e.message, 'err'); return null; });
    if (!d) return;
    clear(list);
    const todo = [
      d.pending ? link('/agents', { class: 'nf-todo', onclick: close }, icon('bot', 16), `${d.pending} post${d.pending === 1 ? '' : 's'} waiting for your approval`, icon('arrow', 14)) : null,
      d.inbox ? link('/inbox', { class: 'nf-todo', onclick: close }, icon('inbox', 16), `${d.inbox} comment${d.inbox === 1 ? '' : 's'} & DMs to answer`, icon('arrow', 14)) : null,
    ].filter(Boolean);
    if (todo.length) list.append(h('div', { class: 'nf-todos' }, todo));
    if (!d.events.length) {
      list.append(h('div', { class: 'nf-empty' }, icon('bell', 28), h('p', {}, 'Nothing yet.'), h('p', { class: 'muted small' }, 'Your agents and automations report here: posts planned, published, lessons learned, comments answered.')));
    }
    for (const e of d.events) {
      list.append(h('div', { class: `nf-item nf-${e.type}${e.read_at ? '' : ' unread'}` },
        h('span', { class: 'nf-icon' }, e.platform ? icon(PLATFORM_ICONS[e.platform], 15) : icon(TYPE_ICON[e.type] || 'bell', 15)),
        h('div', { class: 'nf-main' },
          h('p', {}, e.message),
          h('span', { class: 'muted small' }, [e.agent?.name, timeAgo(e.created_at)].filter(Boolean).join(' · ')),
          e.social_agent_id ? link(`/agents/${e.social_agent_id}`, { class: 'small', onclick: close }, 'Open agent') : null)));
    }
    if (d.unread) { await api('POST', 'agents/notifications/read').catch(() => {}); refresh(); }
  }
  await draw();
}
