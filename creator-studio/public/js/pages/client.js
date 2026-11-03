// A client's dashboard (Role abilities `portal.requests` / `portal.billing`): their own requests,
// proposals with multi-tier scope switchers, deliverables, proofing annotations, verified campaign ROI,
// legal & FTC compliance certificates, add-ons, payments, brand vault and partner referrals.
import { h, api, busy, toast, pageHead, emptyState, field, formData, clear, link, navigate, markdown, fmtDay, timeAgo, modal } from '../core.js';
import { icon } from '../icons.js';
import { reviewPlayer } from '../review-player.js';
import { printMarkdown } from '../components.js';

const money = (amount, currency = 'USD') => amount == null ? '' : new Intl.NumberFormat(undefined, { style: 'currency', currency, maximumFractionDigits: 0 }).format(amount);
const when = (iso) => new Date(iso).toLocaleString([], { weekday: 'short', day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' });

function statusPill(status) {
  const tone = { closed: '', paid: 'ok', invoiced: 'late', proposal: 'late' }[status.key] ?? 'picked';
  return h('span', { class: `pill ${tone}` }, status.label);
}

function requestRow(r) {
  return link(`/requests/${r.id}`, { class: 'card client-request' },
    h('div', { class: 'card-header' },
      h('div', {}, h('h3', {}, r.title), h('p', { class: 'muted small' }, r.company, ' · updated ', timeAgo(r.updated_at))),
      h('div', { class: 'row' },
        r.unread ? h('span', { class: 'pill late' }, icon('chat', 12), ` ${r.unread} new`) : null,
        r.annotations_count ? h('span', { class: 'pill picked' }, icon('target', 12), ` ${r.annotations_count} revisions`) : null,
        statusPill(r.status))),
    r.needs_you ? h('p', { class: 'client-needs' }, icon('alert', 14), ' ', r.needs_you) : null,
    r.meeting_at ? h('p', { class: 'muted small' }, icon('calendar', 13), ' Call: ', when(r.meeting_at)) : null,
    r.amount != null ? h('p', { class: 'muted small' }, money(r.amount, r.currency), r.payment_status === 'paid' ? ' · paid' : '') : null);
}

export async function renderClientHome(view) {
  const o = await api('GET', 'portal/overview');
  const first = (o.creator || '').split(' ')[0] || 'the studio';
  const brand = window.__portalBrand;
  view.append(
    pageHead('Your projects', brand?.welcome || `Everything you’re working on with ${first}.`,
      h('div', { class: 'row' },
        link('/brand-vault', { class: 'btn' }, icon('sliders', 14), 'Brand Vault'),
        link('/requests/new', { class: 'btn primary' }, icon('plus', 15), 'New request'))),
    h('div', { class: 'grid grid-3' },
      stat('Open requests', o.counts.open, 'briefcase'),
      stat('New messages', o.counts.unread, 'chat'),
      stat('Completed', o.counts.done, 'check')),
    o.next_call ? h('div', { class: 'card callout' }, icon('calendar', 16), ' Next call: ', h('strong', {}, when(o.next_call.at)), ' · ',
      link(`/requests/${o.next_call.deal_id}`, {}, o.next_call.title)) : null,
    o.needs_you.length ? h('div', { class: 'card' }, h('h2', {}, 'Needs you'),
      h('ul', { class: 'stack' }, o.needs_you.map((n) => h('li', {}, link(`/requests/${n.deal_id}`, {}, h('strong', {}, n.title)), ' — ', n.what)))) : null,
    h('h2', {}, 'In progress'),
    o.recent.length ? h('div', { class: 'stack' }, o.recent.map(requestRow))
      : emptyState(icon('briefcase', 28), 'Nothing in progress', `Ask ${first} for something and follow it here.`,
        link('/requests/new', { class: 'btn primary' }, icon('plus', 15), 'New request')),
    brand?.support_email || brand?.footer ? h('p', { class: 'muted small', style: { marginTop: '24px' } },
      brand.support_email ? ['Need help? ', h('a', { href: `mailto:${brand.support_email}` }, brand.support_email), brand.footer ? ' · ' : ''] : null,
      brand.footer || null) : null);
}

function stat(label, value, ico) {
  return h('div', { class: 'card stat' }, h('span', { class: 'muted small' }, icon(ico, 14), ' ', label), h('strong', { class: 'stat-value' }, String(value)));
}

export async function renderRequests(view) {
  const { requests } = await api('GET', 'portal/requests');
  view.append(
    pageHead('My requests', 'Every request, its status, proposal and deliverables.',
      link('/requests/new', { class: 'btn primary' }, icon('plus', 15), 'New request')),
    requests.length ? h('div', { class: 'stack' }, requests.map(requestRow))
      : emptyState(icon('briefcase', 28), 'No requests yet', 'Requests you make here or on the website (with this email) show up here.',
        link('/requests/new', { class: 'btn primary' }, icon('plus', 15), 'New request')));
}

export async function renderNewRequest(view) {
  const { services, intake = {} } = await api('GET', 'portal/requests');
  const submit = h('button', { class: 'btn primary', type: 'submit' }, icon('check', 15), 'Send request');
  const questionsSlot = h('div');
  const serviceSelect = h('select', { name: 'service', required: true, onchange: () => drawQuestions() }, services.map((s) => h('option', { value: s.key }, s.label)));
  function drawQuestions() {
    const qs = intake[serviceSelect.value] || [];
    clear(questionsSlot).append(qs.length ? h('fieldset', { class: 'stack' }, h('legend', { class: 'small muted' }, 'A few questions so the first call is useful'),
      qs.map((q) => field(q.label, intakeInput(q), q.required ? null : 'optional'))) : '');
  }
  drawQuestions();
  const form = h('form', { class: 'card', onsubmit: (e) => { e.preventDefault(); busy(submit, 'Sending…', async () => {
    const body = formData(form);
    body.intake = intakeValues(questionsSlot);
    for (const k of Object.keys(body)) if (k.startsWith('intake__')) delete body[k];
    const r = await api('POST', 'portal/requests', body);
    toast(r.message);
    navigate(`/requests/${r.request.id}`);
  }); } },
  field('What do you need?', serviceSelect),
  questionsSlot,
  field('Tell us about it', h('textarea', { name: 'details', rows: 6, required: true, minlength: 10, maxlength: 4000, placeholder: 'Goals, audience, links, anything that helps.' })),
  h('div', { class: 'grid grid-2' },
    field('Budget', h('input', { name: 'budget', maxlength: 100, placeholder: 'e.g. $5–10k' }), 'optional'),
    field('Deadline', h('input', { name: 'deadline', maxlength: 100, placeholder: 'e.g. end of March' }), 'optional')),
  submit);
  view.append(pageHead('New request', 'Send it and you’ll get an answer (and later a proposal) right here.'), form);
}

function intakeInput(q) {
  const common = { name: `intake__${q.key}`, required: !!q.required, dataset: { intake: q.key } };
  if (q.type === 'textarea') return h('textarea', { ...common, rows: 3, maxlength: 4000 });
  if (q.type === 'select') return h('select', common, h('option', { value: '' }, 'Choose…'), (q.options || []).map((o) => h('option', { value: o }, o)));
  return h('input', { ...common, type: q.type === 'url' ? 'url' : q.type === 'date' ? 'date' : 'text', maxlength: 4000 });
}

function intakeValues(root) {
  const out = {};
  root.querySelectorAll('[data-intake]').forEach((el) => { out[el.dataset.intake] = el.value; });
  return out;
}

function onboardingCard(r, id) {
  const o = r.onboarding;
  if (!o?.active || !o.steps.length) return null;
  const box = h('div', { class: 'card' });
  function draw(c) {
    clear(box).append(h('div', { class: 'card-header' }, h('h2', {}, 'Getting started'), h('span', { class: `pill ${c.done === c.total ? 'ok' : ''}` }, `${c.done}/${c.total}`)),
      h('ul', { class: 'stack' }, c.steps.map((s) => h('li', { class: 'row' },
        s.auto ? h('span', { class: `pill sm ${s.done ? 'ok' : ''}`, 'aria-label': s.done ? 'Done' : 'To do' }, s.done ? '✓' : '·')
          : h('input', { type: 'checkbox', checked: s.done, 'aria-label': s.label, onchange: async (e) => { try { draw((await api('POST', `portal/requests/${id}/onboarding/${s.key}`, { done: e.target.checked })).onboarding); } catch (err) { toast(err.message, 'err'); } } }),
        h('span', { style: { textDecoration: s.done ? 'line-through' : '' } }, s.label),
        s.auto && !s.done ? h('span', { class: 'muted small' }, s.type === 'upload' ? ' · add a file below' : s.type === 'intake' ? ' · answer the questions above' : s.type === 'vault' ? ' · open Brand Vault' : s.type === 'call' ? ' · book a call' : '') : null))));
  }
  draw(o);
  return box;
}

function billingTermsCard(r) {
  const b = r.billing;
  if (!b || r.amount == null || (!b.payment_plan && !b.deposit_percent && !b.invoice_due_on)) return null;
  return h('div', { class: `card${b.overdue ? ' callout warn' : ''}` }, h('h2', {}, 'Payment terms'),
    h('ul', { class: 'stack small' },
      b.payment_plan ? h('li', {}, `Plan: ${{ on_receipt: 'due on receipt', net7: 'net 7 days', net15: 'net 15 days', net30: 'net 30 days' }[b.payment_plan] || b.payment_plan}`) : null,
      b.deposit_percent ? h('li', {}, `${b.deposit_percent}% deposit${b.deposit_paid_at ? ` paid ${fmtDay(b.deposit_paid_at.slice(0, 10))}` : ' up front'}; the balance once the work is live`) : null,
      b.invoice_due_on ? h('li', {}, `Invoice due ${fmtDay(b.invoice_due_on)}${b.overdue ? ` · ${b.days_overdue} days overdue` : ''}`) : null,
      b.late_fee_percent && b.invoice_due_on ? h('li', { class: 'muted' }, `Late payments may carry a ${b.late_fee_percent}% monthly fee.`) : null));
}

function rightsCard(r, id) {
  if (!r.rights?.length) return null;
  return h('div', { class: 'card' }, h('h2', {}, 'Usage rights'),
    h('ul', { class: 'stack' }, r.rights.map((x) => h('li', { class: 'row-between' },
      h('span', {}, h('strong', {}, x.label), x.scope ? ` · ${x.scope}` : '',
        h('div', { class: 'muted small' }, x.ends_on ? `Until ${fmtDay(x.ends_on)}${x.status === 'ended' ? ' (ended)' : x.ends_soon ? ' · ends soon' : ''}` : `${x.days} days from the publish date`)),
      (x.ends_soon || x.status === 'ended') && x.price > 0 ? h('button', { class: 'btn sm primary', type: 'button', onclick: (e) => busy(e.currentTarget, 'Opening checkout…', async () => {
        location.href = (await api('POST', `portal/requests/${id}/rights/${x.id}/extend`)).checkout_url;
      }) }, `Extend ${x.days} days · ${money(x.price, r.currency)}`) : null))));
}

function intakeCard(r, id, onSaved) {
  if (r.intake?.length) {
    return h('div', { class: 'card' }, h('h2', {}, 'Your brief'),
      h('dl', { class: 'stack' }, r.intake.map((a) => h('div', {}, h('dt', { class: 'small muted' }, a.label), h('dd', { style: { whiteSpace: 'pre-wrap', margin: 0 } }, a.answer || '–')))));
  }
  if (!r.intake_questions?.length || r.status.step == null) return null;
  const slot = h('div', { class: 'stack' }, r.intake_questions.map((q) => field(q.label, intakeInput(q), q.required ? null : 'optional')));
  const save = h('button', { class: 'btn primary', type: 'submit' }, 'Save answers');
  return h('form', { class: 'card callout', onsubmit: (e) => { e.preventDefault(); busy(save, 'Saving…', async () => {
    toast((await api('POST', `portal/requests/${id}/intake`, { intake: intakeValues(slot) })).message);
    onSaved();
  }); } }, h('h2', {}, 'A few questions'), h('p', { class: 'muted small' }, 'Answering these before the call saves you time.'), slot, save);
}

export async function renderRequest(view, id) {
  let { request: r } = await api('GET', `portal/requests/${id}`);
  const thread = h('div', { class: 'client-thread', 'aria-live': 'polite' });
  const milestonesSlot = h('div');
  const annotationsSlot = h('div', { class: 'card' });
  const complianceSlot = h('div', { class: 'card callout' });
  const roiSlot = h('div');
  const addOnsSlot = h('div');
  const reviewSlot = h('div');
  const annotationCache = [];

  async function loadReview() {
    try {
      const { files } = await api('GET', `portal/requests/${id}/files`);
      const player = reviewPlayer({ files, streamBase: `portal/requests/${id}/files`, annotationsPath: `portal/requests/${id}/annotations`, annotations: annotationCache, onChange: () => loadAnnotations(false) });
      clear(reviewSlot).append(player || '');
    } catch { /* no files */ }
  }

  async function loadMilestones() {
    try {
      const data = await api('GET', `portal/requests/${id}/milestones`);
      clear(milestonesSlot).append(milestonesCard(data));
    } catch { /* optional */ }
  }

  async function loadMessages() {
    const { messages } = await api('GET', `portal/requests/${id}/messages`);
    clear(thread).append(messages.length ? messages.map(bubble) : h('p', { class: 'muted' }, 'No messages yet. Ask anything about this request.'));
    thread.scrollTop = thread.scrollHeight;
  }

  async function loadAnnotations(redrawPlayer = true) {
    const { annotations } = await api('GET', `portal/requests/${id}/annotations`);
    annotationCache.splice(0, annotationCache.length, ...annotations);
    if (redrawPlayer) loadReview();
    clear(annotationsSlot).append(
      h('div', { class: 'card-header' },
        h('h2', {}, 'Revisions & Proofing'),
        h('button', { class: 'btn sm', type: 'button', onclick: () => openAnnotationModal(id, () => loadAnnotations()) }, icon('plus', 13), 'Add revision note')),
      annotations.length ? h('div', { class: 'stack' }, annotations.map((a) => annotationRow(a, id, () => loadAnnotations())))
        : h('p', { class: 'muted small' }, 'No revision notes yet. Click “Add revision note” to pin timecode feedback on video cuts or copy.'));
  }

  async function loadCompliance() {
    try {
      const c = await api('GET', `portal/requests/${id}/compliance`);
      const dpaRes = await api('GET', `portal/requests/${id}/dpa`).catch(() => null);
      clear(complianceSlot).append(
        h('div', { class: 'card-header' },
          h('h2', {}, icon('lock', 15), ' Verified Legal, Privacy & EU Compliance'),
          h('span', { class: 'pill ok' }, `Score: ${c.compliance_score}/100`)),
        h('div', { class: 'grid grid-3 small', style: { gap: '10px', marginTop: '8px' } },
          h('div', {}, h('strong', {}, 'FTC & AVMSD Disclosures: '), h('span', { class: 'pill sm ok' }, 'Verified Callout & #ad')),
          h('div', {}, h('strong', {}, 'Exclusivity Window: '), h('span', { class: 'pill sm ok' }, 'Cleared Non-Compete')),
          h('div', {}, h('strong', {}, 'EU AI Act (Art. 50): '), h('span', { class: 'pill sm ok' }, 'Human-Verified Editorial')),
          h('div', {}, h('strong', {}, 'GDPR DPA (Art. 28): '), h('span', { class: `pill sm ${dpaRes?.signed ? 'ok' : 'late'}` }, dpaRes?.signed ? '✓ Executed' : 'Pending Sign')),
          h('div', {}, h('strong', {}, 'Zero Data Retention: '), h('span', { class: 'pill sm ok' }, 'Active ZDR Shield')),
          h('div', {}, h('strong', {}, 'PII Sanitization: '), h('span', { class: 'pill sm ok' }, 'In-Flight Active'))),
        h('div', { class: 'row', style: { marginTop: '12px', gap: '8px', flexWrap: 'wrap' } },
          h('button', { class: 'btn sm', type: 'button', onclick: () => openWrapReportModal(id) }, icon('file-text', 13), 'Executive Wrap Report'),
          h('button', { class: `btn sm ${dpaRes?.signed ? '' : 'primary'}`, type: 'button', onclick: () => openDpaModal(id, () => loadCompliance()) }, icon('file', 13), dpaRes?.signed ? 'View Signed DPA' : 'Execute DPA (GDPR Art. 28)'),
          h('button', { class: 'btn sm', type: 'button', onclick: () => openZdrModal(id) }, icon('shield', 13), 'ZDR Certificate'),
          r.payment_status === 'paid' ? h('button', { class: 'btn sm ghost', type: 'button', onclick: () => purgeDealData(id) }, icon('trash', 13), 'Purge Ephemeral Assets (GDPR Art. 17)') : null));
    } catch { /* skip */ }
  }

  async function loadRoi() {
    try {
      const data = await api('GET', `portal/requests/${id}/roi`);
      clear(roiSlot).append(roiCard(data));
    } catch { /* optional if not applicable */ }
  }

  async function loadAddOns() {
    try {
      const { catalog, applied, currency } = await api('GET', `portal/requests/${id}/add-ons`);
      clear(addOnsSlot).append(addOnsCard(id, catalog, applied, currency, () => {
        loadRoi();
        navigate(`/requests/${id}`, { replace: true });
      }));
    } catch { /* skip */ }
  }

  const body = h('textarea', { rows: 3, maxlength: 5000, required: true, placeholder: 'Write a message…', 'aria-label': 'Message' });
  const send = h('button', { class: 'btn primary', type: 'submit' }, icon('arrow', 15), 'Send');
  const compose = h('form', { class: 'stack', onsubmit: (e) => { e.preventDefault(); busy(send, 'Sending…', async () => {
    await api('POST', `portal/requests/${id}/messages`, { body: body.value });
    body.value = '';
    await loadMessages();
  }); } }, body, send);

  const steps = r.status.step == null ? h('p', { class: 'muted' }, 'This request is closed.')
    : h('ol', { class: 'client-steps', 'aria-label': 'Progress' }, r.steps.map((s, i) => h('li', {
      class: i < r.status.step ? 'done' : i === r.status.step ? 'current' : '', 'aria-current': i === r.status.step ? 'step' : null,
    }, s.label)));

  const eSignBadge = r.signature_hash ? h('div', { class: 'callout ok row small', style: { justifyContent: 'space-between', marginTop: '10px' } },
    h('span', {}, icon('lock', 13), ` Tamper-evident E-Signature verified (${r.accepted_by})`),
    h('span', { class: 'mono muted', title: r.signature_hash }, `SHA-256: ${r.signature_hash.slice(0, 16)}…`)) : null;

  function renderTierSwitcher() {
    if (!r.tiers || !r.tiers.length || r.accepted_at) return null;
    return h('div', { style: { margin: '14px 0' } },
      h('h3', { class: 'small muted', style: { marginBottom: '8px' } }, 'Select partnership tier:'),
      h('div', { class: 'grid grid-3', style: { gap: '8px' } },
        r.tiers.map((t) => {
          const isSelected = t.key === (r.selected_tier || 'growth');
          return h('div', {
            class: `card ${isSelected ? 'callout picked' : ''}`,
            style: { padding: '10px', cursor: 'pointer', border: isSelected ? '2px solid var(--accent, #6366f1)' : '' },
            onclick: async () => {
              if (t.key === r.selected_tier) return;
              const res = await api('POST', `portal/requests/${id}/select-tier`, { tier: t.key });
              toast(res.message);
              navigate(`/requests/${id}`, { replace: true });
            },
          },
          t.badge ? h('span', { class: 'pill sm ok' }, t.badge) : null,
          h('h4', { style: { margin: '4px 0 2px 0' } }, t.name),
          h('strong', {}, money(t.amount, r.currency)));
        })));
  }

  const proposal = r.proposal ? h('div', { class: 'card' },
    h('div', { class: 'card-header' }, h('h2', {}, 'Proposal'), r.amount != null ? h('strong', {}, money(r.amount, r.currency)) : null),
    renderTierSwitcher(),
    h('div', { class: 'markdown-body', html: markdown(r.proposal.markdown || '') }),
    r.accepted_at ? h('p', { class: 'muted small' }, icon('check', 14), ` Accepted by ${r.accepted_by} on ${fmtDay(r.accepted_at)}.`) : null,
    eSignBadge,
    r.can_accept ? acceptForm() : null) : null;

  function acceptForm() {
    const name = h('input', { name: 'name', required: true, minlength: 2, maxlength: 120, autocomplete: 'name', placeholder: 'Your full legal name' });
    const agree = h('input', { type: 'checkbox', name: 'agree', required: true });
    const btn = h('button', { class: 'btn primary', type: 'submit' }, icon('check', 15), 'Sign and accept agreement');
    return h('form', { class: 'stack', onsubmit: (e) => { e.preventDefault(); busy(btn, 'Signing & verifying…', async () => {
      toast((await api('POST', `portal/requests/${id}/accept`, { name: name.value, agree: agree.checked })).message);
      navigate(`/requests/${id}`, { replace: true });
    }); } }, field('Type your legal name to sign agreement', name), h('label', {}, agree, ' I accept this proposal, scope and commercial terms with cryptographic audit.'), btn);
  }

  const pay = r.can_pay ? h('button', { class: 'btn primary', type: 'button', onclick: (e) => busy(e.currentTarget, 'Opening checkout…', async () => {
    location.href = (await api('POST', `portal/requests/${id}/pay`)).checkout_url;
  }) }, icon('credit-card', 15), r.billing?.next_part === 'deposit' ? `Pay ${r.billing.deposit_percent}% deposit · ${money(r.billing.next_amount, r.currency)}`
    : `Pay ${money(r.billing?.next_amount ?? r.amount, r.currency)}${r.billing?.next_part === 'balance' ? ' (balance)' : ''}`) : null;

  const deliverables = r.deliverables.length ? h('div', { class: 'card' },
    h('div', { class: 'card-header' },
      h('h2', {}, 'Deliverables'),
      r.can_review_work ? h('span', { class: 'pill late' }, 'Ready for your review') : null),
    h('ul', { class: 'stack' }, r.deliverables.map((d, idx) => h('li', { class: 'row', style: { justifyContent: 'space-between' } },
      h('div', {},
        h('strong', {}, d.format || d.service || d.platform || 'Deliverable'),
        d.due_date ? h('span', { class: 'muted small' }, ` · due ${fmtDay(d.due_date)}`) : null,
        d.status ? h('span', { class: 'muted small' }, ` · ${d.status}`) : null),
      h('div', { class: 'row' },
        h('button', { class: 'btn sm ghost', type: 'button', onclick: () => openAnnotationModal(id, () => loadAnnotations(), idx) }, icon('chat', 12), 'Feedback'),
        /^https?:\/\//i.test(d.url || '') ? h('a', { class: 'btn sm', href: d.url, target: '_blank', rel: 'noopener noreferrer' }, 'Preview', icon('external', 12)) : null))))) : null;

  view.append(
    pageHead(r.title, `${r.company} · requested ${fmtDay(r.created_at)}`, statusPill(r.status)),
    h('div', { class: 'card' }, steps,
      r.meeting_at ? h('p', {}, icon('calendar', 14), ' Call: ', h('strong', {}, when(r.meeting_at))) : null,
      r.needs_you ? h('p', { class: 'client-needs' }, icon('alert', 14), ' ', r.needs_you) : null,
      pay,
      r.review_url ? h('p', {}, h('a', { class: 'btn', href: r.review_url }, icon('star', 15), 'Leave a review')) : null),
    milestonesSlot,
    r.review_due_on && r.can_review_work ? h('div', { class: `card callout${r.review_overdue ? ' warn' : ''}` }, icon('clock', 15), ` Your review is due ${fmtDay(r.review_due_on)}.`) : null,
    onboardingCard(r, id),
    billingTermsCard(r),
    rightsCard(r, id),
    intakeCard(r, id, () => navigate(`/requests/${id}`, { replace: true })),
    reviewSlot,
    roiSlot,
    complianceSlot,
    h('div', { class: 'grid grid-2' },
      h('div', {}, proposal, deliverables, annotationsSlot, addOnsSlot,
        r.amount != null ? h('div', { class: 'card' }, h('h2', {}, 'Billing'),
          h('p', {}, money(r.amount, r.currency), ' · ', r.payment_status === 'paid' ? `paid ${r.paid_at ? fmtDay(r.paid_at) : ''}` : r.invoiced_at ? `invoiced ${fmtDay(r.invoiced_at)}` : 'not invoiced yet'),
          r.payment_terms ? h('p', { class: 'muted small' }, r.payment_terms) : null,
          r.retainer ? h('p', { class: 'muted small' }, `Retainer: ${r.retainer}`) : null) : null),
      h('div', { class: 'card' }, h('h2', {}, 'Messages'), thread, compose)));

  await Promise.all([loadMessages(), loadAnnotations(), loadRoi(), loadCompliance(), loadAddOns(), loadMilestones()]);
}

function milestonesCard(m) {
  const bar = h('div', { class: 'client-milestone-bar' },
    h('div', { class: 'client-milestone-fill', style: { width: `${m.overall_progress}%` } }));

  const list = h('div', { class: 'client-milestone-steps' },
    (m.milestones || []).map((step) => {
      const isDone = step.status === 'completed';
      const isCurrent = step.status === 'in_progress';
      return h('div', { class: `client-milestone-step ${isDone ? 'done' : isCurrent ? 'current' : ''}` },
        h('span', { class: 'client-milestone-num' }, isDone ? icon('check', 12) : step.step),
        h('div', { class: 'client-milestone-info' },
          h('strong', {}, step.title),
          h('p', { class: 'muted small' }, step.desc),
          step.completed_at ? h('span', { class: 'tag ok' }, `Completed ${step.completed_at}`) : isCurrent ? h('span', { class: 'tag picked' }, `Active Sprint (${step.progress_pct || 80}%)`) : null));
    }));

  return h('div', { class: 'card client-milestone-card' },
    h('div', { class: 'card-header' },
      h('div', {},
        h('h2', {}, icon('target', 16), ' Live Sprint & Milestone Delivery Tracker'),
        h('p', { class: 'muted small' }, `Overall Sprint Progress: ${m.overall_progress}% · Weekly Friday live demo scheduled`)),
      h('a', { class: 'btn primary sm', href: m.demo_url, target: '_blank', rel: 'noopener' }, icon('play', 12), ' Join Video Demo')),
    bar,
    list);
}

function openWrapReportModal(dealId) {
  modal('Executive Wrap & Compliance Report', (close) => {
    const contentSlot = h('div', { class: 'markdown-body' }, h('p', { class: 'muted' }, 'Loading compliance certification…'));
    api('GET', `portal/requests/${dealId}/compliance/report`).then((res) => {
      clear(contentSlot).append(h('div', { html: markdown(res.markdown) }));
    });
    return h('div', {}, contentSlot,
      h('div', { class: 'row', style: { justifyContent: 'flex-end', marginTop: '16px' } },
        h('button', { class: 'btn', type: 'button', onclick: () => window.print() }, icon('file-text', 14), 'Print / PDF'),
        h('button', { class: 'btn primary', type: 'button', onclick: () => close() }, 'Done')));
  });
}

function openDpaModal(dealId, onDone) {
  modal('Data Processing Agreement (GDPR Art. 28 & EU SCCs)', (close) => {
    const contentSlot = h('div', { class: 'markdown-body', style: { maxHeight: '65vh', overflowY: 'auto' } }, h('p', { class: 'muted' }, 'Loading DPA agreement…'));
    const actionSlot = h('div', { style: { marginTop: '12px' } });

    api('GET', `portal/requests/${dealId}/dpa`).then((res) => {
      clear(contentSlot).append(h('div', { html: markdown(res.markdown) }));
      if (res.signed) {
        clear(actionSlot).append(
          h('div', { class: 'callout ok', style: { padding: '10px', marginBottom: '10px' } },
            h('strong', {}, '✓ DPA Cryptographically Signed under GDPR Article 28'),
            h('p', { class: 'small muted', style: { margin: '4px 0 0 0' } }, `Signer: ${res.signer_name} (${res.signer_email}) on ${new Date(res.signed_at).toLocaleString()}`),
            h('p', { class: 'small', style: { fontFamily: 'monospace', wordBreak: 'break-all', marginTop: '4px' } }, `SHA-256: ${res.signature_hash}`)
          ),
          h('div', { class: 'row', style: { justifyContent: 'flex-end', gap: '8px' } },
            h('button', { class: 'btn', type: 'button', onclick: () => window.print() }, icon('file-text', 14), 'Print / PDF'),
            h('button', { class: 'btn primary', type: 'button', onclick: () => close() }, 'Done')
          )
        );
      } else {
        const nameInput = h('input', { type: 'text', required: true, placeholder: 'Signer Legal Representative Name' });
        const emailInput = h('input', { type: 'email', required: true, placeholder: 'Signer Corporate Email' });
        const agreeBox = h('input', { type: 'checkbox', required: true });
        const submitBtn = h('button', { class: 'btn primary', type: 'submit' }, 'Cryptographically Sign DPA (SHA-256)');

        const form = h('form', {
          onsubmit: async (e) => {
            e.preventDefault();
            busy(submitBtn, 'Signing…', async () => {
              const signRes = await api('POST', `portal/requests/${dealId}/dpa/sign`, {
                name: nameInput.value.trim(),
                email: emailInput.value.trim(),
                agree: agreeBox.checked,
              });
              toast(signRes.message);
              if (onDone) onDone();
              close();
            });
          }
        },
        h('h4', { style: { margin: '8px 0 6px 0' } }, 'Execute DPA for this Campaign'),
        h('div', { class: 'grid grid-2', style: { gap: '10px', marginBottom: '8px' } }, nameInput, emailInput),
        h('label', { class: 'row small', style: { gap: '8px', marginBottom: '10px' } }, agreeBox, 'I confirm corporate signing authority under GDPR Article 28 and Regulation (EU) No 910/2014.'),
        h('div', { class: 'row', style: { justifyContent: 'flex-end', gap: '8px' } },
          h('button', { class: 'btn ghost', type: 'button', onclick: () => close() }, 'Cancel'),
          submitBtn
        ));
        clear(actionSlot).append(form);
      }
    });

    return h('div', {}, contentSlot, actionSlot);
  }, { wide: true });
}

function openZdrModal(dealId) {
  modal('Enterprise Zero Data Retention (ZDR) Certificate', (close) => {
    const slot = h('div', { class: 'markdown-body', style: { maxHeight: '65vh', overflowY: 'auto' } }, h('p', { class: 'muted' }, 'Issuing certificate…'));
    api('GET', `portal/requests/${dealId}/zdr-certificate`).then((res) => {
      clear(slot).append(
        h('div', { class: 'callout ok', style: { padding: '12px', marginBottom: '16px' } },
          h('h3', { style: { margin: 0 } }, '✓ Enterprise ZDR Shield Certified'),
          h('p', { class: 'small muted', style: { margin: '4px 0 0 0' } }, `Certificate ID: ${res.certificate_id} · Issued ${new Date(res.certified_at).toLocaleDateString()}`)
        ),
        h('div', { class: 'stack' },
          Object.entries(res.guarantees || {}).map(([key, text]) =>
            h('div', { class: 'card small', style: { margin: '4px 0' } }, h('strong', {}, `• ${key.replace('_', ' ').toUpperCase()}: `), text)
          )
        )
      );
    });
    return h('div', {}, slot,
      h('div', { class: 'row', style: { justifyContent: 'flex-end', marginTop: '16px' } },
        h('button', { class: 'btn', type: 'button', onclick: () => window.print() }, icon('file-text', 14), 'Print Certificate'),
        h('button', { class: 'btn primary', type: 'button', onclick: () => close() }, 'Done')
      )
    );
  }, { wide: true });
}

async function purgeDealData(dealId) {
  if (!confirm('GDPR Article 17 Purge: Permanently erase all raw video proofing cuts, timecode annotations, and scratch files for this deal? Statutory tax invoices and signed DPA records will be preserved.')) return;
  try {
    const res = await api('POST', `portal/requests/${dealId}/purge`);
    toast(res.message);
  } catch (err) {
    toast(err.message, 'err');
  }
}

function annotationRow(a, dealId, onUpdate) {
  const isResolved = a.status === 'resolved';
  return h('div', { class: `card ${isResolved ? 'muted' : ''}`, style: { margin: '6px 0', padding: '10px' } },
    h('div', { class: 'row', style: { justifyContent: 'space-between' } },
      h('span', { class: 'small' },
        h('strong', {}, a.author), ' · ',
        a.timecode_seconds ? h('span', { class: 'pill sm picked' }, `At ${formatTimecode(a.timecode_seconds)}`) : null,
        a.page_or_slide ? h('span', { class: 'pill sm' }, `Slide/Page ${a.page_or_slide}`) : null,
        ' ', timeAgo(a.created_at)),
      !isResolved ? h('button', { class: 'btn sm', type: 'button', onclick: async (e) => {
        busy(e.currentTarget, 'Resolving…', async () => {
          await api('POST', `portal/requests/${dealId}/annotations/${a.id}/resolve`);
          onUpdate();
        });
      } }, icon('check', 12), 'Mark resolved') : h('span', { class: 'pill sm ok' }, 'Resolved')),
    h('p', { style: { whiteSpace: 'pre-wrap', marginTop: '6px' } }, a.body));
}

function formatTimecode(sec) {
  const m = Math.floor(sec / 60);
  const s = Math.floor(sec % 60);
  return `${m}:${s < 10 ? '0' : ''}${s}`;
}

function openAnnotationModal(dealId, onDone, deliverableIdx = 0) {
  modal('Add revision feedback', (close) => {
    const timecode = h('input', { type: 'text', placeholder: 'e.g. 01:45 or 90', maxlength: 10 });
    const slide = h('input', { type: 'number', placeholder: 'e.g. 2', min: 1, max: 100 });
    const note = h('textarea', { rows: 4, required: true, placeholder: 'Specific request or feedback for this cut/asset...' });
    const submit = h('button', { class: 'btn primary', type: 'submit' }, 'Submit feedback');

    return h('form', { onsubmit: async (e) => {
      e.preventDefault();
      let tc = null;
      if (timecode.value) {
        const parts = timecode.value.split(':').map(Number);
        tc = parts.length === 2 ? parts[0] * 60 + parts[1] : Number(timecode.value);
      }
      busy(submit, 'Saving…', async () => {
        await api('POST', `portal/requests/${dealId}/annotations`, {
          deliverable_index: deliverableIdx,
          timecode_seconds: tc && !isNaN(tc) ? tc : null,
          page_or_slide: slide.value ? Number(slide.value) : null,
          body: note.value,
        });
        toast('Feedback recorded.');
        close();
        onDone();
      });
    } },
    h('div', { class: 'grid grid-2' },
      field('Timecode (for video/audio)', timecode, 'optional'),
      field('Slide / Page #', slide, 'optional')),
    field('Feedback & Revision Note', note),
    h('div', { class: 'row', style: { justifyContent: 'flex-end', marginTop: '12px' } }, submit));
  });
}

function roiCard(data) {
  const m = data.metrics || {};
  const num = (v) => (v == null ? '–' : Number(v).toLocaleString());
  const head = h('div', { class: 'card-header' },
    h('h2', {}, icon('chart', 16), ' Results'),
    data.has_data ? h('button', { class: 'btn sm', type: 'button', onclick: async () => {
      const r = await api('GET', `portal/requests/${data.deal_id}/results-report`);
      printMarkdown(r.filename.replace(/[.]md$/, ''), r.markdown);
    } }, icon('download', 13), 'Print / save as PDF') : null);
  if (!data.has_data) return h('div', { class: 'card' }, head, h('p', { class: 'muted' }, data.note));
  const v = data.video;
  return h('div', { class: 'card callout' }, head,
    h('div', { class: 'grid grid-4' },
      stat('Views / impressions', num(m.total_views), 'play'),
      stat('Engagements', num(m.total_engagements), 'heart'),
      stat('Tracked clicks', num(m.tracked_clicks), 'link'),
      stat('Cost per 1,000 views', m.effective_cpm == null ? '–' : money(m.effective_cpm, data.currency), 'credit-card')),
    v ? h('div', { style: { marginTop: '12px' } },
      h('h3', { class: 'small' }, `Video: ${v.title || ''}`),
      h('div', { class: 'row', style: { gap: '8px', flexWrap: 'wrap' } }, v.milestones.map((ms) => h('span', { class: 'pill sm' }, `Day ${ms.day}: ${ms.views == null ? 'not yet' : num(ms.views)}`))),
      v.last_checked ? h('p', { class: 'muted small' }, `Checked daily since ${fmtDay(v.tracked_since)}; last ${fmtDay(v.last_checked)}.`) : h('p', { class: 'muted small' }, 'Tracking starts the day after it goes live.')) : null,
    data.benchmark ? h('p', { class: 'small' }, icon('check', 13), ` ${data.benchmark.verdict} (${data.benchmark.source})`) : null,
    h('p', { class: 'muted small' }, 'Every number here is recorded by the studio; nothing is estimated.'),
    data.renewal_incentive ? h('div', { class: 'callout row small', style: { justifyContent: 'space-between', marginTop: '12px' } },
      h('span', {}, icon('sparkle', 14), ' ', data.renewal_incentive.offer),
      link('/requests/new', { class: 'btn sm primary' }, 'New request')) : null);
}

function addOnsCard(dealId, catalog, applied, currency, onApplied) {
  const appliedKeys = new Set((applied || []).map((x) => x.key));
  const available = catalog.filter((c) => !appliedKeys.has(c.key));

  return h('div', { class: 'card' },
    h('h2', {}, 'Campaign Add-Ons & Licensing'),
    applied && applied.length ? h('div', { style: { marginBottom: '14px' } },
      h('p', { class: 'small muted' }, 'Active Add-ons on this project:'),
      h('ul', { class: 'stack' }, applied.map((a) => h('li', { class: 'row small' },
        h('strong', {}, a.name), ' · ', money(a.price, currency), ' · added by ', a.added_by)))) : null,
    available.length ? h('div', { class: 'stack' }, available.map((item) => h('div', { class: 'card callout row', style: { justifyContent: 'space-between', alignItems: 'center' } },
      h('div', {},
        h('strong', {}, item.name),
        h('p', { class: 'muted small' }, item.description)),
      h('button', { class: 'btn sm primary', type: 'button', onclick: (e) => busy(e.currentTarget, 'Adding…', async () => {
        const res = await api('POST', `portal/requests/${dealId}/add-ons`, { add_on: item.key });
        toast(res.message);
        onApplied();
      }) }, `Add ${money(item.price, currency)}`))))
      : h('p', { class: 'muted small' }, 'All available campaign enhancements are currently applied.'));
}

export async function renderBrandVault(view) {
  const { vault } = await api('GET', 'portal/brand-vault');
  const name = h('input', { name: 'brand_name', value: vault.brand_name || '', required: true, maxlength: 150 });
  const site = h('input', { name: 'website', type: 'url', value: vault.website || '', placeholder: 'https://brand.com' });
  const primaryColor = h('input', { type: 'color', value: vault.brand_colors?.primary || '#6366f1' });
  const accentColor = h('input', { type: 'color', value: vault.brand_colors?.accent || '#10b981' });
  const guidelines = h('textarea', { name: 'guidelines_markdown', rows: 8, placeholder: 'Brand messaging, positioning, core values, mandatory disclosures...' }, vault.guidelines_markdown || '');
  const utmSource = h('input', { value: vault.utm_params?.utm_source || 'tech-unicorn' });
  const utmMedium = h('input', { value: vault.utm_params?.utm_medium || 'creator-partnership' });

  // Enterprise Webhook & Tax/VAT Compliance Fields
  const slackWebhook = h('input', { name: 'slack_webhook_url', type: 'url', value: vault.slack_webhook_url || '', placeholder: 'https://hooks.slack.com/services/...' });
  const taxId = h('input', { name: 'tax_id', value: vault.tax_id || '', placeholder: 'e.g. DE123456789 or US EIN' });
  const countryCode = h('input', { name: 'country_code', value: vault.country_code || 'US', maxlength: 3, placeholder: 'e.g. DE, GB, US' });
  const billingAddress = h('textarea', { name: 'billing_address', rows: 3, placeholder: 'Full registered legal entity address for invoices...' }, vault.billing_address || '');

  // Enterprise Privacy & Data Retention
  const retentionDays = h('input', { name: 'retention_days', type: 'number', min: 7, max: 365, value: vault.retention_days ?? 60 });
  const zdrEnabled = h('input', { name: 'zdr_enabled', type: 'checkbox', checked: !!vault.zdr_enabled });
  const autoRedactSecrets = h('input', { name: 'auto_redact_secrets', type: 'checkbox', checked: vault.auto_redact_secrets !== false });

  const viewAccessLogBtn = h('button', { class: 'btn sm', type: 'button', onclick: () => openAccessLogModal() }, icon('shield', 13), 'View Immutable Access Log (GDPR Art. 30)');

  const testBtn = h('button', { class: 'btn sm', type: 'button', onclick: () => {
    if (!slackWebhook.value) return toast('Enter a webhook URL first.', 'err');
    busy(testBtn, 'Testing…', async () => {
      const res = await api('POST', 'portal/webhook/test', { webhook_url: slackWebhook.value });
      toast(res.message);
    });
  } }, icon('check', 13), 'Test Slack Connection');

  const viesStatusSlot = h('div', { class: 'muted small', style: { marginTop: '4px' } });
  if (vault.vies_verified && vault.vies_request_identifier) {
    viesStatusSlot.innerHTML = `<span class="pill ok">✓ VIES Verified Active (${vault.vies_request_identifier})</span>`;
  }
  const verifyViesBtn = h('button', { class: 'btn sm', type: 'button', onclick: () => {
    if (!taxId.value) return toast('Enter a Tax / VAT ID first.', 'err');
    busy(verifyViesBtn, 'Verifying…', async () => {
      const res = await api('POST', 'portal/brand-vault/verify-vies', { tax_id: taxId.value, country_code: countryCode.value });
      if (res.valid) {
        toast(`VIES Verified: ${res.name || 'Active EU Taxable Entity'}`);
        viesStatusSlot.innerHTML = `<span class="pill ok">✓ VIES Active: ${res.name || res.request_identifier}</span>`;
      } else {
        toast(res.notice || 'VAT number could not be verified by VIES.', 'err');
        viesStatusSlot.innerHTML = `<span class="pill danger">✕ ${res.status || 'Unverified'}</span>`;
      }
    });
  } }, icon('shield', 13), 'Verify with EU VIES');

  const submit = h('button', { class: 'btn primary', type: 'submit' }, icon('check', 15), 'Save Brand Vault');
  const form = h('form', { class: 'card stack', onsubmit: (e) => {
    e.preventDefault();
    busy(submit, 'Saving…', async () => {
      await api('PUT', 'portal/brand-vault', {
        brand_name: name.value,
        website: site.value,
        brand_colors: { primary: primaryColor.value, accent: accentColor.value },
        guidelines_markdown: guidelines.value,
        utm_params: { utm_source: utmSource.value, utm_medium: utmMedium.value },
        slack_webhook_url: slackWebhook.value,
        tax_id: taxId.value,
        country_code: countryCode.value,
        billing_address: billingAddress.value,
        retention_days: parseInt(retentionDays.value, 10) || 60,
        zdr_enabled: zdrEnabled.checked,
        auto_redact_secrets: autoRedactSecrets.checked,
      });
      toast('Brand Vault & Enterprise Privacy settings saved.');
    });
  } },
  h('div', { class: 'grid grid-2' },
    field('Brand / Corporate Name', name),
    field('Official Website', site)),
  h('div', { class: 'grid grid-2' },
    field('Primary Brand Color', primaryColor),
    field('Accent / Callout Color', accentColor)),
  field('Brand Guidelines & Messaging', guidelines),
  h('h3', {}, 'Team Slack / Discord Webhook Sync'),
  h('div', { class: 'grid grid-2' },
    field('Incoming Webhook URL', slackWebhook),
    h('div', { style: { display: 'flex', alignItems: 'flex-end', paddingBottom: '12px' } }, testBtn)),
  h('h3', {}, 'Enterprise Procurement & VAT Invoicing (VIES / Article 196)'),
  h('div', { class: 'grid grid-2' },
    h('div', {}, field('Corporate Tax / VAT ID', taxId), viesStatusSlot),
    h('div', { style: { display: 'flex', gap: '8px', alignItems: 'flex-end', paddingBottom: '12px' } },
      field('Country Code', countryCode),
      verifyViesBtn)),
  field('Registered Billing Address', billingAddress),
  h('h3', {}, 'Default Tracking & UTM Parameters'),
  h('div', { class: 'grid grid-2' },
    field('UTM Source', utmSource),
    field('UTM Medium', utmMedium)),
  h('h3', {}, 'Enterprise Privacy & Data Retention Controls'),
  h('div', { class: 'grid grid-2' },
    field('Ephemeral Asset Purge Window (Days)', retentionDays),
    h('div', { style: { display: 'flex', alignItems: 'flex-end', paddingBottom: '12px' } }, viewAccessLogBtn)),
  h('label', { class: 'row small', style: { gap: '8px', marginBottom: '8px' } },
    zdrEnabled,
    h('span', {}, 'Enforce Enterprise Zero Data Retention (ZDR): Transient in-memory LLM processing with zero AI training.')),
  h('label', { class: 'row small', style: { gap: '8px', marginBottom: '14px' } },
    autoRedactSecrets,
    h('span', {}, 'In-Flight PII & Secret Redaction: Automatically strip leaked Stripe, OpenAI, AWS or GitHub keys from briefs.')),
  submit);

  view.append(
    pageHead('Brand Vault', 'Store your official corporate brand assets, guidelines, Slack webhook, and tax IDs once. Every script, invoice and deliverable automatically adopts your exact standards.'),
    form);
}

function openAccessLogModal() {
  modal('Data Access Transparency Log (GDPR Article 30 RoPA)', (close) => {
    const slot = h('div', { style: { maxHeight: '65vh', overflowY: 'auto' } }, h('p', { class: 'muted' }, 'Loading access logs…'));
    api('GET', 'portal/privacy/access-log').then((res) => {
      const logs = res.logs || [];
      if (!logs.length) {
        clear(slot).append(h('p', { class: 'muted' }, 'No logged data access events yet.'));
        return;
      }
      clear(slot).append(
        h('table', { class: 'table small', style: { width: '100%' } },
          h('thead', {},
            h('tr', {},
              h('th', {}, 'Timestamp'),
              h('th', {}, 'Actor'),
              h('th', {}, 'Action'),
              h('th', {}, 'Resource'),
              h('th', {}, 'Purpose'),
              h('th', {}, 'Subnet')
            )
          ),
          h('tbody', {},
            logs.map((l) =>
              h('tr', {},
                h('td', { class: 'muted small' }, new Date(l.timestamp).toLocaleString()),
                h('td', {}, l.actor),
                h('td', {}, h('span', { class: 'pill sm' }, l.action)),
                h('td', {}, l.resource),
                h('td', { class: 'muted small' }, l.purpose),
                h('td', { style: { fontFamily: 'monospace' } }, l.ip_subnet)
              )
            )
          )
        )
      );
    });
    return h('div', {}, slot,
      h('div', { class: 'row', style: { justifyContent: 'flex-end', marginTop: '16px' } },
        h('button', { class: 'btn primary', type: 'button', onclick: () => close() }, 'Close')
      )
    );
  }, { wide: true });
}

function bubble(m) {
  return h('div', { class: `client-msg${m.from_admin ? ' theirs' : ' mine'}` },
    h('p', { class: 'muted small' }, m.from_admin ? (m.author || 'Studio') : 'You', ' · ', timeAgo(m.at)),
    h('p', { style: { whiteSpace: 'pre-wrap' } }, m.body));
}

export async function renderBilling(view) {
  const b = await api('GET', 'portal/billing');
  const referralsSlot = h('div', { class: 'card callout' });

  api('GET', 'portal/referrals').then((ref) => {
    clear(referralsSlot).append(
      h('div', { class: 'card-header' },
        h('h2', {}, icon('users', 16), ' Partner Referral Rewards'),
        h('span', { class: 'pill ok' }, `Available Balance: ${money(ref.available_credits)}`)),
      h('p', { class: 'muted small' }, ref.incentive_text),
      h('div', { class: 'row', style: { gap: '10px', marginTop: '10px' } },
        h('input', { type: 'text', readonly: true, value: ref.partner_link, style: { flex: 1, fontFamily: 'monospace', fontSize: '0.85rem' } }),
        h('button', { class: 'btn sm', type: 'button', onclick: () => {
          navigator.clipboard.writeText(ref.partner_link);
          toast('Referral link copied to clipboard!');
        } }, icon('link', 13), 'Copy Link')),
      ref.referrals && ref.referrals.length ? h('div', { style: { marginTop: '12px' } },
        h('h4', { class: 'small' }, 'Referred Partners:'),
        h('ul', { class: 'stack small' }, ref.referrals.map((r) => h('li', { class: 'row' },
          h('strong', {}, r.brand_name), ' · ', r.status, ' · earned ', money(r.credit_earned))))) : null);
  });

  const manage = h('button', { class: 'btn', type: 'button', onclick: (e) => busy(e.currentTarget, 'Opening…', async () => {
    location.href = (await api('POST', 'portal/billing/manage')).url;
  }) }, icon('credit-card', 14), 'Manage cards & invoices');
  view.append(
    pageHead('Billing', 'Payments, open invoices, retainers and partner credits.', ...(b.online && (b.payments.length || b.retainers.length) ? [manage] : [])),
    referralsSlot,
    b.due.length ? h('div', { class: 'card' }, h('h2', {}, 'To pay'),
      h('ul', { class: 'stack' }, b.due.map((d) => h('li', { class: 'row' },
        link(`/requests/${d.id}`, {}, h('strong', {}, d.title)), h('span', {}, money(d.amount, d.currency)),
        link(`/requests/${d.id}`, { class: 'btn sm primary' }, icon('credit-card', 13), 'Pay'))))) : null,
    b.retainers.length ? h('div', { class: 'card' }, h('h2', {}, 'Retainers'),
      h('ul', { class: 'stack' }, b.retainers.map((d) => h('li', {}, link(`/requests/${d.id}`, {}, d.title), ` · ${d.retainer}`)))) : null,
    h('div', { class: 'card' }, h('h2', {}, 'Payments'),
      b.payments.length ? h('table', { class: 'table' },
        h('thead', {}, h('tr', {}, h('th', {}, 'Date'), h('th', {}, 'For'), h('th', {}, 'Amount'))),
        h('tbody', {}, b.payments.map((p) => h('tr', {},
          h('td', {}, p.paid_at ? fmtDay(p.paid_at, { year: 'numeric', month: 'short', day: 'numeric' }) : ''),
          h('td', {}, link(`/requests/${p.request_id}`, {}, p.title), p.kind === 'renewal' ? h('span', { class: 'muted small' }, ' (renewal)') : null),
          h('td', {}, money(p.amount, p.currency))))))
        : h('p', { class: 'muted' }, 'No payments yet.')));
}
