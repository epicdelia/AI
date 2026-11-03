// Her cross-role pages: Needs you (everything waiting on her), the CRM, and the white-label
// portal branding her clients and community members see.
import { h, api, clear, toast, busy, modal, confirmDialog, pageHead, emptyState, loading, field, timeAgo, fmtDay, link, navigate, apiUpload } from '../core.js';
import { icon } from '../icons.js';

const money = (n) => `$${Math.round(Number(n) || 0).toLocaleString()}`;

// =============================================================================================
// Needs you
// =============================================================================================

/** The dashboard card (null when nothing is waiting). */
export function needsYouCard() {
  const card = h('div', { class: 'card needs-you-card', hidden: true });
  api('GET', 'needs-you').then((d) => {
    if (!d.total) return;
    card.hidden = false;
    card.append(
      h('div', { class: 'card-header' }, h('h2', {}, icon('alert', 16), ` Needs you (${d.total})`), link('/needs-you', { class: 'btn ghost sm' }, 'See all')),
      h('div', { class: 'row', style: { flexWrap: 'wrap', gap: '8px' } }, d.groups.map((g) => link('/needs-you', { class: 'pill' }, icon(g.icon, 12), ` ${g.title}: ${g.count}`))),
      h('ul', { class: 'stack', style: { marginTop: '10px' } }, d.groups.flatMap((g) => g.items.slice(0, 2)).slice(0, 5).map((i) => h('li', {}, link(i.url, {}, i.title), i.meta ? h('span', { class: 'muted small' }, ` · ${i.meta}`) : null))));
  }).catch(() => {});
  return card;
}

async function weeklyDialog() {
  const w = await api('GET', 'needs-you/weekly');
  const on = h('input', { type: 'checkbox', checked: w.on, onchange: async (e) => { await api('PUT', 'needs-you/weekly', { on: e.target.checked }); toast(e.target.checked ? 'Monday email on.' : 'Monday email off.'); } });
  await modal('Your Monday email', () => h('div', {},
    h('label', { class: 'row small', style: { gap: '6px' } }, on, 'Send me this every Monday at 07:30'),
    h('pre', { style: { whiteSpace: 'pre-wrap', marginTop: '12px' } }, w.text)), { wide: true });
}

export async function renderNeedsYou(view) {
  view.append(loading());
  const d = await api('GET', 'needs-you');
  clear(view).append(
    pageHead('Needs you', d.total ? `${d.total} thing${d.total === 1 ? '' : 's'} waiting across clients, team, community and contacts.` : 'Nothing is waiting on you.',
      h('button', { type: 'button', class: 'btn ghost', onclick: weeklyDialog }, icon('mail', 14), 'Monday email')),
    d.groups.length ? h('div', { class: 'grid grid-2' }, d.groups.map((g) => h('div', { class: 'card' },
      h('h2', {}, icon(g.icon, 15), ` ${g.title} (${g.count})`),
      h('ul', { class: 'stack' }, g.items.map((i) => h('li', { class: 'row-between' },
        h('span', { style: { minWidth: 0 } }, link(i.url, {}, i.title), i.meta ? h('div', { class: 'muted small' }, i.meta) : null),
        i.at ? h('span', { class: 'muted small', style: { flexShrink: 0 } }, timeAgo(i.at)) : null))))))
      : emptyState(icon('check', 28), 'All caught up', 'Client messages, revision notes, payouts, questions and follow-ups appear here.'));
}

// =============================================================================================
// CRM
// =============================================================================================

const STAGE_LABEL = { lead: 'Lead', contacted: 'Contacted', qualified: 'Qualified', customer: 'Customer', lost: 'Lost' };
const KIND_ICON = { note: 'pen', call: 'mic', email: 'mail', meeting: 'calendar', task: 'check' };

