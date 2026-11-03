// Income beyond sponsorships: affiliate links (/go/…), the digital products shop (hers and
// the public shop), and the newsletter to her subscribers.
import { h, api, apiUpload, toast, busy, modal, field, clear, pageHead, emptyState, loading, confirmDialog, copyText, timeAgo, fmtDay, markdown } from '../core.js';
import { icon } from '../icons.js';

const money = (n, c = 'USD') => new Intl.NumberFormat(undefined, { style: 'currency', currency: c || 'USD', maximumFractionDigits: 2 }).format(n || 0);

// =============================================================================================
// Affiliate links
// =============================================================================================

export async function renderAffiliates(view) {
  view.append(loading());
  let d = await api('GET', 'affiliates');
  const body = h('div');
  function draw() {
    clear(body).append(
      h('div', { class: 'grid grid-4' },
        stat('Earned (30 days)', money(d.totals.earned_30d)), stat('Earned (all time)', money(d.totals.earned_all)),
        stat('Clicks (30 days)', d.totals.clicks_30d.toLocaleString()), stat('Broken links', String(d.totals.broken), d.totals.broken ? 'warn' : '')),
      d.links.length ? h('div', { class: 'card table-wrap' }, h('table', { class: 'table' },
        h('thead', {}, h('tr', {}, ['Link', 'Partner', 'Where', 'Clicks 30d', 'Earned', ''].map((c) => h('th', {}, c)))),
        h('tbody', {}, d.links.map((l) => h('tr', {},
          h('td', {}, h('strong', {}, `/go/${l.slug}`), l.broken ? h('span', { class: 'pill sm late' }, ` broken (${l.last_status || 'no answer'})`) : null, !l.active ? h('span', { class: 'pill sm' }, ' off') : null,
            h('div', { class: 'muted small clamp-1', style: { maxWidth: '320px' } }, l.url)),
          h('td', {}, l.partner || '—', l.commission_note ? h('div', { class: 'muted small' }, l.commission_note) : null),
          h('td', {}, l.placement || '—'),
          h('td', { class: 'num' }, String(l.clicks_30d)),
          h('td', { class: 'num' }, money(l.earned)),
          h('td', {}, h('div', { class: 'row' },
            h('button', { type: 'button', class: 'btn ghost sm', onclick: () => copyText(l.short_url, 'Link copied.') }, icon('copy', 12)),
            h('button', { type: 'button', class: 'btn ghost sm', onclick: () => editLink(l) }, 'Edit'),
            h('button', { type: 'button', class: 'btn ghost sm', 'aria-label': 'Delete', onclick: async () => { if (await confirmDialog(`Delete /go/${l.slug}? Old links to it will stop working.`)) { d = await api('DELETE', `affiliates/${l.id}`); draw(); } } }, icon('trash', 12))))))))) : emptyState(icon('link', 28), 'No affiliate links yet', 'Add one, then use /go/your-link in descriptions, posts and the newsletter.'),
      h('div', { class: 'card' },
        h('div', { class: 'card-header' }, h('h2', {}, 'Commissions'), h('div', { class: 'row' },
          h('button', { type: 'button', class: 'btn sm', onclick: addEarning }, icon('plus', 12), 'Add'),
          h('label', { class: 'btn sm' }, icon('download', 12), 'Import CSV', h('input', { type: 'file', accept: '.csv,text/csv', hidden: true, onchange: async (e) => {
            const fd = new FormData(); fd.append('file', e.target.files[0]);
            try { const r = await apiUpload('affiliates/import', fd); d = r; toast(`Imported ${r.added} row(s)${r.skipped_rows.length ? `; skipped row(s) ${r.skipped_rows.join(', ')}` : ''}.`); draw(); } catch (err) { toast(err.message, 'err'); }
          } })))),
        h('p', { class: 'muted small' }, 'CSV columns: date, link slug (or partner name), amount[, currency, note]. Only what partners actually paid, so the numbers are real.'),
        d.earnings.length ? h('ul', { class: 'stack small' }, d.earnings.map((e) => h('li', { class: 'row-between' },
          h('span', {}, `${fmtDay(e.earned_on)} · ${e.slug ? `/go/${e.slug}` : (e.partner || '—')}${e.note ? ` · ${e.note}` : ''}`),
          h('span', { class: 'row' }, h('strong', {}, money(e.amount, e.currency)), h('button', { type: 'button', class: 'btn ghost sm', 'aria-label': 'Delete', onclick: async () => { d = await api('DELETE', `affiliates/earnings/${e.id}`); draw(); } }, icon('x', 11)))))) : h('p', { class: 'muted' }, 'No commissions logged yet.')));
  }
  async function editLink(l = null) {
    const url = h('input', { type: 'url', value: l?.url || '', required: true, placeholder: 'https://partner.com/?ref=you' });
    const slug = h('input', { value: l?.slug || '', placeholder: 'e.g. my-mic', maxlength: 60 });
    const partner = h('input', { value: l?.partner || '', maxlength: 120 });
    const note = h('input', { value: l?.commission_note || '', maxlength: 200, placeholder: 'e.g. 20% recurring' });
    const placement = h('select', {}, h('option', { value: '' }, '—'), d.placements.map((p) => h('option', { value: p, selected: l?.placement === p }, p)));
    const active = h('input', { type: 'checkbox', checked: l ? !!l.active : true });
    await modal(l ? 'Edit link' : 'New affiliate link', (close) => h('form', { onsubmit: async (e) => { e.preventDefault();
      const b = { url: url.value, slug: slug.value || null, partner: partner.value || null, commission_note: note.value || null, placement: placement.value || null, active: active.checked };
      try { d = await api(l ? 'PATCH' : 'POST', l ? `affiliates/${l.id}` : 'affiliates', b); close(); draw(); } catch (err) { toast(err.message, 'err'); } } },
    field('Partner link', url), h('div', { class: 'grid grid-2' }, field('Short name', slug, 'yoursite/go/…'), field('Partner', partner)),
    h('div', { class: 'grid grid-2' }, field('Commission', note), field('Used in', placement)),
    h('label', { class: 'row small', style: { gap: '6px' } }, active, 'Active'),
    h('div', { class: 'row-end' }, h('button', { type: 'submit', class: 'btn primary' }, 'Save'))));
  }
  async function addEarning() {
    const link = h('select', {}, h('option', { value: '' }, 'No link (partner only)'), d.links.map((l) => h('option', { value: l.id }, `/go/${l.slug}`)));
    const partner = h('input', { maxlength: 120 });
    const amount = h('input', { type: 'number', step: '0.01', required: true });
    const on = h('input', { type: 'date', value: new Date().toISOString().slice(0, 10), required: true });
    const note = h('input', { maxlength: 200 });
    await modal('Log a commission', (close) => h('form', { onsubmit: async (e) => { e.preventDefault();
      try { d = await api('POST', 'affiliates/earnings', { link_id: link.value ? Number(link.value) : null, partner: partner.value || null, amount: Number(amount.value), earned_on: on.value, note: note.value || null }); close(); draw(); } catch (err) { toast(err.message, 'err'); } } },
    field('Link', link), field('Partner', partner), h('div', { class: 'grid grid-2' }, field('Amount', amount), field('Date', on)), field('Note', note),
    h('div', { class: 'row-end' }, h('button', { type: 'submit', class: 'btn primary' }, 'Save'))));
  }
  clear(view).append(pageHead('Affiliate links', 'Short links you own, real clicks, and what partners actually paid.',
    h('button', { type: 'button', class: 'btn', onclick: (e) => busy(e.currentTarget, 'Checking…', async () => { d = await api('POST', 'affiliates/check'); toast(`${d.broken} broken link(s).`); draw(); }) }, icon('refresh', 14), 'Check links'),
    h('button', { type: 'button', class: 'btn primary', onclick: () => editLink() }, icon('plus', 14), 'New link')), body);
  draw();
}

