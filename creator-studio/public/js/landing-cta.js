// Landing page closing section ("Let us build something"), a full-width band that runs into the
// footer. Every way in feeds her dashboard: the intent tiles book with a type and a source
// ("Let us build: …") that shows up in "From your website", the availability line follows her
// Client journey build slots, and the payment button says "Request an invoice" until Stripe is set up.
import { h, api, toast } from './core.js';
import { icon } from './icons.js';

const EMAIL = 'hello@thetechunicorn.com';

/** What visitors can start with: booking type, the source recorded on the deal, and copy. */
const INTENTS = [
  { type: 'mvp_build', icon: 'zap', label: 'An app or MVP', hint: 'Shipped in 2 to 4 weeks' },
  { type: 'consulting', icon: 'sparkle', label: 'AI in my product', hint: 'Agents, LLM features, reviews' },
  { type: 'brand_deal', icon: 'briefcase', label: 'A brand campaign', hint: 'Video, reels and posts' },
  { type: 'speaking', icon: 'mic', label: 'A keynote or workshop', hint: 'In person or virtual' },
];

/** @param {{ onBookCall: (type: string, service?: string) => void, onStripePay: (pkg: string) => void }} options */
export function ctaSection({ onBookCall, onStripePay }) {
  const payBtn = h('button', { type: 'button', class: 'btn ghost lg', onclick: () => onStripePay('retainer') }, icon('tag', 16), 'Pay with Stripe');
  const availability = h('span', { class: 'landing-close-fact', hidden: true });

  api('GET', 'stripe/packages').then((p) => {
    if (!p.stripe_configured) payBtn.replaceChildren(icon('file-text', 16), 'Request an invoice');
  }).catch(() => {});
  api('GET', 'landing/availability').then((a) => {
    if (!a?.show) return;
    availability.hidden = false;
    availability.classList.add(`is-${a.status}`);
    availability.replaceChildren(h('i', { 'aria-hidden': 'true' }), a.label);
  }).catch(() => {});

  const copyBtn = h('button', {
    type: 'button', class: 'landing-close-copy', 'aria-label': `Copy ${EMAIL}`, title: 'Copy email',
    onclick: async () => {
      try {
        await navigator.clipboard.writeText(EMAIL);
        copyBtn.replaceChildren(icon('check', 14));
        toast('Email copied.');
        setTimeout(() => copyBtn.replaceChildren(icon('copy', 14)), 1600);
      } catch {
        location.href = `mailto:${EMAIL}`;
      }
    },
  }, icon('copy', 14));

  const tiles = INTENTS.map((it) => h('button', {
    type: 'button', class: 'landing-close-tile', onclick: () => onBookCall(it.type, `Let us build: ${it.label}`),
  },
    h('span', { class: 'landing-close-tile-icon', 'aria-hidden': 'true' }, icon(it.icon, 18)),
    h('span', { class: 'landing-close-tile-text' }, h('strong', {}, it.label), h('span', { class: 'muted small' }, it.hint)),
    h('span', { class: 'landing-close-tile-go', 'aria-hidden': 'true' }, icon('arrow', 14))));

  return h('section', { class: 'landing-close', id: 'contact', 'aria-labelledby': 'landing-close-title' },
    h('div', { class: 'landing-container landing-close-inner' },
      h('div', { class: 'landing-close-copy-col' },
        h('p', { class: 'landing-section-eyebrow' }, 'Let us build something'),
        h('h2', { id: 'landing-close-title' }, 'Have an idea that needs ', h('em', {}, 'shipping?')),
        h('p', { class: 'landing-close-lede' }, 'App, campaign, keynote, or something bolder. If it makes tech less scary, let us talk.'),
        h('div', { class: 'landing-close-actions' },
          h('button', { type: 'button', class: 'btn primary lg', onclick: () => onBookCall('mvp_build', 'Let us build: Intro call') }, icon('calendar', 16), 'Book an Intro Call'),
          payBtn),
        h('div', { class: 'landing-close-email' },
          icon('mail', 15),
          h('a', { href: `mailto:${EMAIL}?subject=Project%20Inquiry` }, EMAIL),
          copyBtn),
        h('div', { class: 'landing-close-facts' },
          h('span', { class: 'landing-close-fact' }, icon('clock', 13), 'Replies within 1 business day'),
          h('span', { class: 'landing-close-fact' }, icon('check', 13), 'Free 30-minute call'),
          availability)),
      h('div', { class: 'landing-close-intents' },
        h('p', { class: 'landing-close-intents-title' }, 'What are we building?'),
        h('div', { class: 'landing-close-tiles' }, tiles))));
}
