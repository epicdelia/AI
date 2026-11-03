// Dashboard "Packages & pricing": the landing page's plans with their price, whether they show,
// which one is "Most popular", and what each brought in through the website (checkouts or invoice
// requests, paid, revenue, and free calls booked from the card). Saving changes the page and the
// Stripe checkout price at once.
import { h, api, link, toast } from './core.js';
import { icon } from './icons.js';

const usd = (n) => `$${Math.round(Number(n) || 0).toLocaleString('en-US')}`;

export function pricingCard(d, onChange) {
  if (!d?.plans?.length) return null;
  const rows = {};
  const saveBtn = h('button', { class: 'btn primary sm', disabled: true, onclick: save }, 'Save');
  const dirty = () => { saveBtn.disabled = false; };

  const body = d.plans.map((p) => {
    const price = h('input', { type: 'number', min: '1', step: '1', class: 'input pricing-input', value: String(p.price), 'aria-label': `${p.name} price in dollars`, oninput: dirty });
    const visible = h('input', { type: 'checkbox', checked: p.visible, 'aria-label': `Show ${p.name} on your website`, onchange: dirty });
    const popular = h('input', { type: 'radio', name: 'pricing-popular', checked: p.popular, 'aria-label': `${p.name} is most popular`, onchange: dirty });
    rows[p.key] = { price, visible, popular };
    return h('tr', { class: p.visible ? '' : 'is-hidden' },
      h('td', {}, h('strong', {}, p.name), h('span', { class: 'muted small' }, ` ${p.per}`)),
      h('td', {}, h('span', { class: 'pricing-money' }, '$', price), p.price !== p.default_price ? h('span', { class: 'muted small', title: 'Original price' }, ` was ${usd(p.default_price)}`) : null),
      h('td', { class: 'center' }, h('label', { class: 'switch-mini' }, visible, h('span', { 'aria-hidden': 'true' }))),
      h('td', { class: 'center' }, popular),
      h('td', { class: 'num' }, String(p.requests), p.calls ? h('span', { class: 'muted small' }, ` + ${p.calls} calls`) : null),
      h('td', { class: 'num' }, String(p.paid)),
      h('td', { class: 'num' }, h('strong', {}, usd(p.revenue))));
  });

  async function save() {
    saveBtn.disabled = true;
    const plans = Object.fromEntries(Object.entries(rows).map(([k, r]) => [k, { price: Number(r.price.value), visible: r.visible.checked, popular: r.popular.checked }]));
    try {
      await api('PUT', 'pricing', { plans });
      toast('Your packages are updated on the website and at checkout.');
      onChange?.();
    } catch (err) {
      saveBtn.disabled = false;
      toast(err.message || 'Could not save.', 'err');
    }
  }

  return h('div', { class: 'card pricing-card' },
    h('div', { class: 'card-header' },
      h('div', {},
        h('h2', {}, 'Packages & pricing'),
        h('p', { class: 'muted small', style: { margin: '2px 0 0' } }, `${usd(d.revenue)} paid through your website · ${d.requests} checkout${d.requests === 1 ? '' : 's'} or invoice requests`)),
      h('div', { class: 'row' }, link('/landing', { class: 'btn ghost sm' }, icon('eye', 14), 'Preview'), saveBtn)),
    h('div', { class: 'table-wrap' },
      h('table', { class: 'reach-table pricing-table' },
        h('thead', {}, h('tr', {}, ['Package', 'Price', 'On site', 'Popular', 'Requests', 'Paid', 'Revenue'].map((t) => h('th', {}, t)))),
        h('tbody', {}, body))),
    h('p', { class: 'muted small', style: { margin: '10px 0 0' } }, 'The MVP deposit is always half the build price. Paid means Stripe confirmed it; invoice requests wait in Deals.'));
}
