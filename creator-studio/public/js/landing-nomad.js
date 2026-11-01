// Landing page "Shipped from 69 Countries · The Global Async Setup".
// Explains Delia's authentic digital nomad lifestyle, ultra-mobile engineering rig,
// redundant connectivity, and async-first protocols that guarantee 99.9% project velocity.
import { h } from './core.js';
import { icon } from './icons.js';

const DISPATCH_LOCATIONS = [
  { flag: '🇵🇹', city: 'Lisbon, Portugal', tz: 'UTC+0', highlight: 'Engineered autonomous agent tool routing & streaming SSE pipelines.' },
  { flag: '🇯🇵', city: 'Tokyo, Japan', tz: 'UTC+9', highlight: 'Benchmarked sub-75ms vector similarity queries aboard the Shinkansen.' },
  { flag: '🇮🇩', city: 'Bali, Indonesia', tz: 'UTC+8', highlight: 'Scaled high-concurrency Laravel & Redis queues for fintech launch week.' },
  { flag: '🇹🇭', city: 'Bangkok, Thailand', tz: 'UTC+7', highlight: 'Produced 4K YouTube deep dives on AI developer tools for 519K+ community.' },
  { flag: '🇨🇭', city: 'Zurich, Switzerland', tz: 'UTC+1', highlight: 'Architected enterprise cloud security audits and GCP Secret Manager vaults.' },
];

const RIG_ITEMS = [
  { icon: 'monitor', title: 'Ultra-Mobile M3 Max Rig', desc: '16" M3 Max workstation, Roost ergonomic stand, NuPhy low-profile mechanical keyboard, and dual 65W GaN travel chargers.' },
  { icon: 'globe', title: 'Redundant 5G & Encrypted WireGuard', desc: 'Dual-carrier e-SIM failover routing and encrypted WireGuard VPN tunnels directly into isolated production VPC networks.' },
  { icon: 'flow', title: 'Async-First Communication Flow', desc: 'Private Slack/WhatsApp channels, continuous GitHub commits, zero meeting bloat, and weekly Friday recorded Loom video demos.' },
  { icon: 'clock', title: 'Smart Timezone Matching', desc: 'Live booking slots automatically calculated in your local timezone. You get responses within 24 hours guaranteed on business days.' },
];

export function nomadSection({ onBookCall }) {
  const rigCards = h('div', { class: 'landing-nomad-rig' },
    RIG_ITEMS.map((item) =>
      h('div', { class: 'landing-nomad-rig-card' },
        h('span', { class: 'landing-nomad-rig-icon', 'aria-hidden': 'true' }, icon(item.icon, 15)),
        h('div', {},
          h('strong', {}, item.title),
          h('p', { class: 'muted small' }, item.desc)))));

  const dispatchList = h('div', { class: 'landing-nomad-dispatch' },
    h('div', { class: 'landing-nomad-dispatch-head' },
      h('span', { class: 'landing-nomad-badge' }, icon('globe', 12), 'Global Field Dispatches'),
      h('span', { class: 'small muted' }, '69 Countries Explored')),
    h('ul', { class: 'landing-nomad-dispatch-list' },
      DISPATCH_LOCATIONS.map((d) =>
        h('li', { class: 'landing-nomad-dispatch-item' },
          h('span', { class: 'landing-nomad-flag', 'aria-hidden': 'true' }, d.flag),
          h('div', { class: 'landing-nomad-dispatch-info' },
            h('div', { class: 'landing-nomad-dispatch-city-row' },
              h('strong', {}, d.city),
              h('span', { class: 'landing-nomad-tz-badge' }, d.tz)),
            h('p', { class: 'muted small' }, d.highlight))))),
    h('div', { class: 'landing-nomad-dispatch-foot' },
      h('span', { class: 'landing-nomad-live-dot' }),
      h('span', { class: 'small' }, 'Currently taking on 1 build sprint slot for next month'),
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
        rigCards,
        dispatchList)));

  return section;
}

