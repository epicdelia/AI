// Her side of brand inquiries (autopilot rules, a deal's fit + drafted reply) and of
// sponsored-copy approval (send the exact words to the client, see their answer, override).
import { h, api, toast, busy, modal, field, clear } from './core.js';
import { icon } from './icons.js';

export async function inquiryRulesDialog() {
  const r = await api('GET', 'inquiry-rules');
  const f = {
    enabled: h('input', { type: 'checkbox', checked: r.enabled }),
    mode: h('select', {}, h('option', { value: 'draft', selected: r.mode === 'draft' }, 'Draft each reply for my approval'), h('option', { value: 'send', selected: r.mode === 'send' }, 'Send replies automatically')),
    min: h('input', { type: 'number', min: 0, value: r.min_budget || '' }),
    lead: h('input', { type: 'number', min: 0, value: r.min_lead_days || '' }),
    blocked: h('input', { value: r.blocked_words.join(', '), placeholder: 'crypto, casino, vape…' }),
    price: h('input', { value: r.starting_price, maxlength: 60, placeholder: 'e.g. $2,500 (leave empty to not quote)' }),
    pursue: h('textarea', { rows: 2, maxlength: 1000 }, r.pursue_note),
    pass: h('textarea', { rows: 2, maxlength: 1000 }, r.pass_note),
    reviewed: h('input', { type: 'checkbox' }),
  };
  const types = Object.entries(r.project_type_labels).map(([k, label]) => {
    const cb = h('input', { type: 'checkbox', checked: r.project_types.includes(k) });
    cb.dataset.key = k;
    return h('label', { class: 'row small', style: { gap: '6px' } }, cb, label);
  });
  await modal('Inquiry autopilot', (close) => h('div', {},
    h('label', { class: 'row small', style: { gap: '6px' } }, f.enabled, 'Triage new inquiries from the website'),
    field('Replies', f.mode),
    h('div', { class: 'grid grid-2' }, field('Minimum budget ($)', f.min, '0 = no minimum'), field('Minimum lead time (days)', f.lead)),
    field('Never promote (blocked words)', f.blocked, 'Comma separated; a match means “pass”'),
    field('Starting price to quote', f.price), field('Extra line for good fits', f.pursue), field('Polite “not now” text', f.pass),
    h('div', {}, h('span', { class: 'muted small' }, 'Apply to'), ...types),
    h('label', { class: 'row small', style: { gap: '6px' } }, f.reviewed, `My rates are up to date${r.rate_card_stale ? ' (not reviewed in 90+ days)' : ''}`),
    h('p', { class: 'muted small' }, 'Pursue → your price (if set), media kit and booking link. Pass → the polite text. Unclear → nothing is sent; it waits in Needs you. Rules are yours: no AI guesses.'),
    h('div', { class: 'row-end' }, h('button', { type: 'button', class: 'btn primary', onclick: (e) => busy(e.currentTarget, 'Saving…', async () => {
      await api('PUT', 'inquiry-rules', {
        enabled: f.enabled.checked, mode: f.mode.value, min_budget: Number(f.min.value || 0), min_lead_days: Number(f.lead.value || 0),
        blocked_words: f.blocked.value.split(',').map((x) => x.trim()).filter(Boolean), starting_price: f.price.value || '',
        pursue_note: f.pursue.value || '', pass_note: f.pass.value || '', rate_card_reviewed: f.reviewed.checked,
        project_types: types.map((l) => l.querySelector('input')).filter((cb) => cb.checked).map((cb) => cb.dataset.key),
      });
      toast('Saved.'); close();
    }) }, 'Save'))), { wide: true });
}

export async function copyDialog(deal, onChange = () => {}) {
  let c = await api('GET', `deals/${deal.id}/copy`);
  const body = h('div');
  function draw() {
    const text = h('textarea', { rows: 8, maxlength: 20000 }, c.copy?.text || c.suggested || '');
    const due = h('input', { type: 'date', value: c.copy?.due_on || '' });
    clear(body).append(
      c.fit ? h('div', { class: 'card' }, h('strong', {}, `Inquiry fit: ${c.fit.score}/100 · ${c.fit.verdict}`), h('ul', { class: 'small' }, (c.fit.reasons || []).map((x) => h('li', {}, x)))) : null,
      c.auto_reply ? h('div', { class: 'card' },
        h('strong', {}, c.auto_reply.status === 'sent' ? 'Reply sent' : 'Reply drafted'),
        h('pre', { style: { whiteSpace: 'pre-wrap' } }, c.auto_reply.body || ''),
        c.auto_reply.status === 'draft' ? h('div', { class: 'row' },
          h('button', { type: 'button', class: 'btn primary sm', onclick: (e) => busy(e.currentTarget, 'Sending…', async () => { toast((await api('POST', `deals/${deal.id}/auto-reply/send`)).message); c = await api('GET', `deals/${deal.id}/copy`); draw(); onChange(); }) }, 'Send it'),
          h('button', { type: 'button', class: 'btn ghost sm', onclick: async () => { await api('DELETE', `deals/${deal.id}/auto-reply`); c = await api('GET', `deals/${deal.id}/copy`); draw(); } }, 'Discard')) : null) : null,
      h('h3', {}, 'Sponsored copy for the client to approve'),
      !c.linked_client ? h('p', { class: 'muted small' }, 'Invite the contact to their dashboard first: they approve it there.') : null,
      c.copy ? h('p', { class: 'small' }, h('span', { class: `pill sm ${c.copy.status === 'approved' ? 'ok' : c.copy.status === 'changes' ? 'late' : ''}` }, c.copy.status),
        c.copy.note ? ` “${c.copy.note}”` : '', c.copy.due_on ? ` · due ${c.copy.due_on}` : '') : null,
      field('Exact wording', text, c.suggested && !c.copy ? 'Pre-filled from the linked post or the script’s sponsor section' : null),
      field('Approve by', due),
      h('div', { class: 'row', style: { justifyContent: 'space-between' } },
        c.copy && c.copy.status !== 'approved' ? h('label', { class: 'row small', style: { gap: '6px' } }, h('input', { type: 'checkbox', checked: c.override, onchange: async (e) => { c = await api('PUT', `deals/${deal.id}/copy/override`, { on: e.target.checked }); draw(); } }), 'Publish without waiting (override)') : h('span'),
        h('button', { type: 'button', class: 'btn primary', disabled: !c.linked_client, onclick: (e) => busy(e.currentTarget, 'Sending…', async () => {
          c = await api('POST', `deals/${deal.id}/copy`, { text: text.value, due_on: due.value || null }); toast('Sent for approval.'); draw(); onChange();
        }) }, c.copy ? 'Send the new version' : 'Send for approval')),
      h('p', { class: 'muted small' }, 'While it waits, the linked post can’t be scheduled or published.'));
  }
  draw();
  await modal(`Inquiry & sponsored copy · ${deal.brand_name}`, () => body, { wide: true });
}

export { icon };
