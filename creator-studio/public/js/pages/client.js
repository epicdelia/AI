// A client's dashboard (Role abilities `portal.requests` / `portal.billing`): their own requests,
// proposals, deliverables, proofing annotations, campaign ROI, add-ons, payments and messages.
// Everything comes from /api/portal, which strictly isolates tenant data (ClientDeal).
import { h, api, busy, toast, pageHead, emptyState, field, formData, clear, link, navigate, markdown, fmtDay, timeAgo, modal } from '../core.js';
import { icon } from '../icons.js';

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
  view.append(
    pageHead('Your projects', `Everything you’re working on with ${first}.`,
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
        link('/requests/new', { class: 'btn primary' }, icon('plus', 15), 'New request')));
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
  const { services } = await api('GET', 'portal/requests');
  const submit = h('button', { class: 'btn primary', type: 'submit' }, icon('check', 15), 'Send request');
  const form = h('form', { class: 'card', onsubmit: (e) => { e.preventDefault(); busy(submit, 'Sending…', async () => {
    const r = await api('POST', 'portal/requests', formData(form));
    toast(r.message);
    navigate(`/requests/${r.request.id}`);
  }); } },
  field('What do you need?', h('select', { name: 'service', required: true }, services.map((s) => h('option', { value: s.key }, s.label)))),
  field('Tell us about it', h('textarea', { name: 'details', rows: 6, required: true, minlength: 10, maxlength: 4000, placeholder: 'Goals, audience, links, anything that helps.' })),
  h('div', { class: 'grid grid-2' },
    field('Budget', h('input', { name: 'budget', maxlength: 100, placeholder: 'e.g. $5–10k' }), 'optional'),
    field('Deadline', h('input', { name: 'deadline', maxlength: 100, placeholder: 'e.g. end of March' }), 'optional')),
  submit);
  view.append(pageHead('New request', 'Send it and you’ll get an answer (and later a proposal) right here.'), form);
}

