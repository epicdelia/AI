// Content calendar for every network: month, week, day and list views, platform and status
// filters, event pills with a hover card (account, format · status, slide carousel, caption,
// date at time), drag to reschedule, and the drafts that still need a date.
import { h, api, busy, toast, pageHead, modal, clear, field, navigate, link } from '../core.js';
import { icon } from '../icons.js';
import { PLATFORM_ICONS, STATUS_LABEL, newPostDialog, progressChips } from './social.js';
import { drawSlide } from '../carousel.js';
import { demoControl } from '../social-tools.js';

const VIEWS = [['month', 'month'], ['week', 'week'], ['day', 'day'], ['list', 'list']];
const STATUSES = ['scheduled', 'posted', 'draft', 'idea'];
const HOUR_PX = 48;
const PREFS = 'studio-calendar';
const pad = (n) => String(n).padStart(2, '0');
const dayKey = (d) => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
const sameDay = (a, b) => dayKey(a) === dayKey(b);
const atMidnight = (d) => new Date(d.getFullYear(), d.getMonth(), d.getDate());
const addDays = (d, n) => { const x = new Date(d); x.setDate(x.getDate() + n); return x; };
const sundayOf = (d) => addDays(atMidnight(d), -d.getDay());
const hm = (d) => `${pad(d.getHours())}:${pad(d.getMinutes())}`;
const plain = (s) => String(s || '').replace(/\s+/g, ' ').trim();
const toLocalInput = (d) => `${dayKey(d)}T${hm(d)}`;

function loadPrefs() {
  try { return JSON.parse(localStorage.getItem(PREFS) || '{}'); } catch { return {}; }
}
function savePrefs(p) {
  try { localStorage.setItem(PREFS, JSON.stringify(p)); } catch { /* not remembered */ }
}

