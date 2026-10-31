// Landing page "Content & Social Channels": her latest YouTube videos and the posts she published
// on Instagram, TikTok, X and Threads, from /api/landing/social-feed (rebuilt hourly by
// studio:landing, curated on her dashboard). Nothing here is sample data: a network without
// published posts shows a follow card. Clicks are counted with sendBeacon so links never wait.
import { h, api, compact, timeAgo } from './core.js';
import { icon } from './icons.js';

const TABS = [
  { id: 'youtube', icon: 'brand:youtube', label: 'YouTube', blurb: 'Deep dives on engineering, AI and tech careers.' },
  { id: 'instagram', icon: 'brand:instagram', label: 'Instagram', blurb: 'Reels and carousels that make tech feel obvious.' },
  { id: 'tiktok', icon: 'brand:tiktok', label: 'TikTok', blurb: 'Short, sharp takes on building with AI.' },
  { id: 'twitter', icon: 'brand:x', label: 'X', blurb: 'Raw engineering notes and hot takes.' },
  { id: 'threads', icon: 'brand:threads', label: 'Threads', blurb: 'Conversations about building in public.' },
];

const METRIC_ICONS = { views: 'eye', likes: 'heart', comments: 'chat', shares: 'repeat' };
const METRIC_LABELS = { views: 'views', likes: 'likes', comments: 'comments', shares: 'shares' };

/** Count a visitor opening an item; fire-and-forget, never blocks the link. */
export function countClick(key, platform) {
  try {
    const body = new Blob([JSON.stringify({ key, platform })], { type: 'application/json' });
    if (!navigator.sendBeacon?.('/api/landing/click', body)) {
      fetch('/api/landing/click', { method: 'POST', body, keepalive: true, headers: { 'Content-Type': 'application/json' } }).catch(() => {});
    }
  } catch { /* counting is best effort */ }
}

let feedPromise = null;
/** The landing feed, fetched once per page for both the content and the reach sections. */
export function loadFeed() {
  feedPromise ||= api('GET', 'landing/social-feed').catch((err) => { feedPromise = null; throw err; });
  return feedPromise;
}

const external = (href, props, ...children) => h('a', { href, target: '_blank', rel: 'noopener', ...props }, ...children);

function videoCard(v) {
  const meta = [v.tag || (v.date ? timeAgo(v.date) : null), v.views_label].filter(Boolean);
  return external(v.url, {
    class: 'landing-yt-card',
    title: `Watch “${v.title}” on YouTube`,
    onclick: () => countClick(v.key, 'youtube'),
  },
    h('div', { class: 'landing-yt-thumb-wrap' },
      h('img', { src: v.thumb, alt: '', class: 'landing-yt-thumb', loading: 'lazy', referrerpolicy: 'no-referrer', decoding: 'async' }),
      h('div', { class: 'landing-yt-play', 'aria-hidden': 'true' }, h('div', { class: 'landing-yt-play-circle' }, icon('play', 20))),
      v.pinned ? h('span', { class: 'landing-feed-pin' }, icon('pin', 11), 'Featured') : null,
      v.short ? h('span', { class: 'landing-yt-duration' }, 'Short') : v.duration ? h('span', { class: 'landing-yt-duration' }, v.duration) : null),
    h('div', { class: 'landing-yt-info' },
      meta.length ? h('div', { class: 'landing-yt-meta' }, meta.flatMap((m, i) => (i ? [h('span', { class: 'dot', 'aria-hidden': 'true' }, '·'), h('span', {}, m)] : [h('span', {}, m)]))) : null,
      h('h3', { class: 'landing-yt-title' }, v.title),
      v.desc ? h('p', { class: 'landing-yt-desc' }, v.desc) : null));
}

