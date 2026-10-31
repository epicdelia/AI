// Landing page "Reach & Content": all six networks with their official logos, follower counts
// (synced ones once she connects or types them on her dashboard, else her public figures), the
// real 30-day change and trend, posts this month and engagement from her own published posts, and
// each network's share of her audience. Networks she hides on the dashboard aren't sent at all.
import { h, compact } from './core.js';
import { icon } from './icons.js';
import { loadFeed, countClick } from './landing-content.js';

/** Brand colour per network for the audience-mix bar (X and Threads follow the text colour). */
const MIX_COLORS = {
  instagram: 'linear-gradient(90deg, #FA7E1E, #D62976 55%, #962FBF)',
  youtube: '#FF0033',
  linkedin: '#0A66C2',
  tiktok: '#FE2C55',
  x: 'var(--ink)',
  threads: 'var(--muted)',
};

/** Her profiles, so the section still links every network if the feed can't load. */
const PROFILES = [
  { key: 'instagram', label: 'Instagram', handle: '@tech.unicorn', url: 'https://instagram.com/tech.unicorn' },
  { key: 'youtube', label: 'YouTube', handle: '@Tech.Unicorn', url: 'https://youtube.com/@Tech.Unicorn' },
  { key: 'linkedin', label: 'LinkedIn', handle: 'Delia Lazarescu', url: 'https://linkedin.com/in/delialazarescu' },
  { key: 'tiktok', label: 'TikTok', handle: '@tech.unicorn', url: 'https://tiktok.com/@tech.unicorn' },
  { key: 'x', label: 'X', handle: '@techunicorn_', url: 'https://x.com/techunicorn_' },
  { key: 'threads', label: 'Threads', handle: '@tech.unicorn', url: 'https://threads.net/@tech.unicorn' },
];

function profileCard(p) {
  return h('a', { href: p.url, target: '_blank', rel: 'noopener', class: `landing-reach-card is-plain net-${p.key}`, onclick: () => countClick(`profile:${p.key}`, p.key) },
    h('div', { class: 'landing-reach-top' },
      h('span', { class: 'landing-reach-logo' }, icon(`brand:${p.key}`, 24)),
      h('div', { class: 'landing-reach-who' }, h('strong', {}, p.label), h('span', { class: 'muted' }, p.handle)),
      h('span', { class: 'landing-reach-go', 'aria-hidden': 'true' }, icon('external', 14))),
    h('div', { class: 'landing-reach-foot' }, h('span', { class: 'muted small' }, `Follow ${p.handle}`), h('span', { class: 'landing-reach-follow' }, 'Follow', icon('arrow', 12))));
}

const reduceMotion = () => window.matchMedia?.('(prefers-reduced-motion: reduce)').matches;

/** Counts up to the number the first time it scrolls into view. */
function counter(value, label) {
  const el = h('span', { class: 'landing-reach-num', 'aria-label': `${Number(value).toLocaleString()}` }, label);
  if (reduceMotion() || typeof IntersectionObserver !== 'function' || value < 100) return el;
  el.textContent = '0';
  const io = new IntersectionObserver((entries) => {
    if (!entries.some((e) => e.isIntersecting)) return;
    io.disconnect();
    const start = performance.now();
    const dur = 1100;
    const tick = (now) => {
      const t = Math.min(1, (now - start) / dur);
      const eased = 1 - (1 - t) ** 3;
      el.textContent = t < 1 ? compact(Math.round(value * eased)) : label;
      if (t < 1) requestAnimationFrame(tick);
    };
    requestAnimationFrame(tick);
  }, { threshold: 0.4 });
  io.observe(el);
  return el;
}

/** A small line of her real follower snapshots (only drawn with 2+ synced points). */
function sparkline(points, key) {
  if (!points || points.length < 2) return null;
  const w = 120;
  const hgt = 32;
  const min = Math.min(...points);
  const max = Math.max(...points);
  const span = max - min || 1;
  const xy = points.map((v, i) => [(i / (points.length - 1)) * w, hgt - 3 - ((v - min) / span) * (hgt - 6)]);
  const d = xy.map(([x, y], i) => `${i ? 'L' : 'M'}${x.toFixed(1)},${y.toFixed(1)}`).join(' ');
  const area = `${d} L${w},${hgt} L0,${hgt} Z`;
  const id = `reach-grad-${key}`;
  return h('span', {
    class: 'landing-reach-spark',
    'aria-hidden': 'true',
    html: `<svg viewBox="0 0 ${w} ${hgt}" preserveAspectRatio="none" width="100%" height="${hgt}"><defs><linearGradient id="${id}" x1="0" x2="0" y1="0" y2="1"><stop offset="0" stop-color="currentColor" stop-opacity=".25"/><stop offset="1" stop-color="currentColor" stop-opacity="0"/></linearGradient></defs><path d="${area}" fill="url(#${id})"/><path d="${d}" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round" stroke-linecap="round" vector-effect="non-scaling-stroke"/></svg>`,
  });
}

