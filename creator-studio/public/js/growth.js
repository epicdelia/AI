// Growth pieces shared by several pages: the live post score (+ hook lab), the dashboard's
// daily brief, and follower goals with a forecast.
import { h, api, busy, toast, clear, compact, link, modal, field, debounce, timeAgo } from './core.js';
import { icon } from './icons.js';
import { PLATFORM_ICONS } from './pages/social.js';

const LABEL = { linkedin: 'LinkedIn', instagram: 'Instagram', x: 'X', tiktok: 'TikTok', threads: 'Threads', youtube: 'YouTube' };
const band = (s) => (s >= 58 ? 'good' : s >= 42 ? 'mid' : 'low');
const fmtTime = (iso) => new Date(iso).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
const fmtDay = (iso) => new Date(iso).toLocaleDateString([], { weekday: 'short', day: 'numeric', month: 'short' });

/** A score dial: the number is the headline, the ring shows it out of 100. */
function dial(score, size = 64) {
  const r = (size - 8) / 2;
  const c = 2 * Math.PI * r;
  const el = h('div', { class: `ps-dial ps-${band(score)}`, style: { width: `${size}px`, height: `${size}px` }, role: 'img', 'aria-label': `Score ${score} out of 100` });
  el.innerHTML = `<svg width="${size}" height="${size}" viewBox="0 0 ${size} ${size}" aria-hidden="true"><circle cx="${size / 2}" cy="${size / 2}" r="${r}" class="ps-track"/><circle cx="${size / 2}" cy="${size / 2}" r="${r}" class="ps-arc" stroke-dasharray="${(c * score) / 100} ${c}" transform="rotate(-90 ${size / 2} ${size / 2})"/></svg>`;
  el.append(h('span', { class: 'ps-num' }, score));
  return el;
}

/** Small inline badge (agent approvals, lists). */
export function scoreBadge(score, title) {
  return h('span', { class: `ps-badge ps-${band(score)}`, title: title || `Post score ${score}/100` }, icon('chart', 12), score);
}

/**
 * Live post score card. getDraft() returns the draft; onHook(text) replaces the opening line.
 * Returns the card; call card.refresh() after edits (it debounces).
 */
export function scoreCard(getDraft, onHook) {
  const body = h('div', { class: 'ps-body' }, h('p', { class: 'muted small' }, 'Scoring…'));
  const lab = h('button', { class: 'btn sm', onclick: () => hookLab(getDraft(), onHook) }, icon('sparkle', 14), 'Improve the hook');
  const card = h('div', { class: 'card ps-card' }, h('div', { class: 'card-header' }, h('h3', {}, 'Post score'), lab), body);
  let seq = 0;
  const run = async () => {
    const mine = ++seq;
    try {
      const r = await api('POST', 'social/score', getDraft());
      if (mine !== seq) return;
      clear(body).append(
        h('div', { class: 'ps-head' }, dial(r.score),
          h('div', {}, h('strong', {}, r.label),
            h('p', { class: 'muted small' }, r.basis === 'your data'
              ? `Predicted from your ${r.history} ${LABEL[getDraft().platform]} posts with numbers: about ${r.predicted}${r.metric === 'engagement rate (%)' ? '%' : ''} vs your usual ${r.usual}${r.metric === 'engagement rate (%)' ? '%' : ''}.`
              : `Rule-of-thumb score: it becomes a prediction from your own results once you have ${r.needed} posts with numbers here (${r.history} so far).`))),
        h('ul', { class: 'ps-checks' }, r.checks.map((c) => h('li', { class: `ps-${c.status}` },
          icon(c.status === 'good' ? 'check' : c.status === 'info' ? 'eye' : 'alert', 13), h('span', {}, c.text, h('em', { class: 'muted' }, ` · ${c.source}`))))),
        r.similar.length ? h('details', { class: 'ps-similar' }, h('summary', { class: 'small' }, 'Most similar past posts'),
          h('ul', {}, r.similar.map((s) => h('li', { class: 'small' }, link(`/posts/${s.id}`, { class: 'clamp-1' }, s.title),
            h('span', { class: 'muted' }, ` ${s.value}${r.metric === 'engagement rate (%)' ? '%' : ''}`))))) : null);
    } catch (e) {
      if (mine === seq) clear(body).append(h('p', { class: 'muted small' }, e.message));
    }
  };
  card.refresh = debounce(run, 1100);
  run();
  return card;
}

