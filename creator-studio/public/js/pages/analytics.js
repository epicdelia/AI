// Analytics: what's working on each platform, from the numbers she logged or imported.
// Charts: one single-series bar chart per platform (small multiples, so no legend), and a
// best-times heatmap on a validated one-hue ramp, with the top slots also listed as text.
import { h, api, busy, toast, pageHead, emptyState, clear, link, compact, timeAgo, mdBlock, apiStream, liveMarkdown, modal, field } from '../core.js';
import { icon } from '../icons.js';
import { platformChip } from './social.js';
import { importDialog, demoControl } from '../social-tools.js';
import { printMarkdown } from '../components.js';
import { skLines } from '../skeleton.js';
import { goalsCard } from '../growth.js';
import { attributionCard } from '../revenue.js';
import { evergreenDialog } from '../ai-checks.js';

const DAYS = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
const NS = 'http://www.w3.org/2000/svg';
const svg = (tag, attrs = {}) => {
  const el = document.createElementNS(NS, tag);
  for (const [k, v] of Object.entries(attrs)) el.setAttribute(k, v);
  return el;
};
const plainText = (p) => String(p.body || (p.parts || []).join(' / ') || '').replace(/\s+/g, ' ').trim();

/** One tooltip for the page, driven by [data-tip] on any mark (hover and keyboard focus). */
export function tooltipLayer(root) {
  const tip = h('div', { class: 'chart-tip', role: 'tooltip', hidden: true });
  const show = (e) => {
    const t = e.target.closest?.('[data-tip]');
    if (!t || !root.contains(t)) return;
    tip.textContent = t.getAttribute('data-tip');
    tip.hidden = false;
    const r = t.getBoundingClientRect();
    tip.style.left = `${Math.min(window.innerWidth - 230, Math.max(8, r.left + r.width / 2 - 110))}px`;
    tip.style.top = `${Math.max(8, r.top - 46)}px`;
  };
  const hide = () => { tip.hidden = true; };
  root.addEventListener('mouseover', show);
  root.addEventListener('focusin', show);
  root.addEventListener('mouseout', hide);
  root.addEventListener('focusout', hide);
  return tip;
}

/** Weekly engagement for one platform: thin bars, 4px rounded tops, 2px gaps, recessive baseline. */
function weekBars(series) {
  const w = 260;
  const ht = 72;
  const max = Math.max(1, ...series.map((s) => s.engagement));
  const gap = 2;
  const bw = Math.max(4, Math.min(28, (w - gap * (series.length - 1)) / series.length));
  const chart = svg('svg', { viewBox: `0 0 ${w} ${ht + 2}`, class: 'week-bars', role: 'img', 'aria-label': `Engagement per week: ${series.map((s) => s.engagement).join(', ')}` });
  chart.append(svg('line', { x1: 0, x2: w, y1: ht + 1, y2: ht + 1, class: 'baseline' }));
  series.forEach((s, i) => {
    const bh = s.engagement ? Math.max(3, (s.engagement / max) * (ht - 4)) : 0;
    const x = i * (bw + gap);
    const label = `Week of ${new Date(`${s.week}T00:00`).toLocaleDateString([], { day: 'numeric', month: 'short' })}: ${s.engagement.toLocaleString()} engagements, ${s.posts} post${s.posts === 1 ? '' : 's'}`;
    // Hit target is the full column, bigger than the mark.
    const hit = svg('rect', { x, y: 0, width: bw, height: ht, class: 'hit', 'data-tip': label, tabindex: '0' });
    const r = Math.min(4, bw / 2, bh);
    const bar = bh ? svg('path', { class: 'bar', d: `M${x},${ht} V${ht - bh + r} Q${x},${ht - bh} ${x + r},${ht - bh} H${x + bw - r} Q${x + bw},${ht - bh} ${x + bw},${ht - bh + r} V${ht} Z` }) : null;
    if (bar) chart.append(bar);
    chart.append(hit);
  });
  return chart;
}

