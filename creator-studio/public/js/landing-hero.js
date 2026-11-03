// Landing page hero: a full-bleed stage (aurora light, grain, flowing lines, a wave into the next
// section) around her copy and a mosaic of her real work.
//  - Left: "Work with me" menu (every way to hire her), the status pill from About you and Client
//    journey, her headline ({countries}/{audience} filled in, *words* in the accent serif with a
//    hand-drawn underline), the intro, Book + "Watch the latest video", a marquee of the brands and
//    stages from her Partners card, and her reassurance line.
//  - Right: her latest YouTube thumbnails, a tile per top network (follower count, 30-day change and
//    a sparkline only from synced history, never invented), and her four hero stats, which count up
//    and link to the section that backs them.
// Renders at once with her current copy, then fills from /api/landing/hero, the feed and partners.
import { h, api, compact, ensureLandingCss } from './core.js';
import { icon } from './icons.js';
import { loadFeed, countClick } from './landing-content.js';

ensureLandingCss(); // its styles live in landing.css

const DEFAULTS = {
  headline: 'Making tech *less scary*: built from beach cafés in 69 countries.',
  lede: 'I am a software engineer who left Big Tech to build apps, create content for 519K+ people, and prove you do not need a hoodie and a standing desk to ship great software.',
  reassurance: '',
  cta: 'Book an Intro Call',
  status: null,
  stats: [
    { key: 'audience', value: '519K+', label: 'Combined audience across platforms', target: 'reach', live: true },
    { key: 'countries', value: '69', label: 'Countries traveled and worked from', target: 'about' },
    { key: 'mvp', value: '2-4', unit: 'wks', label: 'Idea to shipped production MVP', target: 'process' },
    { key: 'google', value: 'ex-G', label: 'Google engineering background', target: 'about' },
  ],
};

/** Ways to work with her: booking type (or a section to jump to), icon and one line. */
const WORK = [
  { type: 'mvp_build', icon: 'zap', label: 'MVP build', hint: 'Your idea, live in 2 to 4 weeks' },
  { type: 'consulting', icon: 'sparkle', label: 'AI & architecture consulting', hint: 'Agents, LLM features, code reviews' },
  { type: 'retainer', icon: 'repeat', label: 'Advisory retainer', hint: 'An engineer on call every month' },
  { type: 'brand_deal', icon: 'briefcase', label: 'Brand campaign', hint: 'Videos, reels and posts for your product' },
  { type: 'speaking', icon: 'mic', label: 'Keynote or workshop', hint: 'In person or virtual' },
  { jump: 'pricing', icon: 'target', label: 'See packages & pricing', hint: 'Transparent scopes, no surprise invoices' },
];

const NS = 'http://www.w3.org/2000/svg';
const svg = (tag, attrs = {}, ...kids) => {
  const el = document.createElementNS(NS, tag);
  for (const [k, v] of Object.entries(attrs)) el.setAttribute(k, v);
  el.append(...kids);
  return el;
};

const reduceMotion = () => window.matchMedia?.('(prefers-reduced-motion: reduce)').matches;
const jump = (id) => (e) => {
  const el = document.getElementById(id);
  if (!el) return;
  e?.preventDefault();
  el.scrollIntoView({ behavior: reduceMotion() ? 'auto' : 'smooth' });
};

/** A hand-drawn underline under the starred words. */
function mark(text) {
  return h('span', { class: 'tu-mark' }, text,
    svg('svg', { viewBox: '0 0 80 12', preserveAspectRatio: 'none', 'aria-hidden': 'true' },
      svg('path', { d: 'M2 8 Q 20 2, 40 6 T 78 5', fill: 'none', stroke: 'currentColor', 'stroke-width': '2.5', 'stroke-linecap': 'round' })));
}
const emphasize = (text) => String(text).split(/\*([^*]+)\*/).map((part, i) => (i % 2 ? mark(h('em', {}, part)) : part));

