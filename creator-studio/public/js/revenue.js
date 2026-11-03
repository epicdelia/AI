// The money cards: revenue at a glance (dashboard), the website funnel, which content brings
// leads and revenue, tracked links, and booking hours (Website page). All from her own data.
// Charts: single-series (no legend), labelled bars with a tooltip per mark, and a table.
import { h, api, busy, toast, clear, link, compact, field, copyText, fmtDay } from './core.js';
import { icon } from './icons.js';
import { seriesChart, tooltipLayer } from './pages/analytics.js';
import { SOURCE_ICONS } from './deal-tools.js';
import { skLines } from './skeleton.js';

const usd = (n) => `$${Math.round(Number(n) || 0).toLocaleString()}`;
const pct = (n) => (n === null || n === undefined ? '–' : `${n}%`);

function stat(num, label, tone, to) {
  const body = [h('div', { class: 'num' }, num), h('div', { class: 'lbl' }, label)];
  return to ? link(to, { class: `card stat-box ${tone || ''}` }, body) : h('div', { class: `card stat-box ${tone || ''}` }, body);
}

function dayToggle(options, current, onPick) {
  const btns = options.map((n) => h('button', {
    class: `btn sm${n === current ? ' primary' : ''}`,
    onclick: () => { btns.forEach((b, i) => b.classList.toggle('primary', options[i] === n)); onPick(n); },
  }, n === 365 ? '1 year' : `${n} days`));
  return h('div', { class: 'row', role: 'group', 'aria-label': 'Period' }, btns);
}

/** Dashboard: paid this month, weighted pipeline, follow-ups, build slots, calls this week. */
export function revenueCard(rev) {
  if (!rev) return null;
  const f = rev.funnel_30d || {};
  const change = rev.paid_last_month ? Math.round(((rev.paid_this_month - rev.paid_last_month) / rev.paid_last_month) * 100) : null;
  return h('div', { class: 'card revenue-card' },
    h('div', { class: 'card-header' },
      h('div', {},
        h('h2', {}, 'Revenue'),
        h('p', { class: 'muted small', style: { margin: '2px 0 0' } }, 'From Deals: website clients and brand deals')),
      h('div', { class: 'row' },
        link('/website', { class: 'btn ghost sm' }, 'Website funnel'),
        link('/deals', { class: 'btn ghost sm' }, 'Open deals', icon('arrow', 14)))),
    h('div', { class: 'grid grid-4' },
      stat(usd(rev.paid_this_month), change === null ? 'paid this month' : `paid this month · ${change >= 0 ? '+' : ''}${change}% vs last`, 'tone-good'),
      stat(usd(rev.weighted), `likely from ${usd(rev.pipeline)} open (${rev.open_deals} deals)`, 'tone-accent', '/deals'),
      stat(rev.follow_ups, rev.follow_ups === 1 ? 'deal needs a follow-up' : 'deals need a follow-up', rev.follow_ups ? 'tone-warn' : '', '/deals'),
      rev.mrr ? stat(usd(rev.mrr), `recurring a month · ${rev.retainers} retainer${rev.retainers === 1 ? '' : 's'}`, 'tone-violet') : stat(rev.capacity?.show ? rev.capacity.open : '–', rev.capacity?.show ? `build slot${rev.capacity.open === 1 ? '' : 's'} open of ${rev.capacity.capacity}` : 'availability hidden', 'tone-violet')),
    h('p', { class: 'muted small', style: { margin: '10px 0 0' } },
      `Website, last 30 days: ${compact(f.visit || 0)} visits → ${f.lead || 0} request${f.lead === 1 ? '' : 's'} → ${f.paid || 0} paid${rev.subscribers_30d ? ` · ${rev.subscribers_30d} new subscriber${rev.subscribers_30d === 1 ? '' : 's'}` : ''}. `,
      h('span', { title: 'Inquiry 10%, pitching 25%, negotiating 50%, contracted and later 90%+: a rule of thumb, not a forecast from your history.' }, '“Likely” weights each deal by its stage.')),
    rev.meetings?.length ? h('ul', { class: 'mini-list', style: { marginTop: '8px' } }, rev.meetings.map((m) => h('li', {},
      h('span', {}, icon('calendar', 13), ` Intro call: ${m.brand_name}${m.contact_name ? ` (${m.contact_name})` : ''}`),
      h('span', { class: 'row', style: { gap: '6px' } }, h('span', { class: 'muted small' }, new Date(m.at).toLocaleString([], { weekday: 'short', hour: '2-digit', minute: '2-digit' })),
        link(`/deals?prep=${m.id}`, { class: 'btn ghost sm' }, 'Prep'))))) : null);
}