export async function renderCalendar(view) {
  const meta = await api('GET', 'social/meta');
  const prefs = loadPrefs();
  const state = {
    view: prefs.view || (window.innerWidth < 700 ? 'list' : 'month'),
    anchor: atMidnight(new Date()),
    platforms: new Set(prefs.platforms || Object.keys(meta.platforms)),
    statuses: new Set(prefs.statuses || STATUSES),
    data: null,
  };
  const persist = () => savePrefs({ view: state.view, platforms: [...state.platforms], statuses: [...state.statuses] });

  const toolbar = h('div', { class: 'fc-toolbar' });
  const filters = h('div', { class: 'fc-filters' });
  const body = h('div', { class: 'fc-body' });
  const side = h('div', { class: 'fc-side' });
  const progressBox = h('div');
  const card = h('div', { class: 'fc-card', role: 'tooltip', hidden: true });

  // ---- range for the current view ----
  function range() {
    const a = state.anchor;
    if (state.view === 'month') {
      const first = new Date(a.getFullYear(), a.getMonth(), 1);
      const start = sundayOf(first);
      const days = new Date(a.getFullYear(), a.getMonth() + 1, 0).getDate();
      const weeks = Math.ceil((first.getDay() + days) / 7);
      return [start, addDays(start, weeks * 7)];
    }
    if (state.view === 'day') return [a, addDays(a, 1)];
    const start = sundayOf(a);
    return [start, addDays(start, 7)]; // week and list
  }
  function title() {
    const [s, e] = range();
    const last = addDays(e, -1);
    if (state.view === 'month') return state.anchor.toLocaleDateString([], { month: 'long', year: 'numeric' });
    if (state.view === 'day') return state.anchor.toLocaleDateString([], { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' });
    const sameMonth = s.getMonth() === last.getMonth();
    return `${s.toLocaleDateString([], { month: 'short', day: 'numeric' })} – ${last.toLocaleDateString([], sameMonth ? { day: 'numeric', year: 'numeric' } : { month: 'short', day: 'numeric', year: 'numeric' })}`;
  }
  function shift(dir) {
    const a = state.anchor;
    state.anchor = state.view === 'month' ? new Date(a.getFullYear(), a.getMonth() + dir, 1)
      : addDays(a, dir * (state.view === 'day' ? 1 : 7));
    load();
  }

  // ---- events: her posts plus YouTube due/published dates ----
  function events() {
    const out = [];
    for (const p of state.data.posts) {
      const t = p.status === 'posted' && p.posted_at ? p.posted_at : p.scheduled_for;
      if (!t || !state.platforms.has(p.platform) || !state.statuses.has(p.status)) continue;
      out.push({ kind: 'post', post: p, platform: p.platform, at: new Date(t), allDay: false });
    }
    if (state.platforms.has('youtube')) {
      for (const y of state.data.youtube) {
        out.push({ kind: 'youtube', yt: y, platform: 'youtube', at: new Date(`${y.date}T00:00`), allDay: true });
      }
    }
    return out.sort((a, b) => (a.allDay === b.allDay ? a.at - b.at : a.allDay ? -1 : 1));
  }

  // ---- event pill + hover card ----
  const handle = (p) => (meta.platforms[p]?.handle ? `@${meta.platforms[p].handle.replace(/^@/, '')}` : meta.platforms[p]?.label || p);
  const pillTitle = (ev) => (ev.kind === 'youtube'
    ? `${ev.yt.kind === 'due' ? 'Due' : 'Published'}: ${ev.yt.title}`
    : ev.post.title || plain(ev.post.body) || plain(ev.post.parts?.[0]) || meta.platforms[ev.platform].formats[ev.post.format]?.label);

  function pill(ev, { compact = false } = {}) {
    const posted = ev.kind === 'post' && ev.post.status === 'posted';
    const el = link(
      ev.kind === 'post' ? `/posts/${ev.post.id}` : ev.yt.link,
      {
        class: `fc-event pf-${ev.platform} st-${ev.kind === 'post' ? ev.post.status : 'youtube'}${compact ? ' compact' : ''}`,
        draggable: ev.kind === 'post' && !posted ? 'true' : 'false',
        'aria-label': `${meta.platforms[ev.platform]?.label || 'YouTube'}, ${ev.allDay ? 'all day' : hm(ev.at)}: ${pillTitle(ev)}`,
      },
      h('span', { class: 'fc-event-icon' }, icon(PLATFORM_ICONS[ev.platform] || 'play', 12)),
      ev.allDay ? null : h('span', { class: 'fc-event-time' }, hm(ev.at)),
      h('span', { class: 'fc-event-title' }, pillTitle(ev)));
    el.addEventListener('mouseenter', () => showCard(ev, el));
    el.addEventListener('focus', () => showCard(ev, el));
    el.addEventListener('mouseleave', hideCardSoon);
    el.addEventListener('blur', hideCardSoon);
    if (ev.kind === 'post' && !posted) {
      el.addEventListener('dragstart', (e) => { hideCard(); e.dataTransfer.setData('text/plain', String(ev.post.id)); e.dataTransfer.effectAllowed = 'move'; });
    }
    return el;
  }

  const slideCache = new Map();
  function slidesFor(p) {
    if (!(meta.platforms[p.platform]?.formats[p.format]?.parts === 'Slides') || !p.parts?.length) return [];
    if (!slideCache.has(p.id)) {
      const parts = p.parts.filter((s) => s.trim()).slice(0, 10);
      slideCache.set(p.id, parts.map((s, i) => drawSlide(s, i, parts.length, { color: meta.brand_color, handle: meta.platforms[p.platform].handle }).toDataURL('image/jpeg', 0.72)));
    }
    return slideCache.get(p.id);
  }

  let hideTimer = 0;
  let showTimer = 0;
  function hideCardSoon() { clearTimeout(showTimer); clearTimeout(hideTimer); hideTimer = setTimeout(hideCard, 160); }
  function hideCard() { card.hidden = true; }
  card.addEventListener('mouseenter', () => clearTimeout(hideTimer));
  card.addEventListener('mouseleave', hideCardSoon);

  function showCard(ev, anchorEl) {
    clearTimeout(hideTimer);
    clearTimeout(showTimer);
    showTimer = setTimeout(() => {
      const isPost = ev.kind === 'post';
      const p = ev.post;
      const fmt = isPost ? meta.platforms[p.platform].formats[p.format]?.label : (ev.yt.kind === 'due' ? 'Long-form video' : 'Video');
      const status = isPost ? STATUS_LABEL[p.status] : (ev.yt.kind === 'due' ? 'Due' : 'Published');
      const slides = isPost ? slidesFor(p) : [];
      let idx = 0;
      const imgs = slides.map((src, i) => h('img', { src, alt: `Slide ${i + 1}`, hidden: i !== 0 }));
      const dots = slides.map((_, i) => h('button', { class: `fc-dot${i === 0 ? ' active' : ''}`, 'aria-label': `Slide ${i + 1}`, onclick: (e) => { e.preventDefault(); go(i); } }));
      const go = (i) => {
        idx = (i + slides.length) % slides.length;
        imgs.forEach((im, j) => { im.hidden = j !== idx; });
        dots.forEach((d, j) => d.classList.toggle('active', j === idx));
      };
      const caption = isPost ? (plain(p.body) || plain(p.parts?.join(' · '))) : ev.yt.title;
      clear(card).append(
        h('div', { class: 'fc-card-head' },
          h('span', { class: `fc-card-account pf-${ev.platform}` }, icon(PLATFORM_ICONS[ev.platform] || 'play', 16), isPost ? handle(p.platform) : 'YouTube'),
          h('span', { class: 'fc-card-meta' }, fmt, h('span', { class: 'fc-sep', 'aria-hidden': 'true' }, '·'), status)),
        slides.length ? h('figure', { class: 'fc-card-media' }, imgs,
          slides.length > 1 ? [
            h('button', { class: 'fc-nav prev', title: 'Previous slide', onclick: (e) => { e.preventDefault(); go(idx - 1); } }, icon('chevron-left', 16)),
            h('button', { class: 'fc-nav next', title: 'Next slide', onclick: (e) => { e.preventDefault(); go(idx + 1); } }, icon('chevron-right', 16)),
            h('div', { class: 'fc-dots' }, dots),
          ] : null) : null,
        caption ? h('p', { class: 'fc-card-text' }, caption.length > 260 ? `${caption.slice(0, 260)}…` : caption) : null,
        isPost && p.engagement ? h('p', { class: 'fc-card-stats' }, `${p.engagement.total.toLocaleString()} engagements${p.engagement.rate !== null ? ` · ${p.engagement.rate}%` : ''}`) : null,
        h('p', { class: 'fc-card-date' }, ev.allDay ? dayKey(ev.at) : `${dayKey(ev.at)} at ${hm(ev.at)}`));
      card.hidden = false;
      const r = anchorEl.getBoundingClientRect();
      const w = Math.min(400, window.innerWidth - 30);
      const left = Math.min(window.innerWidth - w - 15, Math.max(15, r.left));
      card.style.width = `${w}px`;
      card.style.left = `${left}px`;
      const below = r.bottom + 8;
      const ch = card.offsetHeight;
      card.style.top = `${below + ch > window.innerHeight - 10 && r.top - ch - 8 > 10 ? r.top - ch - 8 : below}px`;
    }, 180);
  }

  // ---- scheduling ----
  async function reschedule(postId, day, time = null) {
    const p = [...state.data.posts, ...state.data.unscheduled].find((x) => x.id === postId);
    if (!p) return;
    const old = p.scheduled_for ? new Date(p.scheduled_for) : null;
    const at = new Date(day);
    if (time) at.setHours(...time.split(':').map(Number), 0, 0);
    else at.setHours(old ? old.getHours() : 9, old ? old.getMinutes() : 0, 0, 0);
    await api('PATCH', `social/posts/${postId}`, { scheduled_for: at.toISOString(), status: p.status === 'posted' ? 'posted' : 'scheduled' });
    toast(`Scheduled for ${at.toLocaleString([], { weekday: 'short', day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' })}.`);
    await load();
  }
  function dropTarget(el, getDayAndTime) {
    el.addEventListener('dragover', (e) => { e.preventDefault(); el.classList.add('fc-drop'); });
    el.addEventListener('dragleave', () => el.classList.remove('fc-drop'));
    el.addEventListener('drop', (e) => {
      e.preventDefault();
      el.classList.remove('fc-drop');
      const id = Number(e.dataTransfer.getData('text/plain'));
      const [day, time] = getDayAndTime(e);
      if (id) reschedule(id, day, time).catch((err) => toast(err.message, 'err'));
    });
  }
  function scheduleDialog(post) {
    return modal('Schedule', (close) => {
      const at = h('input', { type: 'datetime-local', required: true, value: post.scheduled_for ? toLocalInput(new Date(post.scheduled_for)) : `${dayKey(new Date())}T09:00` });
      const form = h('form', {
        onsubmit: (e) => {
          e.preventDefault();
          busy(form.querySelector('[type=submit]'), 'Saving…', async () => {
            const [d, t] = at.value.split('T');
            await reschedule(post.id, new Date(`${d}T00:00`), t);
            close(true);
          });
        },
      }, field('When', at), h('div', { class: 'row', style: { justifyContent: 'flex-end' } }, h('button', { type: 'submit', class: 'btn primary' }, 'Schedule')));
      return form;
    });
  }
  const newOn = (d, time = '09:00') => newPostDialog(null, { scheduled_for: new Date(`${dayKey(d)}T${time}`).toISOString(), status: 'scheduled' }).catch((e) => toast(e.message, 'err'));

  // ---- views ----
  function monthView() {
    const [start, end] = range();
    const evs = events();
    const today = new Date();
    const month = state.anchor.getMonth();
    const grid = h('div', { class: 'fc-month', role: 'grid', 'aria-label': title() },
      h('div', { class: 'fc-month-head', role: 'row' }, [0, 1, 2, 3, 4, 5, 6].map((i) => h('div', { role: 'columnheader', class: 'fc-col-head' },
        addDays(start, i).toLocaleDateString([], { weekday: 'short' })))));
    for (let w = start; w < end; w = addDays(w, 7)) {
      const row = h('div', { class: 'fc-week', role: 'row' });
      for (let i = 0; i < 7; i++) {
        const d = addDays(w, i);
        const dayEvs = evs.filter((ev) => sameDay(ev.at, d));
        const max = 3;
        const cell = h('div', {
          role: 'gridcell',
          class: `fc-day${d.getMonth() !== month ? ' fc-other' : ''}${sameDay(d, today) ? ' fc-today' : ''}${d < atMidnight(today) ? ' fc-past' : ''}`,
          'aria-label': d.toLocaleDateString([], { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' }),
        },
        h('div', { class: 'fc-day-top' },
          h('button', { class: 'fc-day-number', title: 'Open this day', onclick: () => { state.anchor = d; state.view = 'day'; persist(); load(); } }, d.getDate()),
          h('button', { class: 'fc-add', title: 'New post on this day', 'aria-label': 'New post on this day', onclick: () => newOn(d) }, icon('plus', 13))),
        h('div', { class: 'fc-day-events' },
          dayEvs.slice(0, max).map((ev) => pill(ev)),
          dayEvs.length > max ? h('button', { class: 'fc-more', onclick: () => { state.anchor = d; state.view = 'day'; persist(); load(); } }, `+${dayEvs.length - max} more`) : null));
        dropTarget(cell, () => [d, null]);
        row.append(cell);
      }
      grid.append(row);
    }
    return grid;
  }

  function timeGridView(days) {
    const evs = events();
    const today = new Date();
    const cols = Array.from({ length: days }, (_, i) => addDays(range()[0], i));
    const head = h('div', { class: 'fc-tg-head', style: { gridTemplateColumns: `56px repeat(${days}, minmax(0, 1fr))` } },
      h('div'),
      cols.map((d) => h('button', {
        class: `fc-tg-day${sameDay(d, today) ? ' fc-today' : ''}`,
        onclick: () => { state.anchor = d; state.view = 'day'; persist(); load(); },
      }, h('span', { class: 'muted small' }, d.toLocaleDateString([], { weekday: 'short' })), h('strong', {}, d.getDate()))));
    const allDay = h('div', { class: 'fc-tg-allday', style: { gridTemplateColumns: `56px repeat(${days}, minmax(0, 1fr))` } },
      h('div', { class: 'fc-tg-label muted small' }, 'all-day'),
      cols.map((d) => h('div', { class: 'fc-tg-allday-cell' }, evs.filter((ev) => ev.allDay && sameDay(ev.at, d)).map((ev) => pill(ev, { compact: true })))));

    const hours = h('div', { class: 'fc-tg-hours' }, Array.from({ length: 24 }, (_, hr) => h('div', { class: 'fc-tg-hour muted small', style: { height: `${HOUR_PX}px` } }, hr ? `${pad(hr)}:00` : '')));
    const columns = cols.map((d) => {
      const col = h('div', { class: `fc-tg-col${sameDay(d, today) ? ' fc-today' : ''}`, style: { height: `${HOUR_PX * 24}px` } });
      // 30-minute slots: drop targets and click-to-create.
      for (let s = 0; s < 48; s++) {
        const slot = h('div', { class: `fc-tg-slot${s % 2 ? ' half' : ''}`, style: { top: `${(s * HOUR_PX) / 2}px`, height: `${HOUR_PX / 2}px` } });
        const time = `${pad(Math.floor(s / 2))}:${s % 2 ? '30' : '00'}`;
        slot.addEventListener('dblclick', () => newOn(d, time));
        dropTarget(slot, () => [d, time]);
        col.append(slot);
      }
      // Events side by side when they share a half hour.
      const dayEvs = evs.filter((ev) => !ev.allDay && sameDay(ev.at, d));
      const buckets = {};
      dayEvs.forEach((ev) => { (buckets[Math.floor((ev.at.getHours() * 60 + ev.at.getMinutes()) / 30)] ||= []).push(ev); });
      for (const [slot, list] of Object.entries(buckets)) {
        list.forEach((ev, i) => {
          const top = ((ev.at.getHours() * 60 + ev.at.getMinutes()) / 60) * HOUR_PX;
          const el = pill(ev, { compact: true });
          el.classList.add('fc-tg-event');
          Object.assign(el.style, { top: `${top}px`, left: `calc(${(i / list.length) * 100}% + 2px)`, width: `calc(${100 / list.length}% - 4px)` });
          el.dataset.slot = slot;
          col.append(el);
        });
      }
      if (sameDay(d, today)) {
        col.append(h('div', { class: 'fc-now', style: { top: `${((today.getHours() * 60 + today.getMinutes()) / 60) * HOUR_PX}px` }, 'aria-hidden': 'true' }));
      }
      return col;
    });
    const scroller = h('div', { class: 'fc-tg-scroll' },
      h('div', { class: 'fc-tg-grid', style: { gridTemplateColumns: `56px repeat(${days}, minmax(0, 1fr))` } }, hours, columns));
    requestAnimationFrame(() => { scroller.scrollTop = 7 * HOUR_PX; });
    return h('div', { class: 'fc-timegrid' }, head, allDay, scroller);
  }

  function listView() {
    const [start, end] = range();
    const evs = events();
    const groups = [];
    for (let d = start; d < end; d = addDays(d, 1)) {
      const dayEvs = evs.filter((ev) => sameDay(ev.at, d));
      if (dayEvs.length) groups.push([d, dayEvs]);
    }
    if (!groups.length) return h('div', { class: 'fc-list-empty muted' }, 'Nothing planned this week. Drag a draft onto the calendar, or add a post.');
    return h('div', { class: 'fc-list' }, groups.map(([d, list]) => h('section', { class: 'fc-list-day' },
      h('h3', { class: `fc-list-head${sameDay(d, new Date()) ? ' fc-today' : ''}` },
        h('span', {}, d.toLocaleDateString([], { weekday: 'long' })), h('span', { class: 'muted' }, d.toLocaleDateString([], { month: 'long', day: 'numeric', year: 'numeric' }))),
      h('ul', {}, list.map((ev) => {
        const isPost = ev.kind === 'post';
        return h('li', { class: `fc-list-row pf-${ev.platform}` },
          h('span', { class: 'fc-list-time' }, ev.allDay ? 'all-day' : hm(ev.at)),
          h('span', { class: 'fc-list-dot', 'aria-hidden': 'true' }),
          h('span', { class: 'fc-list-main' }, pill(ev)),
          h('span', { class: 'muted small fc-list-meta' },
            isPost ? `${meta.platforms[ev.platform].formats[ev.post.format]?.label} · ${STATUS_LABEL[ev.post.status]}` : (ev.yt.kind === 'due' ? 'Due' : 'Published')));
      })))));
  }

  // ---- chrome ----
  function drawToolbar() {
    const [s, e] = range();
    const now = new Date();
    const showingToday = now >= s && now < e;
    clear(toolbar).append(
      h('div', { class: 'fc-chunk' }, h('div', { class: 'fc-btn-group', role: 'group', 'aria-label': 'View' },
        VIEWS.map(([key, label]) => h('button', {
          class: `fc-btn${state.view === key ? ' active' : ''}`, 'aria-pressed': String(state.view === key), title: `${label} view`,
          onclick: () => { state.view = key; persist(); load(); },
        }, label)))),
      h('div', { class: 'fc-chunk' }, h('h2', { class: 'fc-title' }, title())),
      h('div', { class: 'fc-chunk' },
        h('button', { class: 'fc-btn', disabled: showingToday, title: state.view === 'month' ? 'This month' : state.view === 'day' ? 'Today' : 'This week', onclick: () => { state.anchor = atMidnight(new Date()); load(); } }, 'today'),
        h('div', { class: 'fc-btn-group' },
          h('button', { class: 'fc-btn', title: `Previous ${state.view === 'list' ? 'week' : state.view}`, onclick: () => shift(-1) }, icon('chevron-left', 16)),
          h('button', { class: 'fc-btn', title: `Next ${state.view === 'list' ? 'week' : state.view}`, onclick: () => shift(1) }, icon('chevron-right', 16)))));
  }

  function drawFilters() {
    const counts = {};
    for (const p of state.data.posts) counts[p.platform] = (counts[p.platform] || 0) + 1;
    counts.youtube = (counts.youtube || 0) + state.data.youtube.length;
    const toggle = (set, key) => { if (set.has(key)) set.delete(key); else set.add(key); persist(); draw(); };
    clear(filters).append(
      h('div', { class: 'fc-filter-group', role: 'group', 'aria-label': 'Platforms' },
        Object.entries(meta.platforms).map(([k, p]) => h('button', {
          class: `fc-filter pf-${k}${state.platforms.has(k) ? ' on' : ''}`, 'aria-pressed': String(state.platforms.has(k)),
          onclick: () => toggle(state.platforms, k),
        }, icon(PLATFORM_ICONS[k], 13), p.label, counts[k] ? h('span', { class: 'fc-count' }, counts[k]) : null))),
      h('div', { class: 'fc-filter-group', role: 'group', 'aria-label': 'Status' },
        STATUSES.map((s) => h('button', {
          class: `fc-filter st-${s}${state.statuses.has(s) ? ' on' : ''}`, 'aria-pressed': String(state.statuses.has(s)),
          onclick: () => toggle(state.statuses, s),
        }, h('span', { class: `fc-status-dot st-${s}`, 'aria-hidden': 'true' }), STATUS_LABEL[s]))));
  }

  function drawSide() {
    const list = state.data.unscheduled.filter((p) => state.platforms.has(p.platform));
    clear(side).append(h('div', { class: 'card' },
      h('div', { class: 'card-header' }, h('h3', {}, 'Not scheduled yet'),
        h('button', { class: 'btn ghost sm', onclick: () => newPostDialog().catch((e) => toast(e.message, 'err')) }, icon('plus', 15), 'New')),
      h('p', { class: 'muted small' }, 'Drag one onto a day or a time, or press Schedule.'),
      list.length ? h('div', { class: 'fc-unscheduled' }, list.map((p) => h('div', { class: 'fc-u' },
        pill({ kind: 'post', post: p, platform: p.platform, at: new Date(p.updated_at), allDay: true }),
        h('button', { class: 'btn ghost sm', onclick: () => scheduleDialog(p).catch((e) => toast(e.message, 'err')) }, 'Schedule'))))
        : h('p', { class: 'muted small' }, 'All caught up. Repurpose a script for a fresh batch.')));
  }

  function draw() {
    hideCard();
    drawToolbar();
    drawFilters();
    drawSide();
    clear(body).append(state.view === 'month' ? monthView() : state.view === 'week' ? timeGridView(7) : state.view === 'day' ? timeGridView(1) : listView());
    clear(progressBox).append(progressChips(state.data.progress) || h('p', { class: 'muted small' }, 'Set weekly targets per platform in Settings → Social to track them here.'));
  }

  async function load() {
    const [from, to] = range();
    state.data = await api('GET', `social/calendar?from=${encodeURIComponent(from.toISOString())}&to=${encodeURIComponent(to.toISOString())}`);
    slideCache.clear();
    draw();
  }
  await load();

  const onScroll = () => hideCard();
  window.addEventListener('scroll', onScroll, true);
  view.append(
    pageHead('Calendar', 'Everything you’re publishing, on every platform. YouTube dates come from your weekly plan and scripts.',
      meta.demo ? null : demoControl(0),
      link('/repurpose', { class: 'btn' }, icon('repeat', 16), 'Repurpose'),
      h('button', { class: 'btn primary', onclick: () => newPostDialog().catch((e) => toast(e.message, 'err')) }, icon('plus', 16), 'New post')),
    meta.demo ? demoControl(meta.demo) : null,
    progressBox,
    h('div', { class: 'fc-layout' }, h('div', { class: 'fc-main card' }, toolbar, filters, body), side),
    card);
  return () => { window.removeEventListener('scroll', onScroll, true); card.remove(); };
}
