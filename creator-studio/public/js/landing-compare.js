// Landing page "05: Why Tech Unicorn": Comparison Matrix.
// Demonstrates the concrete advantages of partnering directly with an Ex-Google Principal Engineer
// versus bloated traditional software agencies or high-variance freelance marketplaces.
import { h } from './core.js';
import { icon, brandMark } from './icons.js';

const COMPARISON_ROWS = [
  {
    criterion: 'Who Actually Writes Your Code',
    delia: {
      text: 'Delia directly (Ex-Google systems engineer and for exstreme difficult requirements - partner with a dedicated corporate software company. )',
      positive: true,
      badge: 'Principal Engineer',
    },
    agency: {
      text: 'Junior devs rotated behind account managers',
      positive: false,
    },
    freelance: {
      text: 'Unvetted contractors with variable experience',
      positive: false,
    },
  },
  {
    criterion: 'Turnaround & Shipping Velocity',
    delia: {
      text: '2 to 4 Weeks fixed sprint with weekly demos',
      positive: true,
      badge: 'Fast & Predictable',
    },
    agency: {
      text: '3 to 6 Months bogged down in committee meetings',
      positive: false,
    },
    freelance: {
      text: 'Unpredictable timelines & high ghosting risk',
      positive: false,
    },
  },
  {
    criterion: 'Code Quality & Production Standards',
    delia: {
      text: 'Production-grade, automated tests & 30-day warranty',
      positive: true,
      badge: 'Ex-Google Quality',
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
    criterion: 'Pricing & Budget Predictability',
    delia: {
      text: '100% Fixed-price contract or transparent retainer',
      positive: true,
      badge: 'Zero Surprise Fees',
    },
    agency: {
      text: 'Billable hours, change orders & ballooning invoices',
      positive: false,
    },
    freelance: {
      text: 'Low initial bids that escalate rapidly with add-ons',
      positive: false,
    },
  },
  {
    criterion: 'Communication & Visibility',
    delia: {
      text: 'Async Slack/WhatsApp + weekly Friday Loom video demos',
      positive: true,
      badge: 'Full Transparency',
    },
    agency: {
      text: 'Bureaucratic weekly status decks & endless sync calls',
      positive: false,
    },
    freelance: {
      text: 'Sporadic messages, language friction & vanishing updates',
      positive: false,
    },
  },
  {
    criterion: 'Launch & Community Amplification',
    delia: {
      text: 'Built-in launch boost to 519K+ developer community',
      positive: true,
      badge: 'Built-in Distribution',
    },
    agency: {
      text: 'Zero distribution (they hand over code and walk away)',
      positive: false,
    },
    freelance: {
      text: 'Zero distribution or audience reach',
      positive: false,
    },
  },
];

export function comparisonSection({ onBookCall }) {
  const table = h('div', { class: 'landing-compare-table-wrap' },
    h('table', { class: 'landing-compare-table', 'aria-label': 'Comparison between Tech Unicorn, Agencies, and Freelancers' },
      h('thead', {},
        h('tr', {},
          h('th', { scope: 'col', class: 'landing-compare-th-dim' }, 'Feature / Criterion'),
          h('th', { scope: 'col', class: 'landing-compare-th-delia' },
            h('div', { class: 'landing-compare-delia-badge-wrap' },
              brandMark(18),
              h('strong', {}, 'Delia · Tech Unicorn')),
            h('span', { class: 'landing-compare-sub' }, 'Ex-Google Solo Principal')),
          h('th', { scope: 'col', class: 'landing-compare-th-other' },
            h('strong', {}, 'Traditional Dev Agency'),
            h('span', { class: 'landing-compare-sub' }, 'Typical 5–15 person firm')),
          h('th', { scope: 'col', class: 'landing-compare-th-other' },
            h('strong', {}, 'Freelance Marketplace'),
            h('span', { class: 'landing-compare-sub' }, 'Upwork / Fiverr')))),
      h('tbody', {},
        COMPARISON_ROWS.map((row) =>
          h('tr', { class: 'landing-compare-tr' },
            h('th', { scope: 'row', class: 'landing-compare-td-dim' },
              h('span', { class: 'landing-compare-dim-title' }, row.criterion)),
            h('td', { class: 'landing-compare-td-delia' },
              h('div', { class: 'landing-compare-val-wrap' },
                h('span', { class: 'landing-compare-icon is-good', 'aria-hidden': 'true' }, icon('check', 14)),
                h('span', { class: 'landing-compare-val-text' }, row.delia.text)),
              row.delia.badge ? h('span', { class: 'landing-compare-pill' }, row.delia.badge) : null),
            h('td', { class: 'landing-compare-td-other' },
              h('div', { class: 'landing-compare-val-wrap' },
                h('span', { class: 'landing-compare-icon is-bad', 'aria-hidden': 'true' }, icon('x', 13)),
                h('span', { class: 'landing-compare-val-text muted' }, row.agency.text))),
            h('td', { class: 'landing-compare-td-other' },
              h('div', { class: 'landing-compare-val-wrap' },
                h('span', { class: 'landing-compare-icon is-bad', 'aria-hidden': 'true' }, icon('x', 13)),
                h('span', { class: 'landing-compare-val-text muted' }, row.freelance.text))))))));

  const section = h('section', { class: 'landing-section', id: 'why-delia', 'aria-labelledby': 'compare-heading' },
    h('div', { class: 'landing-container' },
      h('div', { class: 'landing-section-head' },
        h('p', { class: 'landing-section-eyebrow' }, '05: Why Tech Unicorn'),
        h('h2', { id: 'compare-heading' }, 'The principal engineer ', h('em', {}, 'advantage')),
        h('p', { class: 'muted' },
          'No junior handoffs, no bureaucratic agency layers, and no surprise invoices. Here is how working with Delia compares to traditional options.')),
      table,
      h('div', { class: 'landing-compare-foot' },
        h('p', { class: 'muted' }, 'Ready to skip agency overhead and ship your MVP in weeks?'),
        h('button', {
          type: 'button',
          class: 'btn primary',
          onclick: () => onBookCall('mvp_build', 'Comparison Matrix CTA'),
        }, icon('zap', 14), ' Book Your 30-Min Intro Call', icon('arrow', 12)))));

  return section;
}