function changeChip(change, small = false) {
  if (change === null || change === undefined || change === 0) return null;
  const up = change > 0;
  return h('span', { class: `landing-reach-change ${up ? 'up' : 'down'}${small ? ' sm' : ''}` },
    icon(up ? 'trend' : 'chevron-down', 12), `${up ? '+' : '−'}${compact(Math.abs(change))}`, h('span', { class: 'muted' }, ' in 30 days'));
}

function networkCard(n) {
  const facts = [
    n.posts_30d ? `${n.posts_30d} ${n.posts_30d === 1 ? 'post' : 'posts'} this month` : null,
    n.engagement_rate !== null && n.engagement_rate !== undefined ? `${n.engagement_rate}% engagement` : null,
  ].filter(Boolean);
  return h('a', {
    href: n.url,
    target: '_blank',
    rel: 'noopener',
    class: `landing-reach-card net-${n.key}`,
    'aria-label': `${n.label}: ${Number(n.count).toLocaleString()} ${n.unit}. Follow ${n.handle}`,
    onclick: () => countClick(`profile:${n.key}`, n.key),
  },
    h('div', { class: 'landing-reach-top' },
      h('span', { class: 'landing-reach-logo' }, icon(`brand:${n.key}`, 24)),
      h('div', { class: 'landing-reach-who' },
        h('strong', {}, n.label),
        h('span', { class: 'muted' }, n.handle)),
      n.synced ? h('span', { class: 'landing-reach-live', title: 'Synced from her account' }, h('i', { 'aria-hidden': 'true' }), 'Live') : null,
      h('span', { class: 'landing-reach-go', 'aria-hidden': 'true' }, icon('external', 14))),
    h('div', { class: 'landing-reach-count' },
      counter(n.count, n.count_label),
      h('span', { class: 'landing-reach-unit muted' }, n.unit)),
    changeChip(n.change_30d, true),
    sparkline(n.trend, n.key),
    h('p', { class: 'landing-reach-blurb muted' }, n.blurb),
    h('div', { class: 'landing-reach-foot' },
      h('span', { class: 'muted small' }, facts.join(' · ') || `${n.share}% of her audience`),
      h('span', { class: 'landing-reach-follow' }, 'Follow', icon('arrow', 12))),
    h('span', { class: 'landing-reach-share', style: { width: `${Math.max(2, n.share)}%`, background: MIX_COLORS[n.key] }, 'aria-hidden': 'true' }));
}

function mixBar(networks) {
  return h('div', { class: 'landing-reach-mix' },
    h('div', { class: 'landing-reach-bar', role: 'img', 'aria-label': `Audience mix: ${networks.map((n) => `${n.label} ${n.share}%`).join(', ')}` },
      networks.map((n) => h('span', { style: { flexGrow: String(Math.max(n.share, 0.6)), background: MIX_COLORS[n.key] }, title: `${n.label}: ${n.share}%` }))),
    h('ul', { class: 'landing-reach-legend' }, networks.map((n) => h('li', {},
      h('i', { style: { background: MIX_COLORS[n.key] }, 'aria-hidden': 'true' }),
      icon(`brand:${n.key}`, 13), n.label, h('b', {}, `${n.share}%`)))));
}

function skeleton() {
  return Array.from({ length: 6 }, () => h('div', { class: 'landing-reach-card is-skeleton', 'aria-hidden': 'true' },
    h('div', { class: 'sk sk-logo' }), h('div', { class: 'sk sk-num' }), h('div', { class: 'sk sk-line' }), h('div', { class: 'sk sk-line short' })));
}

/** @returns {HTMLElement} */
export function reachSection() {
  const grid = h('div', { class: 'landing-reach-grid', 'aria-busy': 'true' }, skeleton());
  const summary = h('div', { class: 'landing-reach-summary' });

  loadFeed().then((feed) => {
    const reach = feed.reach;
    if (!reach?.networks?.length) throw new Error('no reach');
    // Biggest first, so the grid reads like the mix bar.
    const networks = [...reach.networks].sort((a, b) => b.count - a.count);
    summary.replaceChildren(
      h('p', { class: 'landing-reach-total' },
        h('strong', {}, reach.headline || `${reach.total_label}+`),
        ` people follow along across ${networks.length} ${networks.length === 1 ? 'network' : 'networks'}`),
      changeChip(reach.total_change_30d),
      mixBar(networks));
    grid.replaceChildren(...networks.map(networkCard));
    grid.removeAttribute('aria-busy');
  }).catch(() => {
    grid.removeAttribute('aria-busy');
    grid.replaceChildren(...PROFILES.map(profileCard));
  });

  return h('section', { class: 'landing-section landing-reach', id: 'reach', 'aria-labelledby': 'landing-reach-title' },
    h('div', { class: 'landing-container' },
      h('div', { class: 'landing-section-head' },
        h('p', { class: 'landing-section-eyebrow' }, '05: Reach & Content'),
        h('h2', { id: 'landing-reach-title' }, 'Where the ', h('em', {}, 'internet'), ' finds me'),
        summary),
      grid));
}
