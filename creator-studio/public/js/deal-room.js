// /deal/<token>: the client's own page for a deal. Her proposal, tier switcher (Essential / Growth / Enterprise),
// "I accept" with cryptographic E-signature, and Pay with Stripe when it's set up. Works signed out.
import { h, api, toast, mdBlock } from './core.js';
import { icon, brandMark } from './icons.js';

export async function renderDealRoom(view, token) {
  const d = await api('GET', `room/${token}`);
  const money = (amt) => `${d.currency === 'USD' ? '$' : `${d.currency} `}${Math.round(amt || d.amount || 0).toLocaleString()}`;
  const first = (d.creator || '').split(' ')[0];
  const status = h('div', { class: 'deal-room-status' });
  const tierContainer = h('div', { class: 'deal-room-tiers' });
  const rateDisplay = h('strong', { class: 'deal-rate' }, money(d.amount));

  function drawTiers() {
    if (!d.tiers || !d.tiers.length || d.accepted_at) {
      tierContainer.replaceChildren();
      return;
    }

    tierContainer.replaceChildren(
      h('h3', { style: { marginTop: '16px', marginBottom: '8px' } }, 'Select your partnership scope:'),
      h('div', { class: 'grid grid-3', style: { gap: '10px' } },
        d.tiers.map((t) => {
          const isSelected = t.key === (d.selected_tier || 'growth');
          return h('div', {
            class: `card ${isSelected ? 'callout picked' : ''}`,
            style: { cursor: 'pointer', position: 'relative', padding: '12px', border: isSelected ? '2px solid var(--accent, #6366f1)' : '' },
            onclick: async () => {
              if (t.key === d.selected_tier) return;
              try {
                const res = await api('POST', `room/${token}/select-tier`, { tier: t.key });
                d.selected_tier = res.selected_tier;
                d.amount = res.new_rate;
                rateDisplay.textContent = money(res.new_rate);
                toast(res.message);
                drawTiers();
                drawStatus();
              } catch (err) {
                toast(err.message, 'err');
              }
            },
          },
          t.badge ? h('span', { class: 'pill sm ok', style: { position: 'absolute', top: '8px', right: '8px' } }, t.badge) : null,
          h('h4', { style: { margin: '0 0 4px 0' } }, t.name),
          h('strong', { style: { fontSize: '1.1rem' } }, money(t.amount)),
          h('p', { class: 'muted small', style: { margin: '6px 0 8px 0', fontSize: '0.8rem' } }, t.summary),
          h('ul', { class: 'small muted', style: { paddingLeft: '14px', margin: 0, fontSize: '0.75rem' } },
            t.features.slice(0, 3).map((f) => h('li', {}, f))));
        })));
  }
  drawTiers();

  function drawStatus() {
    const name = h('input', { type: 'text', required: true, minlength: 2, placeholder: 'Your full legal name', 'aria-label': 'Your full legal name', autocomplete: 'name' });
    const agree = h('input', { type: 'checkbox', required: true });
    const acceptBtn = h('button', { type: 'submit', class: 'btn primary' }, icon('check', 15), ' Sign & accept agreement');
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
    }, icon('lock', 15), ` Pay ${money(d.amount)}`) : null;

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
                Object.assign(d, { accepted_at: r.accepted_at, accepted_by: r.accepted_by, signature_hash: r.signature_hash });
                toast(r.message);
                drawTiers();
                drawStatus();
              } catch (err) {
                acceptBtn.disabled = false;
                toast(err.message, 'err');
              }
            },
          },
          h('label', { class: 'row small', style: { gap: '8px' } }, agree, `I accept this proposal from ${d.creator} (scope, timeline and selected tier above).`),
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
    tierContainer,
    h('div', { class: 'card deal-room-actions' },
      h('div', { class: 'row-between', style: { flexWrap: 'wrap', gap: '8px' } }, h('strong', {}, 'Investment'), rateDisplay),
      d.proposal?.investment?.terms ? h('p', { class: 'muted small' }, d.proposal.investment.terms) : null,
      status),
    h('p', { class: 'muted small', style: { textAlign: 'center' } }, 'Questions? Just reply to the email this link came in.'),
    h('div', { class: 'deal-room-legal-footer', style: { textAlign: 'center', marginTop: '1.5rem', fontSize: '0.8rem', color: 'var(--muted)' } },
      h('p', {}, 'EU VAT Notice: Invoices issued under Article 196 Directive 2006/112/EC (Reverse Charge applicable for verified EU business VAT numbers).'),
      h('div', { style: { display: 'flex', justifyContent: 'center', gap: '1rem', marginTop: '0.35rem' } },
        h('a', { href: '/impressum', target: '_blank', style: { color: 'var(--muted)', textDecoration: 'underline' } }, 'Legal Notice (Impressum)'),
        h('span', {}, '•'),
        h('a', { href: '/terms', target: '_blank', style: { color: 'var(--muted)', textDecoration: 'underline' } }, 'Commercial Terms & Conditions'),
        h('span', {}, '•'),
        h('a', { href: '/privacy', target: '_blank', style: { color: 'var(--muted)', textDecoration: 'underline' } }, 'GDPR Privacy Policy')
      )
    )));
}