function platformCard(key, p) {
  const change = p.change === null ? null : h('span', { class: `delta ${p.change >= 0 ? 'up' : 'down'}` },
    `${p.change >= 0 ? '↑' : '↓'} ${Math.abs(p.change)}% vs previous`);
  const best = p.by_format[0];
  return h('div', { class: `card an-card pf-${key}` },
    h('div', { class: 'card-header' }, platformChip(key, { platforms: { [key]: { label: p.label } } }), change),
    h('div', { class: 'an-stats' },
      h('div', {}, h('div', { class: 'an-num' }, compact(p.engagement)), h('div', { class: 'muted small' }, 'engagements')),
      h('div', {}, h('div', { class: 'an-num' }, p.posts), h('div', { class: 'muted small' }, `posts · ${p.logged} with numbers`)),
      h('div', {}, h('div', { class: 'an-num' }, p.avg_rate !== null ? `${p.avg_rate}%` : '–'), h('div', { class: 'muted small' }, 'avg rate'))),
    weekBars(p.series),
    best ? h('p', { class: 'small' }, 'Best format: ', h('strong', {}, best.label), h('span', { class: 'muted' }, ` · median ${compact(best.median_engagement)} over ${best.posts} post${best.posts === 1 ? '' : 's'}`)) : null,
    p.missing_numbers ? h('p', { class: 'small warn-text' }, `${p.missing_numbers} posted without numbers: add them, or import.`) : null);
}

/** Best times: day × time-of-day, colour = median score (5 validated steps), empty cells neutral. */
function heatmap(bt) {
  const values = [];
  bt.grid.forEach((row) => row.forEach((c) => { if (c.median !== null) values.push(c.median); }));
  const sorted = [...values].sort((a, b) => a - b);
  const step = (v) => (sorted.length < 2 ? 3 : Math.min(5, 1 + Math.floor((sorted.indexOf(v) / (sorted.length - 1)) * 4.999)));
  const unit = bt.metric.startsWith('engagement rate') ? '%' : ' engagements';
  return h('div', { class: 'heat' },
    h('div', { class: 'heat-grid', role: 'table', 'aria-label': `Median ${bt.metric} by day and time` },
      h('div', { class: 'heat-corner' }),
      bt.blocks.map((b) => h('div', { class: 'heat-col muted small', role: 'columnheader' }, b.replace(' & early afternoon', ''))),
      bt.grid.map((row, d) => [
        h('div', { class: 'heat-day muted small', role: 'rowheader' }, DAYS[d]),
        row.map((c, b) => (c.median === null
          ? h('div', { class: 'heat-cell empty', role: 'cell', 'aria-label': `${DAYS[d]} ${bt.blocks[b]}: no posts` })
          : h('div', {
            class: `heat-cell s${step(c.median)}`, role: 'cell', tabindex: '0',
            'data-tip': `${DAYS[d]}, ${bt.blocks[b].toLowerCase()}: median ${c.median}${unit} (${c.posts} post${c.posts === 1 ? '' : 's'})`,
            'aria-label': `${DAYS[d]} ${bt.blocks[b]}: median ${c.median}${unit} from ${c.posts} posts`,
          }))),
      ])),
    h('div', { class: 'heat-legend muted small' }, 'Lower', [1, 2, 3, 4, 5].map((s) => h('span', { class: `heat-swatch s${s}` })), 'Higher',
      h('span', { class: 'heat-swatch empty' }), 'No posts'),
    bt.best.length ? h('ol', { class: 'best-slots' }, bt.best.map((s) => h('li', {},
      h('strong', {}, `${DAYS[s.day]}, ${bt.blocks[s.block].toLowerCase()}`),
      h('span', { class: 'muted' }, ` · median ${Math.round(s.median * 100) / 100}${unit} over ${s.posts} posts`)))) : null,
    h('p', { class: 'muted small' }, `From your ${bt.posts} posts with numbers, in ${bt.timezone}. Medians, so one viral post doesn’t skew a slot. Slots with 1 post are shown but not ranked.`));
}

