// Website page: testimonials (approve what clients sent, add ones she already has, order them)
// and the email list (the landing page's free-download opt-in, subscribers, CSV export).
import { h, api, busy, toast, clear, modal, field, copyText, pill, timeAgo, confirmDialog, compact, guardDownload } from './core.js';
import { icon } from './icons.js';
import { skLines } from './skeleton.js';
import { SOURCE_ICONS } from './deal-tools.js';

const STATUS = { requested: ['', 'link sent'], submitted: ['new', 'to approve'], approved: ['ok', 'on your site'], hidden: ['', 'hidden'] };

export function testimonialsCard() {
  const box = h('div', { class: 'card' }, skLines(3, 'testimonials'));
  async function draw() {
    const { testimonials } = await api('GET', 'testimonials');
    const live = testimonials.filter((t) => t.status === 'approved' && t.permission && t.quote).length;
    clear(box).append(
      h('div', { class: 'card-header' },
        h('div', {}, h('h2', {}, 'Testimonials'), h('p', { class: 'muted small', style: { margin: '2px 0 0' } }, `${live} on your landing page (“What clients say” shows once there’s one)`)),
        h('button', { class: 'btn sm', onclick: () => edit() }, icon('plus', 14), 'Add one you have')),
      testimonials.length ? h('ul', { class: 'item-list' }, testimonials.map((t) => {
        const [tone, label] = STATUS[t.status] || STATUS.requested;
        return h('li', {},
          h('div', { class: 'item-main' },
            h('div', { class: 'row', style: { gap: '8px', alignItems: 'center' } }, h('strong', {}, t.name || t.company || 'Client'), pill(tone, label), t.rating ? h('span', { class: 'muted small' }, '★'.repeat(t.rating)) : null,
              t.status === 'submitted' && !t.permission ? pill('', 'private: not for the site') : null),
            t.quote ? h('div', { class: 'muted small clamp-2' }, `“${t.quote}”`) : h('div', { class: 'muted small' }, `Asked ${timeAgo(t.requested_at)}${t.deal ? ` · ${t.deal.brand_name}` : ''}: waiting for their words`)),
          h('div', { class: 'row', style: { gap: '6px' } },
            t.status === 'submitted' && t.permission ? h('button', { class: 'btn sm primary', onclick: (e) => busy(e.currentTarget, '…', async () => { await api('PATCH', `testimonials/${t.id}`, { status: 'approved' }); toast('On your landing page.'); draw(); }) }, icon('check', 13), 'Approve') : null,
            t.status === 'approved' ? h('button', { class: 'btn sm ghost', onclick: (e) => busy(e.currentTarget, '…', async () => { await api('PATCH', `testimonials/${t.id}`, { status: 'hidden' }); draw(); }) }, 'Hide') : null,
            t.status === 'hidden' ? h('button', { class: 'btn sm ghost', onclick: (e) => busy(e.currentTarget, '…', async () => { await api('PATCH', `testimonials/${t.id}`, { status: 'approved' }); draw(); }) }, 'Show') : null,
            t.url && !t.quote ? h('button', { class: 'btn sm ghost', onclick: () => copyText(t.url, 'Review link copied.') }, icon('copy', 13), 'Link') : null,
            t.quote ? h('button', { class: 'btn sm ghost', 'aria-label': 'Edit', onclick: () => edit(t) }, icon('pen', 13)) : null,
            h('button', { class: 'btn sm ghost', 'aria-label': 'Delete', onclick: async () => { if (await confirmDialog('Delete this testimonial?')) { await api('DELETE', `testimonials/${t.id}`); draw(); } } }, icon('trash', 13))));
      })) : h('p', { class: 'muted small' }, 'Ask paid clients from Deals (“Ask for a review”): they get a private link, you approve what they write. A follow-up reminds you a week after each payment.'));
  }
  function edit(t = null) {
    return modal(t ? 'Edit testimonial' : 'Add a testimonial', (close) => {
      const name = h('input', { type: 'text', required: true, value: t?.name || '' });
      const role = h('input', { type: 'text', value: t?.role || '' });
      const company = h('input', { type: 'text', value: t?.company || '' });
      const quote = h('textarea', { rows: 5, required: true }, t?.quote || '');
      const rating = h('select', {}, h('option', { value: '' }, 'No rating'), [5, 4, 3, 2, 1].map((n) => h('option', { value: n, selected: t?.rating === n }, '★'.repeat(n))));
      const form = h('form', {
        onsubmit: (e) => {
          e.preventDefault();
          busy(form.querySelector('[type=submit]'), 'Saving…', async () => {
            const body = { name: name.value.trim(), role: role.value.trim() || null, company: company.value.trim() || null, quote: quote.value.trim(), rating: rating.value ? Number(rating.value) : null };
            if (t) await api('PATCH', `testimonials/${t.id}`, body); else await api('POST', 'testimonials', body);
            toast(t ? 'Saved.' : 'Added to your landing page.');
            close(true);
            draw();
          });
        },
      },
      t ? null : h('p', { class: 'muted small' }, 'Only add words the person actually wrote (an email, a LinkedIn recommendation) and are happy to have shown.'),
      field('Quote', quote), h('div', { class: 'grid grid-3' }, field('Name', name), field('Role', role), field('Company', company)), field('Rating', rating),
      h('div', { class: 'row', style: { justifyContent: 'flex-end', marginTop: '12px' } }, h('button', { type: 'submit', class: 'btn primary' }, 'Save')));
      return form;
    });
  }
  draw().catch((e) => clear(box).append(h('p', { class: 'error-text' }, e.message)));
  return box;
}

