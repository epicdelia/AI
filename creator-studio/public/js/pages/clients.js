// Her Clients page (Role ability `clients.manage`): client accounts, the deals linked to each,
// switching an account off, confirming an email by hand, and the message thread per deal.
import { h, api, busy, toast, pageHead, emptyState, clear, link, navigate, confirmDialog, timeAgo } from '../core.js';
import { icon } from '../icons.js';

const money = (n, c = 'USD') => new Intl.NumberFormat(undefined, { style: 'currency', currency: c || 'USD', maximumFractionDigits: 0 }).format(n || 0);

export async function renderClients(view) {
  const { clients, unread_total: unread } = await api('GET', 'clients');
  view.append(
    pageHead('Clients', 'People who signed up to follow their requests. Invite a contact from any deal (Brand deals → a deal → Invite to dashboard).'),
    unread ? h('div', { class: 'card callout' }, icon('chat', 15), ` ${unread} unread message${unread === 1 ? '' : 's'} from clients.`) : null,
    clients.length ? h('div', { class: 'card' }, h('table', { class: 'table' },
      h('thead', {}, h('tr', {}, h('th', {}, 'Client'), h('th', {}, 'Requests'), h('th', {}, 'Messages'), h('th', {}, 'Due'), h('th', {}, 'Last sign-in'), h('th', {}, 'Status'))),
      h('tbody', {}, clients.map((c) => h('tr', { class: 'clickable', onclick: () => navigate(`/clients/${c.id}`) },
        h('td', {}, link(`/clients/${c.id}`, {}, h('strong', {}, c.name)), h('div', { class: 'muted small' }, c.email, c.company ? ` · ${c.company}` : '')),
        h('td', {}, String(c.requests)),
        h('td', {}, c.unread ? h('span', { class: 'pill late' }, `${c.unread} new`) : '—'),
        h('td', {}, c.due ? money(c.due) : '—'),
        h('td', { class: 'muted small' }, timeAgo(c.last_login_at)),
        h('td', {}, statusPills(c)))))))
      : emptyState(icon('users', 28), 'No client accounts yet', 'Clients sign up from the sign-in page (“Create a client account”), or from your invite.'));
}

function statusPills(c) {
  return [
    c.disabled ? h('span', { class: 'pill' }, 'Off') : null,
    c.verified ? null : h('span', { class: 'pill late' }, 'Email not confirmed'),
    !c.disabled && c.verified ? h('span', { class: 'pill ok' }, 'Active') : null,
    c.two_factor ? h('span', { class: 'pill picked', title: 'Signs in with two-factor' }, '2FA') : null,
  ];
}

