// Landing page hero: her headline and intro from the dashboard's "Hero" card ({countries} and
// {audience} filled from her real numbers, *words* in the accent serif), a status pill from About
// you and Client journey, and four stats she picks. Live stats count up once and link to the section
// that backs them. Renders at once with her current copy, then updates from /api/landing/hero.
import { h, api, compact } from './core.js';
import { icon } from './icons.js';

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

/** "Making tech *less scary*: …" → the starred words in the accent serif. */
const emphasize = (text) => String(text).split(/\*([^*]+)\*/).map((part, i) => (i % 2 ? h('em', {}, part) : part));

const reduceMotion = () => window.matchMedia?.('(prefers-reduced-motion: reduce)').matches;
const jump = (id) => (e) => {
  const el = document.getElementById(id);
  if (!el) return;
  e.preventDefault();
  el.scrollIntoView({ behavior: reduceMotion() ? 'auto' : 'smooth' });
};

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

function statTile(s) {
  const num = h('span', { class: 'landing-stat-value' }, s.value);
  countUp(num, s.count, s.value);
  return h('a', { class: `landing-stat-box${s.status ? ` is-${s.status}` : ''}`, href: `#${s.target}`, onclick: jump(s.target) },
    s.live ? h('span', { class: 'landing-stat-live', title: 'Updates from her studio' }, h('i', { 'aria-hidden': 'true' }), 'Live') : null,
    h('div', { class: 'landing-stat-num' }, num, s.unit ? h('span', { class: 'landing-stat-unit' }, s.unit) : null),
    h('div', { class: 'landing-stat-lbl' }, s.label),
    h('span', { class: 'landing-stat-go', 'aria-hidden': 'true' }, icon('arrow', 12)));
}

/** @param {{ onBookCall: Function }} options */
export function heroSection({ onBookCall }) {
  const section = h('section', { class: 'landing-hero', 'aria-labelledby': 'landing-hero-title' });
  const render = (d) => {
    section.replaceChildren(h('div', { class: 'landing-container landing-hero-grid' },
      h('div', { class: 'landing-hero-copy' },
        d.status ? h('p', { class: `landing-hero-status is-${d.status.state}` }, h('i', { 'aria-hidden': 'true' }), d.status.text) : null,
        h('h1', { id: 'landing-hero-title' }, ...emphasize(d.headline)),
        h('p', { class: 'landing-lede' }, d.lede),
        h('div', { class: 'landing-hero-actions' },
          h('button', { type: 'button', class: 'btn primary lg', onclick: () => onBookCall('mvp_build', 'Hero: Intro call') }, icon('calendar', 16), d.cta),
          h('a', { href: '#pricing', class: 'btn ghost lg', onclick: jump('pricing') }, 'See Packages & Pricing', icon('arrow', 14))),
        d.reassurance ? h('p', { class: 'landing-hero-reassure' }, ...d.reassurance.split('·').map((part, i) => [i ? h('span', { class: 'sep', 'aria-hidden': 'true' }, '·') : null, h('span', {}, icon('check', 12), part.trim())]).flat()) : null),
      h('div', { class: 'landing-stats-grid' }, d.stats.map(statTile))));
  };
  render(DEFAULTS);
  api('GET', 'landing/hero').then((d) => render({ ...DEFAULTS, ...d, stats: d.stats?.length ? d.stats : DEFAULTS.stats })).catch(() => {});
  return section;
}