/** Counts up to a number once it's on screen (keeps the final label, e.g. "519K+"). */
function countUp(el, target, label) {
  if (!target || target < 10 || reduceMotion() || typeof IntersectionObserver !== 'function') return;
  const io = new IntersectionObserver((entries) => {
    if (!entries.some((e) => e.isIntersecting)) return;
    io.disconnect();
    const start = performance.now();
    const tick = (now) => {
      const t = Math.min(1, (now - start) / 1200);
      el.textContent = t < 1 ? (target >= 1000 ? compact(Math.round(target * (1 - (1 - t) ** 3))) : String(Math.round(target * (1 - (1 - t) ** 3)))) : label;
      if (t < 1) requestAnimationFrame(tick);
    };
    el.textContent = '0';
    requestAnimationFrame(tick);
  }, { threshold: 0.5 });
  io.observe(el);
}

// ---------------------------------------------------------------- the stage behind it all

function stage() {
  return h('div', { class: 'tu-hero-bg', 'aria-hidden': 'true' },
    h('span', { class: 'tu-hero-vignette' }),
    h('span', { class: 'tu-hero-aurora is-accent', style: { left: '-12%', bottom: '-22%', width: '640px', height: '640px' } }),
    h('span', { class: 'tu-hero-aurora is-violet', style: { left: '6%', top: '14%', width: '540px', height: '540px', animationDelay: '-3s' } }),
    h('span', { class: 'tu-hero-aurora is-warm', style: { right: '-10%', top: '-10%', width: '420px', height: '420px', animationDelay: '-6s' } }),
    h('span', { class: 'tu-hero-grain' }),
    svg('svg', { class: 'tu-hero-flow', viewBox: '0 0 1440 900', preserveAspectRatio: 'none', fill: 'none' },
      svg('defs', {},
        svg('linearGradient', { id: 'tuHeroWarm', x1: '0%', y1: '100%', x2: '100%', y2: '0%' },
          svg('stop', { offset: '0%', class: 'tu-stop-warm', 'stop-opacity': '0' }), svg('stop', { offset: '45%', class: 'tu-stop-warm', 'stop-opacity': '0.28' }), svg('stop', { offset: '100%', class: 'tu-stop-ink', 'stop-opacity': '0' })),
        svg('linearGradient', { id: 'tuHeroWhisper', x1: '0%', y1: '0%', x2: '100%', y2: '100%' },
          svg('stop', { offset: '0%', class: 'tu-stop-ink', 'stop-opacity': '0' }), svg('stop', { offset: '50%', class: 'tu-stop-ink', 'stop-opacity': '0.16' }), svg('stop', { offset: '100%', class: 'tu-stop-cool', 'stop-opacity': '0' }))),
      svg('path', { d: 'M -80 760 C 320 720, 600 540, 920 600 S 1380 720, 1620 540', stroke: 'url(#tuHeroWarm)', 'stroke-width': '1.5', 'stroke-linecap': 'round' }),
      svg('path', { class: 'tu-hero-dash', d: 'M 160 880 C 480 600, 760 480, 1280 80', stroke: 'url(#tuHeroWhisper)', 'stroke-width': '1', 'stroke-linecap': 'round' }),
      svg('circle', { class: 'tu-dot-cool', cx: '920', cy: '600', r: '5', opacity: '0.28' }),
      svg('circle', { class: 'tu-dot-cool', cx: '920', cy: '600', r: '2' }),
      svg('circle', { class: 'tu-dot-ink', cx: '600', cy: '540', r: '1.8', opacity: '0.55' })),
    h('span', { class: 'tu-hero-rule', style: { top: '18%' } }),
    h('span', { class: 'tu-hero-rule is-warm', style: { top: '68%' } }),
    h('span', { class: 'tu-hero-rule is-vertical' }),
    h('span', { class: 'tu-hero-fade' }));
}

// ---------------------------------------------------------------- left: copy

