// Landing page "The 6-Point Client Guarantee & Code Quality Manifesto".
// Concrete, binding commitments ensuring 100% peace of mind for prospective clients:
// IP ownership, zero junior handoffs, weekly Friday Loom demos, fixed prices, 30-day warranty, and complete runbooks.
import { h } from './core.js';
import { icon } from './icons.js';

export const GUARANTEES = [
  {
    num: '01',
    icon: 'lock',
    title: '100% IP & Asset Custody',
    desc: 'From Day 1, all code is pushed to your private GitHub repository under your organization. Cloud accounts (GCP, AWS, Vercel) and API keys are set up in your name. Zero hostage fees or proprietary lock-ins.',
    badge: '100% Yours',
  },
  {
    num: '02',
    icon: 'code',
    title: 'Zero Junior Delegation',
    desc: 'No bait-and-switch. You hire an Ex-Google Systems Engineer, and Delia personally architects, writes, tests, and deploys every single line of production code. No junior contractors or outsourced teams.',
    badge: 'Direct Principal',
  },
  {
    num: '03',
    icon: 'play',
    title: 'Weekly Friday Loom Demos',
    desc: 'Complete engineering transparency. Every Friday afternoon, you receive a detailed recorded video walk-through demonstrating working software running on your password-protected live staging environment.',
    badge: 'Weekly Proof',
  },
  {
    num: '04',
    icon: 'scale',
    title: '100% Fixed-Price Contract',
    desc: 'The quote agreed in Phase 02 Blueprint is final and binding. No hourly meter running, no surprise invoices, and no unexpected change orders. Complete budget certainty from start to finish.',
    badge: 'Fixed Guarantee',
  },
  {
    num: '05',
    icon: 'check',
    title: '30-Day Post-Launch Warranty',
    desc: 'Every shipped MVP includes 30 days of comprehensive post-launch warranty at zero extra cost. If any bug or unintended behavior arises within the agreed scope, it is patched immediately with priority.',
    badge: '30-Day Shield',
  },
  {
    num: '06',
    icon: 'file-text',
    title: 'Complete Ops Runbooks & Video Handover',
    desc: 'You receive thorough documentation, API schema contracts, environment secrets setup guides, and recorded video onboarding for your future developers or maintenance team.',
    badge: 'Zero Debt',
  },
];

export function guaranteesSection({ onBookCall }) {
  const cards = GUARANTEES.map((g) =>
    h('article', { class: 'landing-guar-card' },
      h('div', { class: 'landing-guar-top' },
        h('div', { class: 'landing-guar-icon-wrap' },
          h('span', { class: 'landing-guar-icon', 'aria-hidden': 'true' }, icon(g.icon, 16)),
          h('span', { class: 'landing-guar-num', 'aria-hidden': 'true' }, g.num)),
        h('span', { class: 'landing-guar-badge' }, g.badge)),
      h('h3', { class: 'landing-guar-title' }, g.title),
      h('p', { class: 'landing-guar-desc' }, g.desc)));

  const section = h('section', { class: 'landing-section tinted', id: 'guarantees', 'aria-labelledby': 'guar-heading' },
    h('div', { class: 'landing-container' },
      h('div', { class: 'landing-section-head' },
        h('p', { class: 'landing-section-eyebrow' }, 'Commitments & Standards'),
        h('h2', { id: 'guar-heading' }, 'The client ', h('em', {}, 'guarantee manifesto')),
        h('p', { class: 'muted' },
          'Engineering contracts built on mutual trust, transparency, and accountability. Here are the 6 ironclad commitments behind every build.')),
      h('div', { class: 'landing-guar-grid' }, cards),
      h('div', { class: 'landing-guar-banner' },
        h('div', { class: 'landing-guar-banner-text' },
          h('strong', {}, 'Ready for a high-trust engineering partnership?'),
          h('p', { class: 'muted small', style: { margin: '2px 0 0' } }, 'Book a complimentary 30-minute intro call to review your build scope.')),
        h('button', {
          type: 'button',
          class: 'btn primary sm',
          onclick: () => onBookCall('mvp_build', 'Guarantee Manifesto CTA'),
        }, icon('calendar', 14), ' Book 30-Min Intro Call', icon('arrow', 12)))));

  return section;
}

