// Her side of getting paid and keeping work moving: per deal (payment plan, deposit, review
// deadline, usage rights) and her defaults (late fee, rights pricing, review days, onboarding).
import { h, api, toast, busy, modal, field, clear, fmtDay } from './core.js';
import { icon } from './icons.js';

const PLAN_LABEL = { on_receipt: 'Due on receipt', net7: 'Net 7', net15: 'Net 15', net30: 'Net 30' };
const money = (n, c = 'USD') => new Intl.NumberFormat(undefined, { style: 'currency', currency: c || 'USD', maximumFractionDigits: 2 }).format(n || 0);

export async function termsDialog(deal, onChange = () => {}) {
  let t = await api('GET', `deals/${deal.id}/terms`);
  const defaults = await api('GET', 'clients/terms');
  const body = h('div');
  function draw() {
    const b = t.billing;
    const plan = h('select', {}, h('option', { value: '' }, 'No plan'), defaults.plans.map((p) => h('option', { value: p, selected: b.payment_plan === p }, PLAN_LABEL[p] || p)));
    const deposit = h('input', { type: 'number', min: 0, max: 90, value: b.deposit_percent || 0, disabled: !!b.deposit_paid_at });
    const due = h('input', { type: 'date', value: b.invoice_due_on || '' });
    const review = h('input', { type: 'date', value: t.review_due_on || '' });
    const kind = h('select', {}, Object.entries(defaults.rights_kinds).map(([k, l]) => h('option', { value: k }, l)));
    const days = h('select', {}, defaults.rights_days.map((d) => h('option', { value: d }, `${d} days`)));
    const scope = h('input', { placeholder: 'Scope, e.g. Paid social, US only', maxlength: 200 });
    const price = h('input', { type: 'number', min: 0, step: '0.01', placeholder: 'Price' });
    const suggest = () => { const s = t.suggested_prices?.[kind.value]?.[days.value]; price.placeholder = s ? `Suggested ${money(s, deal.currency)}` : 'Price'; };
    kind.onchange = suggest; days.onchange = suggest; suggest();
    clear(body).append(
      h('h3', {}, 'Payment'),
      h('div', { class: 'grid grid-3' }, field('Payment plan', plan), field('Deposit %', deposit, b.deposit_paid_at ? `Paid ${fmtDay(b.deposit_paid_at.slice(0, 10))}` : '0 = no deposit'), field('Invoice due', due, 'Set from the plan when invoiced')),
      h('p', { class: 'small muted' }, `Next payment: ${money(b.next_amount, deal.currency)} (${b.next_part})${b.overdue ? ` · overdue ${b.days_overdue} days` : ''}`),
      h('h3', {}, 'Review deadline'),
      field('Client review due', review, 'Set automatically when the deal moves to “in review”; they get reminders the day before and on the day'),
      h('div', { class: 'row-end' }, h('button', {
        type: 'button', class: 'btn primary sm',
        onclick: (e) => busy(e.currentTarget, 'Saving…', async () => {
          t = await api('PUT', `deals/${deal.id}/terms`, { payment_plan: plan.value || null, deposit_percent: Number(deposit.value || 0), invoice_due_on: due.value || null, review_due_on: review.value || null });
          toast('Saved.'); onChange(); draw();
        }),
      }, 'Save terms')),
      h('h3', {}, 'Usage rights, whitelisting & exclusivity'),
      t.rights.length ? h('ul', { class: 'stack' }, t.rights.map((r) => h('li', { class: 'row-between' },
        h('span', {}, h('strong', {}, r.label), r.scope ? ` · ${r.scope}` : '', ` · ${r.days} days · ${money(r.price, deal.currency)}`,
          h('div', { class: 'muted small' }, r.ends_on ? `${fmtDay(r.starts_on)} → ${fmtDay(r.ends_on)}${r.ends_soon ? ' · ends soon' : ''}${r.status === 'ended' ? ' · ended' : ''}` : 'Starts when the content goes live')),
        h('div', { class: 'row' },
          h('button', { type: 'button', class: 'btn ghost sm', onclick: async () => { t = await api('PATCH', `deals/${deal.id}/rights/${r.id}`, { extend: true }); toast('Extended.'); draw(); } }, `+${r.days} days`),
          h('button', { type: 'button', class: 'btn ghost sm', 'aria-label': 'Remove', onclick: async () => { t = await api('DELETE', `deals/${deal.id}/rights/${r.id}`); draw(); } }, icon('trash', 12)))))) : h('p', { class: 'muted small' }, 'None yet. Price them separately from the content itself.'),
      h('div', { class: 'grid grid-2' }, kind, days),
      h('div', { class: 'grid grid-2' }, scope, price),
      h('div', { class: 'row-end' }, h('button', {
        type: 'button', class: 'btn sm',
        onclick: (e) => busy(e.currentTarget, 'Adding…', async () => {
          t = await api('POST', `deals/${deal.id}/rights`, { kind: kind.value, days: Number(days.value), scope: scope.value || null, price: price.value === '' ? null : Number(price.value) });
          draw();
        }),
      }, icon('plus', 12), 'Add')),
      h('p', { class: 'muted small' }, 'Rights start on the publish date. 14 days before they end, you and the client get a reminder; they can pay the extension online.'));
  }
  draw();
  await modal(`Terms & rights · ${deal.brand_name}`, () => body, { wide: true });
}