function stat(label, value, tone = '') {
  return h('div', { class: `card ${tone === 'warn' ? 'callout warn' : ''}` }, h('span', { class: 'muted small' }, label), h('strong', { style: { display: 'block', fontSize: '1.4rem' } }, value));
}

// =============================================================================================
// Shop: hers
// =============================================================================================

export async function renderShopAdmin(view) {
  view.append(loading());
  let d = await api('GET', 'shop/admin');
  const body = h('div');
  function draw() {
    clear(body).append(
      d.online ? null : h('div', { class: 'card callout warn' }, 'Add your Stripe keys in Settings to sell online.'),
      h('div', { class: 'grid grid-3' }, stat('Sales (30 days)', money(d.totals.revenue_30d)), stat('Orders (30 days)', String(d.totals.orders_30d)), stat('All time', money(d.totals.revenue_all))),
      d.products.length ? h('div', { class: 'stack' }, d.products.map((p) => h('div', { class: 'card row-between', style: { alignItems: 'flex-start' } },
        h('div', {}, h('strong', {}, p.title), ' ', h('span', { class: `pill sm ${p.status === 'published' ? 'ok' : ''}` }, p.status),
          h('div', { class: 'muted small' }, `${money(p.price, p.currency)}${p.pro_discount_percent ? ` · Pro −${p.pro_discount_percent}%` : ''} · ${p.sales_count} sold · ${money(p.revenue, p.currency)} · ${p.has_file ? p.file_name : 'no file yet'}`),
          p.status === 'published' ? h('div', { class: 'small' }, h('a', { href: `/shop#${p.slug}`, target: '_blank', rel: 'noopener' }, 'View in shop')) : null),
        h('div', { class: 'row' },
          h('label', { class: 'btn sm' }, icon('download', 12), p.has_file ? 'Replace file' : 'Upload file', h('input', { type: 'file', hidden: true, onchange: async (e) => {
            const fd = new FormData(); fd.append('file', e.target.files[0]);
            try { d = await apiUpload(`shop/admin/products/${p.id}/file`, fd); toast('File saved.'); draw(); } catch (err) { toast(err.message, 'err'); }
          } })),
          h('button', { type: 'button', class: 'btn sm', onclick: async () => { try { d = await api('PATCH', `shop/admin/products/${p.id}`, { status: p.status === 'published' ? 'draft' : 'published' }); draw(); } catch (err) { toast(err.message, 'err'); } } }, p.status === 'published' ? 'Unpublish' : 'Publish'),
          h('button', { type: 'button', class: 'btn ghost sm', onclick: () => edit(p) }, 'Edit'),
          h('button', { type: 'button', class: 'btn ghost sm', 'aria-label': 'Delete', onclick: async () => { if (await confirmDialog(p.sales_count ? 'It has sales, so it’s taken off sale (buyers keep their downloads).' : `Delete “${p.title}”?`)) { d = await api('DELETE', `shop/admin/products/${p.id}`); draw(); } } }, icon('trash', 12)))))) : emptyState(icon('gift', 28), 'Nothing for sale yet', 'Templates, presets, guides or a recorded workshop: anything people can download.'),
      d.orders.length ? h('div', { class: 'card' }, h('h2', {}, 'Recent orders'), h('div', { class: 'table-wrap' }, h('table', { class: 'table' },
        h('thead', {}, h('tr', {}, ['Date', 'Product', 'Buyer', 'Amount', 'Downloads', ''].map((c) => h('th', {}, c)))),
        h('tbody', {}, d.orders.map((o) => h('tr', {}, h('td', {}, timeAgo(o.paid_at)), h('td', {}, o.title), h('td', {}, o.email), h('td', { class: 'num' }, money(o.amount, o.currency)), h('td', { class: 'num' }, String(o.downloads)),
          h('td', {}, h('button', { type: 'button', class: 'btn ghost sm', onclick: async () => { await api('POST', `shop/admin/orders/${o.id}/resend`); toast('New link emailed.'); } }, 'Resend link')))))))) : null);
  }
  async function edit(p = null) {
    const title = h('input', { value: p?.title || '', required: true, maxlength: 200 });
    const desc = h('textarea', { rows: 5 }, p?.description || '');
    const price = h('input', { type: 'number', min: 0.5, step: '0.01', value: p?.price || '', required: true });
    const cur = h('select', {}, ['USD', 'EUR', 'GBP'].map((c) => h('option', { value: c, selected: (p?.currency || 'USD') === c }, c)));
    const disc = h('input', { type: 'number', min: 0, max: 100, value: p?.pro_discount_percent || 0 });
    await modal(p ? 'Edit product' : 'New product', (close) => h('form', { onsubmit: async (e) => { e.preventDefault();
      const b = { title: title.value, description: desc.value || null, price: Number(price.value), currency: cur.value, pro_discount_percent: Number(disc.value || 0) };
      try { d = await api(p ? 'PATCH' : 'POST', p ? `shop/admin/products/${p.id}` : 'shop/admin/products', b); close(); draw(); } catch (err) { toast(err.message, 'err'); } } },
    field('Title', title), field('Description (Markdown)', desc), h('div', { class: 'grid grid-3' }, field('Price', price), field('Currency', cur), field('Pro discount %', disc)),
    h('div', { class: 'row-end' }, h('button', { type: 'submit', class: 'btn primary' }, 'Save'))), { wide: true });
  }
  clear(view).append(pageHead('Shop', 'Digital products: buyers get a download link by email (5 downloads, 30 days).', h('button', { type: 'button', class: 'btn primary', onclick: () => edit() }, icon('plus', 14), 'New product')), body);
  draw();
}