function postCard(p, tab, feed) {
  const metrics = Object.entries(p.metrics || {});
  return external(p.url, {
    class: `landing-post-card${p.thumb ? ' has-media' : ''}`,
    title: `Open on ${feed.labels?.[tab.id] || tab.label}`,
    onclick: () => countClick(p.key, tab.id),
  },
    h('div', { class: 'landing-post-head' },
      h('span', { class: 'landing-post-avatar', 'aria-hidden': 'true' }, 'D'),
      h('div', { class: 'landing-post-who' },
        h('strong', {}, 'Delia Lazarescu'),
        h('span', { class: 'muted' }, [feed.handles?.[tab.id], p.date ? timeAgo(p.date) : null].filter(Boolean).join(' · '))),
      h('span', { class: 'landing-post-net', 'aria-hidden': 'true' }, icon(tab.icon, 16))),
    p.pinned ? h('span', { class: 'landing-feed-pin inline' }, icon('pin', 11), 'Featured') : null,
    p.thumb ? h('div', { class: 'landing-post-media' }, h('img', { src: p.thumb, alt: p.alt || '', loading: 'lazy', decoding: 'async' })) : null,
    h('p', { class: 'landing-post-text' }, p.text || p.title),
    h('div', { class: 'landing-post-foot' },
      metrics.length ? h('div', { class: 'landing-post-metrics' }, metrics.map(([k, n]) => h('span', { title: `${n.toLocaleString()} ${METRIC_LABELS[k]}` },
        icon(METRIC_ICONS[k], 13), compact(n), h('span', { class: 'sr-only' }, ` ${METRIC_LABELS[k]}`)))) : h('span'),
      h('span', { class: 'landing-post-open' }, 'Open', icon('external', 12))));
}

/** Always last in a network's row (or alone when nothing is published there yet). */
function followCard(tab, feed, empty) {
  const f = feed.followers?.[tab.id];
  return external(feed.urls?.[tab.id] || '#', {
    class: `landing-follow-card${empty ? ' is-empty' : ''}`,
    onclick: () => countClick(`profile:${tab.id}`, tab.id),
  },
    h('span', { class: 'landing-follow-icon', 'aria-hidden': 'true' }, icon(tab.icon, 26)),
    h('strong', {}, empty ? `Catch the latest on ${feed.labels?.[tab.id] || tab.label}` : `More on ${feed.labels?.[tab.id] || tab.label}`),
    h('p', { class: 'muted' }, tab.blurb),
    f ? h('span', { class: 'landing-follow-count' }, h('b', {}, f.label), ` ${f.unit}`) : null,
    h('span', { class: 'landing-follow-btn' }, feed.actions?.[tab.id] || `Follow ${feed.handles?.[tab.id] || ''}`, icon('external', 12)));
}

function skeletonCards() {
  return Array.from({ length: 3 }, () => h('div', { class: 'landing-yt-card landing-feed-skeleton', 'aria-hidden': 'true' },
    h('div', { class: 'landing-yt-thumb-wrap sk' }), h('div', { class: 'landing-yt-info' }, h('div', { class: 'sk sk-line' }), h('div', { class: 'sk sk-line short' }))));
}

/**
 * @param {{ onFeed?: (feed) => void }} options onFeed gets the loaded feed (the page uses its headline).
 * @returns {{ el: HTMLElement, destroy: () => void }}
 */