export async function renderCrm(view) {
  let filter = { q: '', stage: '', due: false };
  const list = h('div');
  const detail = h('div');
  const params = new URLSearchParams(location.search);

  async function load() {
    const qs = new URLSearchParams(Object.entries({ q: filter.q, stage: filter.stage, due: filter.due ? 1 : '' }).filter(([, v]) => v)).toString();
    const d = await api('GET', `crm${qs ? `?${qs}` : ''}`);
    clear(list).append(
      h('div', { class: 'row', style: { flexWrap: 'wrap', gap: '8px', marginBottom: '12px' } },
        h('button', { type: 'button', class: `pill${!filter.stage && !filter.due ? ' picked' : ''}`, onclick: () => { filter = { ...filter, stage: '', due: false }; load(); } }, 'All'),
        d.stages.map((s) => h('button', { type: 'button', class: `pill${filter.stage === s.stage ? ' picked' : ''}`, onclick: () => { filter = { ...filter, stage: s.stage, due: false }; load(); } },
          `${STAGE_LABEL[s.stage]} ${s.count}${s.value ? ` · ${money(s.value)}` : ''}`)),
        h('button', { type: 'button', class: `pill${filter.due ? ' late' : ''}`, onclick: () => { filter = { ...filter, due: !filter.due, stage: '' }; load(); } }, `Follow up today: ${d.due}`)),
      d.contacts.length ? h('div', { class: 'table-wrap' }, h('table', { class: 'table' },
        h('thead', {}, h('tr', {}, ['Name', 'Company', 'Stage', 'Value', 'Next follow-up', 'Last contact'].map((c) => h('th', {}, c)))),
        h('tbody', {}, d.contacts.map((c) => h('tr', { class: 'clickable', onclick: () => open(c.id) },
          h('td', {}, h('strong', {}, c.name), c.email ? h('div', { class: 'muted small' }, c.email) : null),
          h('td', {}, c.company || '—'),
          h('td', {}, h('span', { class: `pill sm ${c.stage === 'customer' ? 'ok' : c.stage === 'lost' ? '' : 'picked'}` }, STAGE_LABEL[c.stage])),
          h('td', { class: 'num' }, c.value ? money(c.value) : '—'),
          h('td', {}, c.next_follow_up_at ? h('span', { class: c.follow_up_due ? 'pill sm late' : '' }, fmtDay(c.next_follow_up_at.slice(0, 10))) : '—'),
          h('td', { class: 'muted small' }, c.last_contacted_at ? timeAgo(c.last_contacted_at) : 'never'))))))
        : emptyState(icon('users', 28), 'No contacts here', 'Add one, or Sync to pull in your clients and deal contacts.'));
  }

  async function open(id) {
    const d = await api('GET', `crm/${id}`);
    const c = d.contact;
    history.replaceState({}, '', `/crm?contact=${id}`);
    const kind = h('select', { 'aria-label': 'Type' }, Object.keys(KIND_ICON).map((k) => h('option', { value: k }, k)));
    const body = h('textarea', { rows: 2, maxlength: 5000, placeholder: 'What happened, or what to do', required: true, 'aria-label': 'Note' });
    const due = h('input', { type: 'date', 'aria-label': 'Due (for tasks)' });
    const next = h('input', { type: 'date', 'aria-label': 'Next follow-up', value: c.next_follow_up_at?.slice(0, 10) || '' });
    const add = h('button', { type: 'submit', class: 'btn primary sm' }, 'Add');
    clear(detail).append(h('div', { class: 'card' },
      h('div', { class: 'card-header' },
        h('div', {}, h('h2', {}, c.name), h('p', { class: 'muted small' }, [c.company, c.email, c.phone].filter(Boolean).join(' · '))),
        h('div', { class: 'row' },
          c.email ? h('a', { class: 'btn sm', href: `mailto:${c.email}` }, icon('mail', 13), 'Email') : null,
          h('button', { type: 'button', class: 'btn sm ghost', onclick: () => edit(c) }, 'Edit'))),
      h('div', { class: 'row', style: { flexWrap: 'wrap', gap: '6px' } },
        Object.keys(STAGE_LABEL).map((s) => h('button', {
          type: 'button', class: `pill sm${c.stage === s ? ' picked' : ''}`,
          onclick: async () => { await api('PATCH', `crm/${id}`, { stage: s }); open(id); load(); },
        }, STAGE_LABEL[s]))),
      c.tags.length ? h('p', {}, c.tags.map((t) => h('span', { class: 'tag' }, t))) : null,
      c.notes ? h('p', { style: { whiteSpace: 'pre-wrap' } }, c.notes) : null,
      d.deals.length ? h('div', {}, h('h3', { class: 'small' }, 'Deals'), h('ul', { class: 'stack small' }, d.deals.map((x) => h('li', {}, link('/deals', {}, x.brand_name), ` · ${x.stage} · ${money(x.rate)}`)))) : null,
      h('form', { class: 'stack', style: { marginTop: '12px' }, onsubmit: (e) => { e.preventDefault(); busy(add, '…', async () => {
        await api('POST', `crm/${id}/activities`, { kind: kind.value, body: body.value, due_at: due.value || null, next_follow_up_at: next.value || null });
        open(id);
        load();
      }); } }, h('div', { class: 'row' }, kind, h('label', { class: 'small muted' }, 'Due ', due), h('label', { class: 'small muted' }, 'Next follow-up ', next)), body, h('div', { class: 'row-end' }, add)),
      h('ul', { class: 'stack', style: { marginTop: '12px' } }, d.activities.map((a) => h('li', { class: 'row', style: { alignItems: 'flex-start' } },
        icon(KIND_ICON[a.kind] || 'pen', 14),
        h('div', { style: { flex: 1, minWidth: 0 } },
          h('p', { style: { whiteSpace: 'pre-wrap', margin: 0, textDecoration: a.done_at ? 'line-through' : '' } }, a.body),
          h('span', { class: 'muted small' }, `${a.kind} · ${timeAgo(a.at)}${a.due_at ? ` · due ${fmtDay(a.due_at.slice(0, 10))}` : ''}`)),
        a.kind === 'task' ? h('button', { type: 'button', class: 'btn ghost sm', onclick: async () => { await api('POST', `crm/${id}/activities/${a.id}/done`); open(id); } }, a.done_at ? 'Undo' : 'Done') : null)))));
  }

  async function edit(c = null) {
    const f = {
      name: h('input', { value: c?.name || '', required: true, maxlength: 150 }),
      email: h('input', { type: 'email', value: c?.email || '' }),
      company: h('input', { value: c?.company || '' }),
      phone: h('input', { value: c?.phone || '' }),
      value: h('input', { type: 'number', min: 0, value: c?.value || '' }),
      tags: h('input', { value: (c?.tags || []).join(', '), placeholder: 'sponsor, podcast…' }),
      next_follow_up_at: h('input', { type: 'date', value: c?.next_follow_up_at?.slice(0, 10) || '' }),
      notes: h('textarea', { rows: 3 }, c?.notes || ''),
    };
    await modal(c ? 'Edit contact' : 'New contact', (close) => h('form', {
      onsubmit: async (e) => {
        e.preventDefault();
        const body = Object.fromEntries(Object.entries(f).map(([k, el]) => [k, el.value || null]));
        try {
          const r = c ? await api('PATCH', `crm/${c.id}`, body) : await api('POST', 'crm', body);
          close();
          load();
          open(r.contact.id);
        } catch (err) { toast(err.message, 'err'); }
      },
    }, field('Name', f.name), h('div', { class: 'grid grid-2' }, field('Email', f.email), field('Company', f.company)),
    h('div', { class: 'grid grid-2' }, field('Phone', f.phone), field('Value', f.value)), field('Tags', f.tags), field('Next follow-up', f.next_follow_up_at), field('Notes', f.notes),
    h('div', { class: 'row', style: { justifyContent: 'space-between', marginTop: '12px' } },
      c ? h('button', { type: 'button', class: 'btn ghost', onclick: async () => { if (await confirmDialog(`Delete ${c.name}?`)) { await api('DELETE', `crm/${c.id}`); close(); clear(detail); load(); } } }, 'Delete') : h('span'),
      h('button', { type: 'submit', class: 'btn primary' }, 'Save'))));
  }

  const search = h('input', { type: 'search', placeholder: 'Search name, email or company', 'aria-label': 'Search contacts', oninput: (e) => { filter.q = e.target.value; clearTimeout(search.t); search.t = setTimeout(load, 250); } });
  view.append(
    pageHead('Contacts', 'Leads, clients, sponsors and engaged followers, with follow-ups.',
      search,
      h('button', { type: 'button', class: 'btn', onclick: (e) => busy(e.currentTarget, 'Syncing…', async () => { toast((await api('POST', 'crm/sync')).message); load(); }) }, icon('refresh', 14), 'Sync'),
      h('a', { class: 'btn ghost', href: '/api/crm/export', download: '' }, icon('download', 14), 'CSV'),
      h('button', { type: 'button', class: 'btn primary', onclick: () => edit() }, icon('plus', 14), 'Add')),
    h('div', { class: 'grid grid-2 crm-layout' }, list, detail));
  await load();
  if (params.get('contact')) open(Number(params.get('contact')));
}