// =============================================================================================
// Shop: public (signed in or not)
// =============================================================================================

export async function renderShop(view) {
  const d = await api('GET', 'shop');
  const params = new URLSearchParams(location.search);
  if (params.get('bought')) toast('Thank you! Your download link is on its way by email.');
  const signedIn = !!d.signed_in;
  clear(view).append(
    pageHead('Shop', 'Templates, guides and resources.'),
    d.purchases?.length ? h('div', { class: 'card' }, h('h2', {}, 'Your purchases'), h('ul', { class: 'stack' }, d.purchases.map((o) => h('li', { class: 'row-between' },
      h('span', {}, o.title, h('span', { class: 'muted small' }, ` · ${fmtDay((o.paid_at || '').slice(0, 10))}`)),
      h('a', { class: 'btn sm', href: `/api/account/purchases/${o.id}/download` }, icon('download', 12), 'Download'))))) : null,
    d.products.length ? h('div', { class: 'grid grid-3' }, d.products.map((p) => {
      const email = h('input', { type: 'email', placeholder: 'Your email', required: true, 'aria-label': 'Your email' });
      return h('div', { class: 'card', id: p.slug },
        h('h3', {}, p.title), p.file_type ? h('span', { class: 'pill sm' }, p.file_type) : null,
        p.description ? h('div', { class: 'markdown-body small', html: markdown(p.description) }) : null,
        h('p', {}, p.your_price < p.price ? [h('s', { class: 'muted' }, money(p.price, p.currency)), ' ', h('strong', {}, money(p.your_price, p.currency)), h('span', { class: 'muted small' }, ' Pro price')] : h('strong', {}, money(p.price, p.currency)),
          !signedIn && p.pro_discount_percent ? h('span', { class: 'muted small' }, ` · ${p.pro_discount_percent}% off for Pro members`) : null),
        h('form', { class: 'stack', onsubmit: (e) => { e.preventDefault(); busy(e.submitter || e.target.querySelector('button'), 'Opening checkout…', async () => {
          location.href = (await api('POST', `shop/${p.slug}/checkout`, signedIn ? {} : { email: email.value })).checkout_url;
        }); } }, signedIn ? null : email, h('button', { type: 'submit', class: 'btn primary', disabled: !d.online }, d.online ? 'Buy' : 'Not on sale right now')));
    })) : emptyState(icon('gift', 28), 'Nothing here yet', 'Check back soon.'));
}

