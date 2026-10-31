// Toolkit: one-click writing tools for creators, her own templates, and AI YouTube.
import { h, api, busy, toast, pageHead, clear, modal, confirmDialog, field, navigate, link, emptyState } from '../core.js';
import { icon } from '../icons.js';
import { outputPanel, youtubeDialog } from '../components.js';

export async function renderTools(view, key) {
  const data = await api('GET', 'tools');
  const favorites = new Set(data.favorites);
  if (key) {
    const tool = data.tools.find((t) => t.key === key);
    if (!tool) { navigate('/tools', { replace: true }); return; }
    return renderRunner(view, tool, data);
  }

  let filter = 'all';
  let search = '';
  const grid = h('div');
  const chips = h('div', { class: 'tabs' });

  async function toggleFav(t, star) {
    try {
      const r = await api('POST', 'tools/favorite', { key: t.key });
      favorites.clear();
      r.favorites.forEach((k) => favorites.add(k));
      star.classList.toggle('on', favorites.has(t.key));
      if (filter === 'favorites') draw();
    } catch (e) { toast(e.message, 'err'); }
  }

  function card(t) {
    const star = h('button', {
      class: `star${favorites.has(t.key) ? ' on' : ''}`, title: 'Favourite', 'aria-label': `Favourite ${t.name}`,
      onclick: (e) => { e.stopPropagation(); toggleFav(t, star); },
    }, '★');
    return h('article', { class: 'tool-card', tabindex: '0', onclick: () => navigate(`/tools/${t.key}`), onkeydown: (e) => { if (e.key === 'Enter') navigate(`/tools/${t.key}`); } },
      h('div', { class: 'tool-icon' }, t.icon), star,
      h('h3', {}, t.name),
      h('p', { class: 'muted small' }, t.description || ''),
      t.custom ? h('span', { class: 'tag' }, 'my template') : null);
  }

  function draw() {
    const q = search.toLowerCase();
    let tools = data.tools.filter((t) => !q || `${t.name} ${t.description}`.toLowerCase().includes(q));
    if (filter === 'favorites') tools = tools.filter((t) => favorites.has(t.key));
    else if (filter !== 'all') tools = tools.filter((t) => t.category === filter);

    clear(chips).append(
      [['all', 'All'], ['favorites', '★ Favourites'], ...Object.entries(data.categories)].map(([k, label]) => h('button', {
        class: `tab${filter === k ? ' active' : ''}`, onclick: () => { filter = k; draw(); },
      }, label)));

    clear(grid);
    if (!tools.length) {
      grid.append(h('div', { class: 'card' }, emptyState(filter === 'favorites' ? '⭐' : '🔍',
        filter === 'favorites' ? 'No favourites yet' : 'Nothing matches',
        filter === 'favorites' ? 'Tap the star on any tool to pin it here.' : filter === 'custom' ? 'Create a template for anything you write often.' : 'Try another word.')));
      return;
    }
    const groups = filter === 'all' && !q
      ? Object.entries(data.categories).map(([k, label]) => [label, tools.filter((t) => t.category === k)]).filter(([, list]) => list.length)
      : [[null, tools]];
    for (const [label, list] of groups) {
      if (label) grid.append(h('h2', { class: 'group-title' }, label));
      grid.append(h('div', { class: 'tool-grid' }, list.map(card)));
    }
  }

  const searchInput = h('input', { type: 'search', placeholder: 'Search tools…', class: 'search-input' });
  searchInput.addEventListener('input', () => { search = searchInput.value; draw(); });

  view.append(
    pageHead('Toolkit', 'Titles, thumbnails, descriptions, captions, threads, sponsor emails and more, written in your voice.',
      h('button', { class: 'btn', onclick: () => customToolDialog(null, () => navigate('/tools')) }, icon('plus'), 'New template')),
    h('div', { class: 'card yt-feature' },
      h('div', { class: 'yt-feature-icon' }, '▶'),
      h('div', { style: { flex: 1 } },
        h('h2', {}, 'AI YouTube'),
        h('p', { class: 'muted' }, 'Paste any video link: get why it worked, a summary, quotable lines, chapters or a blog post from its transcript.')),
      h('button', { class: 'btn primary', onclick: () => youtubeDialog() }, icon('play', 16), 'Analyse a video')),
    h('div', { class: 'row-between wrap toolbar' }, chips, searchInput),
    grid);
  draw();
}

