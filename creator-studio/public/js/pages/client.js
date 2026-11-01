// A client's dashboard (Role abilities `portal.requests` / `portal.billing`): their own requests,
// proposals, deliverables, payments and messages with her. Everything comes from /api/portal,
// which only ever returns this client's own requests in a client-safe shape (ClientDeal).
import { h, api, busy, toast, pageHead, emptyState, field, formData, clear, link, navigate, markdown, fmtDay, timeAgo } from '../core.js';
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
      h('div', { class: 'row' }, r.unread ? h('span', { class: 'pill late' }, icon('chat', 12), ` ${r.unread} new`) : null, statusPill(r.status))),
    r.needs_you ? h('p', { class: 'client-needs' }, icon('alert', 14), ' ', r.needs_you) : null,
    r.meeting_at ? h('p', { class: 'muted small' }, icon('calendar', 13), ' Call: ', when(r.meeting_at)) : null,
    r.amount != null ? h('p', { class: 'muted small' }, money(r.amount, r.currency), r.payment_status === 'paid' ? ' · paid' : '') : null);
}

export async function renderClientHome(view) {
  const o = await api('GET', 'portal/overview');
  const first = (o.creator || '').split(' ')[0] || 'the studio';
  view.append(
    pageHead('Your projects', `Everything you’re working on with ${first}.`,
      link('/requests/new', { class: 'btn primary' }, icon('plus', 15), 'New request')),
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

  async function loadMessages() {
    const { messages } = await api('GET', `portal/requests/${id}/messages`);
    clear(thread).append(messages.length ? messages.map(bubble) : h('p', { class: 'muted' }, 'No messages yet. Ask anything about this request.'));
    thread.scrollTop = thread.scrollHeight;
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

  const proposal = r.proposal ? h('div', { class: 'card' },
    h('div', { class: 'card-header' }, h('h2', {}, 'Proposal'), r.amount != null ? h('strong', {}, money(r.amount, r.currency)) : null),
    h('div', { class: 'markdown-body', html: markdown(r.proposal.markdown || '') }),
    r.accepted_at ? h('p', { class: 'muted' }, icon('check', 14), ` Accepted by ${r.accepted_by} on ${fmtDay(r.accepted_at)}.`) : null,
    r.can_accept ? acceptForm() : null) : null;

  function acceptForm() {
    const name = h('input', { name: 'name', required: true, minlength: 2, maxlength: 120, autocomplete: 'name', placeholder: 'Your full name' });
    const agree = h('input', { type: 'checkbox', name: 'agree', required: true });
    const btn = h('button', { class: 'btn primary', type: 'submit' }, icon('check', 15), 'Accept proposal');
    return h('form', { class: 'stack', onsubmit: (e) => { e.preventDefault(); busy(btn, 'Accepting…', async () => {
      toast((await api('POST', `portal/requests/${id}/accept`, { name: name.value, agree: agree.checked })).message);
      navigate(`/requests/${id}`, { replace: true });
    }); } }, field('Type your name to accept', name), h('label', {}, agree, ' I accept this proposal and its terms.'), btn);
  }

  const pay = r.can_pay ? h('button', { class: 'btn primary', type: 'button', onclick: (e) => busy(e.currentTarget, 'Opening checkout…', async () => {
    location.href = (await api('POST', `portal/requests/${id}/pay`)).checkout_url;
  }) }, icon('credit-card', 15), `Pay ${money(r.amount, r.currency)}`) : null;

  const deliverables = r.deliverables.length ? h('div', { class: 'card' }, h('h2', {}, 'Deliverables'),
    h('ul', { class: 'stack' }, r.deliverables.map((d) => h('li', {},
      h('strong', {}, d.format || d.service || d.platform || 'Deliverable'),
      d.due_date ? h('span', { class: 'muted small' }, ` · due ${fmtDay(d.due_date)}`) : null,
      d.status ? h('span', { class: 'muted small' }, ` · ${d.status}`) : null,
      /^https?:\/\//i.test(d.url || '') ? [' · ', h('a', { href: d.url, target: '_blank', rel: 'noopener noreferrer' }, 'Open', icon('external', 12))] : null)))) : null;

  view.append(
    pageHead(r.title, `${r.company} · requested ${fmtDay(r.created_at)}`, statusPill(r.status)),
    h('div', { class: 'card' }, steps,
      r.meeting_at ? h('p', {}, icon('calendar', 14), ' Call: ', h('strong', {}, when(r.meeting_at))) : null,
      r.needs_you ? h('p', { class: 'client-needs' }, icon('alert', 14), ' ', r.needs_you) : null,
      pay,
      r.review_url ? h('p', {}, h('a', { class: 'btn', href: r.review_url }, icon('star', 15), 'Leave a review')) : null),
    h('div', { class: 'grid grid-2' },
      h('div', {}, proposal, deliverables,
        r.amount != null ? h('div', { class: 'card' }, h('h2', {}, 'Billing'),
          h('p', {}, money(r.amount, r.currency), ' · ', r.payment_status === 'paid' ? `paid ${r.paid_at ? fmtDay(r.paid_at) : ''}` : r.invoiced_at ? `invoiced ${fmtDay(r.invoiced_at)}` : 'not invoiced yet'),
          r.payment_terms ? h('p', { class: 'muted small' }, r.payment_terms) : null,
          r.retainer ? h('p', { class: 'muted small' }, `Retainer: ${r.retainer}`) : null) : null),
      h('div', { class: 'card' }, h('h2', {}, 'Messages'), thread, compose)));
  await loadMessages();
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