export function contentSection({ onFeed } = {}) {
  let feed = null;
  let active = 'youtube';

  const rail = h('div', { class: 'landing-yt-track', id: 'landing-social-track', role: 'tabpanel', tabindex: '0', 'aria-busy': 'true' }, skeletonCards());
  const status = h('p', { class: 'landing-feed-status muted', 'aria-live': 'polite' });
  const visitBtn = external('https://youtube.com/@Tech.Unicorn', { class: 'landing-process-btn no-underline', onclick: () => countClick(`profile:${active}`, active) }, 'Visit YouTube', icon('external', 13));
  const prevBtn = h('button', { type: 'button', class: 'landing-yt-nav-btn', 'aria-label': 'Previous', 'aria-controls': 'landing-social-track', disabled: true, onclick: () => scroll(-1) }, icon('chevron-left', 16));
  const nextBtn = h('button', { type: 'button', class: 'landing-yt-nav-btn', 'aria-label': 'Next', 'aria-controls': 'landing-social-track', onclick: () => scroll(1) }, icon('chevron-right', 16));

  function scroll(dir) {
    const card = rail.firstElementChild;
    const step = card ? card.getBoundingClientRect().width + 20 : 340;
    rail.scrollBy({ left: dir * step, behavior: 'smooth' });
  }
  function updateArrows() {
    const max = rail.scrollWidth - rail.clientWidth - 2;
    prevBtn.disabled = rail.scrollLeft <= 2;
    nextBtn.disabled = rail.scrollLeft >= max;
  }
  rail.addEventListener('scroll', updateArrows, { passive: true });
  rail.addEventListener('keydown', (e) => {
    if (e.key === 'ArrowRight' || e.key === 'ArrowLeft') { e.preventDefault(); scroll(e.key === 'ArrowRight' ? 1 : -1); }
  });
  const ro = typeof ResizeObserver === 'function' ? new ResizeObserver(updateArrows) : null;
  ro?.observe(rail);

  const tabButtons = TABS.map((tab) => h('button', {
    type: 'button',
    class: `landing-media-tab${tab.id === active ? ' is-active' : ''}`,
    role: 'tab',
    id: `landing-tab-${tab.id}`,
    'aria-selected': String(tab.id === active),
    'aria-controls': 'landing-social-track',
    tabindex: tab.id === active ? '0' : '-1',
    onclick: () => select(tab.id),
    onkeydown: (e) => {
      const i = TABS.findIndex((t) => t.id === active);
      const next = { ArrowRight: i + 1, ArrowLeft: i - 1, Home: 0, End: TABS.length - 1 }[e.key];
      if (next === undefined) return;
      e.preventDefault();
      select(TABS[(next + TABS.length) % TABS.length].id, true);
    },
  }, icon(tab.icon, 16), h('span', {}, tab.label), h('span', { class: 'landing-media-tab-badge', 'data-badge': tab.id })));
  const tabs = h('div', { class: 'landing-media-tabs', role: 'tablist', 'aria-label': 'Choose a platform' }, tabButtons);

  function select(id, focus = false) {
    active = id;
    tabButtons.forEach((b, i) => {
      const on = TABS[i].id === id;
      b.classList.toggle('is-active', on);
      b.setAttribute('aria-selected', String(on));
      b.tabIndex = on ? 0 : -1;
      if (on && focus) b.focus();
    });
    rail.setAttribute('aria-labelledby', `landing-tab-${id}`);
    render();
  }

  function render() {
    if (!feed) return;
    const tab = TABS.find((t) => t.id === active);
    const items = feed.platforms?.[active] || [];
    const cards = items.map((it) => (it.type === 'video' ? videoCard(it) : postCard(it, tab, feed)));
    rail.replaceChildren(...cards, followCard(tab, feed, !items.length));
    rail.classList.toggle('is-sparse', items.length < 2);
    rail.scrollTo({ left: 0 });
    rail.removeAttribute('aria-busy');
    visitBtn.href = feed.urls?.[active] || visitBtn.href;
    visitBtn.replaceChildren(feed.actions?.[active] || `Visit ${tab.label}`, icon('external', 13));
    const label = feed.labels?.[active] || tab.label;
    status.textContent = items.length
      ? `${items.length} latest from ${label}${feed.updated_at ? ` · updated ${timeAgo(feed.updated_at)}` : ''}`
      : `Follow ${feed.handles?.[active] || ''} on ${label} for new posts.`;
    requestAnimationFrame(updateArrows);
  }

  loadFeed().then((res) => {
    feed = res;
    for (const tab of TABS) {
      const f = res.followers?.[tab.id];
      const badge = tabs.querySelector(`[data-badge="${tab.id}"]`);
      if (f && badge) {
        badge.textContent = f.label;
        badge.title = `${Number(f.count).toLocaleString()} ${f.unit}`;
      }
    }
    render();
    onFeed?.(res);
  }).catch(() => {
    rail.removeAttribute('aria-busy');
    rail.replaceChildren(...TABS.map((t) => followCard(t, { urls: {}, handles: {}, labels: {}, actions: {} }, true)));
    tabs.hidden = true;
    status.textContent = '';
  });

  const el = h('section', { class: 'landing-section landing-yt-section', id: 'youtube', 'aria-labelledby': 'landing-content-title' },
    h('div', { class: 'landing-container' },
      h('span', { id: 'content', class: 'landing-anchor' }),
      h('div', { class: 'landing-yt-head' },
        h('div', {},
          h('p', { class: 'landing-section-eyebrow' }, '02b: Content & Social Channels'),
          h('h2', { id: 'landing-content-title', style: { margin: 0 } }, 'Watch, listen, and follow across ', h('em', {}, 'platforms')),
          h('p', { class: 'muted landing-yt-sub' },
            'From deep dives on YouTube to 60-second reels on Instagram and raw engineering notes on X and Threads.')),
        h('div', { class: 'landing-yt-controls' }, visitBtn, prevBtn, nextBtn)),
      tabs,
      rail,
      status));

  return { el, destroy: () => ro?.disconnect() };
}
