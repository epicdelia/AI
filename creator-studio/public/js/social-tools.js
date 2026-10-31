// Shared social tools: the bio studio card and the analytics CSV import dialog.
import { h, api, apiUpload, busy, toast, modal, clear, field, copyText, navigate } from './core.js';
import { icon } from './icons.js';
import { skLines } from './skeleton.js';

const chars = (s) => [...String(s || '')].length;

/** Profile bio / headline with a live limit, AI options in her voice, and save. */
export function bioCard(platform) {
  const box = h('div', {}, skLines(3, 'your bio'));
  api('GET', 'social/bios').then(({ bios }) => {
    const b = bios[platform];
    if (!b) { box.replaceChildren(); return; }
    const count = h('span', { class: 'char-count' });
    const ta = h('textarea', { rows: platform === 'youtube' ? 5 : 3, placeholder: `Your ${b.label} ${b.what.toLowerCase()}…` }, b.text || '');
    const draw = () => { const n = chars(ta.value); count.textContent = `${n} / ${b.limit}`; count.classList.toggle('over', n > b.limit); };
    ta.addEventListener('input', draw);
    draw();
    const options = h('div', { class: 'bio-options' });
    const saveBtn = h('button', {
      class: 'btn sm primary',
      onclick: () => busy(saveBtn, 'Saving…', async () => {
        const r = await api('PUT', 'social/bios', { platform, text: ta.value });
        toast(r.message);
      }),
    }, 'Save');
    const ideaBtn = h('button', {
      class: 'btn sm',
      onclick: () => busy(ideaBtn, 'Writing…', async () => {
        const r = await api('POST', 'social/bios/options', { platform });
        clear(options).append(r.options.map((o) => h('button', {
          class: `bio-option${o.fits ? '' : ' too-long'}`, title: o.fits ? 'Use this' : 'Too long: edit it down',
          onclick: () => { ta.value = o.text; draw(); ta.focus(); },
        }, h('span', {}, o.text), h('span', { class: 'muted small' }, `${o.chars}/${b.limit}`))));
      }),
    }, icon('sparkle', 14), '3 options');
    box.replaceChildren(
      h('div', { class: 'card-header' }, h('h3', {}, `${b.label} ${b.what.toLowerCase()}`), count),
      ta, options,
      h('div', { class: 'row' }, ideaBtn, saveBtn,
        h('button', { class: 'btn ghost sm', onclick: () => copyText(ta.value, 'Copied. Paste it into your profile.') }, icon('copy', 14), 'Copy')),
      h('p', { class: 'muted small' }, `${b.limit} characters is the commonly listed limit${platform === 'threads' ? ' (Threads doesn’t publish one)' : ''}; the app shows the real cut-off.`));
  }).catch(() => box.replaceChildren());
  return h('div', { class: 'card bio-card' }, box);
}

/**
 * Import post numbers from a platform's analytics export. Step 1 previews the CSV with the
 * columns guessed; she fixes any she needs to; step 2 imports. Resolves true if anything changed.
 */
export function importDialog(platforms, preset = null) {
  return modal('Import your numbers', (close) => {
    let file = null;
    let preview = null;
    const platformSel = h('select', {}, Object.entries(platforms).map(([k, p]) => h('option', { value: k, selected: k === preset }, p.label)));
    const fileIn = h('input', { type: 'file', accept: '.csv,.tsv,.txt,text/csv' });
    const stage = h('div');
    const LABELS = { date: 'Date posted', text: 'Post text', url: 'Post link', impressions: 'Impressions / views', likes: 'Likes / reactions', comments: 'Comments / replies', shares: 'Shares / reposts', saves: 'Saves / bookmarks' };

    async function loadPreview() {
      if (!fileIn.files[0]) return;
      file = fileIn.files[0];
      clear(stage).append(skLines(4, 'your file'));
      const fd = new FormData();
      fd.append('file', file);
      fd.append('platform', platformSel.value);
      try {
        preview = await apiUpload('social/import', fd);
      } catch (err) { clear(stage).append(h('p', { class: 'error-text' }, err.message)); return; }
      const selects = {};
      clear(stage).append(
        h('p', { class: 'muted small' }, `${preview.rows} row(s) found. Check the columns below (guessed from the headers), then import.`),
        h('div', { class: 'grid grid-2 tight' }, preview.fields.map((f) => {
          selects[f] = h('select', {}, h('option', { value: '' }, '— not in this file —'),
            preview.headers.map((hd, i) => h('option', { value: i, selected: preview.guess[f] === i }, hd || `Column ${i + 1}`)));
          return field(LABELS[f], selects[f]);
        })),
        h('div', { class: 'table-wrap import-sample' }, h('table', {},
          h('thead', {}, h('tr', {}, preview.headers.map((hd) => h('th', {}, hd)))),
          h('tbody', {}, preview.sample.map((r) => h('tr', {}, r.map((c) => h('td', { class: 'clamp-1' }, c))))))),
        h('div', { class: 'row', style: { justifyContent: 'flex-end', marginTop: '12px' } }, (() => {
          const go = h('button', {
            class: 'btn primary',
            onclick: () => busy(go, 'Importing…', async () => {
              const mapping = Object.fromEntries(Object.entries(selects).map(([f, s]) => [f, s.value === '' ? null : Number(s.value)]));
              const fd2 = new FormData();
              fd2.append('file', file);
              fd2.append('platform', platformSel.value);
              fd2.append('mapping', JSON.stringify(mapping));
              const r = await apiUpload('social/import', fd2);
              toast(r.message);
              close(true);
            }),
          }, icon('download', 15), `Import ${preview.rows} row(s)`);
          return go;
        })()));
    }
    fileIn.addEventListener('change', loadPreview);
    platformSel.addEventListener('change', loadPreview);

    return h('div', {},
      h('p', { class: 'muted small' }, 'Export your post analytics from the platform (or a scheduling tool) as CSV. Excel files: open them and “Save as → CSV” first. Rows match your existing posts by link, or by date and opening words; the rest are added as posted.'),
      h('div', { class: 'grid grid-2 tight' }, field('Platform', platformSel), field('CSV file', fileIn)),
      stage);
  }, { wide: true });
}

/** Demo posts: a button to show them, or a banner saying they're on, with Remove. */
export function demoControl(count) {
  const reload = () => navigate(location.pathname + location.search, { replace: true });
  if (count > 0) {
    const btn = h('button', {
      class: 'btn sm',
      onclick: () => busy(btn, 'Removing…', async () => { const r = await api('DELETE', 'social/demo'); toast(r.message); reload(); }),
    }, icon('trash', 14), 'Remove demo posts');
    return h('div', { class: 'demo-banner', role: 'status' }, icon('eye', 16),
      h('span', {}, h('strong', {}, `${count} demo posts`), ' are showing so you can see how everything looks on every network. Your own posts are never touched.'), btn);
  }
  const btn = h('button', {
    class: 'btn ghost sm', title: 'Add example posts for every network (removable in one click)',
    onclick: () => busy(btn, 'Adding…', async () => { const r = await api('POST', 'social/demo'); toast(r.message); reload(); }),
  }, icon('eye', 14), 'Show demo posts');
  return btn;
}
