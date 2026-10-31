// Scripts: the list, and the editor with live AI-tell linting, autosave, review,
// packaging (titles, thumbnails, description...), transcript, Substack and edit brief.
import {
  h, api, busy, toast, pageHead, emptyState, clear, pill, modal, confirmDialog, field, formData,
  navigate, link, timeAgo, debounce, wordCount, mdBlock, copyText, download, slug, isoDay,
  apiStream, liveMarkdown,
} from '../core.js';
import { icon } from '../icons.js';
import { outputPanel, transcribeButton, memoryList } from '../components.js';
import { scriptWizard } from '../wizard.js';
import { readAloudCard, thumbnailStudio, shortsPanel } from '../media.js';

const STATUSES = ['draft', 'reviewed', 'filmed', 'published'];

// ------------------------------------------------------------------
// List
// ------------------------------------------------------------------

export async function renderScripts(view) {
  const res = await api('GET', 'scripts');
  const scripts = res.scripts.data;

  const newBtn = h('button', {
    class: 'btn primary',
    onclick: () => modal('New script', (close) => {
      const form = h('form', {
        onsubmit: async (e) => {
          e.preventDefault();
          await busy(form.querySelector('[type=submit]'), 'Creating…', async () => {
            const r = await api('POST', 'scripts', { title: formData(form).title });
            close(true);
            navigate(`/scripts/${r.script.id}`);
          });
        },
      },
      field('Working title', h('input', { type: 'text', name: 'title', required: true, maxlength: 200 })),
      h('p', { class: 'muted' }, 'Tip: to have the AI draft a full script, pick an idea on the Ideas page and choose “Draft script”.'),
      h('div', { class: 'row', style: { justifyContent: 'flex-end' } },
        h('button', { type: 'button', class: 'btn', onclick: () => close(false) }, 'Cancel'),
        h('button', { type: 'submit', class: 'btn primary' }, 'Create blank script')));
      return form;
    }),
  }, icon('plus'), 'New script');

  view.append(pageHead('Scripts', 'Draft, lint, review and package every video. The linter catches the AI tells before anyone else does.',
    h('button', { class: 'btn primary', onclick: () => scriptWizard() }, icon('target'), 'Plan with wizard'),
    newBtn, link('/ideas', { class: 'btn' }, icon('bulb'), 'From an idea')));

  if (!scripts.length) {
    view.append(h('div', { class: 'card' }, emptyState('📝', 'No scripts yet', 'Pick an idea and let the AI draft it, or start from a blank page.', newBtn.cloneNode(true))));
    view.querySelector('.empty .btn')?.addEventListener('click', () => newBtn.click());
    return;
  }

  const byStatus = Object.fromEntries(STATUSES.map((s) => [s, scripts.filter((x) => x.status === s).length]));
  view.append(h('div', { class: 'pipeline' }, STATUSES.map((s) => h('div', { class: `pipeline-step ${s}` },
    h('div', { class: 'num' }, byStatus[s]), h('div', { class: 'lbl' }, s)))));

  view.append(h('div', { class: 'card' }, h('div', { class: 'table-wrap' }, h('table', {},
    h('thead', {}, h('tr', {}, h('th', {}, 'Title'), h('th', {}, 'Status'), h('th', { class: 'num' }, 'Words'), h('th', { class: 'num hide-sm' }, 'Est. length'), h('th', { class: 'hide-sm' }, 'Updated'))),
    h('tbody', {}, scripts.map((sc) => {
      const words = wordCount(sc.body);
      return h('tr', { class: 'clickable', onclick: () => navigate(`/scripts/${sc.id}`) },
        h('td', {}, link(`/scripts/${sc.id}`, {}, h('strong', {}, sc.title)), sc.idea ? h('div', { class: 'muted' }, `from idea: ${sc.idea.title}`) : null),
        h('td', {}, pill(sc.status)),
        h('td', { class: 'num' }, words.toLocaleString()),
        h('td', { class: 'num hide-sm' }, words ? `${(words / 150).toFixed(1)} min` : '–'),
        h('td', { class: 'hide-sm muted' }, timeAgo(sc.updated_at)));
    }))))));
}

