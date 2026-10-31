// Dashboard: this week's commitment, scoreboard, setup checklist and the latest pipeline items.
import { h, api, link, pill, fmtDay, daysUntil, compact, timeAgo, emptyState } from '../core.js';
import { icon } from '../icons.js';
import { weekDots } from './goals.js';
import { loveCard } from '../love.js';
import { socialStrip } from './social.js';
import { homeComposer } from '../composer.js';
import { dueStrip } from '../queue.js';
import { briefCard } from '../growth.js';
import { arrangeDashboard, customiseDashboard, prefs } from '../prefs.js';
import { loveExtras } from './love.js';

const SETUP_LINKS = {
  channel_id: '/settings',
  youtube_api_key: '/settings',
  llm_api_key: '/settings',
  channels: '/breakouts',
  goals: '/goals',
};

function greeting() {
  const hr = new Date().getHours();
  if (hr < 5) return 'Up late';
  if (hr < 12) return 'Good morning';
  if (hr < 18) return 'Good afternoon';
  return 'Good evening';
}

function heroCard(d) {
  const w = d.current_week;
  const total = d.weeks.length;
  let line;
  let sub;
  if (!w) {
    line = 'No weekly plan yet.';
    sub = 'Set a 12-week plan and the mentor keeps score for you.';
  } else {
    const left = daysUntil(w.due_date);
    const when = left > 1 ? `${left} days left` : left === 1 ? 'due tomorrow' : left === 0 ? 'due today' : `${-left} days past due`;
    line = `Week ${w.number} of ${total} · ${when}`;
    sub = w.status === 'shipped'
      ? `Shipped: “${w.video_title || 'this week’s video'}”. Nice.`
      : w.video_title
        ? `This week’s video: “${w.video_title}”.`
        : 'This week’s video isn’t picked yet. That’s the first job.';
  }

  return h('div', { class: 'card hero-card' },
    h('div', { class: 'hero-inner' },
      h('div', {},
        h('p', { class: 'hero-eyebrow' }, `${greeting()}, ${(window.__studioName || d.creator_name || '').split(' ')[0]}`),
        h('h1', {}, line),
        prefs().motto ? h('p', { class: 'hero-motto' }, `“${prefs().motto}”`) : null,
        h('p', { class: 'hero-sub' }, sub),
        h('div', { class: 'row', style: { marginTop: '14px' } },
          link('/flow', { class: 'btn on-hero' }, icon('mic'), 'Dictate an idea'),
          link('/ideas', { class: 'btn on-hero ghost-light' }, icon('sparkle'), 'Get fresh ideas'),
          link('/goals', { class: 'btn on-hero ghost-light' }, icon('target'), w ? 'Scoreboard' : 'Plan my weeks'))),
      w ? h('div', { class: 'hero-week' },
        h('div', { class: 'hero-week-num' }, w.number),
        h('div', { class: 'hero-week-lbl' }, `due ${fmtDay(w.due_date, { weekday: 'short', month: 'short', day: 'numeric' })}`),
        pill(w.status)) : null));
}

function statBox(num, label, tone) {
  return h('div', { class: `card stat-box ${tone || ''}` }, h('div', { class: 'num' }, num), h('div', { class: 'lbl' }, label));
}

function setupCard(checks) {
  const todo = checks.filter((c) => !c.done);
  if (!todo.length) return null;
  const done = checks.length - todo.length;
  return h('div', { class: 'card setup-card' },
    h('div', { class: 'card-header' },
      h('h2', {}, 'Finish setting up'),
      h('span', { class: 'muted' }, `${done} of ${checks.length} done`)),
    h('div', { class: 'progress' }, h('div', { class: 'progress-bar', style: { width: `${(done / checks.length) * 100}%` } })),
    h('ul', { class: 'checklist' }, checks.map((c) => h('li', { class: c.done ? 'done' : '' },
      h('span', { class: 'check' }, c.done ? icon('check', 14) : ''),
      c.done ? h('span', {}, c.label) : link(SETUP_LINKS[c.key] || '/settings', {}, c.label)))));
}

/** The one thing to do next for this week's video (NextStep on the server). */
function nextStepCard(step) {
  if (!step) return null;
  return h('div', { class: 'card next-step' },
    h('div', { class: 'next-step-body' },
      h('p', { class: 'next-step-eyebrow' }, 'Next step'),
      h('h2', {}, step.title),
      step.detail ? h('p', { class: 'muted' }, step.detail) : null),
    link(step.link, { class: 'btn primary' }, step.action, icon('arrow', 15)));
}

/** Intro-call requests and checkouts from the public landing page, waiting in Deals. */
function inboundCard(inb) {
  if (!inb || (!inb.new_inquiries && !inb.awaiting_payment && !inb.paid_this_month)) return null;
  const usd = (n) => `$${Math.round(Number(n) || 0).toLocaleString()}`;
  return h('div', { class: 'card' },
    h('div', { class: 'card-header' },
      h('h2', {}, 'From your website'),
      link('/deals', { class: 'btn ghost sm' }, 'Open deals', icon('arrow', 14))),
    h('div', { class: 'grid grid-3' },
      statBox(inb.new_inquiries, inb.new_inquiries === 1 ? 'new inquiry' : 'new inquiries', 'tone-accent'),
      statBox(usd(inb.awaiting_payment), 'agreed, not paid yet', 'tone-warn'),
      statBox(usd(inb.paid_this_month), 'paid this month', 'tone-good')),
    inb.by_service?.length ? h('div', { class: 'row', style: { flexWrap: 'wrap', gap: '6px', marginTop: '12px' }, 'aria-label': 'Open inquiries by service' },
      h('span', { class: 'muted small' }, 'Asked about:'),
      inb.by_service.map((s) => link('/deals', { class: 'pill' }, `${s.service} · ${s.count}`))) : null,
    inb.latest.length ? h('ul', { class: 'item-list', style: { marginTop: '12px' } }, inb.latest.map((d) => h('li', {},
      link('/deals', { class: 'item-main' },
        h('strong', {}, d.brand_name),
        h('div', { class: 'muted clamp-1' }, `${d.deliverables?.[0]?.service || d.deliverables?.[0]?.format || 'Inquiry'} · ${d.contact_email} · ${timeAgo(d.created_at)}`)),
      h('span', { class: 'score' }, usd(d.rate))))) : null);
}