export async function renderAnalytics(view) {
  let weeks = 4;
  let data = await api('GET', `social/analytics?weeks=${weeks}`);
  const meta = await api('GET', 'social/meta');
  const cardsBox = h('div');
  const timesBox = h('div');
  const topBox = h('div');
  const recycleBox = h('div');
  const pillarBox = h('div');
  const reviewBox = h('div', { class: 'doc-body' });

  function drawCards() {
    const entries = Object.entries(data.platforms);
    clear(cardsBox).append(entries.length
      ? h('div', { class: 'an-grid' }, entries.map(([k, p]) => platformCard(k, p)))
      : h('div', { class: 'card' }, emptyState(icon('chart', 30), 'No posted posts yet',
        'Mark posts as posted with their numbers, or import your analytics export, and this fills in.',
        h('button', { class: 'btn primary', onclick: openImport }, icon('download', 16), 'Import numbers'))));
  }

  let timesPlatform = Object.keys(data.platforms)[0] || 'linkedin';
  async function drawTimes() {
    const sel = h('select', { class: 'inline-select', 'aria-label': 'Platform' },
      Object.entries(meta.platforms).map(([k, p]) => h('option', { value: k, selected: k === timesPlatform }, p.label)));
    sel.addEventListener('change', () => { timesPlatform = sel.value; drawTimes(); });
    const body = h('div', {}, skLines(4, 'best times'));
    clear(timesBox).append(h('div', { class: 'card' }, h('div', { class: 'card-header' }, h('h2', {}, 'Best times to post'), sel), body));
    const bt = await api('GET', `social/best-times?platform=${timesPlatform}`).catch(() => null);
    clear(body).append(!bt ? h('p', { class: 'error-text' }, 'Couldn’t load best times.')
      : bt.enough ? heatmap(bt)
        : h('p', { class: 'muted' }, `Needs ${bt.needed} ${meta.platforms[timesPlatform].label} posts with numbers (you have ${bt.posts}). Importing your analytics is the quickest way.`));
  }

  function drawTop() {
    clear(topBox).append(h('div', { class: 'card' }, h('h2', {}, 'Top posts'),
      data.top.length ? h('ul', { class: 'mini-list an-top' }, data.top.map((t) => h('li', {},
        h('span', { class: 'row' }, platformChip(t.post.platform, meta), link(`/posts/${t.post.id}`, { class: 'clamp-1' }, t.post.title || plainText(t.post).slice(0, 80) || 'Post')),
        h('span', { class: 'score score-ok', title: 'Against your median on that platform' }, `${t.vs_median}×`))))
        : h('p', { class: 'muted small' }, 'Posts with numbers from this period show up here.')));
  }

  function drawRecycle() {
    clear(recycleBox).append(h('div', { class: 'card' },
      h('h2', {}, 'Worth posting again'),
      h('p', { class: 'muted small' }, 'Your proven posts: 1.5× your usual or better, at least 45 days old. A fresh take keeps the idea, changes the hook and wording.'),
      data.recycle.length ? h('ul', { class: 'mini-list an-top' }, data.recycle.map((t) => {
        const btn = h('button', {
          class: 'btn sm',
          onclick: () => busy(btn, 'Writing…', async () => {
            const r = await api('POST', `social/posts/${t.post.id}/fresh-take`);
            toast(r.message);
            btn.replaceWith(link(`/posts/${r.post.id}`, { class: 'btn sm ghost' }, icon('check', 14), 'Open draft'));
          }),
        }, icon('repeat', 14), 'Fresh take');
        const reboot = h('button', { class: 'btn sm ghost', title: 'Three new angles on it, scheduled for your approval', onclick: () => evergreenDialog({ post_id: t.post.id, platform: t.post.platform }) }, 'New angles');
        return h('li', {},
          h('span', { class: 'row' }, platformChip(t.post.platform, meta), h('span', { class: 'clamp-1' }, t.post.title || plainText(t.post).slice(0, 70)),
            h('span', { class: 'muted small' }, ` · ${t.vs_median}× · ${timeAgo(t.post.posted_at)}`)),
          h('span', { class: 'row', style: { gap: '6px' } }, btn, reboot));
      })) : h('p', { class: 'muted small' }, 'Nothing yet: needs posts with numbers that are 45+ days old.')));
  }

  function drawReview() {
    clear(reviewBox).append(data.review ? mdBlock(data.review) : h('p', { class: 'muted' }, 'Get a read of the last four weeks across every platform: what worked, what didn’t, and three experiments for next week.'));
  }
  const reviewBtn = h('button', {
    class: 'btn primary sm',
    onclick: () => busy(reviewBtn, 'Reading your posts…', async () => {
      const live = liveMarkdown();
      clear(reviewBox).append(live.el);
      try {
        const r = await apiStream('social/review', {}, { onDelta: live.append });
        data.review = r.review;
        data.review_at = new Date().toISOString();
      } finally { drawReview(); reviewAt.textContent = data.review_at ? `Updated ${timeAgo(data.review_at)}` : ''; }
    }),
  }, icon('sparkle', 15), 'Review my last 4 weeks');
  const reviewAt = h('span', { class: 'muted small' }, data.review_at ? `Updated ${timeAgo(data.review_at)}` : '');

  async function openImport() {
    if (await importDialog(meta.platforms)) { data = await api('GET', `social/analytics?weeks=${weeks}`); drawAll(); }
  }
  const periodBtns = [4, 12].map((n) => h('button', {
    class: `btn sm${n === weeks ? ' primary' : ''}`,
    onclick: async () => {
      weeks = n;
      periodBtns.forEach((b, i) => b.classList.toggle('primary', [4, 12][i] === n));
      data = await api('GET', `social/analytics?weeks=${weeks}`);
      drawCards();
      drawTop();
    },
  }, `${n} weeks`));

  function drawPillars() {
    const rows = data.pillars || [];
    clear(pillarBox).append(h('div', { class: 'card' },
      h('div', { class: 'card-header' }, h('h2', {}, 'Content mix'), link('/settings', { class: 'btn ghost sm' }, 'Pillars')),
      rows.length ? [
        h('div', { class: 'table-wrap' }, h('table', {},
          h('thead', {}, h('tr', {}, h('th', {}, 'Pillar'), h('th', {}, 'Network'), h('th', { class: 'num' }, 'Posts'), h('th', { class: 'num' }, 'Median engagement'))),
          h('tbody', {}, rows.map((r) => h('tr', {},
            h('td', {}, h('strong', {}, r.pillar)), h('td', {}, platformChip(r.platform, meta)),
            h('td', { class: 'num' }, r.posts), h('td', { class: 'num' }, compact(r.median_engagement))))))),
        data.untagged ? h('p', { class: 'muted small' }, `${data.untagged} post(s) with numbers have no pillar yet; pick one in each post’s Publishing box.`) : null,
      ] : h('p', { class: 'muted small' }, meta.pillars?.length
        ? 'Tag posts with a pillar (new drafts are tagged for you) and log their numbers to see which topics work where.'
        : 'Add your content pillars in Settings → Social (e.g. AI tools, Desk setups, Women in tech) to see which topics work on which network.')));
  }

  function drawAll() { drawCards(); drawTimes(); drawTop(); drawRecycle(); drawPillars(); drawReview(); }
  drawAll();

  // ---- dashboard on top: ticker, KPIs, chart cards (each with its own network filter) ----
  let days = 30;
  let dash = await api('GET', `social/dashboard?days=${days}`);
  const dashBox = h('div');
  let tick = null;
  async function drawDash() {
    tick?.stop();
    tick = ticker(dash.ticker);
    const headlineSum = (label) => (d, v) => [v.reduce((a, b) => a + (b || 0), 0), `${label} · last ${days} days`];
    clear(dashBox).append(
      tick,
      kpiCards(dash.kpis, async () => {
        if (await followersDialog(meta, dash.followers)) { dash = await api('GET', `social/dashboard?days=${days}`); drawDash(); }
      }),
      h('div', { class: 'metric-grid' },
        metricCard(meta, days, { title: 'Published posts', metric: 'posts', unit: ' posts', headline: (d) => [d.kpis.posts_today, 'Today'] }),
        metricCard(meta, days, { title: 'Engagements', metric: 'engagement', unit: ' engagements', headline: headlineSum('Likes, comments, shares and saves') }),
        metricCard(meta, days, { title: 'Impressions', metric: 'impressions', unit: ' impressions', headline: headlineSum('Views where you logged them') }),
        metricCard(meta, days, { title: 'Followers', metric: 'followers', kind: 'line', unit: ' followers', headline: (d, v) => [[...v].reverse().find((x) => x !== null) ?? null, 'Now'] })));
  }
  await drawDash();
  const dayBtns = [7, 30, 90].map((n) => h('button', {
    class: `btn sm${n === days ? ' primary' : ''}`,
    onclick: async () => {
      days = n;
      dayBtns.forEach((b, i) => b.classList.toggle('primary', [7, 30, 90][i] === n));
      dash = await api('GET', `social/dashboard?days=${days}`);
      drawDash();
    },
  }, `${n} days`));

  const askOut = h('div', { class: 'ask-out', 'aria-live': 'polite' });
  const askIn = h('input', { type: 'text', maxlength: 500, placeholder: 'Ask your numbers: “What worked best on LinkedIn this month?”', 'aria-label': 'Ask about your numbers' });
  const askBtn = h('button', { class: 'btn primary', type: 'submit' }, icon('sparkle', 15), 'Ask');
  const askCard = h('form', { class: 'card ask-card', onsubmit: (e) => { e.preventDefault(); if (!askIn.value.trim()) return; busy(askBtn, 'Thinking…', async () => {
    const r = await api('POST', 'social/ask', { question: askIn.value.trim() });
    clear(askOut).append(mdBlock(r.answer));
  }); } },
  h('div', { class: 'ask-row' }, askIn, askBtn),
  h('div', { class: 'ask-chips' }, ['Which network is growing fastest?', 'What should I post more of?', 'When do my posts do best?', 'Am I on track for my goals?'].map((q) => h('button', { type: 'button', class: 'wz-chip', onclick: () => { askIn.value = q; askCard.requestSubmit(); } }, q))),
  askOut);

  const page = h('div', {},
    pageHead('Analytics', 'Reports across every network, from the numbers you log or import. Your own posts only, no outside benchmarks.',
      h('div', { class: 'row' }, dayBtns),
      h('button', { class: 'btn', onclick: openImport }, icon('download', 16), 'Import numbers'),
      h('button', { class: 'btn', onclick: () => printReport(dash, data, meta) }, icon('doc', 16), 'Download report'),
      meta.demo ? null : demoControl(0)),
    meta.demo ? demoControl(meta.demo) : null,
    askCard,
    dashBox,
    goalsCard(),
    attributionCard(),
    h('div', { class: 'section-head' }, h('h2', { class: 'section-title' }, 'By network'), h('div', { class: 'row' }, periodBtns)),
    cardsBox,
    h('div', { class: 'grid grid-2', style: { marginTop: '18px' } }, timesBox, h('div', {}, topBox, recycleBox)),
    pillarBox,
    h('div', { class: 'card' }, h('div', { class: 'card-header' }, h('h2', {}, 'Weekly review'), h('div', { class: 'row' }, reviewAt, reviewBtn)), reviewBox));
  view.append(page, tooltipLayer(page));
  return () => tick?.stop();
}

