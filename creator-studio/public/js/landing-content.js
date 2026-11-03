// Landing page "Content & Social Channels": her latest YouTube videos and the posts she published
// on Instagram, TikTok, X and Threads, from /api/landing/social-feed (rebuilt hourly by
// studio:landing, curated on her dashboard). Includes in-place responsive inline player for YouTube.
import { h, api, compact, timeAgo, ensureLandingCss, modal } from './core.js';
import { icon } from './icons.js';
import { rememberItem } from './landing-tracking.js';

ensureLandingCss(); // its styles live in landing.css

const TABS = [
  { id: 'youtube', icon: 'brand:youtube', label: 'YouTube', blurb: 'Deep dives on engineering, AI and tech careers.' },
  { id: 'instagram', icon: 'brand:instagram', label: 'Instagram', blurb: 'Reels and carousels that make tech feel obvious.' },
  { id: 'tiktok', icon: 'brand:tiktok', label: 'TikTok', blurb: 'Short, sharp takes on building with AI.' },
  { id: 'twitter', icon: 'brand:x', label: 'X', blurb: 'Raw engineering notes and hot takes.' },
  { id: 'threads', icon: 'brand:threads', label: 'Threads', blurb: 'Conversations about building in public.' },
];

const METRIC_ICONS = { views: 'eye', likes: 'heart', comments: 'chat', shares: 'repeat' };
const METRIC_LABELS = { views: 'views', likes: 'likes', comments: 'comments', shares: 'shares' };

/** Helper to generate inline SVGs for social action bars */
function makeSvg(viewBox, pathD, attrs = {}) {
  const ns = 'http://www.w3.org/2000/svg';
  const el = document.createElementNS(ns, 'svg');
  el.setAttribute('viewBox', viewBox);
  el.setAttribute('width', attrs.width || '14');
  el.setAttribute('height', attrs.height || '14');
  el.setAttribute('aria-hidden', 'true');
  if (attrs.fill) el.setAttribute('fill', attrs.fill);
  if (attrs.stroke) {
    el.setAttribute('stroke', attrs.stroke);
    el.setAttribute('stroke-width', attrs['stroke-width'] || '1.9');
    el.setAttribute('stroke-linecap', 'round');
    el.setAttribute('stroke-linejoin', 'round');
  }
  const path = document.createElementNS(ns, 'path');
  path.setAttribute('d', pathD);
  el.appendChild(path);
  return el;
}

const verifiedBadgeSvg = () => makeSvg('0 0 24 24', 'M22.25 12c0-1.43-.88-2.67-2.19-3.34.46-1.39.2-2.9-.81-3.91s-2.52-1.27-3.91-.81c-.66-1.31-1.91-2.19-3.34-2.19s-2.67.88-3.33 2.19c-1.4-.46-2.91-.2-3.92.81s-1.26 2.52-.8 3.91c-1.31.67-2.2 1.91-2.2 3.34s.89 2.67 2.2 3.34c-.46 1.39-.21 2.9.8 3.91s2.52 1.26 3.91.81c.67 1.31 1.91 2.19 3.34 2.19s2.68-.88 3.34-2.19c1.39.45 2.9.2 3.91-.81s1.27-2.52.81-3.91c1.31-.67 2.19-1.91 2.19-3.34zm-11.71 4.2L6.8 12.46l1.41-1.42 2.26 2.26 4.8-5.23 1.47 1.36-6.2 6.77z', { fill: '#1d9bf0', width: '13', height: '13' });
const airplaneSvg = () => makeSvg('0 0 24 24', 'm22 2-7 20-4-9-9-4Z M22 2 11 13', { fill: 'none', stroke: 'currentColor', width: '13', height: '13' });
const bookmarkSvg = () => makeSvg('0 0 24 24', 'm19 21-7-4-7 4V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v16z', { fill: 'none', stroke: 'currentColor', width: '13', height: '13' });
const threadArrowSvg = () => makeSvg('0 0 24 24', 'M17 2l4 4-4 4 M3 11V9a3 3 0 0 1 3-3h15 M7 22l-4-4 4-4 M21 13v2a3 3 0 0 1-3 3H3', { fill: 'none', stroke: 'currentColor', width: '13', height: '13' });