function listCard(title, to, items, renderItem, empty) {
  return h('div', { class: 'card' },
    h('div', { class: 'card-header' }, h('h2', {}, title), link(to, { class: 'btn ghost sm' }, 'See all', icon('arrow', 14))),
    items.length ? h('ul', { class: 'item-list' }, items.map(renderItem)) : h('p', { class: 'muted' }, empty));
}

export async function renderDashboard(view) {
  const d = await api('GET', 'dashboard');
  const s = d.stats;

  const love = loveCard();
  if (love) loveExtras().then((x) => love.append(x));
  // Every block has an id so she can hide and reorder them (Customise).
  const blocks = [
    { id: 'hero', label: 'Week & greeting', node: heroCard(d) },
    { id: 'brief', label: 'Your day', node: briefCard() },
    { id: 'due', label: 'Due now', node: dueStrip() },
    { id: 'composer', label: 'Composer', node: homeComposer() },
    { id: 'next', label: 'Next step', node: nextStepCard(d.next_step) },
    { id: 'inbound', label: 'From your website', node: inboundCard(d.inbound) },
    { id: 'social', label: 'Social this week', node: socialStrip(d.social) },
    { id: 'love', label: 'Today’s note', node: love },
    { id: 'setup', label: 'Setup checklist', node: setupCard(d.setup_checks) },
    { id: 'stats', label: 'YouTube stats', node: h('div', { class: 'grid grid-4' },
      statBox(s.shipped, s.missed ? `shipped · ${s.missed} missed` : 'videos shipped', 'tone-good'),
      statBox(s.streak, s.streak === 1 ? 'week streak' : 'week streak', 'tone-accent'),
      statBox(s.new_ideas, `new ideas · ${s.picked_ideas} picked`, 'tone-violet'),
      statBox(s.breakouts, 'breakouts found', 'tone-warn')) },
    { id: 'scoreboard', label: 'Scoreboard', node: d.weeks.length ? h('div', { class: 'card' },
      h('div', { class: 'card-header' }, h('h2', {}, 'Scoreboard'), link('/goals', { class: 'btn ghost sm' }, 'Open', icon('arrow', 14))),
      weekDots(d.weeks, d.current_week?.id)) : null },
  ];
  const redraw = () => { view.replaceChildren(); renderDashboard(view); };
  view.append(arrangeDashboard(blocks).map((b) => b.node),
    h('div', { class: 'dash-customise' }, h('button', { class: 'btn ghost sm', onclick: () => customiseDashboard(blocks, redraw) }, icon('sliders', 14), 'Customise dashboard')));

  view.append(h('div', { class: 'grid grid-2', style: { marginTop: '18px' } },
    listCard('Picked ideas', '/ideas', d.picked_ideas, (i) => h('li', {},
      h('div', { class: 'item-main' }, h('strong', {}, i.title), i.premise ? h('div', { class: 'muted clamp-1' }, i.premise) : null),
      pill(i.status)), 'Nothing picked yet. Generate ideas and pick one you love.'),

    listCard('Recent scripts', '/scripts', d.recent_scripts, (sc) => h('li', {},
      link(`/scripts/${sc.id}`, { class: 'item-main' }, h('strong', {}, sc.title), h('div', { class: 'muted' }, `edited ${timeAgo(sc.updated_at)}`)),
      pill(sc.status)), 'No scripts yet.'),

    listCard('Top breakouts', '/breakouts', d.recent_outliers, (o) => h('li', {},
      h('a', { href: o.url, target: '_blank', rel: 'noopener', class: 'item-main' },
        h('strong', { class: 'clamp-1' }, o.title),
        h('div', { class: 'muted' }, `${o.channel_title || 'Unknown'} · ${compact(o.views)} views`)),
      h('span', { class: 'score' }, `${Number(o.outlier_score).toFixed(1)}×`)), 'Add competitor channels and run a scan.'),

    listCard('Dictated notes', '/flow', d.recent_notes, (n) => h('li', {},
      h('div', { class: 'item-main' },
        h('div', { class: 'clamp-2' }, n.polished.replace(/[#*_>`]/g, '').slice(0, 220)),
        h('div', { class: 'muted' }, timeAgo(n.created_at)))), 'Hold the mic button and ramble. It becomes a clean note.'),
  ));

  if (!d.weeks.length && !d.picked_ideas.length && !d.recent_scripts.length) {
    view.append(h('div', { class: 'card', style: { marginTop: '18px' } }, emptyState(icon('sprout', 30), 'A fresh studio',
      'Start in Settings: add your channel and an AI key. Then add a few competitor channels to watch.',
      link('/settings', { class: 'btn primary' }, 'Open settings'))));
  }
}