function renderRunner(view, tool, data) {
  const values = {};
  const out = h('div');
  const tone = h('input', { type: 'text', value: data.defaults.tone || '', placeholder: 'your brand voice' });
  const language = h('input', { type: 'text', value: data.defaults.language || 'English' });
  const results = h('select', {}, [1, 2, 3, 4, 5].map((n) => h('option', { value: n, selected: n === 3 }, n)));
  const creativity = h('input', { type: 'range', min: 0, max: 1, step: 0.1, value: 0.7 });
  const creativityLabel = h('span', { class: 'hint' }, ' 0.7');
  creativity.addEventListener('input', () => { creativityLabel.textContent = ` ${creativity.value}`; });

  const fields = (tool.fields || []).map((f) => {
    let input;
    if (f.type === 'textarea') input = h('textarea', { rows: f.rows || 4, placeholder: f.placeholder || '', required: !!f.required });
    else if (f.type === 'select') input = h('select', {}, (f.options || []).map((o) => h('option', { value: o }, o)));
    else input = h('input', { type: f.type === 'url' ? 'url' : 'text', placeholder: f.placeholder || '', required: !!f.required });
    values[f.name] = input;
    return field(f.label + (f.required ? ' *' : ''), input);
  });

  const runBtn = h('button', { type: 'submit', class: 'btn primary' }, icon('sparkle'), 'Generate');
  const form = h('form', {
    onsubmit: (e) => {
      e.preventDefault();
      busy(runBtn, 'Writing…', async () => {
        const inputs = Object.fromEntries(Object.entries(values).map(([k, el]) => [k, el.value]));
        const r = await api('POST', 'tools/run', {
          tool: tool.key, inputs, tone: tone.value || null, language: language.value || null,
          results: tool.results ? Number(results.value) : 1, creativity: Number(creativity.value),
        });
        const titleSource = inputs.title || inputs.topic || inputs.subject || inputs.brand || inputs.keywords || '';
        clear(out).append(outputPanel(r.output, { title: `${tool.name}: ${titleSource.slice(0, 80)}`.replace(/: $/, ''), tool: tool.key, inputs }));
        out.scrollIntoView({ behavior: 'smooth', block: 'start' });
      });
    },
  },
  fields,
  h('details', { class: 'options' }, h('summary', {}, 'Options'),
    h('div', { class: 'grid grid-2 tight', style: { marginTop: '10px' } },
      field('Tone', tone), field('Language', language),
      tool.results ? field('Options to generate', results) : null,
      h('div', { class: 'field' }, h('label', {}, 'Creativity', creativityLabel), creativity))),
  h('div', { class: 'row' }, runBtn));

  view.append(
    h('div', { class: 'editor-head' },
      link('/tools', { class: 'btn ghost sm' }, icon('back', 15), 'Toolkit'),
      h('div', { class: 'runner-title' }, h('span', { class: 'tool-icon sm' }, tool.icon), h('h1', {}, tool.name)),
      tool.custom ? h('div', { class: 'row' },
        h('button', { class: 'btn ghost sm', onclick: () => customToolDialog(tool, () => navigate(`/tools/${tool.key}`)) }, icon('pen', 15), 'Edit'),
        h('button', {
          class: 'btn ghost sm danger',
          onclick: async () => {
            if (!(await confirmDialog(`Delete the “${tool.name}” template?`))) return;
            try { await api('DELETE', `tools/custom/${tool.id}`); toast('Template deleted.'); navigate('/tools'); } catch (e) { toast(e.message, 'err'); }
          },
        }, icon('trash', 15))) : h('span')),
    tool.description ? h('p', { class: 'muted', style: { marginTop: '-6px' } }, tool.description) : null,
    h('div', { class: 'grid grid-runner' }, h('div', { class: 'card' }, form), h('div', {}, out)));

  setTimeout(() => form.querySelector('input, textarea')?.focus(), 0);
}