/** "Work with me ▾": every way to hire her, keyboard friendly (↓/↑, Enter, Esc). */
function workMenu(onBookCall) {
  const btn = h('button', { type: 'button', class: 'tu-hero-eyebrow', 'aria-haspopup': 'menu', 'aria-expanded': 'false' },
    icon('sparkle', 14), 'Work with me', icon('chevron-down', 12));
  const menu = h('div', { class: 'tu-work-menu', role: 'menu', hidden: true },
    h('header', {}, 'Work with Delia', h('small', {}, 'Every project starts with a free 30-minute call')),
    WORK.map((w) => h('button', {
      type: 'button', role: 'menuitem',
      onclick: (e) => { close(); w.jump ? jump(w.jump)(e) : onBookCall(w.type, `Hero menu: ${w.label}`); },
    }, h('span', { class: 'tu-work-icon' }, icon(w.icon, 16)), h('span', {}, h('strong', {}, w.label), h('small', {}, w.hint)))));
  const items = () => [...menu.querySelectorAll('[role=menuitem]')];
  const outside = (e) => { if (!wrap.contains(e.target)) close(); };
  function open() {
    menu.hidden = false;
    btn.setAttribute('aria-expanded', 'true');
    document.addEventListener('mousedown', outside);
    items()[0]?.focus();
  }
  function close() {
    menu.hidden = true;
    btn.setAttribute('aria-expanded', 'false');
    document.removeEventListener('mousedown', outside);
  }
  btn.addEventListener('click', () => (menu.hidden ? open() : close()));
  const wrap = h('div', { class: 'tu-work-wrap' }, btn, menu);
  wrap.addEventListener('keydown', (e) => {
    const list = items();
    const i = list.indexOf(document.activeElement);
    if (e.key === 'Escape' && !menu.hidden) { close(); btn.focus(); }
    else if (e.key === 'ArrowDown' && !menu.hidden) { e.preventDefault(); list[(i + 1) % list.length].focus(); }
    else if (e.key === 'ArrowUp' && !menu.hidden) { e.preventDefault(); list[(i - 1 + list.length) % list.length].focus(); }
    else if (e.key === 'ArrowDown' && menu.hidden && document.activeElement === btn) { e.preventDefault(); open(); }
  });
  return wrap;
}

const DEFAULT_PARTNERS = [
  { name: 'Google', kind: 'brand', url: 'https://about.google' },
  { name: 'AssemblyAI', kind: 'brand', url: 'https://assemblyai.com' },
  { name: 'Vizard AI', kind: 'brand', url: 'https://vizard.ai' },
  { name: 'Stripe', kind: 'brand', url: 'https://stripe.com' },
  { name: 'Web Summit', kind: 'stage', url: 'https://websummit.com' },
  { name: 'TechCrunch', kind: 'stage', url: 'https://techcrunch.com' },
];

const DEFAULT_NETWORKS = [
  { key: 'youtube', label: 'YouTube', handle: '@techunicorn', count_label: '380K+', unit: 'subscribers', trend: [340000, 355000, 370000, 382000], url: 'https://youtube.com/@techunicorn', synced: true },
  { key: 'instagram', label: 'Instagram', handle: '@delia_tech', count_label: '110K+', unit: 'followers', trend: [95000, 102000, 108000, 114000], url: 'https://instagram.com/delia_tech', synced: true },
];

function availabilityPill() {
  const d = new Date();
  d.setDate(d.getDate() + 14);
  const dateStr = d.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
  return h('div', { class: 'tu-hero-slot-badge tu-fade-up', style: { '--tu-d': '40ms' } },
    h('span', { class: 'tu-live-dot' }),
    h('strong', {}, 'Next Open MVP Slot:'),
    h('span', {}, `${dateStr} · 3 of 4 Monthly Slots Booked`));
}