// =============================================================================================
// White-label portal branding
// =============================================================================================

export async function renderPortalBrand(view) {
  let c = await api('GET', 'portal-brand');
  const f = {
    enabled: h('input', { type: 'checkbox', checked: c.enabled }),
    name: h('input', { value: c.name, maxlength: 60, placeholder: 'e.g. Tech Unicorn Studio' }),
    tagline: h('input', { value: c.tagline, maxlength: 120 }),
    accent: h('input', { type: 'color', value: c.accent || '#d8487e' }),
    support_email: h('input', { type: 'email', value: c.support_email }),
    welcome: h('textarea', { rows: 2, maxlength: 300 }, c.welcome),
    footer: h('input', { value: c.footer, maxlength: 300 }),
  };
  const logoSlot = h('div', { class: 'row' });
  const preview = h('div', { class: 'card portal-brand-preview' });
  function drawLogo() {
    clear(logoSlot).append(
      c.logo_url ? h('img', { src: `${c.logo_url}?t=${Date.now()}`, alt: 'Portal logo', style: { height: '40px', borderRadius: '6px' } }) : h('span', { class: 'muted small' }, 'No logo'),
      h('label', { class: 'btn sm' }, icon('image', 13), 'Upload', h('input', {
        type: 'file', accept: 'image/png,image/jpeg,image/webp', hidden: true,
        onchange: async (e) => { const fd = new FormData(); fd.append('logo', e.target.files[0]); try { c = await apiUpload('portal-brand/logo', fd); drawLogo(); drawPreview(); toast('Logo saved.'); } catch (err) { toast(err.message, 'err'); } },
      })),
      c.logo_url ? h('button', { type: 'button', class: 'btn ghost sm', onclick: async () => { c = await api('DELETE', 'portal-brand/logo'); drawLogo(); drawPreview(); } }, 'Remove') : null);
  }
  function drawPreview() {
    clear(preview).append(
      h('p', { class: 'small muted' }, 'Preview: what a client sees in their sidebar'),
      h('div', { class: 'row', style: { gap: '10px', padding: '10px', borderRadius: '10px', background: 'var(--panel-2)' } },
        c.logo_url ? h('img', { src: c.logo_url, alt: '', style: { height: '28px' } }) : h('span', { style: { width: '28px', height: '28px', borderRadius: '8px', background: f.accent.value, display: 'inline-block' } }),
        h('strong', {}, f.name.value || 'Creator Studio'),
        h('span', { class: 'pill sm', style: { background: f.accent.value, color: '#fff' } }, 'CLIENT')),
      f.welcome.value ? h('p', { class: 'small', style: { marginTop: '8px' } }, f.welcome.value) : null);
  }
  Object.values(f).forEach((el) => el.addEventListener('input', drawPreview));
  drawLogo();
  drawPreview();
  view.append(
    pageHead('Portal branding', 'White-label what clients and community members see: your name, logo and colour instead of “Creator Studio”. Your own studio keeps its look.'),
    h('div', { class: 'grid grid-2' },
      h('div', { class: 'card' },
        h('label', { class: 'row small', style: { gap: '6px' } }, f.enabled, 'Use this branding in the portal and on emails'),
        field('Portal name', f.name), field('Tagline', f.tagline), field('Accent colour', f.accent, 'Used for buttons and links'),
        field('Logo', logoSlot, 'PNG, JPG or WebP, up to 1 MB'), field('Support email', f.support_email, 'Shown to clients as “Need help?”'),
        field('Welcome line', f.welcome), field('Footer', f.footer),
        h('div', { class: 'row-end' }, h('button', {
          type: 'button', class: 'btn primary',
          onclick: (e) => busy(e.currentTarget, 'Saving…', async () => {
            c = await api('PUT', 'portal-brand', { enabled: f.enabled.checked, name: f.name.value || null, tagline: f.tagline.value || null, accent: f.accent.value, support_email: f.support_email.value || null, welcome: f.welcome.value || null, footer: f.footer.value || null });
            toast('Saved. Clients see it on their next page load.');
          }),
        }, 'Save'))),
      preview));
}

/** For clients and members: apply her white-label branding (no-op when it's off). */
export async function applyPortalBrand() {
  let b;
  try { b = await api('GET', 'branding'); } catch { return null; }
  if (!b?.enabled) return null;
  if (b.accent) {
    document.documentElement.style.setProperty('--accent', b.accent);
    document.documentElement.style.setProperty('--accent-soft', `${b.accent}22`);
  }
  return b;
}

export { navigate };