// ------------------------------------------------------------------
// Dashboard: insight ticker, KPI counters, per-metric chart cards
// ------------------------------------------------------------------

const reduceMotion = () => window.matchMedia?.('(prefers-reduced-motion: reduce)').matches;
const fmtNum = (n) => (n === null || n === undefined ? '–' : Number(n).toLocaleString());

/** A number whose digits roll into place (static when reduced motion is on). */
function rollNumber(text, delay = 0) {
  const wrap = h('span', { class: 'roll', 'aria-label': text });
  const strips = [];
  for (const ch of String(text)) {
    if (/\d/.test(ch)) {
      const strip = h('span', { class: 'roll-strip', 'aria-hidden': 'true' }, '0123456789'.split('').map((d) => h('span', {}, d)));
      strips.push([strip, Number(ch)]);
      wrap.append(h('span', { class: 'roll-col' }, strip));
    } else {
      wrap.append(h('span', { class: 'roll-sym', 'aria-hidden': 'true' }, ch));
    }
  }
  const settle = () => strips.forEach(([s, d], i) => { s.style.transitionDelay = `${delay + i * 60}ms`; s.style.transform = `translateY(${-d * 10}%)`; });
  if (reduceMotion()) settle(); else requestAnimationFrame(() => requestAnimationFrame(settle));
  return wrap;
}