export function emailListCard() {
  const box = h('div', { class: 'card' }, skLines(3, 'email list'));
  async function draw() {
    const d = await api('GET', 'subscribers');
    const m = d.magnet;
    const enabled = h('input', { type: 'checkbox', checked: m.enabled });
    const title = h('input', { type: 'text', value: m.title, maxlength: 120 });
    const desc = h('textarea', { rows: 2, maxlength: 400 }, m.description || '');
    const button = h('input', { type: 'text', value: m.button, maxlength: 60 });
    const url = h('input', { type: 'url', value: m.delivery_url || '', placeholder: 'https://… (Notion, PDF, Drive)' });
    const label = h('input', { type: 'text', value: m.delivery_label || '', maxlength: 60 });
    clear(box).append(
      h('div', { class: 'card-header' },
        h('div', {}, h('h2', {}, 'Email list'), h('p', { class: 'muted small', style: { margin: '2px 0 0' } }, `${compact(d.total)} subscriber${d.total === 1 ? '' : 's'} · ${d.last_30} in 30 days`)),
        d.total ? h('a', { class: 'btn sm', href: '/api/subscribers/export', onclick: guardDownload }, icon('download', 14), 'Export CSV') : null),
      h('p', { class: 'muted small' }, 'A free download on your landing page in exchange for an email: an audience you own. The download link shows right after they sign up; nothing is emailed from the studio. Import the CSV into your newsletter tool.'),
      h('label', { class: 'row small', style: { gap: '8px', margin: '8px 0' } }, enabled, 'Show the opt-in on my landing page'),
      h('div', { class: 'grid grid-2' }, field('Headline', title), field('Button', button)),
      field('One line about it', desc),
      h('div', { class: 'grid grid-2' }, field('Download link', url), field('Link label', label)),
      h('div', { class: 'row', style: { justifyContent: 'flex-end' } },
        h('button', {
          class: 'btn primary',
          onclick: (e) => busy(e.currentTarget, 'Saving…', async () => {
            await api('PUT', 'landing/lead-magnet', { enabled: enabled.checked, title: title.value.trim(), description: desc.value.trim() || null, button: button.value.trim(), delivery_url: url.value.trim() || null, delivery_label: label.value.trim() || null });
            toast(enabled.checked ? 'Opt-in is live on your landing page.' : 'Saved.');
            draw();
          }),
        }, 'Save')),
      d.by_source.length ? h('p', { class: 'small', style: { marginTop: '10px' } }, 'From: ', d.by_source.map((s) => h('span', { class: 'pf-chip', style: { marginRight: '6px' } }, icon(SOURCE_ICONS[s.source] || 'globe', 12), ` ${s.label} ${s.count}`))) : null,
      d.recent.length ? h('details', {}, h('summary', { class: 'small' }, `Latest ${d.recent.length}`),
        h('ul', { class: 'mini-list' }, d.recent.map((s) => h('li', {}, h('span', {}, s.email, s.name ? h('span', { class: 'muted' }, ` · ${s.name}`) : null),
          h('span', { class: 'row', style: { gap: '6px' } }, h('span', { class: 'muted small' }, timeAgo(s.created_at)),
            h('button', { class: 'btn ghost sm', 'aria-label': `Remove ${s.email}`, onclick: async () => { if (await confirmDialog(`Remove ${s.email}?`)) { await api('DELETE', `subscribers/${s.id}`); draw(); } } }, icon('trash', 12))))))) : null);
  }
  draw().catch((e) => clear(box).append(h('p', { class: 'error-text' }, e.message)));
  return box;
}
