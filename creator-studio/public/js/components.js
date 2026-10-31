// Reusable UI pieces built on core.js: AI output panels and the YouTube analysis dialog.
import { h, api, apiStream, apiUpload, liveMarkdown, busy, toast, mdBlock, copyText, download, slug, modal, field, clear, link } from './core.js';

let transcribeInfo = null;

/**
 * "Upload recording" button: pick an audio/video file, upload with progress, transcribe.
 * onText(transcript, response) runs on success. opts: { scriptId, timestamps, label }
 */
export function transcribeButton(onText, opts = {}) {
  const fileInput = h('input', { type: 'file', accept: 'audio/*,video/*', hidden: true });
  const status = h('span', { class: 'muted small' });
  const btn = h('button', { type: 'button', class: 'btn sm', onclick: () => fileInput.click() }, icon('mic', 15), opts.label || 'Upload recording');

  fileInput.addEventListener('change', async () => {
    const file = fileInput.files[0];
    fileInput.value = '';
    if (!file) return;
    try {
      transcribeInfo ??= await api('GET', 'transcribe');
      if (!transcribeInfo.engine) throw new Error('Add an AssemblyAI key (or use OpenAI as your AI engine) in Settings to transcribe recordings.');
      const mb = file.size / 1048576;
      if (mb > transcribeInfo.upload_limit_mb) throw new Error(`That file is ${mb.toFixed(0)} MB; the server accepts up to ${transcribeInfo.upload_limit_mb} MB. Upload the audio track only, or raise upload_max_filesize / post_max_size in php.ini.`);
      if (transcribeInfo.engine === 'openai' && mb > transcribeInfo.whisper_max_mb) throw new Error(`Whisper accepts up to ${transcribeInfo.whisper_max_mb} MB. Export the audio (m4a/mp3) or add an AssemblyAI key for full videos.`);

      btn.disabled = true;
      const fd = new FormData();
      fd.append('file', file);
      if (opts.scriptId) fd.append('script_id', opts.scriptId);
      if (opts.timestamps) fd.append('timestamps', '1');
      const res = await apiUpload('transcribe', fd, {
        onProgress: (p) => { status.textContent = p < 1 ? `Uploading… ${Math.round(p * 100)}%` : 'Transcribing… (about a quarter of the recording’s length)'; },
      });
      status.textContent = '';
      toast(`${res.message} ${res.words.toLocaleString()} words.`);
      onText(res.transcript, res);
    } catch (err) {
      status.textContent = '';
      toast(err.message, 'err');
    } finally {
      btn.disabled = false;
    }
  });

  return h('span', { class: 'row transcribe-btn' }, btn, status, fileInput);
}
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

const SOURCE_LABELS = { script: 'Script', transcript: 'What you said', document: 'Library', note: 'Note', idea: 'Idea' };

/** Results list for a Memory search ("have I made this before?"). */
export async function memoryList(query, { limit = 6, types = '' } = {}) {
  const res = await api('GET', `memory/search?${new URLSearchParams({ q: query, limit, types })}`);
  if (!res.results.length) return h('p', { class: 'muted' }, 'Nothing similar in your past work.');
  return h('ul', { class: 'memory-list' }, res.results.map((r) => h('li', {},
    h('div', { class: 'row-between' },
      h('div', { class: 'row' }, h('span', { class: `tag src-${r.source_type}` }, SOURCE_LABELS[r.source_type] || r.source_type),
        r.link ? link(r.link, { class: 'memory-title' }, r.title) : h('strong', {}, r.title)),
      h('span', { class: 'muted small', title: r.semantic ? 'meaning match' : 'keyword match' }, r.semantic ? `${Math.round(r.score * 100)}% similar` : 'keyword match')),
    h('p', { class: 'muted small' }, r.excerpt))));
}

export function memoryDialog(query, title = 'Your past work') {
  return modal(title, () => {
    const box = h('div', {}, h('p', { class: 'muted' }, h('span', { class: 'spinner' }), ' Searching your scripts, transcripts, Library and notes…'));
    memoryList(query, { limit: 8 }).then((el) => box.replaceChildren(el)).catch((e) => box.replaceChildren(h('p', { class: 'error-text' }, e.message)));
    return box;
  }, { wide: true });
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
        busy(run, transcript.value ? 'Analysing…' : 'Reading the video…', async () => {
          const live = liveMarkdown(transcript.value ? 'Analysing…' : 'Fetching the transcript…');
          clear(out).append(h('div', { class: 'output-panel', style: { marginTop: '18px' } }, h('div', { class: 'output-body' }, live.el)));
          try {
            const res = await apiStream('youtube/analyze', {
              action: action.value, url: url.value || null, transcript: transcript.value || null, title: opts.title || null,
            }, { onDelta: live.append });
            if (!transcript.value) transcript.value = res.transcript;
            tHint.hidden = false;
            tHint.textContent = `Transcript loaded (${res.transcript.split(/\s+/).length} words). Change the analysis and run again without re-fetching.`;
            clear(out).append(h('h3', { style: { marginTop: '18px' } }, res.action_label),
              outputPanel(res.output, { title: `${res.action_label}: ${res.title || url.value}`, tool: `youtube:${action.value}`, inputs: { url: url.value } }));
          } catch (err) {
            if (!live.text) clear(out);
            if (err.data?.needs_transcript) transcript.focus();
            throw err;
          }
        });
      },
    },
    field('Video link', url),
    h('div', { class: 'grid grid-2 tight' }, field('What do you want?', action), h('div')),
    field('Transcript', transcript),
    h('div', { class: 'row', style: { margin: '-6px 0 10px' } },
      h('span', { class: 'muted small' }, 'Or use a recording:'),
      transcribeButton((text) => { transcript.value = text; tHint.hidden = false; tHint.textContent = 'Transcript ready. Pick an analysis and press Analyse.'; })),
    tHint,
    h('div', { class: 'row' }, run),
    out);
    return form;
  }, { wide: true });
}
