// Landing page "04: Packages & Pricing": the cards come from /api/landing/pricing, so prices,
// which plans show and "Most popular" follow her dashboard ("Packages & pricing"), checkout charges
// the same price, the Brand Deal numbers come from Public reach and the MVP card shows live build-slot
// availability. Every card can also start with a free call, recorded as "Pricing: <plan>".
import { h, api } from './core.js';
import { icon, brandMark } from './icons.js';

const BENEFITS_SHOWN = 4;
const money = (n) => Math.round(n).toLocaleString('en-US');

function card(plan, { onBookCall, onStripePay }) {
  let pkg = plan.package;
  let amount = plan.price;
  const id = `plan-${plan.key}`;
  const ctaBtn = h('button', { type: 'button', class: `lp-cta ${plan.popular ? 'is-primary' : ''}`, onclick: () => onStripePay(pkg) });
  const drawCta = () => ctaBtn.replaceChildren(icon('tag', 14), `${pkg === plan.deposit?.package ? 'Pay 50% deposit' : plan.cta} · $${money(amount)}`);
  drawCta();

  // MVP: pay half to start or the full fixed price.
  const payToggle = plan.deposit ? h('div', { class: 'lp-pay', role: 'radiogroup', 'aria-label': 'How to pay' },
    [[plan.deposit.package, `50% deposit`, plan.deposit.price], [plan.package, 'Pay in full', plan.price]].map(([p, label, price], i) => h('button', {
      type: 'button', role: 'radio', class: `lp-pay-opt${i === 0 ? ' on' : ''}`, 'aria-checked': String(i === 0),
      onclick: (e) => {
        pkg = p;
        amount = price;
        e.currentTarget.parentElement.querySelectorAll('.lp-pay-opt').forEach((b) => { const on = b === e.currentTarget; b.classList.toggle('on', on); b.setAttribute('aria-checked', String(on)); });
        drawCta();
      },
    }, h('span', {}, label), h('b', {}, `$${money(price)}`)))) : null;
  if (plan.deposit) { pkg = plan.deposit.package; amount = plan.deposit.price; drawCta(); }

  const extra = plan.benefits.length - BENEFITS_SHOWN;
  const list = h('ul', { class: 'lp-benefits', id: `${id}-benefits` }, plan.benefits.map((b, i) => h('li', { class: `is-${b.kind}${i >= BENEFITS_SHOWN ? ' is-more' : ''}` },
    h('span', { class: 'lp-bicon', 'aria-hidden': 'true' }, icon(b.kind === 'absent' ? 'x' : 'check', 13)),
    h('span', {}, b.text, b.kind === 'absent' ? h('span', { class: 'sr-only' }, ' (not included)') : null))));
  const more = extra > 0 ? h('button', {
    type: 'button', class: 'landing-service-more', 'aria-expanded': 'false', 'aria-controls': `${id}-benefits`,
    onclick: () => {
      const open = list.classList.toggle('show-all');
      more.setAttribute('aria-expanded', String(open));
      more.replaceChildren(open ? 'Show less' : `+${extra} more`, icon('chevron-down', 12));
    },
  }, `+${extra} more`, icon('chevron-down', 12)) : null;

  return h('article', { class: `lp-card${plan.popular ? ' is-popular' : ''}`, 'aria-labelledby': `${id}-name` },
    plan.popular ? h('span', { class: 'lp-ribbon' }, icon('star', 11), 'Most popular') : null,
    h('header', { class: 'lp-head' },
      h('div', { class: 'lp-title' },
        h('span', { class: 'lp-voxel', 'aria-hidden': 'true' }, brandMark(16)),
        h('h3', { id: `${id}-name` }, plan.name)),
      h('p', { class: 'lp-price' }, h('span', { class: 'lp-cur' }, '$'), h('span', { class: 'lp-amount' }, money(plan.price)), h('span', { class: 'lp-per' }, plan.per)),
      h('p', { class: 'lp-clarify' }, plan.clarify),
      plan.availability ? h('p', { class: `lp-avail is-${plan.availability.status}` }, h('i', { 'aria-hidden': 'true' }), plan.availability.label) : null,
      h('p', { class: 'lp-desc' }, plan.desc)),
    h('div', { class: 'lp-actions' },
      payToggle,
      ctaBtn,
      h('button', { type: 'button', class: 'lp-call', onclick: () => onBookCall(plan.book, `Pricing: ${plan.name}`) }, 'Talk it through first', icon('arrow', 12)),
      h('p', { class: 'lp-trust' }, icon('check', 12), plan.trust)),
    h('div', { class: 'lp-body' },
      h('div', { class: 'lp-strip' },
        h('span', { class: 'lp-badge' }, icon(plan.badge.icon, 13), plan.badge.label),
        h('div', { class: 'lp-metrics' }, plan.metrics.map((m) => h('div', { class: 'lp-metric' },
          h('span', { class: 'lp-m-label' }, m.network ? icon(`brand:${m.network}`, 11) : null, m.label),
          h('strong', {}, m.value),
          h('span', { class: 'lp-m-sub' }, m.sub)))),
        plan.perks.length ? h('ul', { class: 'lp-perks' }, plan.perks.map((p) => h('li', {}, icon('check', 11), p))) : null),
      plan.inherits ? h('p', { class: 'lp-inherits' }, plan.inherits) : null,
      list,
      more ? h('div', { class: 'lp-more' }, more) : null));
}

/** @param {{ onBookCall: Function, onStripePay: Function }} handlers */
export function pricingSection(handlers) {
  const grid = h('div', { class: 'lp-grid', 'aria-busy': 'true' }, Array.from({ length: 4 }, () => h('div', { class: 'lp-card is-skeleton', 'aria-hidden': 'true' })));
  const note = h('p', { class: 'lp-note muted', hidden: true });

  api('GET', 'landing/pricing').then((d) => {
    const plans = d.plans || [];
    grid.style.setProperty('--lp-cols', String(Math.min(4, Math.max(1, plans.length))));
    grid.replaceChildren(...plans.map((p) => card(p, handlers)));
    grid.removeAttribute('aria-busy');
    if (!d.stripe_configured) {
      note.hidden = false;
      note.replaceChildren(icon('file-text', 13), 'Checkout sends an invoice by email for now; nothing is charged on this site.');
    }
  }).catch(() => {
    grid.removeAttribute('aria-busy');
    grid.replaceChildren(h('p', { class: 'muted' }, 'Packages are being updated. Book a free call and Delia will scope it with you.'),
      h('button', { type: 'button', class: 'btn primary', onclick: () => handlers.onBookCall('consulting', 'Pricing: unavailable') }, 'Book a free call'));
  });

  return h('section', { class: 'landing-section', id: 'pricing', 'aria-labelledby': 'landing-pricing-title' },
    h('div', { class: 'landing-container' },
      h('div', { class: 'landing-section-head' },
        h('p', { class: 'landing-section-eyebrow' }, '04: Packages & Pricing'),
        h('h2', { id: 'landing-pricing-title' }, 'Packages that ', h('em', {}, 'pay for themselves')),
        h('p', { class: 'muted' }, 'Start with a free scoping call or go straight to checkout. Transparent scopes and zero surprise invoices.')),
      grid,
      note));
}
