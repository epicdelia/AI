// Reusable UI pieces built on core.js: AI output panels and the YouTube analysis dialog.
import { h, api, busy, toast, mdBlock, copyText, download, slug, modal, field, clear } from './core.js';
import { icon } from './icons.js';

/**
 * A rendered AI result with Copy / Download / Save-to-Library / Edit.
 * opts: { title, tool, inputs, scriptId, onChange }
 */
export function outputPanel(content, opts = {}) {
  let text = content;
  let editing = false;
  let savedId = null;
  const body = h('div', { class: 'output-body' });

  function draw() {
    clear(body);
    if (editing) {
      const ta = h('textarea', { class: 'output-edit', rows: Math.min(28, Math.max(8, text.split('\n').length + 2)) }, text);
      ta.addEventListener('input', () => { text = ta.value; opts.onChange?.(text); });
      body.append(ta);
      setTimeout(() => ta.focus(), 0);
    } else {
      body.append(mdBlock(text));
    }
  }

  const editBtn = h('button', {
    class: 'btn ghost sm',
    onclick: () => { editing = !editing; editBtn.lastChild.textContent = editing ? 'Done' : 'Edit'; draw(); },
  }, icon('pen', 15), h('span', {}, 'Edit'));

  const saveBtn = h('button', {
    class: 'btn sm',
    onclick: () => busy(saveBtn, 'Saving…', async () => {
      const payload = { title: opts.title || firstLine(text), content: text, tool: opts.tool || null, inputs: opts.inputs || null, script_id: opts.scriptId || null };
      if (savedId) {
        await api('PATCH', `documents/${savedId}`, { title: payload.title, content: text });
        toast('Library copy updated.');
      } else {
        const res = await api('POST', 'documents', payload);
        savedId = res.document.id;
        toast(res.message);
      }
      saveBtn.lastChild.textContent = 'Saved ✓';
    }),
  }, icon('heart', 15), h('span', {}, 'Save to Library'));

  draw();
  return h('div', { class: 'output-panel' },
    h('div', { class: 'output-toolbar' },
      h('button', { class: 'btn ghost sm', onclick: () => copyText(text) }, icon('copy', 15), 'Copy'),
      h('button', { class: 'btn ghost sm', onclick: () => download(`${slug(opts.title || firstLine(text))}.md`, text) }, icon('download', 15), 'Download'),
      editBtn,
      opts.noSave ? null : saveBtn),
    body);
}

export function firstLine(text) {
  const line = String(text || '').split('\n').map((l) => l.replace(/^[#>*\-\s]+|[*\s]+$/g, '')).find((l) => l.length > 2) || 'Untitled';
  return line.slice(0, 120);
}

let actionsCache = null;
async function transcriptActions() {
  if (!actionsCache) actionsCache = (await api('GET', 'youtube/actions')).actions;
  return actionsCache;
}

/**
 * "AI YouTube" dialog: analyse a video from its link, or from a pasted transcript when
 * YouTube blocks caption fetching. opts: { url, title, action }
 */
export async function youtubeDialog(opts = {}) {
  const actions = await transcriptActions();
  return modal(opts.title ? `Analyse: ${opts.title}` : 'Analyse a YouTube video', () => {
    const url = h('input', { type: 'url', value: opts.url || '', placeholder: 'https://youtube.com/watch?v=…' });
    const transcript = h('textarea', { rows: 5, placeholder: 'Optional: paste the transcript here if fetching fails (YouTube → ⋯ → Show transcript).' });
    const action = h('select', {}, actions.map((a) => h('option', { value: a.key, selected: a.key === (opts.action || 'breakdown') }, a.label)));
    const out = h('div');
    const run = h('button', { class: 'btn primary', type: 'submit' }, icon('sparkle'), 'Analyse');
    const tHint = h('div', { class: 'hint', hidden: true });

    const form = h('form', {
      onsubmit: (e) => {
        e.preventDefault();
        busy(run, 'Reading the video… (up to a minute)', async () => {
          clear(out);
          try {
            const res = await api('POST', 'youtube/analyze', {
              action: action.value, url: url.value || null, transcript: transcript.value || null, title: opts.title || null,
            });
            if (!transcript.value) transcript.value = res.transcript;
            tHint.hidden = false;
            tHint.textContent = `Transcript loaded (${res.transcript.split(/\s+/).length} words). Change the analysis and run again without re-fetching.`;
            out.append(h('h3', { style: { marginTop: '18px' } }, res.action_label),
              outputPanel(res.output, { title: `${res.action_label}: ${res.title || url.value}`, tool: `youtube:${action.value}`, inputs: { url: url.value } }));
          } catch (err) {
            if (err.data?.needs_transcript) transcript.focus();
            throw err;
          }
        });
      },
    },
    field('Video link', url),
    h('div', { class: 'grid grid-2 tight' }, field('What do you want?', action), h('div')),
    field('Transcript', transcript),
    tHint,
    h('div', { class: 'row' }, run),
    out);
    return form;
  }, { wide: true });
}