/** Website funnel: visits → book/buy click → form started → request → paid, with sources. */
export function funnelCard() {
  let days = 30;
  const body = h('div', {}, skLines(4, 'funnel'));
  const card = h('div', { class: 'card funnel-card' },
    h('div', { class: 'card-header' },
      h('div', {}, h('h2', {}, 'Website funnel'), h('p', { class: 'muted small', style: { margin: '2px 0 0' } }, 'Counts only: no cookies, nothing about who visited')),
      dayToggle([7, 30, 90], days, (n) => { days = n; draw(); })),
    body);
  async function draw() {
    const f = await api('GET', `landing/funnel?days=${days}`).catch(() => null);
    if (!f) { clear(body).append(h('p', { class: 'error-text' }, 'Couldn’t load the funnel.')); return; }
    const max = Math.max(1, ...f.steps.map((s) => s.count));
    clear(body).append(
      f.tracking_since ? null : h('p', { class: 'muted small' }, 'Tracking starts with the next visit to your landing page. Requests and payments below are from Deals.'),
      h('ol', { class: 'funnel-steps' }, f.steps.map((s) => h('li', {},
        h('div', { class: 'row-between' },
          h('span', {}, s.label),
          h('span', {}, h('strong', {}, compact(s.count)), s.of_previous !== null ? h('span', { class: 'muted small' }, ` · ${pct(s.of_previous)} of the step before`) : null)),
        h('div', { class: 'funnel-bar', 'data-tip': `${s.label}: ${s.count.toLocaleString()}${s.of_visits !== null ? ` (${s.of_visits}% of visits)` : ''}`, tabindex: '0' },
          h('span', { style: { width: `${s.count ? Math.max(1.5, (s.count / max) * 100) : 0}%` } }))))),
      f.series.some((d) => d.visits) ? h('div', {}, h('h3', { class: 'small', style: { margin: '16px 0 4px' } }, 'Visits per day'),
        seriesChart(f.series.map((d) => d.day), f.series.map((d) => d.visits), { unit: ' visits' })) : null,
      f.by_source.length ? h('div', { class: 'table-wrap', style: { marginTop: '12px' } }, h('table', {},
        h('thead', {}, h('tr', {}, h('th', {}, 'Source'), h('th', { class: 'num' }, 'Visits'), h('th', { class: 'num' }, 'Requests'), h('th', { class: 'num' }, 'Visit → request'), h('th', { class: 'num' }, 'Paid'))),
        h('tbody', {}, f.by_source.map((r) => h('tr', {},
          h('td', {}, icon(SOURCE_ICONS[r.source] || 'globe', 13), ` ${r.label}`),
          h('td', { class: 'num' }, compact(r.visits)), h('td', { class: 'num' }, r.leads), h('td', { class: 'num' }, pct(r.conversion)),
          h('td', { class: 'num' }, r.paid ? `${r.paid} · ${usd(r.revenue)}` : '0')))))) : null);
  }
  card.append(tooltipLayer(card));
  draw();
  return card;
}

