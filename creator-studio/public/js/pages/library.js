// Library: everything saved from the Toolkit, AI YouTube and chat. Search, star, edit, export.
import { h, api, apiUpload, busy, toast, pageHead, clear, modal, confirmDialog, mdBlock, copyText, download, slug, timeAgo, debounce, link, emptyState } from '../core.js';
import { icon } from '../icons.js';
import { exportMenu } from '../components.js';
import { skList } from '../skeleton.js';

const toolLabel = (t) => (t || 'note').replace(/^tool:/, '').replace(/^youtube:/, 'youtube · ').replace(/_/g, ' ');

export async function renderLibrary(view) {
  const state = { q: '', tool: '', favorite: false, page: 1, lastPage: 1, docs: [], tools: [] };
  const listBox = h('div');
  const toolSelect = h('select', { 'aria-label': 'Filter by tool' });
  const favBtn = h('button', { class: 'btn sm' }, '★ Favourites');

  async function load(reset = true) {
    if (reset) { state.page = 1; clear(listBox).append(skList(8, { thumb: null, label: 'your Library' })); }
    const q = new URLSearchParams({ page: state.page });
    if (state.q) q.set('q', state.q);
    if (state.tool) q.set('tool', state.tool);
    if (state.favorite) q.set('favorite', '1');
    const r = await api('GET', `documents?${q}`);
    state.docs = reset ? r.documents.data : state.docs.concat(r.documents.data);
    state.lastPage = r.documents.last_page;
    state.tools = r.tools;
    drawFilters();
    drawList();
  }

  function drawFilters() {
    clear(toolSelect).append(h('option', { value: '' }, 'All tools'),
      state.tools.map((t) => h('option', { value: t, selected: t === state.tool }, toolLabel(t))));
    favBtn.classList.toggle('primary', state.favorite);
  }

  function openDoc(doc) {
    modal(doc.title, (close) => {
      let editing = false;
      const body = h('div');
      const title = h('input', { type: 'text', value: doc.title, class: 'title-input small' });
      function draw() {
        clear(body);
        if (editing) {
          const ta = h('textarea', { class: 'doc-editor', rows: 20 }, doc.content);
          const save = h('button', {
            class: 'btn primary',
            onclick: () => busy(save, 'Saving…', async () => {
              const r = await api('PATCH', `documents/${doc.id}`, { title: title.value || doc.title, content: ta.value });
              Object.assign(doc, r.document);
              editing = false;
              draw();
              drawList();
              toast('Saved.');
            }),
          }, 'Save');
          body.append(field2('Title', title), ta, h('div', { class: 'row', style: { marginTop: '10px' } }, save,
            h('button', { class: 'btn', onclick: () => { editing = false; draw(); } }, 'Cancel')));
        } else {
          body.append(
            h('div', { class: 'row', style: { marginBottom: '12px' } },
              h('span', { class: 'tag' }, toolLabel(doc.tool)),
              doc.script ? link(`/scripts/${doc.script.id}`, { class: 'tag link', onclick: () => close() }, icon('doc', 13), doc.script.title) : null,
              h('span', { class: 'muted small' }, `updated ${timeAgo(doc.updated_at)}`)),
            h('div', { class: 'output-toolbar' },
              h('button', { class: 'btn ghost sm', onclick: () => copyText(doc.content) }, icon('copy', 15), 'Copy'),
              exportMenu(`documents/${doc.id}`, { label: 'Download', getPrint: () => ({ title: doc.title, markdown: doc.content }) }),
              h('button', { class: 'btn ghost sm', onclick: () => { editing = true; draw(); } }, icon('pen', 15), 'Edit')),
            mdBlock(doc.content));
        }
      }
      draw();
      return body;
    }, { wide: true });
  }

  function card(doc) {
    const star = h('button', {
      class: `star${doc.favorite ? ' on' : ''}`, 'aria-label': 'Favourite',
      onclick: async (e) => {
        e.stopPropagation();
        try {
          const r = await api('PATCH', `documents/${doc.id}`, { favorite: !doc.favorite });
          doc.favorite = r.document.favorite;
          star.classList.toggle('on', doc.favorite);
        } catch (err) { toast(err.message, 'err'); }
      },
    }, '★');
    const preview = doc.content.replace(/[#*_>`[\]]/g, '').replace(/\s+/g, ' ').slice(0, 220);
    return h('article', { class: 'doc-card', tabindex: '0', onclick: () => openDoc(doc), onkeydown: (e) => { if (e.key === 'Enter') openDoc(doc); } },
      star,
      h('div', { class: 'doc-meta' }, h('span', { class: 'tag' }, toolLabel(doc.tool)), h('span', { class: 'muted small' }, timeAgo(doc.updated_at))),
      h('h3', {}, doc.title),
      h('p', { class: 'muted small clamp-3' }, preview),
      h('div', { class: 'row doc-actions' },
        h('button', { class: 'btn ghost sm', onclick: (e) => { e.stopPropagation(); copyText(doc.content); } }, icon('copy', 14)),
        h('button', {
          class: 'btn ghost sm danger',
          onclick: async (e) => {
            e.stopPropagation();
            if (!(await confirmDialog(`Delete “${doc.title}”?`))) return;
            try { await api('DELETE', `documents/${doc.id}`); state.docs = state.docs.filter((d) => d.id !== doc.id); drawList(); } catch (err) { toast(err.message, 'err'); }
          },
        }, icon('trash', 14))));
  }

  function drawList() {
    clear(listBox);
    if (!state.docs.length) {
      listBox.append(h('div', { class: 'card' }, emptyState('📚',
        state.q || state.tool || state.favorite ? 'Nothing matches' : 'Your Library is empty',
        'Anything you generate in the Toolkit, AI YouTube or a script’s Package tab can be saved here.',
        link('/tools', { class: 'btn primary' }, icon('sparkle'), 'Open the Toolkit'))));
      return;
    }
    listBox.append(h('div', { class: 'doc-grid' }, state.docs.map(card)));
    if (state.page < state.lastPage) {
      const more = h('button', { class: 'btn', onclick: () => busy(more, 'Loading…', async () => { state.page++; await load(false); }) }, 'Load more');
      listBox.append(h('div', { class: 'row', style: { justifyContent: 'center', marginTop: '18px' } }, more));
    }
  }

  // Upload a PDF / Word / text / subtitle file (ported from MagicAI's chat-with-PDF upload).
  const fileInput = h('input', { type: 'file', hidden: true, accept: '.pdf,.docx,.txt,.md,.markdown,.csv,.srt,.vtt' });
  const uploadBtn = h('button', { class: 'btn primary', onclick: () => fileInput.click() }, icon('download', 16), 'Upload document');
  fileInput.addEventListener('change', () => {
    const file = fileInput.files[0];
    fileInput.value = '';
    if (!file) return;
    busy(uploadBtn, 'Reading…', async () => {
      const fd = new FormData();
      fd.append('file', file);
      const r = await apiUpload('documents/upload', fd, { onProgress: (p) => { uploadBtn.lastChild.textContent = p < 1 ? `Uploading ${Math.round(p * 100)}%` : 'Reading…'; } });
      toast(r.message);
      await load();
    });
  });

  const search = h('input', { type: 'search', placeholder: 'Search titles and content…', class: 'search-input' });
  const searchSoon = debounce(() => load().catch((e) => toast(e.message, 'err')), 300);
  search.addEventListener('input', () => { state.q = search.value.trim(); searchSoon(); });
  toolSelect.addEventListener('change', () => { state.tool = toolSelect.value; load().catch((e) => toast(e.message, 'err')); });
  favBtn.addEventListener('click', () => { state.favorite = !state.favorite; load().catch((e) => toast(e.message, 'err')); });

  view.append(
    pageHead('Library', 'Saved titles, descriptions, captions, breakdowns and drafts, plus documents you upload. Starred items stay on top.', uploadBtn, fileInput),
    h('div', { class: 'row toolbar' }, search, toolSelect, favBtn),
    listBox);
  await load();

  // Opened from Ctrl+K search: /library?doc=12
  const docId = new URLSearchParams(location.search).get('doc');
  if (docId) {
    history.replaceState({}, '', '/library');
    api('GET', `documents/${docId}`).then((r) => openDoc(r.document)).catch((e) => toast(e.message, 'err'));
  }

  return () => searchSoon.cancel();
}

function field2(label, input) {
  return h('div', { class: 'field' }, h('label', {}, label), input);
}
