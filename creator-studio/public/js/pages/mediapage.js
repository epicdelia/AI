// Media library: every file in the studio in one place (thumbnail mockups, reference photos,
// read-aloud audio, post media), searchable and filterable, ready to reuse or download.
import { h, api, toast, pageHead, clear, confirmDialog, emptyState, timeAgo, apiUpload, link } from '../core.js';
import { icon } from '../icons.js';

const KINDS = [['', 'Everything'], ['thumbnail', 'Thumbnails'], ['photo', 'Photos'], ['social', 'Post media'], ['tts', 'Audio']];

export async function renderMediaLibrary(view) {
  const state = { kind: '', q: '' };
  let items = [];
  const grid = h('div', { class: 'ml-grid' });
  const count = h('span', { class: 'muted small' });

  async function load() {
    const r = await api('GET', `media${state.kind ? `?kind=${state.kind}` : ''}`);
    items = r.media;
    draw();
  }

  function preview(m) {
    if (String(m.mime).startsWith('image/')) return h('img', { src: m.url, alt: m.title || '', loading: 'lazy' });
    if (String(m.mime).startsWith('video/')) return h('video', { src: m.url, muted: true, preload: 'metadata' });
    if (String(m.mime).startsWith('audio/')) return h('div', { class: 'ml-audio' }, icon('music', 28));
    return h('div', { class: 'ml-audio' }, icon('doc', 28));
  }

  function draw() {
    const q = state.q.toLowerCase();
    const shown = items.filter((m) => !q || `${m.title || ''} ${m.kind} ${m.prompt || ''}`.toLowerCase().includes(q));
    count.textContent = `${shown.length} file${shown.length === 1 ? '' : 's'}`;
    clear(grid).append(shown.length ? shown.map((m) => h('figure', { class: 'ml-item' },
      h('a', { class: 'ml-thumb', href: m.url, target: '_blank', rel: 'noopener', title: 'Open full size' }, preview(m)),
      h('figcaption', {},
        h('strong', { class: 'clamp-1' }, m.title || m.kind),
        h('span', { class: 'muted small' }, `${m.kind} · ${timeAgo(m.created_at)}${m.bytes ? ` · ${(m.bytes / 1048576).toFixed(m.bytes > 10485760 ? 0 : 1)} MB` : ''}`),
        h('span', { class: 'row ml-actions' },
          h('a', { class: 'btn ghost sm', href: m.url, download: '', 'aria-label': 'Download' }, icon('download', 13)),
          h('button', { class: 'btn ghost sm', 'aria-label': 'Copy link', onclick: () => navigator.clipboard?.writeText(location.origin + m.url).then(() => toast('Link copied (works while you’re signed in).')) }, icon('copy', 13)),
          h('button', { class: 'btn ghost sm', 'aria-label': 'Delete', onclick: async () => {
            if (!(await confirmDialog(`Delete “${m.title || m.kind}”? Posts using it lose it.`))) return;
            await api('DELETE', `media/${m.id}`).catch((e) => toast(e.message, 'err'));
            load();
          } }, icon('trash', 13)))))) : emptyState(icon('image', 28), 'Nothing here yet', 'Thumbnail mockups, reference photos, read-aloud audio and files you add to posts all collect here.'));
  }

  const upload = h('input', { type: 'file', accept: 'image/*', hidden: true, onchange: async () => {
    const f = upload.files[0];
    if (!f) return;
    const fd = new FormData();
    fd.append('photo', f);
    try { await apiUpload('media/photos', fd); toast('Added.'); load(); } catch (e) { toast(e.message, 'err'); }
    upload.value = '';
  } });
  const search = h('input', { type: 'search', placeholder: 'Search files…', 'aria-label': 'Search files', oninput: (e) => { state.q = e.target.value; draw(); } });
  const filters = h('div', { class: 'segmented' }, KINDS.map(([k, l]) => h('button', { class: `seg${state.kind === k ? ' active' : ''}`, onclick: (e) => {
    state.kind = k; filters.querySelectorAll('.seg').forEach((b) => b.classList.toggle('active', b === e.currentTarget)); load();
  } }, l)));

  view.append(
    pageHead('Media library', 'Every image, video and audio file in your studio, in one place.',
      upload, h('button', { class: 'btn primary', onclick: () => upload.click() }, icon('plus', 15), 'Add a photo')),
    h('div', { class: 'row-between wrap ml-toolbar' }, filters, h('div', { class: 'row' }, count, search)),
    grid);
  await load();
}