/** Analytics: which channels and which videos/posts brought website leads and money. */
export function attributionCard() {
  let days = 90;
  const body = h('div', {}, skLines(4, 'revenue by content'));
  const card = h('div', { class: 'card attribution-card' },
    h('div', { class: 'card-header' },
      h('div', {}, h('h2', {}, 'Revenue by content'), h('p', { class: 'muted small', style: { margin: '2px 0 0' } }, 'Website leads and what they paid, by where they came from')),
      dayToggle([30, 90, 365], days, (n) => { days = n; draw(); })),
    body);
  async function draw() {
    const r = await api('GET', `deals/attribution?days=${days}`).catch(() => null);
    if (!r) { clear(body).append(h('p', { class: 'error-text' }, 'Couldn’t load it.')); return; }
    if (!r.totals.leads) {
      clear(body).append(h('p', { class: 'muted' }, 'No website leads in this period yet. Leads from the booking and checkout forms are tagged with where they came from; links with ?ref=yt-<video id> (Website → Tracked links) also say which video.'));
      return;
    }
    clear(body).append(
      h('div', { class: 'grid grid-3' },
        stat(r.totals.leads, r.totals.leads === 1 ? 'website lead' : 'website leads', 'tone-accent'),
        stat(usd(r.totals.pipeline), 'still open', 'tone-warn'),
        stat(usd(r.totals.paid), `paid · ${r.totals.won} won`, 'tone-good')),
      h('div', { class: 'table-wrap', style: { marginTop: '12px' } }, h('table', {},
        h('thead', {}, h('tr', {}, h('th', {}, 'Channel'), h('th', { class: 'num' }, 'Leads'), h('th', { class: 'num' }, 'Open'), h('th', { class: 'num' }, 'Paid'))),
        h('tbody', {}, r.channels.map((c) => h('tr', {},
          h('td', {}, icon(SOURCE_ICONS[c.source] || 'globe', 13), ` ${c.label}`),
          h('td', { class: 'num' }, c.leads), h('td', { class: 'num' }, usd(c.pipeline)), h('td', { class: 'num' }, h('strong', {}, usd(c.paid)))))))),
      r.items.length ? h('div', {}, h('h3', { class: 'small', style: { margin: '16px 0 6px' } }, 'Videos and posts that brought leads'),
        h('ul', { class: 'mini-list an-top' }, r.items.map((it) => h('li', {},
          h('span', { class: 'row' }, icon(SOURCE_ICONS[it.platform] || 'globe', 13),
            it.link ? link(it.link, { class: 'clamp-1' }, it.title) : it.url ? h('a', { href: it.url, target: '_blank', rel: 'noopener', class: 'clamp-1' }, it.title) : h('span', { class: 'clamp-1' }, it.title)),
          h('span', { class: 'muted small nowrap' }, `${it.leads} lead${it.leads === 1 ? '' : 's'} · ${usd(it.paid)} paid`))))) : null,
      r.untracked ? h('p', { class: 'muted small' }, `${r.untracked} lead${r.untracked === 1 ? '' : 's'} came without a tracked link: add ?ref= links to your descriptions and posts to see which content they came from.`) : null);
  }
  draw();
  return card;
}

/** Her site link with ?ref=, for a video description, a post or her bio. */
export function trackedLinkCard() {
  const channel = h('select', { 'aria-label': 'Where you’ll share it' },
    [['yt', 'A YouTube video'], ['li', 'LinkedIn'], ['ig', 'Instagram'], ['x', 'X'], ['tt', 'TikTok'], ['th', 'Threads'], ['bio', 'Link in bio'], ['nl', 'Newsletter'], ['email', 'Email signature']]
      .map(([v, l]) => h('option', { value: v }, l)));
  const id = h('input', { type: 'text', placeholder: 'Video ID or URL (optional)', 'aria-label': 'Video ID or URL' });
  const out = h('div', { class: 'row', style: { gap: '8px', marginTop: '10px' }, hidden: true });
  const make = h('button', {
    class: 'btn primary',
    onclick: (e) => busy(e.currentTarget, '…', async () => {
      const raw = id.value.trim();
      const vid = raw.match(/(?:youtu\.be\/|v=|\/shorts\/|\/live\/)([\w-]{11})/)?.[1] || (/^[\w-]{11}$/.test(raw) ? raw : null);
      const ref = channel.value === 'yt' && vid ? `yt-${vid}` : channel.value;
      const r = await api('POST', 'landing/tracked-link', { ref });
      const input = h('input', { type: 'text', readonly: true, value: r.url, style: { flex: 1 }, onfocus: (ev) => ev.target.select() });
      clear(out).append(input, h('button', { class: 'btn', onclick: () => copyText(r.url, 'Tracked link copied.') }, icon('copy', 14), 'Copy'));
      out.hidden = false;
      await copyText(r.url, 'Tracked link copied.');
    }),
  }, icon('link', 14), 'Make link');
  return h('div', { class: 'card' },
    h('h2', {}, 'Tracked links'),
    h('p', { class: 'muted small' }, 'Use these instead of your plain website address. A lead that arrives through one is tagged with where it came from (and which video), so Revenue by content can tell you what pays.'),
    h('div', { class: 'row', style: { gap: '8px', flexWrap: 'wrap' } }, channel, id, make),
    out);
}

