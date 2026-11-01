// Landing page "06: Shipped Products": Real production architectures shipped by Delia.
// Concrete case studies delivered in 2 to 4 weeks while working across 60+ countries.
// Includes metrics, tech stack tags, and architecture blueprints.
import { h } from './core.js';
import { icon } from './icons.js';

export const SHIPPED_BUILDS = [
  {
    id: 'build-agents',
    title: 'Autonomous Multi-Agent AI Pipeline',
    tagline: 'Production subagent tool-routing & semantic vector search',
    category: 'ai',
    catLabel: 'AI & Agents',
    turnaround: 'Shipped in 18 Days',
    location: 'Lisbon & Tokyo',
    desc: 'An enterprise research tool integrating Gemini Pro and Claude 3.5 Sonnet for multi-step reasoning, external API retrieval, and deterministic evaluation benchmarks.',
    metrics: [
      { label: 'Uptime', val: '99.98%' },
      { label: 'RAG Latency', val: '<75ms' },
      { label: 'Tool Success', val: '99.4%' },
    ],
    stack: ['Gemini 2.0', 'Claude 3.5', 'pgvector', 'Docker', 'GCP'],
    deliverables: [
      'Subagent pipeline with streaming server-sent events',
      'Automated fallback routing and safety guardrails',
      'Full TypeScript client library and ops runbook',
    ],
  },
  {
    id: 'build-fintech',
    title: 'High-Concurrency Multi-Tenant API',
    tagline: 'Zero-downtime transactional gateway with audit logging',
    category: 'api',
    catLabel: 'Full-Stack & Cloud',
    turnaround: 'Shipped in 21 Days',
    location: 'Bali & Singapore',
    desc: 'Scalable subscription billing engine with Stripe webhook synchronization, strict schema validation, and automated zero-downtime deployments on Google Cloud Run.',
    metrics: [
      { label: 'Daily Events', val: '250K+' },
      { label: 'P99 Latency', val: '42ms' },
      { label: 'Deployment', val: 'Zero Downtime' },
    ],
    stack: ['Laravel 11', 'PHP 8.3', 'PostgreSQL', 'Redis', 'Cloud Run'],
    deliverables: [
      'PCI-compliant Stripe Checkout & webhook verification',
      'Redis transactional queue with retry dead-letter queues',
      'Automated GitHub Actions CI/CD with unit & feature tests',
    ],
  },
  {
    id: 'build-studio',
    title: 'Real-Time Creator Studio Suite',
    tagline: 'Zero-build-step reactive dashboard with local analytics',
    category: 'ux',
    catLabel: 'Reactive UX',
    turnaround: 'Shipped in 14 Days',
    location: 'Bangkok & London',
    desc: 'A lightning-fast creator platform featuring privacy-first analytics, social feed synchronization, audio prompter, and dynamic dark/light design token engine.',
    metrics: [
      { label: 'Lighthouse', val: '98/100' },
      { label: 'First Paint', val: '<50ms' },
      { label: 'Build Step', val: 'Zero npm' },
    ],
    stack: ['Vanilla JS', 'SVG Tokens', 'CSS Variables', 'WebSockets'],
    deliverables: [
      'Pure reactive DOM helper architecture without bundlers',
      'Native SVG icon system with dark & light theme tokens',
      'Offline-capable PWA with local storage persistence',
    ],
  },
];

const CATEGORIES = [
  { id: 'all', label: 'All Builds' },
  { id: 'ai', label: 'AI & Agents' },
  { id: 'api', label: 'High-Scale APIs' },
  { id: 'ux', label: 'Reactive UX' },
];

export function buildsSection({ onBookCall }) {
  let activeCat = 'all';

  const gridEl = h('div', { class: 'landing-builds-grid' });

  function renderGrid() {
    const filtered = SHIPPED_BUILDS.filter((b) => activeCat === 'all' || b.category === activeCat);
    gridEl.replaceChildren(...filtered.map((b) => buildCard(b, { onBookCall })));
  }

  const catButtons = CATEGORIES.map((c) => {
    const btn = h('button', {
      type: 'button',
      class: `landing-faq-pill${c.id === activeCat ? ' is-active' : ''}`,
      'aria-pressed': String(c.id === activeCat),
      onclick: () => {
        activeCat = c.id;
        catButtons.forEach((b, idx) => {
          const on = CATEGORIES[idx].id === activeCat;
          b.classList.toggle('is-active', on);
          b.setAttribute('aria-pressed', String(on));
        });
        renderGrid();
      },
    }, c.label);
    return btn;
  });

  const filterRow = h('div', { class: 'landing-builds-filter', role: 'tablist', 'aria-label': 'Build categories' }, catButtons);

  renderGrid();

  const section = h('section', { class: 'landing-section', id: 'builds', 'aria-labelledby': 'builds-heading' },
    h('div', { class: 'landing-container' },
      h('div', { class: 'landing-section-head' },
        h('p', { class: 'landing-section-eyebrow' }, '06: Shipped Products'),
        h('h2', { id: 'builds-heading' }, 'Shipped from the road: ', h('em', {}, 'recent builds')),
        h('p', { class: 'muted' },
          'Real production systems architected, coded, and deployed in 2 to 4 weeks while living across 60+ countries.')),
      filterRow,
      gridEl,
      h('div', { class: 'landing-builds-cta' },
        h('div', {},
          h('strong', {}, 'Have a similar product you need built?'),
          h('p', { class: 'muted small', style: { margin: '2px 0 0' } }, 'We can scope your architecture and have your first sprint deployed in under 14 days.')),
        h('button', {
          type: 'button',
          class: 'btn primary sm',
          onclick: () => onBookCall('mvp_build', 'Case Studies CTA'),
        }, icon('rocket', 14), ' Start Your Build Sprint', icon('arrow', 12)))));

  return section;
}

function buildCard(build, { onBookCall }) {
  const card = h('article', { class: 'landing-build-card' },
    h('div', { class: 'landing-build-head' },
      h('div', {},
        h('span', { class: 'landing-build-cat' }, build.catLabel),
        h('h3', { class: 'landing-build-title' }, build.title)),
      h('div', { class: 'landing-build-badge-wrap' },
        h('span', { class: 'landing-build-time-badge' }, icon('clock', 12), build.turnaround),
        h('span', { class: 'landing-build-loc-pill' }, icon('globe', 11), build.location))),
    h('p', { class: 'landing-build-desc' }, build.desc),
    h('div', { class: 'landing-build-metrics' },
      build.metrics.map((m) => h('div', { class: 'landing-build-metric' },
        h('strong', {}, m.val),
        h('span', {}, m.label)))),
    h('ul', { class: 'landing-build-deliv' },
      build.deliverables.map((d) => h('li', {}, icon('check', 12), h('span', {}, d)))),
    h('div', { class: 'landing-build-foot' },
      h('ul', { class: 'landing-build-tags', 'aria-label': `${build.title} stack` },
        build.stack.map((t) => h('li', { class: 'landing-build-tag' }, t))),
      h('button', {
        type: 'button',
        class: 'landing-build-btn',
        onclick: () => onBookCall('consulting', `Architecture: ${build.title}`),
      }, 'Explore Architecture', icon('arrow', 12))));

  return card;
}

