// Landing page "Brand Partners & Stages": brands from her dashboard, then the stages from her talks
// list. On wide screens they sit in one row; when they don't fit (smaller screens, or a long list)
// the row becomes a slider: snap scrolling, faded edges, arrows and a slow auto-advance that pauses
// on hover/focus and stays off with reduced motion.
import { h, api } from './core.js';
import { icon } from './icons.js';

const FALLBACK = [
  ...['IBM', 'Adobe', 'Airalo', 'Temporal'].map((name) => ({ name, kind: 'brand', url: null })),
  ...['Nomad Cruise', 'Digital Garage Tokyo', 'Vently'].map((name) => ({ name, kind: 'stage', url: null })),
];

function item(p) {
  const tag = h('span', { class: 'landing-trusted-kind', title: p.kind === 'stage' ? 'Spoke here' : 'Brand partner' }, icon(p.kind === 'stage' ? 'mic' : 'briefcase', 10), p.kind === 'stage' ? 'Stage' : 'Brand');
  const name = h('span', { class: 'landing-trusted-name' }, p.name);
  if (p.url) return h('li', {}, h('a', { href: p.url, target: '_blank', rel: 'noopener', class: 'landing-trusted-item' }, name, tag));
  if (p.kind === 'stage') {
    return h('li', {}, h('a', {
      href: '#speaking', class: 'landing-trusted-item',
      onclick: (e) => { const el = document.getElementById('speaking'); if (el) { e.preventDefault(); el.scrollIntoView({ behavior: 'smooth' }); } },
    }, name, tag));
  }
  return h('li', {}, h('span', { class: 'landing-trusted-item' }, name, tag));
}

export function partnersSection() {
  const list = h('ul', { class: 'landing-trusted-list', tabindex: '-1' });
  const prev = h('button', { type: 'button', class: 'landing-trusted-nav is-prev', 'aria-label': 'Scroll partners back', onclick: () => step(-1) }, icon('chevron-left', 16));
  const next = h('button', { type: 'button', class: 'landing-trusted-nav is-next', 'aria-label': 'Scroll partners forward', onclick: () => step(1) }, icon('chevron-right', 16));
  const rail = h('div', { class: 'landing-trusted-rail' }, prev, list, next);
  const section = h('section', { class: 'landing-trusted', 'aria-label': 'Brand partners and stages' },
    h('div', { class: 'landing-container' },
      h('div', { class: 'landing-trusted-label' }, 'Brand Partners & Stages'),
      rail));

  const reduce = () => window.matchMedia?.('(prefers-reduced-motion: reduce)').matches;
  function step(dir) {
    const first = list.querySelector('li');
    const w = first ? first.getBoundingClientRect().width + 32 : 200;
    const atEnd = list.scrollLeft + list.clientWidth >= list.scrollWidth - 4;
    if (dir > 0 && atEnd) list.scrollTo({ left: 0, behavior: reduce() ? 'auto' : 'smooth' });
    else list.scrollBy({ left: dir * w, behavior: reduce() ? 'auto' : 'smooth' });
  }
  const update = () => {
    const overflowing = list.scrollWidth > list.clientWidth + 2;
    rail.classList.toggle('is-slider', overflowing);
    rail.classList.toggle('at-start', list.scrollLeft <= 2);
    rail.classList.toggle('at-end', list.scrollLeft + list.clientWidth >= list.scrollWidth - 2);
  };
  list.addEventListener('scroll', () => requestAnimationFrame(update), { passive: true });
  const ro = typeof ResizeObserver === 'function' ? new ResizeObserver(update) : null;
  ro?.observe(list);

  // Gentle auto-advance while it's a slider and on screen; any interaction stops it.
  let timer = null;
  let paused = false;
  let visible = false;
  const tick = () => { if (!paused && visible && rail.classList.contains('is-slider') && !reduce()) step(1); };
  const start = () => { if (!timer) timer = setInterval(tick, 3200); };
  for (const ev of ['mouseenter', 'focusin', 'touchstart']) rail.addEventListener(ev, () => { paused = true; }, { passive: true });
  for (const ev of ['mouseleave', 'focusout']) rail.addEventListener(ev, () => { paused = false; });
  list.addEventListener('pointerdown', () => { clearInterval(timer); timer = null; }, { passive: true });
  if (typeof IntersectionObserver === 'function') {
    new IntersectionObserver((entries) => { visible = entries.some((e) => e.isIntersecting); }, { threshold: 0.3 }).observe(rail);
  }

  const render = (items) => {
    list.replaceChildren(...items.map(item));
    requestAnimationFrame(() => { update(); start(); });
  };
  render(FALLBACK);
  api('GET', 'landing/partners').then((d) => { if (d.items?.length) render(d.items); else section.hidden = true; }).catch(() => {});
  return section;
}