/** Working hours for real intro-call slots on the landing page. */
export function bookingHoursCard() {
  const box = h('div', { class: 'card' }, skLines(3, 'booking hours'));
  const DAYS = [[1, 'Mon'], [2, 'Tue'], [3, 'Wed'], [4, 'Thu'], [5, 'Fri'], [6, 'Sat'], [7, 'Sun']];
  async function draw(r) {
    const c = r.config;
    const enabled = h('input', { type: 'checkbox', checked: c.enabled });
    const dayBoxes = DAYS.map(([n, l]) => h('label', { class: 'chip-check' }, h('input', { type: 'checkbox', value: n, checked: c.days.includes(n) }), l));
    const start = h('input', { type: 'time', value: c.start, step: 900 });
    const end = h('input', { type: 'time', value: c.end, step: 900 });
    const minutes = h('select', {}, [15, 20, 30, 45, 60].map((m) => h('option', { value: m, selected: m === c.minutes }, `${m} min`)));
    const notice = h('input', { type: 'number', min: 0, max: 168, value: c.notice_hours });
    const horizon = h('input', { type: 'number', min: 1, max: 60, value: c.horizon_days });
    clear(box).append(
      h('div', { class: 'card-header' }, h('h2', {}, 'Booking hours'),
        h('label', { class: 'row small' }, enabled, ' Show real open times on the booking form')),
      h('p', { class: 'muted small' }, `Visitors pick an open slot (shown in their own timezone) instead of suggesting a time. Booked calls block their slot. Times are in your timezone (${c.timezone}). Nothing is sent: you send the invite.`),
      h('div', { class: 'row', style: { gap: '6px', flexWrap: 'wrap', margin: '8px 0' } }, dayBoxes),
      h('div', { class: 'grid grid-3' }, field('From', start), field('Until', end), field('Call length', minutes)),
      h('div', { class: 'grid grid-2' }, field('Notice (hours)', notice, 'earliest booking from now'), field('Days ahead', horizon)),
      r.preview?.length ? h('p', { class: 'muted small' }, 'Next open: ', r.preview.map((d) => `${fmtDay(d.date, { weekday: 'short', day: 'numeric', month: 'short' })} (${d.slots.length})`).join(', ')) : (c.enabled ? h('p', { class: 'muted small' }, 'No open slots with these settings.') : null),
      h('div', { class: 'row', style: { justifyContent: 'flex-end' } },
        h('button', {
          class: 'btn primary',
          onclick: (e) => busy(e.currentTarget, 'Saving…', async () => {
            const saved = await api('PUT', 'landing/booking-hours', {
              enabled: enabled.checked, days: dayBoxes.map((l) => l.querySelector('input')).filter((i) => i.checked).map((i) => Number(i.value)),
              start: start.value, end: end.value, minutes: Number(minutes.value), notice_hours: Number(notice.value), horizon_days: Number(horizon.value),
            });
            toast(saved.message);
            draw(saved);
          }),
        }, 'Save')));
  }
  api('GET', 'landing/booking-hours').then(draw).catch((e) => clear(box).append(h('p', { class: 'error-text' }, e.message)));
  return box;
}