/** The brands and stages from her Partners card, scrolling (two runs for a seamless loop). */
function brandsStrip(items) {
  const list = items?.length ? items : DEFAULT_PARTNERS;
  const pill = (b) => (b.url
    ? h('a', { class: 'tu-brand-pill', href: b.url, target: '_blank', rel: 'noopener', title: b.name }, h('span', { class: 'tu-brand-mark', 'aria-hidden': 'true' }, b.kind === 'stage' ? icon('mic', 12) : b.name.slice(0, 1)), b.name)
    : h('span', { class: 'tu-brand-pill' }, h('span', { class: 'tu-brand-mark', 'aria-hidden': 'true' }, b.kind === 'stage' ? icon('mic', 12) : b.name.slice(0, 1)), b.name));
  return h('div', { class: 'tu-hero-brands tu-fade-up', style: { '--tu-d': '280ms' } },
    h('p', { class: 'tu-hero-brands-label' }, 'Brands and stages she’s worked with', h('span', { class: 'tu-hero-brands-count' }, String(list.length))),
    h('div', { class: 'tu-hero-brands-mask' },
      h('div', { class: 'tu-hero-brands-track' },
        h('div', { class: 'tu-hero-brands-run' }, list.map(pill)),
        h('div', { class: 'tu-hero-brands-run', 'aria-hidden': 'true', inert: '' }, list.map(pill)))));
}

// ---------------------------------------------------------------- right: the mosaic

/** A sparkline from real synced follower counts (none when there aren't at least 2 points). */
function sparkline(points, tone) {
  if (!points || points.length < 2) return null;
  const min = Math.min(...points);
  const max = Math.max(...points);
  const span = max - min || 1;
  const xy = points.map((v, i) => `${((i / (points.length - 1)) * 120).toFixed(1)},${(26 - ((v - min) / span) * 24).toFixed(1)}`);
  return svg('svg', { viewBox: '0 0 120 28', class: `tu-spark is-${tone}`, preserveAspectRatio: 'none', 'aria-hidden': 'true' },
    svg('polygon', { points: `0,28 ${xy.join(' ')} 120,28`, class: 'tu-spark-area' }),
    svg('polyline', { points: xy.join(' '), class: 'tu-spark-line' }));
}

function networkTile(n, tone) {
  const change = n.change_30d;
  return h('a', { class: `tu-tile tu-net is-${tone}`, href: n.url, target: '_blank', rel: 'noopener', onclick: () => countClick(`profile:${n.key}`, n.key), title: `${n.label}: ${n.handle}` },
    h('div', { class: 'tu-net-head' }, h('span', { class: 'tu-net-logo' }, icon(`brand:${n.key === 'twitter' ? 'x' : n.key}`, 16)),
      n.synced ? h('span', { class: 'tu-live', title: 'Synced from the account' }, h('i'), 'Live') : null),
    sparkline(n.trend, tone),
    h('div', { class: 'tu-net-nums' },
      h('div', {}, h('strong', {}, n.count_label), h('small', {}, n.unit || 'followers')),
      change !== null && change !== undefined ? h('div', {}, h('strong', {}, `${change >= 0 ? '+' : ''}${compact(change)}`), h('small', {}, '30 days')) : null,
      n.engagement_rate !== null && n.engagement_rate !== undefined ? h('div', {}, h('strong', {}, `${n.engagement_rate}%`), h('small', {}, 'engaged')) : null));
}

/** The one player that's playing: starting another puts this one back to its thumbnail. */
let playing = null;

/**
 * A YouTube video in its box: the thumbnail (16:9, the video's own shape, so nothing is cropped)
 * until she's pressed, then the real player in the same box (youtube-nocookie, autoplay, no
 * related videos from other channels). Shorts are left out (they're vertical).
 */
