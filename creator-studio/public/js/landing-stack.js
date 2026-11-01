// Landing page "04: Tech Stack & Architecture": Delia's production engineering ecosystem.
// Highlighting her Ex-Google background, modern AI agent workflows, full-stack velocity,
// and cloud infrastructure standards that enable 2-4 week production shipping.
// Compact 4-card layout with horizontal swipe on mobile screens.
import { h } from './core.js';
import { icon } from './icons.js';

export const TECH_PILLARS = [
  {
    category: 'AI & Autonomous Agents',
    badge: 'GenAI & Agents',
    icon: 'sparkle',
    desc: 'Autonomous tool-calling, custom subagent routing, streaming inference, and deterministic benchmarks.',
    technologies: [
      { name: 'Gemini 1.5 & 2.0 API', role: 'Multimodal reasoning' },
      { name: 'Claude 3.5 Sonnet', role: 'Tool execution & coding' },
      { name: 'Vector DBs (pgvector)', role: 'Low-latency RAG' },
      { name: 'Guardrail Harnesses', role: 'Deterministic outputs' },
    ],
    guarantee: 'Sub-second streaming & evaluated safety bounds.',
  },
  {
    category: 'Full-Stack & High-Scale APIs',
    badge: 'Production APIs',
    icon: 'code',
    desc: 'Modular, maintainable application backends built for high concurrency, clean migrations, and security.',
    technologies: [
      { name: 'TypeScript & Node.js', role: 'Real-time WebSocket layers' },
      { name: 'Laravel 11 / PHP 8.3', role: 'Queues, auth & robust ORM' },
      { name: 'Python Microservices', role: 'Data transformations & ML' },
      { name: 'PostgreSQL & Redis', role: 'Sub-ms caching & ACID data' },
    ],
    guarantee: 'Strict schema validations & 100% test-backed contracts.',
  },
  {
    category: 'Frontend & Reactive Interfaces',
    badge: '95+ Lighthouse',
    icon: 'zap',
    desc: 'Snappy, accessible, mobile-first interfaces with zero npm bloat. Instant 60fps micro-interactions.',
    technologies: [
      { name: 'Reactive Vanilla JS', role: 'Zero-build-step speed' },
      { name: 'Vue 3 & React', role: 'Component-driven web apps' },
      { name: 'Tailwind CSS Tokens', role: 'Fluid responsive dark/light' },
      { name: 'Accessible ARIA', role: 'Keyboard & screen-reader UX' },
    ],
    guarantee: 'Instant page transitions & 60fps responsive fidelity.',
  },
  {
    category: 'Cloud, DevOps & Reliability',
    badge: 'Zero Lock-in',
    icon: 'globe',
    desc: 'Battle-tested cloud infrastructure in your own company accounts with automated CI/CD and secrets isolation.',
    technologies: [
      { name: 'Google Cloud (GCP)', role: 'Serverless Cloud Run & Tasks' },
      { name: 'Docker Containers', role: 'Local-to-production parity' },
      { name: 'GitHub Actions CI/CD', role: 'Automated test pipelines' },
      { name: 'Stripe Checkout API', role: 'PCI billing & subscriptions' },
    ],
    guarantee: '100% infrastructure-as-code handover in your name.',
  },
];

const ARCHITECTURE_STANDARDS = [
  { icon: 'lock', title: '100% IP Ownership', desc: 'Private GitHub repos and cloud accounts created directly in your name.' },
  { icon: 'rocket', title: 'Day-1 Launch Velocity', desc: 'Pre-tested architecture scaffolds cut weeks off setup without compromising quality.' },
  { icon: 'scale', title: 'Built to Scale to 100K+', desc: 'Stateless backend architecture ready for high concurrency spikes.' },
  { icon: 'shield', title: '30-Day Bug Warranty', desc: 'Every build is backed by a 30-day post-launch zero-cost bug fix guarantee.' },
];

export function stackSection({ onBookCall }) {
  const grid = h('div', {
    class: 'landing-stack-grid',
    tabindex: '0',
    'aria-label': 'Four engineering pillars, from AI agents to cloud deployment',
  }, TECH_PILLARS.map((p, idx) => stackCard(p, idx)));

  const swipeHint = h('p', { class: 'landing-swipe-hint muted', 'aria-hidden': 'true' },
    icon('arrow', 13), ' Swipe through the 4 engineering pillars');

  const standardsRow = h('div', { class: 'landing-stack-standards' },
    ARCHITECTURE_STANDARDS.map((s) => h('div', { class: 'landing-stack-standard-item' },
      h('span', { class: 'landing-stack-standard-icon', 'aria-hidden': 'true' }, icon(s.icon === 'shield' ? 'check' : s.icon, 15)),
      h('div', {},
        h('strong', {}, s.title),
        h('p', { class: 'muted small' }, s.desc)))));

  const section = h('section', { class: 'landing-section', id: 'stack', 'aria-labelledby': 'stack-heading' },
    h('div', { class: 'landing-container' },
      h('div', { class: 'landing-section-head' },
        h('p', { class: 'landing-section-eyebrow' }, '04: Engineering Ecosystem'),
        h('h2', { id: 'stack-heading' }, 'Modern tech stack, ', h('em', {}, 'zero bloat')),
        h('p', { class: 'muted' },
          'Production systems engineering honed at Google and proven across 60+ shipped client projects. We build on open, maintainable standards that you control.')),
      grid,
      swipeHint,
      standardsRow,
      h('div', { class: 'landing-stack-cta' },
        h('p', { class: 'muted' }, 'Have a specific stack or proprietary API integration in mind?'),
        h('button', {
          type: 'button',
          class: 'landing-service-btn',
          onclick: () => onBookCall('consulting', 'Tech Stack & Architecture Consultation'),
        }, icon('code', 14), ' Review Your Tech Stack With Delia', icon('arrow', 12)))));

  return section;
}

function stackCard(pillar, idx) {
  const card = h('article', { class: 'landing-stack-card' },
    h('div', { class: 'landing-stack-card-head' },
      h('h3', { class: 'landing-stack-title' }, icon(pillar.icon, 14), pillar.category.toLowerCase())),
    h('p', { class: 'landing-stack-desc' }, pillar.desc),
    h('ul', { class: 'landing-stack-tech-list', 'aria-label': `${pillar.category} tools` },
      pillar.technologies.map((t) => h('li', { class: 'landing-stack-tech-row' },
        h('div', { class: 'landing-stack-tech-name' },
          h('span', { class: 'landing-stack-dot' }),
          h('strong', {}, t.name)),
        h('span', { class: 'landing-stack-tech-role' }, t.role)))),
    h('div', { class: 'landing-stack-guarantee' },
      icon('check', 12),
      h('span', {}, pillar.guarantee)));

  return card;
}
