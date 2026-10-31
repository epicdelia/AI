// Link in bio: her links on one page (/links), with clicks counted per link and per day.
import { h, api, busy, toast, pageHead, clear, field } from '../core.js';
import { icon } from '../icons.js';

export async function renderBio(view) {
  let d = await api('GET', 'bio');
  let rows = d.links.map((l) => ({ id: l.id, title: l.title, url: l.url, active: !!l.active, clicks: l.clicks_30d }));
  const title = h('input', { type: 'text', value: d.title || '', maxlength: 80 });
  const text = h('textarea', { rows: 2, maxlength: 300 });
  text.value = d.text || '';
  const list = h('div', { class: 'bio-edit' });
  const chart = h('div');

  function draw() {
    clear(list).append(rows.map((r, i) => h('div', { class: `bio-row${r.active ? '' : ' off'}` },
      h('input', { type: 'text', value: r.title, placeholder: 'Label', 'aria-label': 'Label', oninput: (e) => { r.title = e.target.value; } }),
      h('input', { type: 'url', value: r.url, placeholder: 'https://…', 'aria-label': 'Address', oninput: (e) => { r.url = e.target.value; } }),
      h('label', { class: 'switch', title: r.active ? 'Shown' : 'Hidden' }, h('input', { type: 'checkbox', checked: r.active, onchange: (e) => { r.active = e.target.checked; draw(); } }), h('span', { class: 'slider' })),
      h('span', { class: 'muted small nowrap', title: 'Clicks in the last 30 days' }, r.clicks != null ? `${r.clicks} click${r.clicks === 1 ? '' : 's'}` : 'new'),
      h('span', { class: 'row' },
        h('button', { type: 'button', class: 'btn ghost sm', 'aria-label': 'Move up', disabled: i === 0, onclick: () => { [rows[i - 1], rows[i]] = [rows[i], rows[i - 1]]; draw(); } }, '↑'),
        h('button', { type: 'button', class: 'btn ghost sm', 'aria-label': 'Remove', onclick: () => { rows.splice(i, 1); draw(); } }, icon('x', 13))))));
  }

  function drawChart() {
    const max = Math.max(1, ...d.series.map((s) => s.clicks));
    const total = d.series.reduce((a, s) => a + s.clicks, 0);
    clear(chart).append(h('div', { class: 'card' },
      h('div', { class: 'card-header' }, h('h2', {}, 'Clicks, last 30 days'), h('strong', {}, total.toLocaleString())),
      h('div', { class: 'bio-chart', role: 'img', 'aria-label': `${total} clicks in the last 30 days` }, d.series.map((s) => h('span', {
        class: 'bio-bar', style: { height: `${Math.max(s.clicks ? 6 : 2, (s.clicks / max) * 100)}%` }, 'data-tip': `${new Date(s.day).toLocaleDateString([], { day: 'numeric', month: 'short' })}: ${s.clicks} click${s.clicks === 1 ? '' : 's'}`, title: `${s.day}: ${s.clicks}`,
      })))));
  }

  const save = h('button', { class: 'btn primary', onclick: () => busy(save, 'Saving…', async () => {
    d = await api('PUT', 'bio', { title: title.value, text: text.value, links: rows.filter((r) => r.title.trim() && r.url.trim()).map((r) => ({ id: r.id, title: r.title, url: r.url, active: r.active })) });
    rows = d.links.map((l) => ({ id: l.id, title: l.title, url: l.url, active: !!l.active, clicks: l.clicks_30d }));
    draw(); drawChart();
    toast('Saved.');
  }) }, icon('check', 15), 'Save');

  view.append(
    pageHead('Link in bio', 'One page with all your links, for your Instagram, TikTok and Threads bios. Clicks are counted per link.',
      h('a', { class: 'btn', href: '/links', target: '_blank', rel: 'noopener' }, icon('external', 15), 'Open the page'), save),
    d.public ? h('div', { class: 'card callout' }, 'Your page: ', h('code', {}, d.url), ' — put this in your bios.')
      : h('div', { class: 'card callout warn' }, 'Right now the page only opens on this computer (', h('code', {}, d.url), '). It becomes a public link for your bios once the studio is online, e.g. with the https setup on Connections or hosting. Everything else here already works.'),
    h('div', { class: 'grid grid-2' },
      h('div', { class: 'card' },
        field('Name on the page', title), field('Short bio', text),
        h('div', { class: 'card-header' }, h('h3', {}, 'Links'), h('button', { class: 'btn sm', onclick: () => { rows.push({ title: '', url: '', active: true, clicks: null }); draw(); } }, icon('plus', 14), 'Add link')),
        list),
      chart));
  draw();
  drawChart();
}
