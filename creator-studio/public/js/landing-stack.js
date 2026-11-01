// Landing page "04: Tech Stack & Architecture": Delia's production engineering ecosystem.
// Highlighting her Ex-Google background, modern AI agent workflows, full-stack velocity,
// and cloud infrastructure standards that enable 2-4 week production shipping.
import { h } from './core.js';
import { icon } from './icons.js';

export const TECH_PILLARS = [
  {
    category: 'Autonomous AI & LLM Systems',
    badge: 'Production GenAI',
    icon: 'sparkle',
    desc: 'Practical, high-retention AI capabilities integrated directly into business workflows. Shipped with deterministic safety guardrails and evaluation benchmarks.',
    technologies: [
      { name: 'Gemini 1.5 & 2.0 API', role: 'Multimodal context & rapid reasoning' },
      { name: 'Claude 3.5 Sonnet & Claude Code', role: 'Agent tool routing & complex reasoning' },
      { name: 'Vector DBs (pgvector, Pinecone)', role: 'Low-latency semantic retrieval (RAG)' },
      { name: 'Structured Outputs & Tool Calling', role: 'Deterministic JSON & automated tool execution' },
    ],
    guarantee: 'Sub-second streaming responses & evaluated safety bounds.',
  },
  {
    category: 'Full-Stack Architecture & APIs',
    badge: 'High Throughput',
    icon: 'code',
    desc: 'Clean, modular, and maintainable application backends designed for speed, easy onboarding, and bulletproof security.',
    technologies: [
      { name: 'TypeScript & Node.js', role: 'Real-time WebSocket & microservice layers' },
      { name: 'Laravel 11 / PHP 8.3 REST APIs', role: 'Robust authentication, queues, and database ORM' },
      { name: 'Python AI Microservices', role: 'Data transformations, embeddings, and pipeline scripts' },
      { name: 'PostgreSQL & Redis', role: 'ACID transactional integrity & sub-millisecond cache' },
    ],
    guarantee: 'Strict schema validations and 100% test-backed API contracts.',
  },
  {
    category: 'Frontend & Reactive Interface',
    badge: '95+ Lighthouse',
    icon: 'zap',
    desc: 'Snappy, accessible, mobile-first interfaces with zero needless npm bloat. Delightful micro-interactions with immediate visual feedback.',
    technologies: [
      { name: 'Reactive Vanilla JS & Web APIs', role: 'Zero-build-step speed and instant page loads' },
      { name: 'Vue 3 & React Ecosystem', role: 'Component-driven interactive web applications' },
      { name: 'Tailwind CSS & Token System', role: 'Adaptive dark/light themes & fluid responsive grids' },
      { name: 'Accessible ARIA Standards', role: 'Screen-reader compliance & keyboard navigable UX' },
    ],
    guarantee: 'Instant page transitions & 60fps responsive animation fidelity.',
  },
  {
    category: 'Cloud, DevOps & Production Reliability',
    badge: 'Zero Vendor Lock-in',
    icon: 'globe',
    desc: 'Battle-tested cloud infrastructure configured in your own company accounts with automated deployments and secrets isolation.',
    technologies: [
      { name: 'Google Cloud Platform (GCP)', role: 'Serverless Cloud Run, Cloud Tasks, and Secret Manager' },
      { name: 'Docker & OCI Containers', role: 'Uniform local-to-production deployment parity' },
      { name: 'GitHub Actions CI/CD', role: 'Automated static analysis, linting, and automated tests' },
      { name: 'Stripe Billing & Webhooks', role: 'PCI-compliant checkout, subscriptions & automated invoices' },
    ],
    guarantee: '100% infrastructure-as-code handover with zero proprietary traps.',
  },
];

const ARCHITECTURE_STANDARDS = [
  { icon: 'lock', title: '100% IP Ownership', desc: 'Private GitHub repos and cloud accounts created directly in your name.' },
  { icon: 'rocket', title: 'Day-1 Launch Velocity', desc: 'Pre-tested architecture scaffolds cut weeks off setup without compromising quality.' },
  { icon: 'scale', title: 'Built to Scale to 100K+', desc: 'Stateless backend architecture ready for high concurrency spikes.' },
  { icon: 'shield', title: '30-Day Bug Warranty', desc: 'Every build is backed by a 30-day post-launch zero-cost bug fix guarantee.' },
];

export function stackSection({ onBookCall }) {
  const grid = h('div', { class: 'landing-stack-grid' },
    TECH_PILLARS.map((p, idx) => stackCard(p, idx)));

  const standardsRow = h('div', { class: 'landing-stack-standards' },
    ARCHITECTURE_STANDARDS.map((s) => h('div', { class: 'landing-stack-standard-item' },
      h('span', { class: 'landing-stack-standard-icon', 'aria-hidden': 'true' }, icon(s.icon === 'shield' ? 'check' : s.icon, 16)),
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
      h('span', { class: 'landing-stack-cat-badge' }, icon(pillar.icon, 13), pillar.category),
      h('span', { class: 'landing-stack-badge' }, pillar.badge)),
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

