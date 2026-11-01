// /deal/<token>: the client's own page for a deal. Her proposal, tier switcher (Essential / Growth / Enterprise),
// "I accept" with cryptographic E-signature, and Pay with Stripe when it's set up. Works signed out.
import { h, api, toast, mdBlock, modal } from './core.js';
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

  const privacyCard = h('div', { class: 'card', style: { marginTop: '16px', background: 'rgba(99, 102, 241, 0.04)', borderColor: 'rgba(99, 102, 241, 0.2)' } });
  function drawPrivacyStatus() {
    privacyCard.replaceChildren(
      h('div', { class: 'row-between', style: { flexWrap: 'wrap', gap: '8px', alignItems: 'center' } },
        h('div', {},
          h('div', { class: 'row', style: { gap: '8px', alignItems: 'center' } },
            icon('shield', 18),
            h('strong', { style: { fontSize: '0.95rem' } }, 'Enterprise Privacy & LLM Shield'),
            h('span', { class: 'pill sm ok' }, 'Zero Data Retention (ZDR)')
          ),
          h('p', { class: 'muted small', style: { margin: '4px 0 0 0' } }, 'In-memory processing · Zero model training · In-flight secret redaction · GDPR Art. 28 DPA')
        ),
        h('button', {
          type: 'button',
          class: `btn sm ${d.dpa_signed_at ? 'ok' : 'ghost'}`,
          onclick: () => dpaDialog(token, d).then(() => drawPrivacyStatus())
        }, icon('file', 14), d.dpa_signed_at ? ' View Signed DPA' : ' Review & Sign DPA (GDPR)')
      )
    );
  }
  drawPrivacyStatus();

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
    privacyCard,
    h('div', { class: 'card deal-room-actions' },
      h('div', { class: 'row-between', style: { flexWrap: 'wrap', gap: '8px' } }, h('strong', {}, 'Investment'), rateDisplay),
      d.proposal?.investment?.terms ? h('p', { class: 'muted small' }, d.proposal.investment.terms) : null,
      status),
    h('p', { class: 'muted small', style: { textAlign: 'center' } }, 'Questions? Just reply to the email this link came in.'),
    h('div', { class: 'deal-room-legal-footer', style: { textAlign: 'center', marginTop: '1.5rem', fontSize: '0.8rem', color: 'var(--muted)' } },
      h('p', {}, 'EU VAT Notice: Invoices issued under Article 196 Directive 2006/112/EC (Reverse Charge applicable for verified EU business VAT numbers).'),
      h('div', { style: { display: 'flex', justifyContent: 'center', gap: '1rem', marginTop: '0.35rem', flexWrap: 'wrap' } },
        h('a', { href: '/impressum', target: '_blank', style: { color: 'var(--muted)', textDecoration: 'underline' } }, 'Legal Notice (Impressum)'),
        h('span', {}, '•'),
        h('a', { href: '/terms', target: '_blank', style: { color: 'var(--muted)', textDecoration: 'underline' } }, 'Commercial Terms'),
        h('span', {}, '•'),
        h('a', { href: '/privacy', target: '_blank', style: { color: 'var(--muted)', textDecoration: 'underline' } }, 'GDPR Privacy'),
        h('span', {}, '•'),
        h('a', { href: '#/dpa', target: '_blank', style: { color: 'var(--muted)', textDecoration: 'underline' } }, 'Data Processing Agreement (DPA)'),
        h('span', {}, '•'),
        h('a', { href: '#/subprocessors', target: '_blank', style: { color: 'var(--muted)', textDecoration: 'underline' } }, 'Sub-Processors')
      )
    )));
}

export async function dpaDialog(token, d) {
  return modal('Enterprise Data Processing Agreement (GDPR Art. 28)', (close) => {
    const body = h('div', { class: 'dpa-dialog-content', style: { maxHeight: '70vh', overflowY: 'auto', paddingRight: '8px' } });
    const content = h('div', { class: 'muted' }, 'Loading DPA agreement...');
    body.appendChild(content);

    api('GET', `room/${token}/dpa`).then((res) => {
      const isSigned = !!res.signed;
      const doc = h('div', { class: 'card', style: { marginBottom: '16px' } }, mdBlock(res.markdown));

      if (isSigned) {
        content.replaceChildren(
          h('div', { class: 'callout ok', style: { marginBottom: '12px', padding: '12px' } },
            h('strong', {}, '✓ DPA Cryptographically Executed under GDPR Article 28'),
            h('p', { class: 'small muted', style: { margin: '4px 0 0 0' } }, `Signed by ${res.signer_name} (${res.signer_email}) on ${new Date(res.signed_at).toLocaleString()}`),
            h('p', { class: 'small', style: { fontFamily: 'monospace', wordBreak: 'break-all', marginTop: '6px' } }, `SHA-256 Hash: ${res.signature_hash}`)
          ),
          doc,
          h('div', { class: 'row', style: { justifyContent: 'flex-end', marginTop: '12px' } },
            h('button', { class: 'btn', onclick: () => close() }, 'Close')
          )
        );
      } else {
        const signerName = h('input', { type: 'text', required: true, placeholder: 'Signer Legal Name', value: d.contact || '' });
        const signerEmail = h('input', { type: 'email', required: true, placeholder: 'Signer Corporate Email' });
        const agreeCheck = h('input', { type: 'checkbox', required: true });
        const signBtn = h('button', { type: 'submit', class: 'btn primary' }, 'Cryptographically Sign DPA (SHA-256)');

        const form = h('form', {
          class: 'card',
          style: { marginTop: '16px' },
          onsubmit: async (e) => {
            e.preventDefault();
            signBtn.disabled = true;
            try {
              const signRes = await api('POST', `room/${token}/dpa/sign`, {
                name: signerName.value.trim(),
                email: signerEmail.value.trim(),
                agree: agreeCheck.checked,
              });
              toast(signRes.message);
              d.dpa_signed_at = signRes.signed_at;
              d.dpa_signer_name = signRes.signer_name;
              d.dpa_signature_hash = signRes.signature_hash;
              close(signRes);
            } catch (err) {
              signBtn.disabled = false;
              toast(err.message, 'err');
            }
          }
        },
        h('h4', { style: { margin: '0 0 8px 0' } }, 'Electronic Execution (Regulation (EU) No 910/2014)'),
        h('p', { class: 'small muted', style: { margin: '0 0 12px 0' } }, 'Executing this DPA incorporates EU Standard Contractual Clauses (SCCs Module 2) and binds both parties to Article 28 GDPR obligations.'),
        h('div', { class: 'grid grid-2', style: { gap: '10px', marginBottom: '10px' } }, signerName, signerEmail),
        h('label', { class: 'row small', style: { gap: '8px', alignItems: 'flex-start', marginBottom: '12px' } },
          agreeCheck,
          h('span', {}, 'I represent that I have legal authority to bind this entity to the GDPR Article 28 Data Processing Agreement and EU Standard Contractual Clauses.')
        ),
        h('div', { class: 'row', style: { justifyContent: 'space-between', alignItems: 'center' } },
          h('button', { type: 'button', class: 'btn ghost', onclick: () => close() }, 'Cancel'),
          signBtn
        ));

        content.replaceChildren(doc, form);
      }
    }).catch((err) => {
      content.replaceChildren(h('p', { class: 'err' }, 'Failed to load DPA: ' + err.message));
    });

    return body;
  }, { wide: true });
}