// =============================================================================================
// Newsletter
// =============================================================================================

export async function renderNewsletter(view) {
  view.append(loading());
  let d = await api('GET', 'newsletter');
  const body = h('div');
  function draw() {
    const s = d.subscribers;
    clear(body).append(
      h('div', { class: 'grid grid-3' }, stat('Subscribers', s.active.toLocaleString()), stat('Unsubscribed', String(s.unsubscribed)), stat('Sent campaigns', String(d.campaigns.filter((c) => c.status === 'sent').length))),
      d.campaigns.length ? h('div', { class: 'stack' }, d.campaigns.map((c) => h('div', { class: 'card row-between' },
        h('div', {}, h('strong', {}, c.subject), ' ', h('span', { class: `pill sm ${c.status === 'sent' ? 'ok' : c.status === 'sending' ? 'new' : ''}` }, c.status),
          h('div', { class: 'muted small' }, c.status === 'draft' ? `Edited ${timeAgo(c.updated_at)}` : `${c.sent_count}/${c.total} sent${c.failed_count ? ` · ${c.failed_count} failed` : ''}${c.sent_at ? ` · ${timeAgo(c.sent_at)}` : ''}`)),
        c.status === 'draft' ? h('div', { class: 'row' }, h('button', { type: 'button', class: 'btn sm', onclick: () => edit(c) }, 'Edit'),
          h('button', { type: 'button', class: 'btn ghost sm', 'aria-label': 'Delete', onclick: async () => { d = await api('DELETE', `newsletter/${c.id}`); draw(); } }, icon('trash', 12))) : null))) : emptyState(icon('mail', 28), 'No newsletters yet', 'Write one, or start from a script.'));
  }
  async function edit(c = null, preset = {}) {
    const subject = h('input', { value: c?.subject || preset.subject || '', required: true, maxlength: 200 });
    const text = h('textarea', { rows: 14, placeholder: 'Markdown. {name} becomes their name.' }, c?.body || preset.body || '');
    const src = h('select', {}, h('option', { value: '' }, 'Everyone'), Object.entries(d.subscribers.sources).map(([k, n]) => h('option', { value: k, selected: c?.segment?.source === k }, `Signed up via ${k} (${n})`)));
    const mag = h('select', {}, h('option', { value: '' }, 'Any lead magnet'), Object.entries(d.subscribers.magnets).map(([k, n]) => h('option', { value: k, selected: c?.segment?.magnet === k }, `${k} (${n})`)));
    const preview = h('div', { class: 'markdown-body card', style: { maxHeight: '300px', overflow: 'auto' } });
    const drawPreview = () => { preview.innerHTML = markdown(text.value.replaceAll('{name}', 'Alex')); };
    text.addEventListener('input', drawPreview);
    drawPreview();
    let id = c?.id || null;
    const saveDraft = async () => { const b = { subject: subject.value, body: text.value, segment: { source: src.value || null, magnet: mag.value || null } };
      const r = await api(id ? 'PATCH' : 'POST', id ? `newsletter/${id}` : 'newsletter', b); id = r.id || id; d = r; draw(); };
    await modal(c ? 'Edit newsletter' : 'New newsletter', (close) => h('div', {},
      field('Subject', subject), h('div', { class: 'grid grid-2' }, field('Send to', src), field('Segment', mag)),
      h('div', { class: 'grid grid-2' }, field('Message', text), h('div', {}, h('span', { class: 'muted small' }, 'Preview'), preview)),
      h('p', { class: 'muted small' }, 'Every email has a one-click unsubscribe link. Tip: use your /go/ affiliate links to see clicks.'),
      h('div', { class: 'row', style: { justifyContent: 'space-between', marginTop: '12px' } },
        h('button', { type: 'button', class: 'btn', onclick: (e) => busy(e.currentTarget, 'Saving…', async () => { await saveDraft(); toast('Draft saved.'); }) }, 'Save draft'),
        h('div', { class: 'row' },
          h('button', { type: 'button', class: 'btn', onclick: (e) => busy(e.currentTarget, 'Sending…', async () => { await saveDraft(); toast((await api('POST', `newsletter/${id}/test`)).message); }) }, 'Send me a test'),
          h('button', { type: 'button', class: 'btn primary', onclick: async (e) => {
            if (!(await confirmDialog('Send it now to everyone in this segment? This can’t be undone.', { ok: 'Send', danger: false }))) return;
            busy(e.currentTarget, 'Starting…', async () => { await saveDraft(); toast((await api('POST', `newsletter/${id}/send`)).message); d = await api('GET', 'newsletter'); draw(); close(); });
          } }, 'Send')))), { wide: true });
  }
  async function fromScript() {
    const { scripts } = await api('GET', 'scripts').then((r) => ({ scripts: r.scripts || r })).catch(() => ({ scripts: [] }));
    const pick = h('select', {}, (scripts || []).map((s) => h('option', { value: s.id }, s.title)));
    await modal('Start from a script', (close) => h('div', {}, field('Script', pick), h('div', { class: 'row-end' }, h('button', { type: 'button', class: 'btn primary', onclick: async () => {
      const preset = await api('GET', `newsletter/from-script/${pick.value}`); close(); edit(null, preset);
    } }, 'Use it'))));
  }
  clear(view).append(pageHead('Newsletter', 'Email the people who subscribed on your site.',
    h('button', { type: 'button', class: 'btn', onclick: fromScript }, icon('doc', 14), 'From a script'),
    h('button', { type: 'button', class: 'btn primary', onclick: () => edit() }, icon('plus', 14), 'New')), body);
  draw();
}