/** Hook lab: 5 scored rewrites of the first line; pick one to use it. */
export async function hookLab(draft, onHook) {
  await modal('Hook lab', (close) => {
    const list = h('div', { class: 'ps-hooks' }, h('div', { class: 'loading' }, h('span', { class: 'spinner' }), 'Writing 5 hooks and scoring each one…'));
    api('POST', 'social/hooks', draft).then((r) => {
      clear(list).append(
        h('div', { class: 'ps-hook current' }, scoreBadge(r.current.score), h('div', {}, h('span', { class: 'muted small' }, 'Your current hook'), h('p', {}, r.current.text || '—'))),
        r.hooks.map((hk) => h('div', { class: 'ps-hook' }, scoreBadge(hk.score),
          h('div', {}, h('span', { class: 'muted small' }, hk.style), h('p', {}, hk.text)),
          h('button', { class: 'btn sm primary', onclick: () => { onHook(hk.text); toast('Hook updated.'); close(true); } }, 'Use'))));
    }).catch((e) => clear(list).append(h('p', { class: 'error-text' }, e.message)));
    return h('div', {}, h('p', { class: 'muted small' }, 'Each rewrite is scored the same way as your post, so you can compare. Only the opening line changes.'), list);
  }, { wide: true });
}

// ---------------------------------------------------------------- daily brief

export function briefCard() {
  const box = h('div', { class: 'card brief-card', 'aria-live': 'polite' }, h('div', { class: 'loading' }, h('span', { class: 'spinner' }), 'Getting your day ready…'));
  api('GET', 'brief').then((b) => {
    const tiles = [
      ['calendar', b.due_today.length, b.due_today.length === 1 ? 'post today' : 'posts today', '/calendar'],
      ['bot', b.approvals, 'to approve', '/agents'],
      ['inbox', b.inbox, b.leads ? `to answer · ${b.leads} lead${b.leads === 1 ? '' : 's'}` : 'to answer', '/inbox'],
      ['external', b.published_24h, 'published (24 h)', '/analytics'],
    ];
    clear(box).append(
      h('div', { class: 'card-header' },
        h('div', {}, h('h2', {}, 'Your day'), h('p', { class: 'muted small' }, new Date(b.date).toLocaleDateString([], { weekday: 'long', day: 'numeric', month: 'long' }))),
        b.failed_runs ? link('/workflows', { class: 'pill bad' }, `${b.failed_runs} workflow run${b.failed_runs === 1 ? '' : 's'} failed`) : null),
      h('div', { class: 'brief-tiles' }, tiles.map(([ic, n, label, to]) => link(to, { class: `brief-tile${n ? ' has' : ''}` }, icon(ic, 16), h('strong', {}, n), h('span', {}, label)))),
      h('div', { class: 'brief-cols' },
        h('div', {},
          h('h3', {}, 'Today'),
          b.due_today.length ? h('ul', { class: 'brief-list' }, b.due_today.map((p) => h('li', {}, icon(PLATFORM_ICONS[p.platform], 14),
            link(`/posts/${p.id}`, { class: 'clamp-1' }, p.title || 'Post'), h('span', { class: 'muted small nowrap' }, `${fmtTime(p.at)}${p.auto ? ' · auto' : ''}`)))) : h('p', { class: 'muted small' }, 'Nothing scheduled today.'),
          b.cadence.filter((c) => c.done < c.target).length ? h('div', { class: 'brief-nudges' }, b.cadence.filter((c) => c.done < c.target).map((c) => h('p', { class: 'small' },
            icon(PLATFORM_ICONS[c.platform], 13), ` ${c.label}: ${c.done}/${c.target} this week. Next good slot ${fmtDay(c.next_slot)} ${fmtTime(c.next_slot)} (${c.next_reason}).`))) : null),
        h('div', {},
          h('h3', {}, 'This week'),
          b.top_post ? h('p', { class: 'small' }, icon('trend', 13), ' Top post: ', link(`/posts/${b.top_post.id}`, {}, b.top_post.title || 'Post'),
            ` · ${compact(b.top_post.engagements)} engagements${b.top_post.rate != null ? ` (${b.top_post.rate}%)` : ''}`) : h('p', { class: 'muted small' }, 'No posts with numbers this week yet.'),
          b.followers.length ? h('ul', { class: 'brief-followers' }, b.followers.map((f) => h('li', {}, icon(PLATFORM_ICONS[f.platform], 13), h('strong', {}, compact(f.count)),
            f.change_7d != null ? h('span', { class: f.change_7d >= 0 ? 'delta up' : 'delta down' }, `${f.change_7d >= 0 ? '+' : ''}${compact(f.change_7d)}`) : null))) : null,
          b.goals.length ? h('div', { class: 'brief-goals' }, b.goals.map((g) => goalLine(g))) : null,
          b.agent_news.length ? h('ul', { class: 'brief-news muted small' }, b.agent_news.map((m) => h('li', {}, icon('bot', 12), ` ${m}`))) : null)));
  }).catch((e) => clear(box).append(h('p', { class: 'muted small' }, e.message)));
  return box;
}