/** Count a visitor opening an item; fire-and-forget, never blocks the link. */
export function countClick(key, platform) {
  rememberItem(key);
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

/** Track single active inline video playback: starting another resets this one to thumbnail */
let activeInlineReset = null;

/** Legacy fallback modal player for standalone triggers */
export function openInternalPlayer(v) {
  countClick(v.key, 'youtube');
  const iframe = h('iframe', {
    src: `https://www.youtube-nocookie.com/embed/${encodeURIComponent(v.id)}?autoplay=1&rel=0&modestbranding=1`,
    title: v.title,
    allow: 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share',
    allowfullscreen: 'true',
    style: {
      width: '100%',
      aspectRatio: '16/9',
      border: '0',
      borderRadius: '12px',
      boxShadow: '0 8px 32px rgba(0,0,0,0.4)',
      display: 'block',
    },
  });

  const header = h('div', { class: 'row-between', style: { marginBottom: '14px', alignItems: 'center' } },
    h('div', {},
      h('strong', { style: { fontSize: '16px', display: 'block', color: 'var(--text)' } }, v.title),
      h('span', { class: 'muted small' }, 'Delia (@Tech.Unicorn) · YouTube', v.views_label ? ` · ${v.views_label}` : '')),
    h('a', {
      class: 'btn ghost sm',
      href: v.url,
      target: '_blank',
      rel: 'noopener',
      style: { gap: '6px' },
    }, icon('external', 13), 'Open on YouTube'));

  const container = h('div', {
    class: 'landing-player-modal-body',
    style: { width: '100%', maxWidth: '860px', margin: '0 auto' },
  }, header, iframe);

  modal(v.title, () => container, { wide: true });
}

/**
 * YouTube Video Card: plays inline in-place when pressed, matching hero video behavior.
 */
function videoCard(v) {
  const meta = [v.tag || (v.date ? timeAgo(v.date) : null), v.views_label].filter(Boolean);
  const box = h('article', {
    class: 'landing-yt-card',
    id: `landing-yt-${v.id}`,
    'data-key': v.key,
  });

  const posterWrap = h('div', { class: 'landing-yt-thumb-wrap' });

  const renderPoster = () => h('button', {
    type: 'button',
    class: 'landing-yt-poster-btn',
    'aria-label': `Play “${v.title}” inline`,
    onclick: (e) => {
      e.preventDefault();
      play();
    },
  },
    h('img', { src: v.thumb, alt: v.title, class: 'landing-yt-thumb', loading: 'lazy', referrerpolicy: 'no-referrer', decoding: 'async' }),
    h('div', { class: 'landing-yt-play', 'aria-hidden': 'true' }, h('div', { class: 'landing-yt-play-circle' }, icon('play', 20))),
    v.pinned ? h('span', { class: 'landing-feed-pin' }, icon('pin', 11), 'Featured') : null,
    v.short ? h('span', { class: 'landing-yt-duration' }, 'Short') : v.duration ? h('span', { class: 'landing-yt-duration' }, v.duration) : null
  );

  function reset() {
    box.classList.remove('is-playing');
    posterWrap.replaceChildren(renderPoster());
  }

  function play() {
    if (activeInlineReset && activeInlineReset !== reset) {
      activeInlineReset();
    }
    activeInlineReset = reset;
    countClick(v.key, 'youtube');
    box.classList.add('is-playing');

    const iframe = h('iframe', {
      src: `https://www.youtube-nocookie.com/embed/${encodeURIComponent(v.id)}?autoplay=1&rel=0&modestbranding=1&playsinline=1`,
      title: v.title,
      allow: 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share',
      allowfullscreen: 'true',
      referrerpolicy: 'strict-origin-when-cross-origin',
      class: 'landing-yt-inline-iframe',
    });

    const outBtn = h('a', {
      class: 'landing-yt-out',
      href: v.url,
      target: '_blank',
      rel: 'noopener',
      title: 'Watch on YouTube',
      onclick: (e) => e.stopPropagation(),
    }, icon('external', 11), ' YouTube');

    posterWrap.replaceChildren(iframe, outBtn);
    iframe.focus?.();
  }

  posterWrap.replaceChildren(renderPoster());

  const info = h('div', { class: 'landing-yt-info' },
    meta.length ? h('div', { class: 'landing-yt-meta' }, meta.flatMap((m, i) => (i ? [h('span', { class: 'dot', 'aria-hidden': 'true' }, '·'), h('span', {}, m)] : [h('span', {}, m)]))) : null,
    h('h3', { class: 'landing-yt-title' },
      h('a', {
        href: v.url,
        class: 'landing-yt-title-link',
        onclick: (e) => {
          if (!e.ctrlKey && !e.metaKey && !e.shiftKey) {
            e.preventDefault();
            play();
          }
        },
      }, v.title)),
    v.desc ? h('p', { class: 'landing-yt-desc' }, v.desc) : null,
    h('div', { class: 'landing-yt-card-foot' },
      h('span', { class: 'landing-yt-watch-btn', onclick: () => play() }, icon('play', 12), ' Play inline'),
      h('a', { href: v.url, target: '_blank', rel: 'noopener', class: 'landing-yt-card-link' }, 'Open on YouTube', icon('external', 11))));

  box.replaceChildren(posterWrap, info);
  box.play = play;
  box.reset = reset;
  return box;
}

/**
 * X (Twitter) Card: Authentic text-first tweet layout.
 * No forced placeholder image for tweets that are text-only.
 */
function xCard(p, tab, feed) {
  const metrics = p.metrics || {};
  const handle = feed.handles?.twitter || '@tech__unicorn';
  // Authentic tweets are primarily text-first. Ignore generic stock images so tweets render natively.
  const hasMedia = p.thumb && !p.thumb.includes('unsplash');

  return external(p.url, {
    class: `landing-post-card is-x${hasMedia ? ' has-media' : ' text-only'}`,
    title: 'Open on X',
    onclick: () => countClick(p.key, 'twitter'),
  },
    h('div', { class: 'landing-post-head' },
      h('span', { class: 'landing-post-avatar x-avatar', 'aria-hidden': 'true' }, 'D'),
      h('div', { class: 'landing-post-who' },
        h('div', { class: 'landing-x-author-row' },
          h('strong', {}, 'Delia Lazarescu'),
          verifiedBadgeSvg()),
        h('span', { class: 'muted' }, [handle, p.date ? timeAgo(p.date) : null].filter(Boolean).join(' · '))),
      h('span', { class: 'landing-post-net', 'aria-hidden': 'true' }, icon('brand:x', 15))),

    p.pinned ? h('span', { class: 'landing-feed-pin inline' }, icon('pin', 11), 'Featured') : null,

    hasMedia ? h('div', { class: 'landing-post-media x-media' },
      h('img', { src: p.thumb, alt: p.alt || '', loading: 'lazy', decoding: 'async' })) : null,

    h('p', { class: 'landing-post-text x-text' }, p.text || p.title),

    h('div', { class: 'landing-post-foot x-foot' },
      h('div', { class: 'landing-x-actions' },
        h('span', { class: 'x-action-item', title: `${(metrics.comments || 0).toLocaleString()} comments` },
          icon('chat', 13), h('span', {}, compact(metrics.comments || 0))),
        h('span', { class: 'x-action-item', title: `${(metrics.shares || 0).toLocaleString()} reposts` },
          threadArrowSvg(), h('span', {}, compact(metrics.shares || 0))),
        h('span', { class: 'x-action-item is-heart', title: `${(metrics.likes || 0).toLocaleString()} likes` },
          icon('heart', 13), h('span', {}, compact(metrics.likes || 0))),
        metrics.impressions ? h('span', { class: 'x-action-item', title: `${metrics.impressions.toLocaleString()} views` },
          icon('chart', 13), h('span', {}, compact(metrics.impressions))) : null,
        h('span', { class: 'x-action-item is-out' },
          icon('external', 12)))));
}

/**
 * Threads Card: Minimalist conversation layout matching Threads.
 */
function threadsCard(p, tab, feed) {
  const metrics = p.metrics || {};
  const handle = feed.handles?.threads || '@tech.unicorn';
  // Authentic Threads are conversation and text-driven. Filter out placeholder stock images.
  const hasMedia = p.thumb && !p.thumb.includes('unsplash');

  return external(p.url, {
    class: `landing-post-card is-threads${hasMedia ? ' has-media' : ' text-only'}`,
    title: 'Open on Threads',
    onclick: () => countClick(p.key, 'threads'),
  },
    h('div', { class: 'landing-post-head' },
      h('div', { class: 'landing-threads-avatar-wrap' },
        h('span', { class: 'landing-post-avatar threads-avatar', 'aria-hidden': 'true' }, 'D'),
        h('span', { class: 'landing-threads-spine', 'aria-hidden': 'true' })),
      h('div', { class: 'landing-post-who' },
        h('div', { class: 'landing-threads-author-row' },
          h('strong', {}, handle.replace('@', '')),
          h('span', { class: 'muted threads-dot' }, '·'),
          h('span', { class: 'muted' }, p.date ? timeAgo(p.date) : 'recent')),
        h('span', { class: 'muted small' }, 'Delia Lazarescu')),
      h('span', { class: 'landing-post-net', 'aria-hidden': 'true' }, icon('brand:threads', 16))),

    p.pinned ? h('span', { class: 'landing-feed-pin inline' }, icon('pin', 11), 'Featured') : null,

    hasMedia ? h('div', { class: 'landing-post-media threads-media' },
      h('img', { src: p.thumb, alt: p.alt || '', loading: 'lazy', decoding: 'async' })) : null,

    h('p', { class: 'landing-post-text threads-text' }, p.text || p.title),

    h('div', { class: 'landing-post-foot threads-foot' },
      h('div', { class: 'landing-threads-actions' },
        h('span', { class: 'threads-action-btn' }, icon('heart', 14)),
        h('span', { class: 'threads-action-btn' }, icon('chat', 14)),
        h('span', { class: 'threads-action-btn' }, threadArrowSvg()),
        h('span', { class: 'threads-action-btn' }, airplaneSvg())),
      h('div', { class: 'landing-threads-metrics' },
        metrics.likes ? h('span', {}, `${compact(metrics.likes)} likes`) : null,
        metrics.comments ? h('span', {}, `${compact(metrics.comments)} replies`) : null,
        icon('external', 11))));
}

/**
 * Instagram Card: Visual-first Reel / Carousel card with story ring and view badge.
 */
function instagramCard(p, tab, feed) {
  const metrics = p.metrics || {};
  const handle = feed.handles?.instagram || '@tech.unicorn';
  const hasVideo = p.format === 'reel' || p.format === 'video';

  return external(p.url, {
    class: 'landing-post-card is-instagram has-media',
    title: 'Open on Instagram',
    onclick: () => countClick(p.key, 'instagram'),
  },
    h('div', { class: 'landing-post-head' },
      h('div', { class: 'landing-ig-avatar-ring' },
        h('span', { class: 'landing-post-avatar ig-avatar', 'aria-hidden': 'true' }, 'D')),
      h('div', { class: 'landing-post-who' },
        h('strong', {}, handle.replace('@', '')),
        h('span', { class: 'muted small' }, hasVideo ? 'Reel · Original audio' : (p.date ? timeAgo(p.date) : 'Post'))),
      h('span', { class: 'landing-post-net', 'aria-hidden': 'true' }, icon('brand:instagram', 16))),

    p.thumb ? h('div', { class: 'landing-post-media ig-media' },
      h('img', { src: p.thumb, alt: p.alt || '', loading: 'lazy', decoding: 'async' }),
      hasVideo ? h('span', { class: 'landing-ig-reel-badge' }, icon('play', 12), metrics.impressions ? ` ${compact(metrics.impressions)}` : ' Reel') : null) : null,

    h('p', { class: 'landing-post-text ig-text' },
      h('strong', { class: 'ig-caption-author' }, handle.replace('@', '') + ' '),
      p.text || p.title),

    h('div', { class: 'landing-post-foot ig-foot' },
      h('div', { class: 'landing-ig-actions' },
        h('span', { class: 'ig-action', title: `${(metrics.likes || 0).toLocaleString()} likes` },
          icon('heart', 14), compact(metrics.likes || 0)),
        h('span', { class: 'ig-action', title: `${(metrics.comments || 0).toLocaleString()} comments` },
          icon('chat', 14), compact(metrics.comments || 0)),
        h('span', { class: 'ig-action' }, airplaneSvg())),
      h('span', { class: 'ig-action is-bookmark' }, bookmarkSvg())));
}

/**
 * TikTok Card: Vertical video card with dark overlay and sound ticker.
 */
function tiktokCard(p, tab, feed) {
  const metrics = p.metrics || {};
  const handle = feed.handles?.tiktok || '@tech.unicorn';

  return external(p.url, {
    class: 'landing-post-card is-tiktok has-media',
    title: 'Open on TikTok',
    onclick: () => countClick(p.key, 'tiktok'),
  },
    h('div', { class: 'landing-post-head' },
      h('span', { class: 'landing-post-avatar tt-avatar', 'aria-hidden': 'true' }, 'D'),
      h('div', { class: 'landing-post-who' },
        h('strong', {}, handle.replace('@', '')),
        h('span', { class: 'muted' }, 'Delia · ' + (p.date ? timeAgo(p.date) : 'TikTok'))),
      h('span', { class: 'landing-post-net', 'aria-hidden': 'true' }, icon('brand:tiktok', 15))),

    p.thumb ? h('div', { class: 'landing-post-media tt-media' },
      h('img', { src: p.thumb, alt: p.alt || '', loading: 'lazy', decoding: 'async' }),
      h('span', { class: 'landing-tt-views' }, icon('play', 11), metrics.impressions ? ` ${compact(metrics.impressions)}` : ' Watch')) : null,

    h('p', { class: 'landing-post-text tt-text' }, p.text || p.title),

    h('div', { class: 'landing-tt-sound' },
      h('span', { class: 'tt-sound-icon' }, '♫'),
      h('span', { class: 'tt-sound-title' }, `original sound - ${handle.replace('@', '')}`)),

    h('div', { class: 'landing-post-foot tt-foot' },
      h('div', { class: 'landing-tt-metrics' },
        h('span', {}, icon('heart', 13), compact(metrics.likes || 0)),
        h('span', {}, icon('chat', 13), compact(metrics.comments || 0)),
        h('span', {}, threadArrowSvg(), compact(metrics.shares || 0))),
      h('span', { class: 'landing-post-open' }, 'Open', icon('external', 11))));
}

/** Default fallback post card */
function postCard(p, tab, feed) {
  const metrics = Object.entries(p.metrics || {});
  return external(p.url, {
    class: `landing-post-card${p.thumb ? ' has-media' : ' text-only'}`,
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
    // When switching tabs, reset any currently playing inline video
    if (activeInlineReset) {
      activeInlineReset();
      activeInlineReset = null;
    }
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
    const cards = items.map((it) => {
      if (it.type === 'video' || active === 'youtube') return videoCard(it);
      if (active === 'twitter') return xCard(it, tab, feed);
      if (active === 'threads') return threadsCard(it, tab, feed);
      if (active === 'instagram') return instagramCard(it, tab, feed);
      if (active === 'tiktok') return tiktokCard(it, tab, feed);
      return postCard(it, tab, feed);
    });

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

  return { el, destroy: () => { ro?.disconnect(); if (activeInlineReset) activeInlineReset(); } };
}