// ------------------------------------------------------------------
// Editor
// ------------------------------------------------------------------

const PACKAGE_TOOLS = [
  { key: 'yt_titles', label: 'Titles', input: (s) => ({ topic: `${s.title}\n\n${s.body.slice(0, 4000)}` }) },
  { key: 'yt_thumbnail', label: 'Thumbnails', input: (s) => ({ topic: s.body.slice(0, 4000), title: s.title }) },
  { key: 'yt_hooks', label: 'Cold opens', input: (s) => ({ title: s.title, topic: s.body.slice(0, 4000) }) },
  { key: 'yt_description', label: 'Description', input: (s) => ({ title: s.title, topic: s.body.slice(0, 6000), links: '' }) },
  { key: 'yt_tags', label: 'Tags', input: (s) => ({ title: s.title, topic: s.body.slice(0, 3000) }) },
  { key: 'yt_chapters', label: 'Chapters', input: (s) => ({ script: s.transcript || s.body }) },
  { key: 'shorts_script', label: 'Shorts', input: (s) => ({ topic: s.title, source: (s.transcript || s.body).slice(0, 6000) }) },
  { key: 'x_thread', label: 'Thread', input: (s) => ({ topic: `${s.title}\n\n${s.body.slice(0, 4000)}`, video_url: s.youtube_url || '' }) },
  { key: 'newsletter', label: 'Newsletter', input: (s) => ({ title: s.title, topic: s.body.slice(0, 4000), video_url: s.youtube_url || '' }) },
];