/** Create or edit one of her own templates. */
function customToolDialog(tool, onSaved) {
  return modal(tool ? 'Edit template' : 'New template', (close) => {
    const rows = h('div', { class: 'field-rows' });
    function addRow(f = { name: '', label: '', type: 'textarea', required: false }) {
      const name = h('input', { type: 'text', value: f.name, placeholder: 'topic', pattern: '[a-z0-9_]+', title: 'lowercase letters, numbers, underscores' });
      const label = h('input', { type: 'text', value: f.label, placeholder: 'What the video is about' });
      const type = h('select', {}, ['textarea', 'text', 'url'].map((t) => h('option', { value: t, selected: t === f.type }, t)));
      const req = h('input', { type: 'checkbox', checked: !!f.required });
      const row = h('div', { class: 'field-row' }, name, label, type, h('label', { class: 'inline-check' }, req, 'required'),
        h('button', { type: 'button', class: 'btn ghost sm', 'aria-label': 'Remove field', onclick: () => row.remove() }, icon('x', 14)));
      row.read = () => ({ name: name.value.trim(), label: label.value.trim(), type: type.value, required: req.checked });
      rows.append(row);
    }
    (tool?.fields?.length ? tool.fields : [{ name: 'topic', label: 'Topic', type: 'textarea', required: true }]).forEach(addRow);

    const name = h('input', { type: 'text', required: true, maxlength: 100, value: tool?.name || '' });
    const iconIn = h('input', { type: 'text', maxlength: 4, value: tool?.icon || '✨', class: 'icon-input' });
    const desc = h('input', { type: 'text', maxlength: 300, value: tool?.description || '' });
    const prompt = h('textarea', { rows: 6, required: true, placeholder: 'Write 5 pinned-comment ideas for my video about {topic}. Each under 200 characters, end with a question.' }, tool?.prompt || '');

    const form = h('form', {
      onsubmit: async (e) => {
        e.preventDefault();
        const payload = { name: name.value, icon: iconIn.value, description: desc.value, prompt: prompt.value, fields: [...rows.children].map((r) => r.read()).filter((f) => f.name) };
        const missing = payload.fields.filter((f) => !payload.prompt.includes(`{${f.name}}`)).map((f) => `{${f.name}}`);
        if (missing.length) toast(`Heads up: the prompt doesn't use ${missing.join(', ')}.`, 'warn');
        await busy(form.querySelector('[type=submit]'), 'Saving…', async () => {
          const r = tool ? await api('PUT', `tools/custom/${tool.id}`, payload) : await api('POST', 'tools/custom', payload);
          toast(r.message);
          close(true);
          if (tool) onSaved(); else navigate(`/tools/custom_${r.tool.id}`);
        });
      },
    },
    h('div', { class: 'grid grid-name-icon tight' }, field('Name', name), field('Icon', iconIn)),
    field('Description', desc, 'optional'),
    h('div', { class: 'field' }, h('label', {}, 'Fields ', h('span', { class: 'hint' }, 'name · label · type')), rows,
      h('button', { type: 'button', class: 'btn ghost sm', onclick: () => addRow() }, icon('plus', 14), 'Add field')),
    field('Prompt', prompt, 'use {field_name} to insert a field; your brand voice and writing rules are added automatically'),
    h('div', { class: 'row', style: { justifyContent: 'flex-end' } },
      h('button', { type: 'button', class: 'btn', onclick: () => close(false) }, 'Cancel'),
      h('button', { type: 'submit', class: 'btn primary' }, 'Save template')));
    return form;
  }, { wide: true });
}