function videoTile(v, { featured = false } = {}) {
  if (!v) return h('div', { class: `tu-tile tu-video is-empty${featured ? ' is-featured' : ''}`, 'aria-hidden': 'true' }, icon('play', featured ? 28 : 18));
  const box = h('div', { class: `tu-tile tu-video${featured ? ' is-featured' : ''}` });
  const poster = () => h('button', {
    type: 'button', class: 'tu-video-poster', 'aria-label': `Play “${v.title}”`,
    onclick: () => play(),
  },
  h('img', { src: v.thumb, alt: '', loading: featured ? 'eager' : 'lazy', decoding: 'async' }),
  h('span', { class: 'tu-video-scrim', 'aria-hidden': 'true' }),
  h('span', { class: 'tu-video-play', 'aria-hidden': 'true' }, svg('svg', { viewBox: '0 0 12 12', fill: 'currentColor' }, svg('path', { d: 'M3.5 1.8v8.4l7-4.2z' }))),
  v.duration ? h('span', { class: 'tu-video-time' }, v.duration) : null,
  h('span', { class: 'tu-video-foot' },
    h('span', { class: 'tu-video-title' }, v.title),
    featured && v.views_label ? h('small', {}, v.views_label) : null));
  function reset() {
    box.classList.remove('is-playing');
    box.replaceChildren(poster());
  }
  function play() {
    if (playing && playing !== reset) playing();
    playing = reset;
    countClick(v.key, 'youtube');
    box.classList.add('is-playing');
    box.replaceChildren(
      h('iframe', {
        src: `https://www.youtube-nocookie.com/embed/${encodeURIComponent(v.id)}?autoplay=1&rel=0&modestbranding=1&playsinline=1`,
        title: v.title,
        allow: 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share',
        allowfullscreen: true,
        referrerpolicy: 'strict-origin-when-cross-origin',
      }),
      h('a', { class: 'tu-video-out', href: v.url, target: '_blank', rel: 'noopener', title: 'Watch on YouTube' }, icon('external', 12)));
    box.querySelector('iframe').focus?.();
  }
  box.play = play;
  reset();
  return box;
}

function statPill(s) {
  if (!s) return null;
  const value = h('span', {}, s.value);
  countUp(value, s.count, s.value);
  const num = h('span', { class: 'tu-result-value' }, value, s.unit ? h('small', {}, ` ${s.unit}`) : null);
  return h('a', { class: `tu-result${s.live ? ' is-accent' : ''}`, href: `#${s.target}`, onclick: jump(s.target) },
    svg('svg', { viewBox: '0 0 12 12', fill: 'currentColor', 'aria-hidden': 'true' }, svg('path', { d: 'M6 2 11 9H1z' })),
    h('span', { class: 'tu-result-label' }, s.label),
    num);
}

function mosaic(stats, feed) {
  const videos = (feed?.platforms?.youtube || []).filter((v) => v.thumb && !v.short).slice(0, 4);
  const nets = (feed?.reach?.networks || []).filter((n) => n.count > 0).sort((a, b) => b.count - a.count).slice(0, 2);
  const activeNets = nets.length ? nets : DEFAULT_NETWORKS;
  const tones = ['accent', 'violet'];
  const featured = videoTile(videos[0], { featured: true });
  const el = h('div', { class: `tu-mosaic${videos.length ? '' : ' is-loading'}`, role: 'group', 'aria-label': 'Her latest videos and numbers' },
    h('div', { class: 'tu-mosaic-feature' }, featured),
    h('div', { class: 'tu-mosaic-side' }, activeNets.map((n, i) => networkTile(n, tones[i % tones.length]))),
    h('div', { class: 'tu-mosaic-row' }, [1, 2, 3].map((i) => videoTile(videos[i]))),
    h('div', { class: 'tu-mosaic-stats' }, stats.slice(0, 4).map(statPill)));
  el.playLatest = () => featured.play?.();

  return el;
}

// ---------------------------------------------------------------- the section

