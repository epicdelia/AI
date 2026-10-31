// Landing page "Reach & Content": all six networks with their official logos, follower counts
// (synced ones once she connects or types them on her dashboard, else her public figures), the
// real 30-day change and trend, posts this month and engagement from her own published posts, and
// each network's share of her audience. Networks she hides on the dashboard aren't sent at all.
import { h, compact, timeAgo } from './core.js';
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

/** Cards shown before "Show all networks" (biggest first). */
const SHOWN = 3;

function networkCard(n, i) {
  const facts = [
    n.posts_30d ? `${n.posts_30d} ${n.posts_30d === 1 ? 'post' : 'posts'} this month` : null,
    n.engagement_rate !== null && n.engagement_rate !== undefined ? `${n.engagement_rate}% engagement` : null,
  ].filter(Boolean);
  return h('a', {
    href: n.url,
    target: '_blank',
    rel: 'noopener',
    class: `landing-reach-card net-${n.key}${i >= SHOWN ? ' is-more' : ''}`,
    'data-net': n.key,
    'aria-label': `${n.label}: ${Number(n.count).toLocaleString()} ${n.unit}. Follow ${n.handle}`,
    onclick: () => countClick(`profile:${n.key}`, n.key),
  },
    h('div', { class: 'landing-reach-top' },
      h('span', { class: 'landing-reach-logo' }, icon(`brand:${n.key}`, 24)),
      h('div', { class: 'landing-reach-who' },
        h('strong', {}, n.label),
        h('span', { class: 'muted' }, n.handle)),
      h('span', { class: 'landing-reach-rank', title: `Her #${i + 1} network by followers` }, `#${i + 1}`),
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

/**
 * The summary panel: her total on the left, the audience mix on the right. Hovering or focusing a
 * legend item lights up its bar segment and card; clicking it scrolls to the card.
 */
function summaryPanel(reach, networks, updatedAt, grid, reveal) {
  const segments = networks.map((n) => h('span', {
    class: 'landing-reach-seg', 'data-net': n.key, title: `${n.label}: ${n.share}%`,
    style: { flexGrow: String(Math.max(n.share, 0.6)), background: MIX_COLORS[n.key] },
  }));
  const bar = h('div', { class: 'landing-reach-bar', role: 'img', 'aria-label': `Audience mix: ${networks.map((n) => `${n.label} ${n.share}%`).join(', ')}` }, segments);

  const panel = h('div', { class: 'landing-reach-panel' });
  const highlight = (key) => {
    panel.classList.toggle('is-focusing', !!key);
    grid.classList.toggle('is-focusing', !!key);
    for (const el of [...segments, ...panel.querySelectorAll('.landing-reach-legend-item'), ...grid.querySelectorAll('.landing-reach-card')]) {
      el.classList.toggle('is-active', el.dataset.net === key);
    }
  };
  const goTo = (key) => {
    const card = grid.querySelector(`.landing-reach-card[data-net="${key}"]`);
    if (!card) return;
    if (card.classList.contains('is-more')) reveal(); // a hidden network: show them all first
    card.scrollIntoView({ behavior: reduceMotion() ? 'auto' : 'smooth', block: 'center' });
    card.classList.remove('is-flash');
    void card.offsetWidth; // restart the flash
    card.classList.add('is-flash');
    card.focus({ preventScroll: true });
  };

  const legend = h('ul', { class: 'landing-reach-legend' }, networks.map((n) => h('li', {},
    h('button', {
      type: 'button', class: 'landing-reach-legend-item', 'data-net': n.key,
      'aria-label': `${n.label}: ${n.share}% of her audience, ${Number(n.count).toLocaleString()} ${n.unit}. Show the card`,
      onmouseenter: () => highlight(n.key), onmouseleave: () => highlight(null),
      onfocus: () => highlight(n.key), onblur: () => highlight(null),
      onclick: () => goTo(n.key),
    },
      h('i', { style: { background: MIX_COLORS[n.key] }, 'aria-hidden': 'true' }),
      icon(`brand:${n.key}`, 14),
      h('span', { class: 'landing-reach-legend-name' }, n.label),
      h('b', {}, `${n.share}%`),
      h('span', { class: 'landing-reach-legend-count' }, n.count_label)))));

  const live = reach.live_networks || 0;
  const facts = [
    live ? `${live} of ${networks.length} synced live` : null,
    updatedAt ? `updated ${timeAgo(updatedAt)}` : null,
  ].filter(Boolean);

  panel.append(
    h('div', { class: 'landing-reach-total' },
      h('strong', { class: 'landing-reach-total-num' }, reach.headline || `${reach.total_label}+`),
      h('p', { class: 'landing-reach-total-label' }, `people follow along across ${networks.length} ${networks.length === 1 ? 'network' : 'networks'}`),
      h('div', { class: 'landing-reach-total-meta' },
        changeChip(reach.total_change_30d),
        facts.length ? h('span', { class: 'muted small' }, facts.join(' · ')) : null)),
    h('div', { class: 'landing-reach-mix' },
      h('div', { class: 'landing-reach-mix-head' },
        h('span', {}, 'Audience mix'),
        h('span', { class: 'muted' }, `Largest: ${networks[0].label} ${networks[0].share}%`)),
      bar,
      legend));

  // The bar fills in from the left the first time it's on screen.
  if (!reduceMotion() && typeof IntersectionObserver === 'function') {
    const io = new IntersectionObserver((entries) => {
      if (!entries.some((e) => e.isIntersecting)) return;
      io.disconnect();
      requestAnimationFrame(() => bar.classList.add('is-in'));
    }, { threshold: 0.5 });
    io.observe(bar);
  } else {
    bar.classList.add('is-in');
  }
  return panel;
}

function skeleton() {
  return Array.from({ length: SHOWN }, () => h('div', { class: 'landing-reach-card is-skeleton', 'aria-hidden': 'true' },
    h('div', { class: 'sk sk-logo' }), h('div', { class: 'sk sk-num' }), h('div', { class: 'sk sk-line' }), h('div', { class: 'sk sk-line short' })));
}

/** @returns {HTMLElement} */
export function reachSection() {
  const grid = h('div', { class: 'landing-reach-grid', id: 'landing-reach-grid', 'aria-busy': 'true' }, skeleton());
  const more = h('div', { class: 'landing-service-all' });
  const summary = h('div', { class: 'landing-reach-summary' });

  loadFeed().then((feed) => {
    const reach = feed.reach;
    if (!reach?.networks?.length) throw new Error('no reach');
    // Biggest first, so the grid reads like the mix bar.
    const networks = [...reach.networks].sort((a, b) => b.count - a.count);
    grid.replaceChildren(...networks.map(networkCard));
    const hidden = networks.length - SHOWN;
    const label = (open) => (open ? 'Show fewer networks' : `Show all ${networks.length} networks`);
    const setOpen = (open) => {
      grid.classList.toggle('show-all', open);
      moreBtn.setAttribute('aria-expanded', String(open));
      moreBtn.replaceChildren(label(open), icon('chevron-down', 14));
    };
    const moreBtn = h('button', {
      type: 'button', class: 'landing-service-all-btn', 'aria-expanded': 'false', 'aria-controls': 'landing-reach-grid',
      onclick: (e) => {
        const open = !grid.classList.contains('show-all');
        setOpen(open);
        // Keyboard users land on the first revealed card; closing brings the grid back into view.
        if (open && e.detail === 0) grid.querySelector('.landing-reach-card.is-more')?.focus();
        if (!open) grid.scrollIntoView({ behavior: reduceMotion() ? 'auto' : 'smooth', block: 'nearest' });
      },
    }, label(false), icon('chevron-down', 14));
    more.replaceChildren(...(hidden > 0 ? [moreBtn] : []));
    summary.replaceChildren(summaryPanel(reach, networks, feed.updated_at, grid, () => setOpen(true)));
    grid.removeAttribute('aria-busy');
  }).catch(() => {
    grid.removeAttribute('aria-busy');
    grid.replaceChildren(...PROFILES.map(profileCard));
  });

  return h('section', { class: 'landing-section landing-reach', id: 'reach', 'aria-labelledby': 'landing-reach-title' },
    h('div', { class: 'landing-container' },
      h('div', { class: 'landing-section-head' },
        h('p', { class: 'landing-section-eyebrow' }, '05: Reach & Content'),
        h('h2', { id: 'landing-reach-title' }, 'Where the ', h('em', {}, 'internet'), ' finds me')),
      summary,
      grid,
      more));
}