/** Rotating plain-language insights, with previous / pause / next. */
function ticker(lines) {
  if (!lines?.length) return null;
  let i = 0;
  let paused = reduceMotion();
  let timer = null;
  const text = h('p', { class: 'ticker-text', 'aria-live': 'polite' }, lines[0]);
  const show = (n) => {
    i = (n + lines.length) % lines.length;
    text.classList.remove('in');
    void text.offsetWidth; // restart the enter animation
    text.textContent = lines[i];
    text.classList.add('in');
  };
  const pauseBtn = h('button', { class: 'ticker-btn', title: 'Pause', 'aria-label': 'Pause', onclick: () => { paused = !paused; sync(); } });
  const sync = () => {
    clearInterval(timer);
    clear(pauseBtn).append(icon(paused ? 'play' : 'pause', 14));
    pauseBtn.title = paused ? 'Play' : 'Pause';
    pauseBtn.setAttribute('aria-label', pauseBtn.title);
    if (!paused && lines.length > 1) timer = setInterval(() => show(i + 1), 5000);
  };
  const el = h('div', { class: 'ticker card' },
    icon('trend', 18),
    h('div', { class: 'ticker-body' }, text),
    lines.length > 1 ? h('div', { class: 'ticker-nav' },
      h('button', { class: 'ticker-btn', title: 'Previous', 'aria-label': 'Previous', onclick: () => { show(i - 1); sync(); } }, icon('chevron-left', 16)),
      pauseBtn,
      h('button', { class: 'ticker-btn', title: 'Next', 'aria-label': 'Next', onclick: () => { show(i + 1); sync(); } }, icon('chevron-right', 16))) : null);
  // Hover or keyboard focus holds the current line still.
  el.addEventListener('mouseenter', () => clearInterval(timer));
  el.addEventListener('mouseleave', sync);
  el.addEventListener('focusin', () => clearInterval(timer));
  el.addEventListener('focusout', sync);
  sync();
  el.stop = () => clearInterval(timer);
  return el;
}