// ---------------------------------------------------------------- goals

const GOAL_STATUS = {
  on_track: ['ok', 'On track'], behind: ['bad', 'Behind'], reached: ['ok', 'Reached'], too_early: ['new', 'Needs a few more days of data'], no_data: ['', 'No follower numbers yet'],
};

function goalLine(g) {
  const pct = g.current != null ? Math.min(100, Math.round((g.current / g.target) * 100)) : 0;
  const [tone, label] = GOAL_STATUS[g.status] || ['', g.status];
  return h('div', { class: 'goal-line' },
    h('div', { class: 'row-between' }, h('span', { class: 'small' }, icon(PLATFORM_ICONS[g.platform], 13), ` ${g.label}: ${g.current != null ? compact(g.current) : '—'} / ${compact(g.target)}`),
      h('span', { class: `pill ${tone}` }, label)),
    h('div', { class: 'goal-bar', role: 'progressbar', 'aria-valuemin': 0, 'aria-valuemax': 100, 'aria-valuenow': pct, 'aria-label': `${g.label} goal progress` }, h('span', { style: { width: `${pct}%` } })),
    h('p', { class: 'muted small' },
      g.status === 'reached' ? 'Goal reached.' : g.per_day != null
        ? `${g.per_day >= 0 ? '+' : ''}${g.per_day}/day now · needs +${g.needed_per_day}/day to hit it by ${fmtDay(g.by)}${g.eta ? ` · at this pace: ${fmtDay(g.eta)}` : ''}`
        : `Needs +${g.needed_per_day ?? '?'}/day by ${fmtDay(g.by)}.`));
}

/** Analytics card: follower goals with progress and forecast, editable. */
export function goalsCard() {
  const box = h('div', { class: 'card goals-card' });
  async function draw() {
    const r = await api('GET', 'social/goals').catch(() => ({ goals: [] }));
    clear(box).append(
      h('div', { class: 'card-header' }, h('h2', {}, 'Follower goals'), h('button', { class: 'btn ghost sm', onclick: () => edit(r.goals) }, icon('pen', 14), r.goals.length ? 'Edit' : 'Set a goal')),
      r.goals.length ? r.goals.map(goalLine) : h('p', { class: 'muted small' }, 'Set a follower target and a date per network. The forecast uses your follower numbers (synced or typed in) to tell you if you’re on track and how many new followers a day it takes.'));
  }
  async function edit(goals) {
    const rows = Object.keys(LABEL).map((p) => {
      const g = goals.find((x) => x.platform === p);
      const target = h('input', { type: 'number', min: 1, value: g?.target ?? '', placeholder: 'target', 'aria-label': `${LABEL[p]} target` });
      const by = h('input', { type: 'date', value: g?.by ?? '', 'aria-label': `${LABEL[p]} date` });
      return { p, target, by, el: h('div', { class: 'goal-edit' }, h('span', {}, icon(PLATFORM_ICONS[p], 14), ` ${LABEL[p]}`), target, by) };
    });
    const ok = await modal('Follower goals', (close) => h('div', {},
      h('p', { class: 'muted small' }, 'Leave a network empty for no goal.'),
      rows.map((r) => r.el),
      h('div', { class: 'row', style: { justifyContent: 'flex-end', marginTop: '14px' } },
        h('button', { class: 'btn', onclick: () => close(false) }, 'Cancel'),
        h('button', {
          class: 'btn primary', onclick: (e) => busy(e.currentTarget, 'Saving…', async () => {
            const payload = rows.filter((r) => r.target.value && r.by.value).map((r) => ({ platform: r.p, target: Number(r.target.value), by: r.by.value }));
            await api('PUT', 'social/goals', { goals: payload });
            close(true);
          }),
        }, 'Save'))));
    if (ok) { toast('Goals saved.'); draw(); }
  }
  draw();
  return box;
}