export async function renderScript(view, id) {
  const data = await api('GET', `scripts/${id}`);
  const script = data.script;
  script.body = script.body || '';

  // ---- autosave ----
  const pending = {};
  const saveState = h('span', { class: 'save-state muted' }, 'Saved');
  async function save() {
    const fields = { ...pending };
    if (!Object.keys(fields).length) return;
    for (const k of Object.keys(fields)) delete pending[k];
    saveState.textContent = 'Saving…';
    try {
      const res = await api('PATCH', `scripts/${script.id}`, fields);
      Object.assign(script, res.script, { body: res.script.body || '' });
      saveState.textContent = `Saved ${new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}`;
    } catch (err) {
      Object.assign(pending, fields);
      saveState.textContent = 'Not saved';
      toast(err.message, 'err');
    }
  }
  const saveSoon = debounce(save, 1200);
  function change(fieldName, value) {
    script[fieldName] = value;
    pending[fieldName] = value;
    saveState.textContent = 'Unsaved changes…';
    saveSoon();
  }

  // ---- header ----
  const titleInput = h('input', { type: 'text', class: 'title-input', value: script.title, maxlength: 200, 'aria-label': 'Script title' });
  titleInput.addEventListener('input', () => { if (titleInput.value.trim()) change('title', titleInput.value.trim()); });
  const statusSelect = h('select', { class: 'status-select', 'aria-label': 'Status' },
    STATUSES.map((s) => h('option', { value: s, selected: s === script.status }, s)));
  statusSelect.addEventListener('change', () => { change('status', statusSelect.value); saveSoon.flush(); });

  // ---- write tab: textarea + linter ----
  const textarea = h('textarea', { class: 'script-editor', spellcheck: 'true', placeholder: '## Cold open\n[VISUAL: ...]\nStart with the payoff...' }, script.body);
  const stats = h('div', { class: 'editor-stats' });
  const lintBox = h('div', { class: 'lint-box' });
  const reviewBtn = h('button', { class: 'btn primary block', onclick: () => runReview() }, icon('sparkle'), 'Run AI review');

  function drawStats() {
    const words = wordCount(textarea.value);
    clear(stats).append(
      h('span', {}, h('strong', {}, words.toLocaleString()), ' words'),
      h('span', {}, h('strong', {}, (words / 150).toFixed(1)), ' min spoken'),
      h('span', { class: words && (words < 1200 || words > 2250) ? 'warn-text' : '' }, words ? (words < 1200 ? 'under 8 min' : words > 2250 ? 'over 15 min' : '8–15 min ✓') : ''));
  }

  function jumpToLine(line) {
    if (!line) return;
    const lines = textarea.value.split('\n');
    const start = lines.slice(0, line - 1).reduce((n, l) => n + l.length + 1, 0);
    textarea.focus();
    textarea.setSelectionRange(start, start + (lines[line - 1] || '').length);
    const lh = parseFloat(getComputedStyle(textarea).lineHeight) || 22;
    textarea.scrollTop = Math.max(0, (line - 4) * lh);
  }

  function drawLint(findings, hasHard) {
    clear(lintBox);
    reviewBtn.disabled = hasHard;
    reviewBtn.title = hasHard ? 'Fix the HARD findings first' : '';
    if (!findings.length) {
      lintBox.append(h('div', { class: 'lint-clean' }, icon('check', 16), 'No AI tells found.'));
      return;
    }
    const hard = findings.filter((f) => f.severity === 'HARD').length;
    lintBox.append(h('div', { class: 'lint-summary' },
      hard ? pill('HARD', `${hard} must fix`) : null, pill('WARN', `${findings.length - hard} warnings`)));
    lintBox.append(h('ul', { class: 'lint-list' }, findings.slice(0, 80).map((f) => h('li', {
      class: `lint-item ${f.severity}`, onclick: () => jumpToLine(f.line), title: f.line ? `Go to line ${f.line}` : '',
    },
    h('div', { class: 'lint-rule' }, h('span', { class: `sev ${f.severity}` }, f.severity), f.rule, f.line ? h('span', { class: 'muted' }, ` · line ${f.line}`) : null),
    h('code', {}, f.match)))));
  }

  const lintSoon = debounce(async () => {
    try {
      const r = await api('POST', 'scripts/lint', { text: textarea.value });
      drawLint(r.findings, r.has_hard);
    } catch { /* linting is best-effort while typing */ }
  }, 500);

  textarea.addEventListener('input', () => { change('body', textarea.value); drawStats(); lintSoon(); });
  textarea.addEventListener('keydown', (e) => {
    if (e.key === 'Tab') {
      e.preventDefault();
      textarea.setRangeText('  ', textarea.selectionStart, textarea.selectionEnd, 'end');
    }
  });

  drawStats();
  drawLint(data.findings, data.has_hard);

  // ---- past work (Memory) ----
  const pastBox = h('div', {}, h('p', { class: 'muted small' }, 'Looking…'));
  const pastWorkCard = h('div', { class: 'card' },
    h('h3', {}, 'From your past work'),
    h('p', { class: 'muted small' }, 'Related scripts, transcripts and notes, so you don’t repeat yourself.'),
    pastBox);
  memoryList(`${script.title} ${script.idea?.premise || ''}`, { limit: 4, types: 'script,transcript,note,document' })
    .then((el) => {
      // Hide the script finding itself.
      el.querySelectorAll('li').forEach((li) => { if (li.querySelector(`a[href="/scripts/${script.id}"]`)) li.remove(); });
      pastBox.replaceChildren(el.tagName === 'P' || el.querySelector('li') ? el : h('p', { class: 'muted small' }, 'Nothing similar yet.'));
    })
    .catch(() => pastBox.replaceChildren(h('p', { class: 'muted small' }, 'Memory search unavailable.')));

  const writePane = h('div', { class: 'editor-layout' },
    h('div', { class: 'editor-main' }, textarea, stats),
    h('aside', { class: 'editor-side' },
      h('div', { class: 'card' },
        h('h3', {}, 'AI-tell linter'),
        h('p', { class: 'muted small' }, 'HARD findings block the AI review. Click one to jump to it.'),
        lintBox),
      h('div', { class: 'card' },
        h('h3', {}, 'Review panel'),
        h('p', { class: 'muted small' }, 'Voice, retention, facts and the top 3 hostile comments. Line edits, never a rewrite.'),
        reviewBtn),
      readAloudCard(script, () => textarea.value.slice(textarea.selectionStart, textarea.selectionEnd).trim()),
      pastWorkCard));

  // ---- other tabs ----
  const reviewPane = h('div');
  function drawReview() {
    clear(reviewPane).append(h('div', { class: 'card' },
      h('div', { class: 'card-header' }, h('h2', {}, 'AI review'),
        h('div', { class: 'row' },
          script.review ? h('button', { class: 'btn ghost sm', onclick: () => copyText(script.review) }, icon('copy', 15), 'Copy') : null,
          h('button', { class: 'btn primary sm', disabled: reviewBtn.disabled, onclick: (e) => runReview(e.currentTarget) }, icon('refresh', 15), script.review ? 'Run again' : 'Run review'))),
      mdBlock(script.review, 'No review yet. Clear the HARD linter findings, then run the review.')));
  }

  async function runReview(btn = reviewBtn) {
    await saveSoon.flush();
    await busy(btn, 'Reviewing…', async () => {
      const live = liveMarkdown('Reading your script…');
      let started = false;
      try {
        const r = await apiStream(`scripts/${script.id}/review`, {}, {
          onDelta: (t) => {
            if (!started) {
              started = true;
              showTab('review');
              clear(reviewPane).append(h('div', { class: 'card' }, h('div', { class: 'card-header' }, h('h2', {}, 'AI review'), h('span', { class: 'muted' }, 'writing…')), live.el));
            }
            live.append(t);
          },
        });
        script.review = r.review;
        script.status = r.script.status;
        statusSelect.value = script.status;
        toast('Review done: voice, retention, facts and hostile comments.');
        drawReview();
        showTab('review');
      } catch (err) {
        if (err.data?.findings) drawLint(err.data.findings, true);
        if (started) drawReview();
        throw err;
      }
    });
  }

  const previewPane = h('div', { class: 'card' });

  // ---- facts (live web fact-check) ----
  const factsPane = h('div');
  function drawFacts() {
    const runBtn = h('button', { class: 'btn primary sm', onclick: (e) => runFactCheck(e.currentTarget) }, icon('search', 15), script.fact_check ? 'Check again' : 'Fact-check with the web');
    clear(factsPane).append(h('div', { class: 'card' },
      h('div', { class: 'card-header' },
        h('h2', {}, 'Fact-check'),
        h('div', { class: 'row' },
          script.fact_checked_at ? h('span', { class: 'muted small' }, `checked ${timeAgo(script.fact_checked_at)}`) : null,
          script.fact_check ? h('button', { class: 'btn ghost sm', onclick: () => copyText(script.fact_check) }, icon('copy', 15), 'Copy') : null,
          runBtn)),
      h('p', { class: 'muted small' }, 'Finds the factual claims in your script, searches the web for each one, and marks it VERIFIED, WRONG, UNSOURCED or UNCLEAR with links. Fix anything flagged before you film.'),
      h('div', { class: 'facts-body' }, mdBlock(script.fact_check, 'Not checked yet.'))));
  }
  async function runFactCheck(btn) {
    await saveSoon.flush();
    await busy(btn, 'Checking…', async () => {
      const live = liveMarkdown('Finding the claims in your script…');
      clear(factsPane.querySelector('.facts-body')).append(live.el);
      try {
        const r = await apiStream(`scripts/${script.id}/fact-check`, {}, {
          onDelta: live.append,
          onStatus: (st) => {
            if (st.step === 'searching') live.status(`Searching claim ${st.done + 1} of ${st.total}: “${st.claim.slice(0, 90)}”`);
            else if (st.step === 'judging') live.status(`Weighing the evidence for ${st.total} claim(s)…`);
          },
        });
        Object.assign(script, { fact_check: r.fact_check, fact_checked_at: r.script.fact_checked_at });
        toast(`Checked ${r.claims} claim(s) against ${r.sources} source(s).`);
      } finally {
        drawFacts();
      }
    });
  }

  const packagePane = h('div');
  const thumbStudio = thumbnailStudio(script); // built once so the form keeps its text between tab switches
  const shorts = shortsPanel(script);
  function drawPackage() {
    const outBox = h('div');
    clear(packagePane).append(h('div', { class: 'card' },
      h('h2', {}, 'Package this video'),
      h('p', { class: 'muted' }, 'Everything around the script, written from it. Outputs can be saved to your Library, linked to this script.'),
      h('div', { class: 'tool-chips' }, PACKAGE_TOOLS.map((t) => {
        const b = h('button', {
          class: 'btn sm',
          onclick: () => busy(b, `${t.label}…`, async () => {
            await saveSoon.flush();
            if (!script.body.trim() && t.key !== 'yt_chapters') throw new Error('Write some of the script first.');
            const live = liveMarkdown();
            clear(outBox).append(h('h3', { style: { marginTop: '16px' } }, t.label), h('div', { class: 'output-panel' }, h('div', { class: 'output-body' }, live.el)));
            try {
              const r = await apiStream('tools/run', { tool: t.key, inputs: t.input(script), results: 3 }, { onDelta: live.append });
              clear(outBox).append(h('h3', { style: { marginTop: '16px' } }, t.label),
                outputPanel(r.output, { title: `${t.label}: ${script.title}`, tool: t.key, scriptId: script.id }));
            } catch (err) {
              if (!live.text) clear(outBox);
              throw err;
            }
          }),
        }, t.label);
        return b;
      })),
      outBox),
    thumbStudio);
  }

  function docTab(fieldName, { title, generate, empty, help }) {
    const pane = h('div');
    let editing = false;
    function draw() {
      const value = script[fieldName] || '';
      const genBtn = generate ? h('button', {
        class: 'btn primary sm',
        onclick: () => busy(genBtn, 'Writing…', async () => {
          await saveSoon.flush();
          const live = liveMarkdown();
          clear(pane.querySelector('.doc-body')).append(live.el);
          try {
            const r = await apiStream(generate.path, {}, { onDelta: live.append });
            script[fieldName] = r[fieldName];
            toast(`${title} ready.`);
          } finally {
            editing = false;
            draw();
          }
        }),
      }, icon('sparkle', 15), value ? 'Regenerate' : 'Generate') : null;

      const body = editing
        ? (() => {
            const ta = h('textarea', { class: 'doc-editor', rows: 22 }, value);
            ta.addEventListener('input', () => change(fieldName, ta.value));
            setTimeout(() => ta.focus(), 0);
            return ta;
          })()
        : mdBlock(value, empty);

      clear(pane).append(h('div', { class: 'card' },
        h('div', { class: 'card-header' },
          h('h2', {}, title),
          h('div', { class: 'row' },
            value ? h('button', { class: 'btn ghost sm', onclick: () => copyText(script[fieldName] || '') }, icon('copy', 15), 'Copy') : null,
            value ? h('button', { class: 'btn ghost sm', onclick: () => download(`${slug(script.title)}-${fieldName}.md`, script[fieldName] || '') }, icon('download', 15), '.md') : null,
            h('button', { class: 'btn ghost sm', onclick: () => { editing = !editing; draw(); } }, icon('pen', 15), editing ? 'Done' : 'Edit'),
            genBtn)),
        help ? h('p', { class: 'muted small' }, help) : null,
        h('div', { class: 'doc-body' }, body)));
    }
    draw();
    return pane;
  }

  const transcriptPane = h('div');
  function drawTranscript() {
    const ta = h('textarea', { class: 'doc-editor', rows: 18, placeholder: 'Paste what you actually said on camera. The Substack post prefers this over the script.' }, script.transcript || '');
    ta.addEventListener('input', () => change('transcript', ta.value));
    const urlIn = h('input', { type: 'url', value: script.youtube_url || '', placeholder: 'https://youtube.com/watch?v=… (your published video)' });
    const fetchBtn = h('button', {
      class: 'btn sm',
      onclick: () => busy(fetchBtn, 'Fetching captions…', async () => {
        if (!urlIn.value) throw new Error('Add the video link first.');
        const r = await api('POST', 'youtube/transcript', { url: urlIn.value });
        ta.value = r.transcript;
        change('transcript', r.transcript);
        if (!script.youtube_url) change('youtube_url', urlIn.value);
        toast(`Transcript loaded: ${r.words.toLocaleString()} words.`);
      }),
    }, icon('download', 15), 'Fetch from YouTube');
    clear(transcriptPane).append(h('div', { class: 'card' },
      h('h2', {}, 'Recording transcript'),
      h('div', { class: 'row', style: { marginBottom: '12px' } }, h('div', { style: { flex: '1', minWidth: '240px' } }, urlIn), fetchBtn),
      h('div', { class: 'row', style: { marginBottom: '12px' } },
        transcribeButton((text, res) => {
          ta.value = text;
          script.transcript = text;
          if (res.script) Object.assign(script, { transcript: res.script.transcript });
        }, { scriptId: script.id, timestamps: true, label: 'Upload your recording' }),
        h('span', { class: 'muted small' }, 'Exact words from your own audio or video file, with timestamps. Saved to this script.')),
      h('p', { class: 'muted small' }, 'Captions are fetched from the public video. If YouTube blocks it, copy the transcript from YouTube (⋯ → Show transcript) and paste it here.'),
      ta));
  }

  const publishPane = h('div');
  function drawPublish() {
    const form = h('form', {
      onsubmit: async (e) => {
        e.preventDefault();
        const v = formData(form);
        await busy(form.querySelector('[type=submit]'), 'Saving…', async () => {
          await saveSoon.flush();
          const r = await api('PATCH', `scripts/${script.id}`, {
            youtube_url: v.youtube_url || null, published_on: v.published_on || null,
            status: v.youtube_url && script.status !== 'published' ? 'published' : script.status,
          });
          Object.assign(script, r.script);
          statusSelect.value = script.status;
          toast('Publishing details saved.');
        });
      },
    },
    h('div', { class: 'grid grid-2 tight' },
      field('YouTube link', h('input', { type: 'url', name: 'youtube_url', value: script.youtube_url || '' })),
      field('Published on', h('input', { type: 'date', name: 'published_on', value: isoDay(script.published_on) }))),
    h('p', { class: 'muted small' }, 'Adding the link marks the script published. Link it to a week on the Goals page so it counts on the scoreboard (check-in also finds it automatically).'),
    h('div', { class: 'row' }, h('button', { type: 'submit', class: 'btn primary' }, 'Save')));

    clear(publishPane).append(
      h('div', { class: 'card' }, h('h2', {}, 'Publishing'), form),
      shorts,
      h('div', { class: 'card danger-zone' },
        h('h3', {}, 'Delete script'),
        h('p', { class: 'muted' }, 'This removes the script, its review, Substack post and edit brief.'),
        h('button', {
          class: 'btn danger',
          onclick: async () => {
            if (!(await confirmDialog(`Delete “${script.title}” permanently?`))) return;
            saveSoon.cancel();
            try { await api('DELETE', `scripts/${script.id}`); toast('Script deleted.'); navigate('/scripts'); } catch (err) { toast(err.message, 'err'); }
          },
        }, icon('trash', 15), 'Delete')));
  }

  // ---- tabs ----
  const tabs = [
    { key: 'write', label: 'Write', pane: writePane },
    { key: 'preview', label: 'Preview', pane: previewPane, onShow: () => clear(previewPane).append(mdBlock(script.body, 'Nothing written yet.')) },
    { key: 'review', label: 'Review', pane: reviewPane, onShow: drawReview },
    { key: 'facts', label: 'Facts', pane: factsPane, onShow: drawFacts },
    { key: 'package', label: 'Package', pane: packagePane, onShow: drawPackage },
    { key: 'transcript', label: 'Transcript', pane: transcriptPane, onShow: drawTranscript },
    { key: 'substack', label: 'Substack', pane: docTab('substack', { title: 'Substack post', generate: { path: `scripts/${script.id}/substack` }, empty: 'Generate a Substack post from the transcript (or the script, if there is no transcript yet).', help: 'Best after filming: paste the transcript first so the post reflects what you actually said.' }) },
    { key: 'brief', label: 'Edit brief', pane: docTab('edit_brief', { title: 'Editor brief', generate: { path: `scripts/${script.id}/edit-brief` }, empty: 'Generate a brief for your editor from the script’s [VISUAL] and [B-ROLL] notes.' }) },
    { key: 'publish', label: 'Publish', pane: publishPane, onShow: drawPublish },
  ];
  const tabBar = h('div', { class: 'tabs', role: 'tablist' });
  const paneBox = h('div', { class: 'tab-panes' });

  function showTab(key) {
    for (const t of tabs) {
      const active = t.key === key;
      t.button.classList.toggle('active', active);
      t.button.setAttribute('aria-selected', String(active));
      t.pane.hidden = !active;
      if (active) t.onShow?.();
    }
    if (key === 'write') textarea.focus();
  }
  for (const t of tabs) {
    t.button = h('button', { class: 'tab', role: 'tab', onclick: () => showTab(t.key) }, t.label);
    tabBar.append(t.button);
    paneBox.append(t.pane);
  }

  view.append(
    h('div', { class: 'editor-head' },
      link('/scripts', { class: 'btn ghost sm' }, icon('back', 15), 'Scripts'),
      h('div', { class: 'editor-title' }, titleInput, h('h1', { class: 'sr-only' }, script.title)),
      h('div', { class: 'row' }, saveState, statusSelect,
        h('button', { class: 'btn ghost sm', title: 'Download script', onclick: () => download(`${slug(script.title)}.md`, `# ${script.title}\n\n${script.body}`) }, icon('download', 16)))),
    script.idea ? h('p', { class: 'muted small', style: { margin: '-8px 0 14px' } }, 'From idea: ', h('strong', {}, script.idea.title), script.idea.premise ? ` · ${script.idea.premise}` : '') : null,
    script.outline?.chapters?.length ? h('details', { class: 'plan-box' },
      h('summary', {}, `Plan: ${script.outline.chapters.length} chapters · ~${script.outline.minutes || 10} min`),
      h('ol', {}, script.outline.chapters.map((c) => h('li', {}, h('strong', {}, c.title), c.beat ? h('span', { class: 'muted' }, ` · ${c.beat}`) : null)))) : null,
    tabBar, paneBox);

  showTab('write');

  // Arrived from Ideas → "Draft script": write the draft straight into the editor.
  let draftAbort = null;
  if (new URLSearchParams(location.search).get('draft') === '1' && (script.idea || script.outline) && !script.body.trim()) {
    history.replaceState({}, '', `/scripts/${script.id}`);
    draftAbort = new AbortController();
    textarea.readOnly = true;
    saveState.textContent = 'Drafting with AI…';
    let first = true;
    apiStream(`scripts/${script.id}/draft`, {}, {
      signal: draftAbort.signal,
      onDelta: (t) => {
        if (first) { first = false; textarea.value = ''; }
        const atEnd = textarea.scrollHeight - textarea.scrollTop - textarea.clientHeight < 60;
        textarea.value += t;
        if (atEnd) textarea.scrollTop = textarea.scrollHeight;
        drawStats();
      },
    }).then((r) => {
      script.body = r.script.body || '';
      textarea.value = script.body;
      drawStats();
      drawLint(r.findings, r.findings.some((f) => f.severity === 'HARD'));
      saveState.textContent = 'Draft saved';
      toast('Draft ready. Read it out loud and make it yours.');
    }).catch((err) => {
      if (err.name === 'AbortError') return;
      saveState.textContent = 'Draft failed';
      toast(err.message, 'err');
    }).finally(() => {
      textarea.readOnly = false;
      draftAbort = null;
    });
  }

  const onKey = (e) => {
    if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 's') { e.preventDefault(); saveSoon.flush(); }
  };
  const onUnload = (e) => { if (Object.keys(pending).length) { saveSoon.flush(); e.preventDefault(); } };
  document.addEventListener('keydown', onKey);
  window.addEventListener('beforeunload', onUnload);

  return () => {
    draftAbort?.abort();
    document.removeEventListener('keydown', onKey);
    window.removeEventListener('beforeunload', onUnload);
    lintSoon.cancel();
    saveSoon.flush();
  };
}
