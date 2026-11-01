// Landing page "04: Packages & Pricing": the cards come from /api/landing/pricing, so prices,
// which plans show and "Most popular" follow her dashboard ("Packages & pricing"), checkout charges
// the same price, the Brand Deal numbers come from Public reach and the MVP card shows live build-slot
// availability. Every card can also start with a free call, recorded as "Pricing: <plan>".
import { h, api, ensureLandingCss } from './core.js';
import { icon, brandMark } from './icons.js';

ensureLandingCss(); // its styles live in landing.css

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
      // An empty line keeps benefit lists starting at the same height in every card.
      plan.inherits ? h('p', { class: 'lp-inherits' }, plan.inherits) : h('p', { class: 'lp-inherits is-empty', 'aria-hidden': 'true' }, ' '),
      list,
      more ? h('div', { class: 'lp-more' }, more) : null));
}

/** Payment & Trust Ledger with safe slot icon, payment marks and cloud security badge */
export function paymentTrustLedger() {
  const wrap = h('div', { class: 'ag-pt-ledger-card' });
  wrap.innerHTML = `
    <div class="ag-pt-note-row">
      <span class="ag-pt-note-icon" aria-hidden="true">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
      </span>
      <span class="ag-pt-note-text">Checkout sends an invoice by email for now; nothing is charged on this site</span>
    </div>
    <span class="ag-pt-ledger-rule" aria-hidden="true"></span>
    <div class="ag-pt-ledger">
      <div class="ag-pt-pay">
        <span class="ag-pt-pay-label">Pay with</span>
        <span class="ag-pt-pay-marks">
          <svg viewBox="0 0 48 16" role="img" aria-label="Visa" class="ag-pay-mark"><path fill="currentColor" d="M18.4 15.2h-3.9L17 .9h3.9zM11.3.9 7.6 10.7l-.4-2.2L5.9 1.8S5.7.9 4.6.9H.1L0 1.3s1.3.3 2.8 1.2l3.4 12.7h4L16.4.9zM44.6 15.2H48L45.1.9h-3c-1.4 0-1.7 1.1-1.7 1.1l-5.5 13.2h3.9l.8-2.1h4.7zM40.7 10.2l1.9-5.2 1.1 5.2zM35.3 4.4l.5-3.1S34.2.7 32.5.7c-1.8 0-6.2.8-6.2 4.7 0 3.7 5.1 3.7 5.1 5.7 0 1.9-4.6 1.6-6.1.4l-.6 3.2s1.7.8 4.2.8 6.3-1.3 6.3-4.9c0-3.7-5.2-4.1-5.2-5.7 0-1.6 3.6-1.4 5.3-.5z"></path></svg>
          <svg viewBox="0 0 36 22" role="img" aria-label="Mastercard" class="ag-pay-mark"><circle cx="12" cy="11" r="10" fill="#EB001B"></circle><circle cx="24" cy="11" r="10" fill="#F79E1B"></circle><path fill="#FF5F00" d="M18 3.2a10 10 0 0 0 0 15.6 10 10 0 0 0 0-15.6z"></path></svg>
          <svg viewBox="0 0 40 22" role="img" aria-label="American Express" class="ag-pay-mark"><rect width="40" height="22" rx="3" fill="#006FCF"></rect><path fill="#fff" d="M8.1 6.8 5 15.2h2.2l.6-1.6h3.3l.6 1.6h2.3L10.9 6.8zm.3 5.1 1-2.8 1 2.8zM15.4 6.8v8.4h6.9v-1.8h-4.8v-1.5h4.7V10h-4.7V8.6h4.8V6.8zM24.6 6.8l3 4.1-3.1 4.3h2.6l1.8-2.6 1.8 2.6H34l-3.1-4.3 3-4.1h-2.5L29.7 9.3 28 6.8z"></path></svg>
          <svg viewBox="0 0 40 22" role="img" aria-label="Apple Pay" class="ag-pay-mark"><path fill="currentColor" d="M9.6 5.9c.5-.6.8-1.4.7-2.2-.7 0-1.6.5-2.1 1.1-.5.5-.9 1.4-.8 2.2.8.1 1.6-.4 2.2-1.1zm.7 1.2c-1.2-.1-2.2.7-2.7.7s-1.4-.6-2.3-.6c-1.2 0-2.3.7-2.9 1.8-1.2 2.2-.3 5.4.9 7.1.6.9 1.3 1.8 2.2 1.8s1.2-.6 2.3-.6 1.4.6 2.3.5c1 0 1.6-.9 2.2-1.7.7-1 1-1.9 1-2-.1 0-1.9-.8-1.9-2.9 0-1.8 1.4-2.6 1.5-2.7-.8-1.2-2.1-1.4-2.6-1.4zM21.8 5.3h-4.2v12.1h1.9v-4.1h2.3c2.3 0 3.9-1.6 3.9-4s-1.6-4-3.9-4zm-.4 6.4h-1.9V6.9h1.9c1.4 0 2.3.9 2.3 2.4s-.9 2.4-2.3 2.4zM30.9 8.4c-1.9 0-3.3 1.1-3.4 2.6h1.7c.1-.7.8-1.2 1.6-1.2 1.1 0 1.7.5 1.7 1.4v.6l-2.3.1c-2.1.1-3.2 1-3.2 2.5s1.2 2.6 2.8 2.6c1.1 0 2.2-.6 2.7-1.5v1.4h1.8v-5.8c0-1.8-1.4-2.7-3.4-2.7zm1.6 5.3c0 1.1-.9 1.8-2 1.8-.9 0-1.5-.4-1.5-1.1s.6-1.1 1.6-1.2l1.9-.1zM37 20.5c1.7 0 2.5-.7 3.2-2.7l-.2-.5-2.3 6.8z" transform="translate(0 -1)"></path></svg>
          <svg viewBox="0 0 44 22" role="img" aria-label="Google Pay" class="ag-pay-mark"><path fill="#5F6368" d="M20.9 11.2v4.9h-1.6V4.1h4.1c1 0 1.9.3 2.6 1 .7.7 1.1 1.5 1.1 2.5s-.4 1.8-1.1 2.5c-.7.7-1.6 1-2.6 1zm0-5.6v4.1h2.6c.6 0 1.1-.2 1.5-.6.4-.4.6-.9.6-1.4s-.2-1-.6-1.4c-.4-.4-.9-.6-1.5-.6zM31 7.5c1.2 0 2.1.3 2.8.9.7.6 1 1.5 1 2.6v5.1h-1.5v-1.2h-.1c-.7.9-1.5 1.4-2.6 1.4-.9 0-1.7-.3-2.3-.8-.6-.5-.9-1.2-.9-2s.3-1.5 1-2c.6-.5 1.5-.7 2.6-.7.9 0 1.7.2 2.3.5v-.4c0-.6-.2-1-.7-1.4-.4-.4-1-.6-1.6-.6-.9 0-1.6.4-2.1 1.2l-1.4-.9c.8-1.1 1.9-1.7 3.5-1.7zm-2.1 6.2c0 .4.2.7.5 1 .3.3.7.4 1.2.4.6 0 1.2-.2 1.7-.7.5-.5.8-1 .8-1.6-.5-.4-1.2-.6-2.1-.6-.7 0-1.2.2-1.6.5-.4.3-.5.6-.5 1z"></path><path fill="#5F6368" d="M43.5 7.8 38.3 20h-1.6l1.9-4.2-3.4-7.9h1.7l2.5 6h.1l2.4-6z"></path><path fill="#4285F4" d="M14.2 10.2c0-.5 0-1-.1-1.4H7.3v2.7h3.9c-.2.9-.7 1.7-1.5 2.2v1.8h2.4c1.4-1.3 2.1-3.2 2.1-5.3z"></path><path fill="#34A853" d="M7.3 17.3c2 0 3.7-.7 4.9-1.8l-2.4-1.8c-.7.4-1.5.7-2.5.7-1.9 0-3.6-1.3-4.1-3.1H.7v1.9c1.2 2.4 3.7 4.1 6.6 4.1z"></path><path fill="#FBBC04" d="M3.2 11.3c-.3-.9-.3-1.8 0-2.7V6.7H.7c-1 1.9-1 4.2 0 6.1z"></path><path fill="#EA4335" d="M7.3 5.5c1.1 0 2.1.4 2.8 1.1l2.1-2.1C10.9 3.3 9.2 2.6 7.3 2.6c-2.9 0-5.4 1.6-6.6 4.1l2.5 1.9c.5-1.8 2.2-3.1 4.1-3.1z"></path></svg>
        </span>
      </div>
      <span class="ag-pt-ledger-divider" aria-hidden="true"></span>
      <div class="ag-pt-cloud">
        <span class="ag-pt-cloud-mark" aria-hidden="true">
          <svg class="ag-gcp-logo" viewBox="0 0 24 19" width="16" height="13" fill="none" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Google Cloud"><path d="M19.35 8.04C18.67 4.59 15.64 2 12 2C9.11 2 6.6 3.64 5.35 6.04C2.34 6.36 0 8.91 0 12C0 15.31 2.69 18 6 18H19C21.76 18 24 15.76 24 13C24 10.36 21.95 8.22 19.35 8.04Z" fill="#4285F4"></path><path d="M12 2C15.64 2 18.67 4.59 19.35 8.04C18.25 8.12 17.22 8.54 16.38 9.25L10.5 4.25C10.98 3.51 11.5 2.8 12 2Z" fill="#EA4335"></path><path d="M5.35 6.04C6.6 3.64 9.11 2 12 2L10.5 4.25L5.75 6.85C5.61 6.57 5.48 6.3 5.35 6.04Z" fill="#FBBC05"></path><path d="M19 18H6C2.69 18 0 15.31 0 12C0 9.87 1.11 8 2.78 6.95L8.5 13.5L19 18Z" fill="#34A853"></path></svg>
        </span>
        <p class="ag-pt-cloud-text">
          <span class="ag-pt-cloud-brand">Powered by Google Cloud</span>
          <span class="ag-pt-cloud-note">Encrypted in transit · Safe payments</span>
        </p>
      </div>
    </div>
  `;
  return wrap;
}

/** @param {{ onBookCall: Function, onStripePay: Function }} handlers */
export function pricingSection(handlers) {
  // Below 900px the plans sit side by side in one swipeable row (focusable, so arrow keys scroll it too).
  const grid = h('div', { class: 'lp-grid', 'aria-busy': 'true', tabindex: '0', role: 'group', 'aria-label': 'Packages' }, Array.from({ length: 4 }, () => h('div', { class: 'lp-card is-skeleton', 'aria-hidden': 'true' })));
  const ledger = paymentTrustLedger();

  api('GET', 'landing/pricing').then((d) => {
    const plans = d.plans || [];
    grid.style.setProperty('--lp-cols', String(Math.min(4, Math.max(1, plans.length))));
    grid.replaceChildren(...plans.map((p) => card(p, handlers)));
    grid.removeAttribute('aria-busy');
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
      h('p', { class: 'landing-swipe-hint muted', 'aria-hidden': 'true' }, icon('arrow', 13), 'Swipe to see every package'),
      ledger));
}
