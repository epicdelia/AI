// Dashboard: this week's commitment, scoreboard, setup checklist and the latest pipeline items.
import { h, api, link, pill, fmtDay, daysUntil, compact, timeAgo, emptyState } from '../core.js';
import { icon } from '../icons.js';
import { weekDots } from './goals.js';
import { loveCard } from '../love.js';

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
        h('p', { class: 'hero-eyebrow' }, `${greeting()}, ${d.creator_name}`),
        h('h1', {}, line),
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

function listCard(title, to, items, renderItem, empty) {
  return h('div', { class: 'card' },
    h('div', { class: 'card-header' }, h('h2', {}, title), link(to, { class: 'btn ghost sm' }, 'See all', icon('arrow', 14))),
    items.length ? h('ul', { class: 'item-list' }, items.map(renderItem)) : h('p', { class: 'muted' }, empty));
}

export async function renderDashboard(view) {
  const d = await api('GET', 'dashboard');
  const s = d.stats;

  view.append(
    heroCard(d),
    nextStepCard(d.next_step),
    loveCard(),
    setupCard(d.setup_checks),
    h('div', { class: 'grid grid-4', style: { marginTop: '18px' } },
      statBox(s.shipped, s.missed ? `shipped · ${s.missed} missed` : 'videos shipped', 'tone-good'),
      statBox(s.streak, s.streak === 1 ? 'week streak' : 'week streak', 'tone-accent'),
      statBox(s.new_ideas, `new ideas · ${s.picked_ideas} picked`, 'tone-violet'),
      statBox(s.breakouts, 'breakouts found', 'tone-warn')),
  );

  if (d.weeks.length) {
    view.append(h('div', { class: 'card' },
      h('div', { class: 'card-header' }, h('h2', {}, 'Scoreboard'), link('/goals', { class: 'btn ghost sm' }, 'Open', icon('arrow', 14))),
      weekDots(d.weeks, d.current_week?.id)));
  }

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
    view.append(h('div', { class: 'card', style: { marginTop: '18px' } }, emptyState('🌱', 'A fresh studio',
      'Start in Settings: add your channel and an AI key. Then add a few competitor channels to watch.',
      link('/settings', { class: 'btn primary' }, 'Open settings'))));
  }
}
