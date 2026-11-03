// Landing page "05: Why Delia Lazarescu": Comparison Matrix.
// Demonstrates the concrete advantages of partnering directly with an Ex-Google Principal Engineer
// versus bloated traditional software agencies or high-variance freelance marketplaces.
// Compact, polished layout designed for quick scanning with natural lowercase / sentence-case typography.
import { h, ensureLandingCss } from './core.js';
import { icon, brandMark } from './icons.js';

ensureLandingCss(); // its styles live in landing.css

const COMPARISON_ROWS = [
  {
    criterion: 'Who writes your code',
    delia: {
      text: 'Delia directly (Ex-Google systems engineer and for exstreme difficult requirements - partner with a dedicated corporate tech firm)',
      positive: true,
      badge: 'solo principal',
    },
    agency: {
      text: 'Junior devs rotated behind layers of account managers',
      positive: false,
    },
    freelance: {
      text: 'Unvetted contractors with unpredictable seniority',
      positive: false,
    },
  },
  {
    criterion: 'Shipping velocity',
    delia: {
      text: '2 to 4 weeks fixed sprint with weekly Friday Loom demos',
      positive: true,
      badge: '2–4 weeks',
    },
    agency: {
      text: '3 to 6 months bogged down in committee meetings',
      positive: false,
    },
    freelance: {
      text: 'Variable timelines & frequent milestone ghosting',
      positive: false,
    },
  },
  {
    criterion: 'Code quality & standards',
    delia: {
      text: 'Production-grade architecture, automated tests & 30-day warranty',
      positive: true,
      badge: 'ex-Google quality',
    },
    agency: {
      text: 'Over-engineered codebases or cookie-cutter templates',
      positive: false,
    },
    freelance: {
      text: 'Patchwork scripts with high hidden technical debt',
      positive: false,
    },
  },
  {
    criterion: 'Pricing predictability',
    delia: {
      text: '100% fixed-price scope or transparent monthly retainer',
      positive: true,
      badge: 'zero surprise fees',
    },
    agency: {
      text: 'Billable hours, change orders & ballooning invoices',
      positive: false,
    },
    freelance: {
      text: 'Low initial bids that escalate with billable extras',
      positive: false,
    },
  },
  {
    criterion: 'Communication',
    delia: {
      text: 'Async Slack/WhatsApp + weekly recorded Loom walkthroughs',
      positive: true,
      badge: 'full transparency',
    },
    agency: {
      text: 'Bureaucratic weekly status decks & endless sync calls',
      positive: false,
    },
    freelance: {
      text: 'Sporadic messages and vanishing progress updates',
      positive: false,
    },
  },
  {
    criterion: 'Launch & distribution',
    delia: {
      text: 'Built-in launch amplification to 519K+ developer community',
      positive: true,
      badge: 'built-in distribution',
    },
    agency: {
      text: 'Zero distribution (they hand over code and walk away)',
      positive: false,
    },
    freelance: {
      text: 'Zero audience reach or post-launch visibility',
      positive: false,
    },
  },
];

export function comparisonSection({ onBookCall }) {
  const table = h('div', { class: 'landing-compare-table-wrap' },
    h('table', { class: 'landing-compare-table', 'aria-label': 'Comparison between Delia Lazarescu, Agencies, and Freelancers' },
      h('thead', {},
        h('tr', {},
          h('th', { scope: 'col', class: 'landing-compare-th-dim' }, 'Feature / criterion'),
          h('th', { scope: 'col', class: 'landing-compare-th-delia' },
            h('div', { class: 'landing-compare-delia-badge-wrap' },
              brandMark(16),
              h('strong', {}, 'Delia Lazarescu')),
            h('span', { class: 'landing-compare-sub' }, 'ex-Google solo principal')),
          h('th', { scope: 'col', class: 'landing-compare-th-other' },
            h('strong', {}, 'Traditional dev agency'),
            h('span', { class: 'landing-compare-sub' }, '5–15 person firm')),
          h('th', { scope: 'col', class: 'landing-compare-th-other' },
            h('strong', {}, 'Freelance marketplace'),
            h('span', { class: 'landing-compare-sub' }, 'Upwork / Fiverr')))),
      h('tbody', {},
        COMPARISON_ROWS.map((row) =>
          h('tr', { class: 'landing-compare-tr' },
            h('th', { scope: 'row', class: 'landing-compare-td-dim' },
              h('span', { class: 'landing-compare-dim-title' }, row.criterion)),
            h('td', { class: 'landing-compare-td-delia' },
              h('div', { class: 'landing-compare-val-wrap' },
                h('span', { class: 'landing-compare-icon is-good', 'aria-hidden': 'true' }, icon('check', 13)),
                h('span', { class: 'landing-compare-val-text' }, row.delia.text)),
              row.delia.badge ? h('span', { class: 'landing-compare-pill' }, row.delia.badge) : null),
            h('td', { class: 'landing-compare-td-other' },
              h('div', { class: 'landing-compare-val-wrap' },
                h('span', { class: 'landing-compare-icon is-bad', 'aria-hidden': 'true' }, icon('x', 12)),
                h('span', { class: 'landing-compare-val-text muted' }, row.agency.text))),
            h('td', { class: 'landing-compare-td-other' },
              h('div', { class: 'landing-compare-val-wrap' },
                h('span', { class: 'landing-compare-icon is-bad', 'aria-hidden': 'true' }, icon('x', 12)),
                h('span', { class: 'landing-compare-val-text muted' }, row.freelance.text))))))));

  const section = h('section', { class: 'landing-section', id: 'why-delia', 'aria-labelledby': 'compare-heading' },
    h('div', { class: 'landing-container' },
      h('div', { class: 'landing-section-head' },
        h('p', { class: 'landing-section-eyebrow' }, '05: Why Delia Lazarescu'),
        h('h2', { id: 'compare-heading' }, 'Why Delia ', h('em', {}, 'Lazarescu')),
        h('p', { class: 'muted' },
          'No junior handoffs, no bureaucratic agency layers, and no surprise invoices. Here is how working with Delia compares to traditional options.')),
      table,
      h('div', { class: 'landing-compare-foot' },
        h('p', { class: 'muted' }, 'Ready to skip agency overhead and ship your MVP in weeks?'),
        h('button', {
          type: 'button',
          class: 'btn primary sm',
          onclick: () => onBookCall('mvp_build', 'Comparison Matrix CTA'),
        }, icon('zap', 14), ' Book Your 30-Min Intro Call', icon('arrow', 12)))));

  return section;
}