function delta(now, before, suffix = '') {
  if (now === null || before === null || before === undefined) return null;
  const d = Math.round((now - before) * 10) / 10;
  if (!d) return h('span', { class: 'delta muted' }, 'no change');
  return h('span', { class: `delta ${d > 0 ? 'up' : 'down'}` }, `${d > 0 ? '↑' : '↓'} ${Math.abs(d).toLocaleString()}${suffix} vs previous`);
}

function kpiCards(k, onFollowers) {
  return h('div', { class: 'kpi-grid' },
    h('div', { class: 'card kpi' }, h('h4', {}, 'Average engagement'),
      h('p', { class: 'kpi-num' }, k.avg_engagement === null ? '–' : rollNumber(`${k.avg_engagement}%`)),
      k.avg_engagement === null ? h('p', { class: 'muted small' }, 'Needs posts with impressions logged.') : delta(k.avg_engagement, k.avg_engagement_prev, ' pts')),
    h('div', { class: 'card kpi' }, h('div', { class: 'card-header' }, h('h4', {}, 'Total followers'), h('button', { class: 'btn ghost sm', onclick: onFollowers }, 'Update')),
      h('p', { class: 'kpi-num' }, k.followers === null ? '–' : rollNumber(fmtNum(k.followers), 100)),
      k.followers === null ? h('p', { class: 'muted small' }, 'Add your follower counts (YouTube fills in daily).')
        : k.followers_change !== null ? h('span', { class: `delta ${k.followers_change >= 0 ? 'up' : 'down'}` }, `${k.followers_change >= 0 ? '↑' : '↓'} ${Math.abs(k.followers_change).toLocaleString()} in 30 days`) : null),
    h('div', { class: 'card kpi' }, h('h4', {}, 'Total posts'),
      h('p', { class: 'kpi-num' }, rollNumber(fmtNum(k.posts), 200)),
      delta(k.posts, k.posts_prev)));
}

/** Axis ticks: 0 and 4 round steps up to the max. */
function ticks(max) {
  if (max <= 0) return [0, 1];
  const raw = max / 4;
  const mag = 10 ** Math.floor(Math.log10(raw));
  // Counts are whole numbers: never a 0.25 step (it would label 0, 0, 1, 1, 1).
  const step = Math.max(1, [1, 2, 2.5, 5, 10].map((m) => m * mag).find((s) => s >= raw));
  return Array.from({ length: Math.min(5, Math.ceil(max / step) + 1) }, (_, i) => i * step);
}
const shortDate = (iso) => new Date(`${iso}T00:00`).toLocaleDateString([], { day: 'numeric', month: 'short' });

