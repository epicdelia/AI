// /deal/<token>: the client's own page for a deal. Her proposal, "I accept" (their typed name
// and the time are recorded), and Pay with Stripe when it's set up. Works signed out. Opening it
// tells her (notification, a follow-up, and the "Proposal or kit opened" workflow trigger).
import { h, api, toast, mdBlock } from './core.js';
import { icon, brandMark } from './icons.js';

export async function renderDealRoom(view, token) {
  const d = await api('GET', `room/${token}`);
  const money = `${d.currency === 'USD' ? '$' : `${d.currency} `}${Math.round(d.amount || 0).toLocaleString()}`;
  const first = (d.creator || '').split(' ')[0];
  const status = h('div', { class: 'deal-room-status' });

  function drawStatus() {
    const name = h('input', { type: 'text', required: true, minlength: 2, placeholder: 'Your full name', 'aria-label': 'Your full name', autocomplete: 'name' });
    const agree = h('input', { type: 'checkbox', required: true });
    const acceptBtn = h('button', { type: 'submit', class: 'btn primary' }, icon('check', 15), ' Accept proposal');
    const payBtn = d.can_pay && !d.paid ? h('button', {
      type: 'button', class: `btn${d.accepted_at ? ' primary' : ''}`,
      onclick: async () => {
        payBtn.disabled = true;
        try {
          const r = await api('POST', `room/${token}/pay`);
          location.href = r.checkout_url;
        } catch (err) {
          payBtn.disabled = false;
          toast(err.message, 'err');
        }
      },
    }, icon('lock', 15), ` Pay ${money}`) : null;

    status.replaceChildren(
      d.paid ? h('p', { class: 'deal-room-done' }, icon('check', 16), ' Paid. Thank you!')
        : d.accepted_at ? h('p', { class: 'deal-room-done' }, icon('check', 16), ` Accepted by ${d.accepted_by} on ${new Date(d.accepted_at).toLocaleDateString([], { day: 'numeric', month: 'long', year: 'numeric' })}.`)
          : h('form', {
            class: 'deal-room-accept',
            onsubmit: async (e) => {
              e.preventDefault();
              acceptBtn.disabled = true;
              try {
                const r = await api('POST', `room/${token}/accept`, { name: name.value.trim(), agree: agree.checked });
                Object.assign(d, { accepted_at: r.accepted_at, accepted_by: r.accepted_by });
                toast(r.message);
                drawStatus();
              } catch (err) {
                acceptBtn.disabled = false;
                toast(err.message, 'err');
              }
            },
          },
          h('label', { class: 'row small', style: { gap: '8px' } }, agree, `I accept this proposal from ${d.creator} (scope, timeline and investment above).`),
          h('div', { class: 'row', style: { gap: '8px', flexWrap: 'wrap' } }, name, acceptBtn)),
      payBtn ? h('div', { class: 'row', style: { gap: '8px', marginTop: '10px', alignItems: 'center' } }, payBtn, h('span', { class: 'muted small' }, icon('lock', 12), ' Secure Stripe checkout')) : null,
      !d.can_pay && !d.paid ? h('p', { class: 'muted small' }, `${first} will send you an invoice.`) : null);
  }
  drawStatus();

  const q = new URLSearchParams(location.search);
  if (q.get('payment') === 'success') toast('Payment received. Thank you!');

  view.append(h('div', { class: 'deal-room' },
    h('div', { class: 'row', style: { gap: '12px', alignItems: 'center', marginBottom: '16px' } }, brandMark(32),
      h('div', {}, h('h1', { style: { margin: 0, fontSize: '1.4rem' } }, d.proposal?.title || `Proposal for ${d.client}`),
        h('p', { class: 'muted small', style: { margin: 0 } }, `From ${d.creator} for ${d.client}${d.proposal?.valid_until ? ` · valid until ${d.proposal.valid_until}` : ''}`))),
    d.has_account ? h('div', { class: 'card callout' }, icon('user', 15), ' This is on your client dashboard too, with messages, files and invoices. ',
      h('a', { href: '/login' }, 'Sign in')) : null,
    d.markdown ? h('div', { class: 'card' }, mdBlock(d.markdown)) : h('div', { class: 'card' }, h('p', { class: 'muted' }, `${first} is finishing your proposal. Check back soon.`)),
    h('div', { class: 'card deal-room-actions' },
      h('div', { class: 'row-between', style: { flexWrap: 'wrap', gap: '8px' } }, h('strong', {}, 'Investment'), h('strong', { class: 'deal-rate' }, money)),
      d.proposal?.investment?.terms ? h('p', { class: 'muted small' }, d.proposal.investment.terms) : null,
      status),
    h('p', { class: 'muted small', style: { textAlign: 'center' } }, 'Questions? Just reply to the email this link came in.')));
}