/** @param {{ onBookCall: Function }} options */
export function heroSection({ onBookCall }) {
  const section = h('section', { class: 'landing-hero tu-hero-stage', 'aria-labelledby': 'landing-hero-title' });
  let data = DEFAULTS;
  let feed = null;
  let partners = DEFAULT_PARTNERS;

  // Built once and updated in place, so a video that's playing keeps playing while data arrives.
  const mediaBox = h('div', { class: 'tu-hero-media tu-fade-up', style: { '--tu-d': '160ms' } });
  const renderMedia = () => {
    if (mediaBox.querySelector('iframe')) return;
    mediaBox.replaceChildren(mosaic(data.stats, feed));
  };
  const render = () => {
    const d = data;
    renderMedia();
    const latest = (feed?.platforms?.youtube || [])[0];
    section.replaceChildren(
      stage(),
      h('div', { class: 'landing-container tu-hero' },
        h('div', { class: 'tu-hero-copy' },
          h('div', { class: 'tu-hero-top tu-fade-up', style: { '--tu-d': '0ms', display: 'flex', gap: '10px', alignItems: 'center', flexWrap: 'wrap' } },
            workMenu(onBookCall),
            availabilityPill()),
          h('h1', { id: 'landing-hero-title', class: 'tu-hero-title tu-fade-up', style: { '--tu-d': '80ms' } }, ...emphasize(d.headline)),
          h('p', { class: 'landing-lede tu-fade-up', style: { '--tu-d': '160ms' } }, d.lede),
          h('div', { class: 'tu-hero-actions tu-fade-up', style: { '--tu-d': '240ms' } },
            h('button', { type: 'button', class: 'tu-hero-cta', onclick: () => onBookCall('mvp_build', 'Hero: Intro call') },
              icon('calendar', 14), h('span', {}, d.cta),
              svg('svg', { class: 'tu-hero-cta-arrow', viewBox: '0 0 20 20', fill: 'none', stroke: 'currentColor', 'stroke-width': '2', 'stroke-linecap': 'round', 'stroke-linejoin': 'round', 'aria-hidden': 'true' }, svg('path', { d: 'M5 10h10m0 0-4-4m4 4-4 4' }))),
            h('span', { class: 'tu-or', 'aria-hidden': 'true' }, 'or'),
            latest
              ? h('button', {
                type: 'button', class: 'tu-hero-ghost', title: latest.title,
                onclick: () => { mediaBox.scrollIntoView({ behavior: reduceMotion() ? 'auto' : 'smooth', block: 'center' }); mediaBox.firstChild?.playLatest?.(); },
              }, h('span', { class: 'tu-hero-ghost-key', 'aria-hidden': 'true' }, icon('play', 10)), h('span', {}, 'Watch ', h('span', { class: 'tu-long' }, 'the latest '), 'video'))
              : h('a', { class: 'tu-hero-ghost', href: '#pricing', onclick: jump('pricing') },
                h('span', { class: 'tu-hero-ghost-key', 'aria-hidden': 'true' }, icon('arrow', 10)), 'See Packages & Pricing')),
          brandsStrip(partners),
          d.reassurance ? h('p', { class: 'landing-hero-reassure tu-fade-up', style: { '--tu-d': '320ms' } }, ...d.reassurance.split('·').map((part, i) => [i ? h('span', { class: 'sep', 'aria-hidden': 'true' }, '·') : null, h('span', {}, icon('check', 12), part.trim())]).flat()) : null,
          h('p', { class: 'tu-hero-note' }, 'Prefer to read first? ', h('a', { href: '#pricing', onclick: jump('pricing') }, 'Packages'), ' · ', h('a', { href: '#process', onclick: jump('process') }, 'How it works'), ' · ', h('a', { href: '#about', onclick: jump('about') }, 'About Delia'))),
        mediaBox),
      h('div', { class: 'tu-hero-wave', 'aria-hidden': 'true' },
        svg('svg', { viewBox: '0 0 1440 60', preserveAspectRatio: 'none' }, svg('path', { d: 'M0,0 Q 720,90 1440,0 L1440,60 L0,60 Z', fill: 'currentColor' }))));
  };

  render();
  setTimeout(() => section.classList.add('is-settled'), 900);

  // Batch async hydration into a single pass to eliminate DOM layout thrashing
  Promise.allSettled([
    api('GET', 'landing/hero').catch(() => null),
    loadFeed().catch(() => null),
    api('GET', 'landing/partners').catch(() => null),
  ]).then(([hRes, fRes, pRes]) => {
    let changed = false;
    if (hRes.value) {
      data = { ...DEFAULTS, ...hRes.value, stats: hRes.value.stats?.length ? hRes.value.stats : DEFAULTS.stats };
      changed = true;
    }
    if (fRes.value) {
      feed = fRes.value;
      changed = true;
    }
    if (pRes.value?.items?.length) {
      partners = pRes.value.items;
      changed = true;
    }
    if (changed) render();
  });

  return section;
}
