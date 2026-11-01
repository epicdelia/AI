// Landing page "Shipped from 69 Countries · The Global Async Setup".
// Explains Delia's authentic digital nomad lifestyle, ultra-mobile engineering rig,
// redundant connectivity, and async-first protocols that guarantee 99.9% project velocity.
// Balanced 2-column layout engineered to have exact matching heights and alignment.
import { h } from './core.js';
import { icon } from './icons.js';

const RIG_ITEMS = [
  { icon: 'monitor', title: 'Ultra-Mobile M3 Max Rig', desc: '16" M3 Max workstation, Roost ergonomic stand, NuPhy low-profile mechanical keyboard, and dual 65W GaN travel chargers.' },
  { icon: 'globe', title: 'Redundant 5G & Encrypted WireGuard', desc: 'Dual-carrier e-SIM failover routing and encrypted WireGuard VPN tunnels directly into isolated production VPC networks.' },
  { icon: 'flow', title: 'Async-First Communication Flow', desc: 'Private Slack/WhatsApp channels, continuous GitHub commits, zero meeting bloat, and weekly Friday recorded Loom video demos.' },
  { icon: 'clock', title: 'Smart Timezone Matching', desc: 'Live booking slots automatically calculated in your local timezone. You get responses within 24 hours guaranteed on business days.' },
];

const DISPATCH_LOCATIONS = [
  { flag: '🇵🇹', city: 'Lisbon, Portugal', tz: 'UTC+0', highlight: 'Engineered autonomous agent tool routing & streaming SSE pipelines.' },
  { flag: '🇯🇵', city: 'Tokyo, Japan', tz: 'UTC+9', highlight: 'Benchmarked sub-75ms vector similarity queries aboard the Shinkansen.' },
  { flag: '🇮🇩', city: 'Bali, Indonesia', tz: 'UTC+8', highlight: 'Scaled high-concurrency Laravel & Redis queues for fintech launch week.' },
  { flag: '🇹🇭', city: 'Bangkok, Thailand', tz: 'UTC+7', highlight: 'Produced 4K YouTube deep dives on AI developer tools for 519K+ community.' },
];

export function nomadSection({ onBookCall }) {
  // Left Column: The Ultra-Mobile Rig
  const rigCol = h('div', { class: 'landing-nomad-col' },
    h('div', { class: 'landing-nomad-col-head' },
      h('div', { class: 'landing-nomad-col-title-wrap' },
        h('span', { class: 'landing-nomad-icon', 'aria-hidden': 'true' }, icon('monitor', 14)),
        h('h3', { class: 'landing-nomad-col-title' }, 'Ultra-Mobile Engineering Rig')),
      h('span', { class: 'landing-nomad-badge' }, 'Hardware & Setup')),
    h('ul', { class: 'landing-nomad-list' },
      RIG_ITEMS.map((item) =>
        h('li', { class: 'landing-nomad-item' },
          h('span', { class: 'landing-nomad-item-icon', 'aria-hidden': 'true' }, icon(item.icon, 14)),
          h('div', { class: 'landing-nomad-item-body' },
            h('strong', {}, item.title),
            h('p', { class: 'muted small' }, item.desc))))),
    h('div', { class: 'landing-nomad-col-foot' },
      h('span', { class: 'small muted' }, icon('check', 12), ' Redundant 5G · WireGuard VPN · Zero Meeting Fatigue')));

  // Right Column: Global Field Dispatches
  const dispatchCol = h('div', { class: 'landing-nomad-col' },
    h('div', { class: 'landing-nomad-col-head' },
      h('div', { class: 'landing-nomad-col-title-wrap' },
        h('span', { class: 'landing-nomad-icon', 'aria-hidden': 'true' }, icon('globe', 14)),
        h('h3', { class: 'landing-nomad-col-title' }, 'Global Field Dispatches')),
      h('span', { class: 'landing-nomad-badge' }, '69 Countries Explored')),
    h('ul', { class: 'landing-nomad-list' },
      DISPATCH_LOCATIONS.map((d) =>
        h('li', { class: 'landing-nomad-item' },
          h('span', { class: 'landing-nomad-flag', 'aria-hidden': 'true' }, d.flag),
          h('div', { class: 'landing-nomad-item-body' },
            h('div', { class: 'landing-nomad-city-row' },
              h('strong', {}, d.city),
              h('span', { class: 'landing-nomad-tz-badge' }, d.tz)),
            h('p', { class: 'muted small' }, d.highlight))))),
    h('div', { class: 'landing-nomad-col-foot is-action' },
      h('div', { class: 'landing-nomad-status-wrap' },
        h('span', { class: 'landing-nomad-live-dot' }),
        h('span', { class: 'small' }, '1 build sprint slot open next month')),
      h('button', {
        type: 'button',
        class: 'landing-service-btn sm',
        onclick: () => onBookCall('mvp_build', 'Nomad Dispatch CTA'),
      }, 'Check Open Slots', icon('arrow', 11))));

  const section = h('section', { class: 'landing-section', id: 'nomad', 'aria-labelledby': 'nomad-heading' },
    h('div', { class: 'landing-container' },
      h('div', { class: 'landing-section-head' },
        h('p', { class: 'landing-section-eyebrow' }, 'Worldwide Velocity'),
        h('h2', { id: 'nomad-heading' }, 'Shipped from ', h('em', {}, '69 countries')),
        h('p', { class: 'muted' },
          'No standing desks or office cubicles needed to write great software. A look at the battle-tested mobile rig, redundant connectivity, and async rituals behind every shipped client build.')),
      h('div', { class: 'landing-nomad-grid' },
        rigCol,
        dispatchCol)));

  return section;
}