/** One-series chart with a y axis and recessive grid: bars (counts) or a line (followers). */
export function seriesChart(dates, values, { kind = 'bar', unit = '' } = {}) {
  const W = 520;
  const H = 220;
  const L = 40;
  const B = 24;
  const T = 8;
  const plotW = W - L - 8;
  const plotH = H - B - T;
  const nums = values.filter((v) => v !== null);
  const lo = kind === 'line' && nums.length ? Math.min(...nums) : 0;
  const tk = ticks(Math.max(1, ...nums) - (kind === 'line' ? lo : 0));
  const top = tk[tk.length - 1];
  const y = (v) => T + plotH - ((v - lo) / top) * plotH;
  const chart = svg('svg', { viewBox: `0 0 ${W} ${H}`, class: `series-chart ${kind}`, role: 'img', 'aria-label': `${dates.length} days, ${kind === 'bar' ? 'total' : 'latest'} ${kind === 'bar' ? nums.reduce((a, b) => a + b, 0) : nums[nums.length - 1] ?? 'none'}` });
  tk.forEach((t) => {
    chart.append(svg('line', { x1: L, x2: W - 8, y1: y(t + lo), y2: y(t + lo), class: 'grid' }));
    const lab = svg('text', { x: L - 6, y: y(t + lo) + 4, class: 'axis', 'text-anchor': 'end' });
    lab.textContent = compact(Math.round(t + lo));
    chart.append(lab);
  });
  const n = dates.length;
  const slot = plotW / n;
  [0, Math.floor((n - 1) / 2), n - 1].forEach((i) => {
    const lab = svg('text', { x: L + slot * i + slot / 2, y: H - 6, class: 'axis', 'text-anchor': i === 0 ? 'start' : i === n - 1 ? 'end' : 'middle' });
    lab.textContent = shortDate(dates[i]);
    chart.append(lab);
  });
  if (kind === 'bar') {
    const gap = 2;
    const bw = Math.max(2, slot - gap);
    values.forEach((v, i) => {
      const x = L + i * slot + gap / 2;
      const bh = v ? Math.max(2, (v / top) * plotH) : 0;
      if (bh) {
        const r = Math.min(4, bw / 2, bh);
        chart.append(svg('path', { class: 'bar', d: `M${x},${T + plotH} V${T + plotH - bh + r} Q${x},${T + plotH - bh} ${x + r},${T + plotH - bh} H${x + bw - r} Q${x + bw},${T + plotH - bh} ${x + bw},${T + plotH - bh + r} V${T + plotH} Z` }));
      }
      chart.append(svg('rect', { class: 'hit', x: L + i * slot, y: T, width: slot, height: plotH, tabindex: '0', 'data-tip': `${shortDate(dates[i])}: ${fmtNum(v)}${unit}` }));
    });
  } else {
    let d = '';
    values.forEach((v, i) => {
      if (v === null) return;
      d += `${d && values[i - 1] !== null ? 'L' : 'M'}${L + i * slot + slot / 2},${y(v)} `;
    });
    chart.append(svg('path', { class: 'line', d }));
    const cross = svg('line', { class: 'crosshair', y1: T, y2: T + plotH, x1: 0, x2: 0, visibility: 'hidden' });
    const dot = svg('circle', { class: 'dot', r: 4, visibility: 'hidden' });
    chart.append(cross, dot);
    values.forEach((v, i) => {
      if (v === null) return;
      const cx = L + i * slot + slot / 2;
      const hit = svg('rect', { class: 'hit', x: L + i * slot, y: T, width: slot, height: plotH, tabindex: '0', 'data-tip': `${shortDate(dates[i])}: ${fmtNum(v)}${unit}` });
      const on = () => { cross.setAttribute('x1', cx); cross.setAttribute('x2', cx); cross.setAttribute('visibility', 'visible'); dot.setAttribute('cx', cx); dot.setAttribute('cy', y(v)); dot.setAttribute('visibility', 'visible'); };
      const off = () => { cross.setAttribute('visibility', 'hidden'); dot.setAttribute('visibility', 'hidden'); };
      hit.addEventListener('mouseenter', on);
      hit.addEventListener('focus', on);
      hit.addEventListener('mouseleave', off);
      hit.addEventListener('blur', off);
      chart.append(hit);
    });
  }
  return chart;
}