export async function renderClient(view, id) {
  let d = await api('GET', `clients/${id}`);
  const threadSlot = h('div');
  const head = h('div');
  const dealsCard = h('div');

  function drawHead() {
    const c = d.client;
    clear(head).append(pageHead(c.name, `${c.email}${c.company ? ` · ${c.company}` : ''} · joined ${timeAgo(c.created_at)}`,
      ...statusPills(c),
      c.verified ? null : h('button', { class: 'btn', onclick: (e) => busy(e.currentTarget, 'Sending…', async () => toast((await api('POST', `clients/${id}/verification`)).message)) }, icon('mail', 15), 'Resend confirmation'),
      c.verified ? null : h('button', { class: 'btn', onclick: async (e) => {
        if (!(await confirmDialog(`Confirm ${c.email} by hand? Only do this if you know this email is really theirs: their requests from that email become visible to them.`, { ok: 'Confirm email', danger: false }))) return;
        busy(e.currentTarget, 'Saving…', async () => { await api('PATCH', `clients/${id}`, { verified: true }); d = await api('GET', `clients/${id}`); drawHead(); drawDeals(); });
      } }, icon('check', 15), 'Confirm email'),
      h('button', { class: `btn${c.disabled ? '' : ' danger'}`, onclick: async (e) => {
        if (!c.disabled && !(await confirmDialog(`Switch off ${c.name}’s access? They’re signed out everywhere.`, { ok: 'Switch off' }))) return;
        busy(e.currentTarget, 'Saving…', async () => { d.client = (await api('PATCH', `clients/${id}`, { disabled: !c.disabled })).client; drawHead(); });
      } }, c.disabled ? 'Switch access on' : 'Switch access off')));
  }

  function drawDeals() {
    clear(dealsCard).append(h('div', { class: 'card' },
      h('h2', {}, 'Requests and deals'),
      d.deals.length ? h('ul', { class: 'stack' }, d.deals.map((deal) => h('li', { class: 'row' },
        h('button', { class: 'btn ghost', type: 'button', onclick: () => openThread(deal) }, h('strong', {}, deal.brand_name), deal.unread ? h('span', { class: 'pill late' }, `${deal.unread} new`) : null),
        h('span', { class: 'muted small' }, `${deal.stage} · they see “${deal.client_status}” · ${money(deal.rate, deal.currency)}`),
        h('button', { class: 'btn ghost sm', type: 'button', title: 'Unlink from this client', onclick: async () => {
          if (!(await confirmDialog(`Unlink “${deal.brand_name}” from ${d.client.name}? They won’t see it any more.`, { ok: 'Unlink' }))) return;
          await api('DELETE', `clients/${id}/deals/${deal.id}`);
          d = await api('GET', `clients/${id}`);
          drawDeals();
        } }, icon('x', 13))))) : h('p', { class: 'muted' }, 'No deals linked yet.'),
      d.suggested.length ? [h('h3', {}, 'From the same email, not linked'),
        h('ul', { class: 'stack' }, d.suggested.map((s) => h('li', { class: 'row' }, h('span', {}, s.brand_name, h('span', { class: 'muted small' }, ` · ${s.stage}`)),
          h('button', { class: 'btn sm', type: 'button', onclick: (e) => busy(e.currentTarget, 'Linking…', async () => {
            await api('POST', `clients/${id}/deals`, { deal_id: s.id });
            d = await api('GET', `clients/${id}`);
            drawDeals();
          }) }, icon('link', 13), 'Link'))))] : null));
  }

  async function openThread(deal) {
    const { messages } = await api('GET', `deals/${deal.id}/messages`);
    deal.unread = 0;
    drawDeals();
    const list = h('div', { class: 'client-thread' }, messages.length ? messages.map((m) => h('div', { class: `client-msg${m.from_admin ? ' mine' : ' theirs'}` },
      h('p', { class: 'muted small' }, m.from_admin ? 'You' : (m.author || d.client.name), ' · ', timeAgo(m.at)),
      h('p', { style: { whiteSpace: 'pre-wrap' } }, m.body))) : h('p', { class: 'muted' }, 'No messages yet.'));
    const body = h('textarea', { rows: 3, maxlength: 5000, required: true, placeholder: `Write to ${d.client.name}… (they get an email too)`, 'aria-label': 'Message' });
    const send = h('button', { class: 'btn primary', type: 'submit' }, icon('arrow', 15), 'Send');
    clear(threadSlot).append(h('div', { class: 'card' },
      h('h2', {}, `Messages · ${deal.brand_name}`), list,
      h('form', { class: 'stack', onsubmit: (e) => { e.preventDefault(); busy(send, 'Sending…', async () => {
        await api('POST', `deals/${deal.id}/messages`, { body: body.value });
        openThread(deal);
      }); } }, body, send)));
    list.scrollTop = list.scrollHeight;
  }

  drawHead();
  drawDeals();
  view.append(head, h('div', { class: 'grid grid-2' }, dealsCard, threadSlot));
  const firstUnread = d.deals.find((x) => x.unread) || d.deals[0];
  if (firstUnread) openThread(firstUnread);
  else threadSlot.append(h('div', { class: 'card' }, h('p', { class: 'muted' }, 'Link a deal to message this client about it.')));
}