export async function termsDefaultsDialog() {
  const d = await api('GET', 'clients/terms');
  const f = {
    plan: h('select', {}, h('option', { value: '' }, 'None'), d.plans.map((p) => h('option', { value: p, selected: d.default_payment_plan === p }, PLAN_LABEL[p]))),
    review: h('input', { type: 'number', min: 1, max: 30, value: d.review_days }),
    fee: h('input', { type: 'number', min: 0, max: 10, step: '0.5', value: d.late_fee_percent }),
    usage: h('input', { type: 'number', min: 0, value: d.rights_pricing.usage }),
    white: h('input', { type: 'number', min: 0, value: d.rights_pricing.whitelisting }),
    excl: h('input', { type: 'number', min: 0, value: d.rights_pricing.exclusivity }),
  };
  await modal('Payment terms & rights pricing', (close) => h('div', {},
    field('Default payment plan', f.plan), field('Days a client has to review work', f.review),
    field('Late fee, % per month', f.fee, '0 = off. Only mentioned in reminders; never charged automatically'),
    h('h3', {}, 'Suggested rights price, % of the deal fee per 30 days'),
    h('div', { class: 'grid grid-3' }, field('Usage', f.usage), field('Whitelisting', f.white), field('Exclusivity', f.excl)),
    h('p', { class: 'muted small' }, '0 = no suggestion. Guides often cite roughly 30–50% for paid usage; set what fits your rates.'),
    h('div', { class: 'row-end' }, h('button', {
      type: 'button', class: 'btn primary',
      onclick: (e) => busy(e.currentTarget, 'Saving…', async () => {
        await api('PUT', 'clients/terms', { default_payment_plan: f.plan.value || null, review_days: Number(f.review.value), late_fee_percent: Number(f.fee.value || 0),
          rights_pricing: { usage: Number(f.usage.value || 0), whitelisting: Number(f.white.value || 0), exclusivity: Number(f.excl.value || 0) } });
        toast('Saved.'); close();
      }),
    }, 'Save'))));
}

export async function onboardingEditor() {
  const d = await api('GET', 'clients/onboarding');
  const services = [{ key: '*', label: 'Default', steps: d.default }, ...d.services];
  let current = services[0];
  const rows = h('div', { class: 'stack' });
  const pick = h('select', { onchange: (e) => { current = services.find((s) => s.key === e.target.value); draw(); } }, services.map((s) => h('option', { value: s.key }, s.label)));
  const TYPE = { upload: 'Ticks when they upload a file', intake: 'Ticks when the brief is answered', call: 'Ticks when a call is booked', vault: 'Ticks when the Brand Vault is filled', custom: 'They tick it' };
  function row(s = { label: '', type: 'custom' }) {
    const label = h('input', { value: s.label, maxlength: 200, 'aria-label': 'Step' });
    const type = h('select', { 'aria-label': 'How it ticks' }, d.types.map((t) => h('option', { value: t, selected: s.type === t }, TYPE[t])));
    const el = h('div', { class: 'grid grid-2' }, label, h('div', { class: 'row' }, type, h('button', { type: 'button', class: 'btn ghost sm', onclick: () => el.remove() }, icon('trash', 12))));
    el.read = () => ({ key: s.key, label: label.value, type: type.value });
    return el;
  }
  function draw() { clear(rows).append(...current.steps.map(row)); }
  draw();
  await modal('Onboarding checklist', () => h('div', {},
    field('Service', pick), rows,
    h('div', { class: 'row', style: { justifyContent: 'space-between', marginTop: '12px' } },
      h('button', { type: 'button', class: 'btn', onclick: () => rows.append(row()) }, icon('plus', 13), 'Add step'),
      h('button', { type: 'button', class: 'btn primary', onclick: (e) => busy(e.currentTarget, 'Saving…', async () => {
        current.steps = (await api('PUT', 'clients/onboarding', { service: current.key, steps: [...rows.children].map((x) => x.read()) })).steps;
        toast('Saved.');
      }) }, 'Save')),
    h('p', { class: 'muted small' }, 'Clients see this once a request is accepted or paid.')), { wide: true });
}