export async function renderRequest(view, id) {
  let { request: r } = await api('GET', `portal/requests/${id}`);
  const thread = h('div', { class: 'client-thread', 'aria-live': 'polite' });
  const annotationsSlot = h('div', { class: 'card' });
  const roiSlot = h('div');
  const addOnsSlot = h('div');

  async function loadMessages() {
    const { messages } = await api('GET', `portal/requests/${id}/messages`);
    clear(thread).append(messages.length ? messages.map(bubble) : h('p', { class: 'muted' }, 'No messages yet. Ask anything about this request.'));
    thread.scrollTop = thread.scrollHeight;
  }

  async function loadAnnotations() {
    const { annotations } = await api('GET', `portal/requests/${id}/annotations`);
    clear(annotationsSlot).append(
      h('div', { class: 'card-header' },
        h('h2', {}, 'Revisions & Proofing'),
        h('button', { class: 'btn sm', type: 'button', onclick: () => openAnnotationModal(id, () => loadAnnotations()) }, icon('plus', 13), 'Add revision note')),
      annotations.length ? h('div', { class: 'stack' }, annotations.map((a) => annotationRow(a, id, () => loadAnnotations())))
        : h('p', { class: 'muted small' }, 'No revision notes yet. Click “Add revision note” to pin timecode feedback on video cuts or copy.'));
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

  const proposal = r.proposal ? h('div', { class: 'card' },
    h('div', { class: 'card-header' }, h('h2', {}, 'Proposal'), r.amount != null ? h('strong', {}, money(r.amount, r.currency)) : null),
    h('div', { class: 'markdown-body', html: markdown(r.proposal.markdown || '') }),
    r.accepted_at ? h('p', { class: 'muted small' }, icon('check', 14), ` Accepted by ${r.accepted_by} on ${fmtDay(r.accepted_at)}.`) : null,
    eSignBadge,
    r.can_accept ? acceptForm() : null) : null;

  function acceptForm() {
    const name = h('input', { name: 'name', required: true, minlength: 2, maxlength: 120, autocomplete: 'name', placeholder: 'Your full legal name' });
    const agree = h('input', { type: 'checkbox', name: 'agree', required: true });
    const btn = h('button', { class: 'btn primary', type: 'submit' }, icon('check', 15), 'Sign and accept proposal');
    return h('form', { class: 'stack', onsubmit: (e) => { e.preventDefault(); busy(btn, 'Signing & verifying…', async () => {
      toast((await api('POST', `portal/requests/${id}/accept`, { name: name.value, agree: agree.checked })).message);
      navigate(`/requests/${id}`, { replace: true });
    }); } }, field('Type your legal name to sign agreement', name), h('label', {}, agree, ' I accept this proposal, scope and commercial terms with cryptographic audit.'), btn);
  }

  const pay = r.can_pay ? h('button', { class: 'btn primary', type: 'button', onclick: (e) => busy(e.currentTarget, 'Opening checkout…', async () => {
    location.href = (await api('POST', `portal/requests/${id}/pay`)).checkout_url;
  }) }, icon('credit-card', 15), `Pay ${money(r.amount, r.currency)}`) : null;

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
    roiSlot,
    h('div', { class: 'grid grid-2' },
      h('div', {}, proposal, deliverables, annotationsSlot, addOnsSlot,
        r.amount != null ? h('div', { class: 'card' }, h('h2', {}, 'Billing'),
          h('p', {}, money(r.amount, r.currency), ' · ', r.payment_status === 'paid' ? `paid ${r.paid_at ? fmtDay(r.paid_at) : ''}` : r.invoiced_at ? `invoiced ${fmtDay(r.invoiced_at)}` : 'not invoiced yet'),
          r.payment_terms ? h('p', { class: 'muted small' }, r.payment_terms) : null,
          r.retainer ? h('p', { class: 'muted small' }, `Retainer: ${r.retainer}`) : null) : null),
      h('div', { class: 'card' }, h('h2', {}, 'Messages'), thread, compose)));

  await Promise.all([loadMessages(), loadAnnotations(), loadRoi(), loadAddOns()]);
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
  const b = data.benchmark || {};
  return h('div', { class: 'card callout' },
    h('div', { class: 'card-header' },
      h('h2', {}, icon('chart', 16), ' Verified Campaign Performance & ROI'),
      h('span', { class: 'pill ok' }, `${b.performance_multiplier}× Tech Benchmark`)),
    h('div', { class: 'grid grid-4' },
      stat('Total Reach / Views', Number(m.total_views || 0).toLocaleString(), 'play'),
      stat('Tracked Clicks', Number(m.tracked_clicks || 0).toLocaleString(), 'link'),
      stat('Click-Through Rate', `${m.click_through_rate}%`, 'trend'),
      stat('Effective CPM', money(m.effective_cpm, data.currency), 'credit-card')),
    h('p', { class: 'small muted', style: { marginTop: '10px' } }, icon('check', 13), ' ', b.verdict),
    data.renewal_incentive ? h('div', { class: 'callout row small', style: { justifyContent: 'space-between', marginTop: '12px' } },
      h('span', {}, icon('sparkle', 14), ' ', data.renewal_incentive.offer),
      link('/requests/new', { class: 'btn sm primary' }, 'Re-book Next Sprint')) : null);
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
      });
      toast('Brand Vault updated. Studio tools and scripts will reference these assets.');
    });
  } },
  h('div', { class: 'grid grid-2' },
    field('Brand / Corporate Name', name),
    field('Official Website', site)),
  h('div', { class: 'grid grid-2' },
    field('Primary Brand Color', primaryColor),
    field('Accent / Callout Color', accentColor)),
  field('Brand Guidelines & Messaging', guidelines),
  h('h3', {}, 'Default Tracking & UTM Parameters'),
  h('div', { class: 'grid grid-2' },
    field('UTM Source', utmSource),
    field('UTM Medium', utmMedium)),
  submit);

  view.append(
    pageHead('Brand Vault', 'Store your official corporate brand assets, guidelines and tracking parameters once. Every script and deliverable automatically adopts your exact standards.'),
    form);
}

function bubble(m) {
  return h('div', { class: `client-msg${m.from_admin ? ' theirs' : ' mine'}` },
    h('p', { class: 'muted small' }, m.from_admin ? (m.author || 'Studio') : 'You', ' · ', timeAgo(m.at)),
    h('p', { style: { whiteSpace: 'pre-wrap' } }, m.body));
}

export async function renderBilling(view) {
  const b = await api('GET', 'portal/billing');
  view.append(
    pageHead('Billing', 'Payments, open invoices and retainers.'),
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