/** A chart card with its own platform dropdown, like the rest of the dashboard. */
function metricCard(meta, days, { title, metric, kind = 'bar', unit = '', headline }) {
  let platform = '';
  const body = h('div', {}, skLines(5, title));
  const sel = h('select', { class: 'inline-select', 'aria-label': `${title}: platform` },
    h('option', { value: '' }, 'All networks'),
    Object.entries(meta.platforms).map(([k, p]) => h('option', { value: k }, p.label)));
  async function draw() {
    const d = await api('GET', `social/dashboard?days=${days}${platform ? `&platform=${platform}` : ''}`).catch(() => null);
    if (!d) { clear(body).append(h('p', { class: 'error-text' }, 'Couldn’t load.')); return; }
    const values = d.series[metric];
    const has = values.some((v) => v);
    const [big, label] = headline(d, values);
    clear(body).append(
      h('p', { class: 'metric-head' }, h('span', { class: 'metric-big' }, rollNumber(fmtNum(big))), h('span', { class: 'muted small' }, label)),
      has ? seriesChart(d.dates, values, { kind, unit })
        : h('p', { class: 'muted small metric-empty' }, metric === 'followers' ? 'No follower counts yet. Press Update on Total followers.' : `Nothing in the last ${days} days${platform ? ` on ${meta.platforms[platform].label}` : ''}.`));
  }
  sel.addEventListener('change', () => { platform = sel.value; draw(); });
  draw();
  return h('div', { class: 'card metric-card' }, h('div', { class: 'card-header' }, h('h4', {}, title), sel), body);
}

function followersDialog(meta, current) {
  return modal('Follower counts today', (close) => {
    const inputs = {};
    const form = h('form', {
      onsubmit: (e) => {
        e.preventDefault();
        busy(form.querySelector('[type=submit]'), 'Saving…', async () => {
          const counts = Object.fromEntries(Object.entries(inputs).filter(([, i]) => i.value !== '').map(([k, i]) => [k, Number(i.value)]));
          const r = await api('POST', 'social/followers', { counts });
          toast(r.message);
          close(true);
        });
      },
    },
    h('p', { class: 'muted small' }, 'Copy them from each profile. YouTube is recorded automatically each morning when your channel and key are set.'),
    h('div', { class: 'grid grid-2 tight' }, Object.entries(meta.platforms).map(([k, p]) => {
      inputs[k] = h('input', { type: 'number', min: 0, value: current[k]?.count ?? '', placeholder: '–' });
      return field(`${p.label}${current[k] ? ` (last ${current[k].recorded_on})` : ''}`, inputs[k]);
    })),
    h('div', { class: 'row', style: { justifyContent: 'flex-end', marginTop: '12px' } }, h('button', { type: 'submit', class: 'btn primary' }, 'Save')));
    return form;
  });
}

/** A printable report: headline numbers, per-platform table, top posts and the latest review. */
function printReport(dash, data, meta) {
  const k = dash.kpis;
  const rows = Object.entries(data.platforms).map(([key, p]) => `| ${p.label} | ${p.posts} | ${p.engagement.toLocaleString()} | ${p.avg_rate ?? '–'}% | ${p.change === null ? '–' : `${p.change > 0 ? '+' : ''}${p.change}%`} | ${dash.followers[key]?.count?.toLocaleString() ?? '–'} |`);
  const top = data.top.map((t) => `- **${meta.platforms[t.post.platform]?.label}**: ${plainText(t.post).slice(0, 90)} (${t.vs_median}× usual)`);
  const md = [
    `# Social report`,
    `${new Date().toLocaleDateString([], { day: 'numeric', month: 'long', year: 'numeric' })} · last ${dash.days} days`,
    '',
    `**Posts:** ${k.posts} · **Average engagement:** ${k.avg_engagement ?? '–'}% · **Followers:** ${k.followers?.toLocaleString() ?? '–'}${k.followers_change ? ` (${k.followers_change > 0 ? '+' : ''}${k.followers_change.toLocaleString()} in 30 days)` : ''}`,
    '',
    `## By network (last ${data.weeks} weeks)`,
    '| Network | Posts | Engagements | Avg rate | Change | Followers |',
    '|---|---|---|---|---|---|',
    ...rows,
    '',
    '## Top posts',
    ...(top.length ? top : ['No posts with numbers yet.']),
    '',
    data.review ? `## Review\n\n${data.review}` : '',
  ].join('\n');
  printMarkdown('Social report', md);
}
