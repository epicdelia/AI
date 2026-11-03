// Scripts: the list, and the editor with live AI-tell linting, autosave, review,
// packaging (titles, thumbnails, description...), transcript, Substack and edit brief.
import {
  h, api, busy, toast, pageHead, emptyState, clear, pill, modal, confirmDialog, field, formData,
  navigate, link, timeAgo, debounce, wordCount, mdBlock, copyText, download, slug, isoDay,
  apiStream, liveMarkdown,
} from '../core.js';
import { icon } from '../icons.js';
import { outputPanel, transcribeButton, memoryList, exportMenu } from '../components.js';
import { scriptWizard } from '../wizard.js';
import { celebrate, loveOn } from '../love.js';
import { setCopilotContext } from '../copilot.js';
import { readAloudCard, thumbnailStudio, shortsPanel, brollPanel, cleanAudioButton } from '../media.js';
import { openPrompter } from '../prompter.js';
import { versionsDialog } from '../versions.js';
import { feedPreview } from '../preview.js';
import { skLines, skList } from '../skeleton.js';
import { aiChecksPane } from '../ai-checks.js';

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
    view.append(h('div', { class: 'card' }, emptyState(icon('doc', 30), 'No scripts yet', 'Pick an idea and let the AI draft it, or start from a blank page.', newBtn.cloneNode(true))));
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
  { key: 'sponsor_read', label: 'Sponsor segment', input: (s) => ({ video: s.title, brand: 'Sponsor partner', points: s.body.slice(0, 3000) }) },
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
  statusSelect.addEventListener('change', () => {
    change('status', statusSelect.value);
    saveSoon.flush();
    if (statusSelect.value === 'published') celebrate('published');
  });

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
      h('span', { class: words && (words < 1200 || words > 2250) ? 'warn-text' : '' }, words ? (words < 1200 ? 'under 8 min' : words > 2250 ? 'over 15 min' : '8–15 min') : ''));
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

  // ---- structure: section timing and retention flags (deterministic, see ScriptStructure) ----
  const structureBox = h('div');
  const clock = (s) => `${Math.floor(s / 60)}:${String(s % 60).padStart(2, '0')}`;
  function drawStructure(st) {
    clear(structureBox);
    if (!st?.sections?.length) { structureBox.append(h('p', { class: 'muted small' }, 'Add ## headings for each part (Cold open, chapters, CTA) to see the timing.')); return; }
    const total = Math.max(1, st.total_seconds);

    // Retention Curve Simulator Graph (YouTube Studio style)
    let retentionSection = null;
    if (st.retention?.points?.length > 1) {
      const pts = st.retention.points;
      const w = 280;
      const hGraph = 60;
      const scaleX = (sec) => (sec / total) * (w - 20) + 10;
      const scaleY = (pct) => hGraph - 6 - ((pct / 100) * (hGraph - 14));

      const pathD = pts.map((p, i) => `${i === 0 ? 'M' : 'L'} ${scaleX(p.sec).toFixed(1)} ${scaleY(p.pct).toFixed(1)}`).join(' ');
      const areaD = `${pathD} L ${scaleX(pts[pts.length - 1].sec).toFixed(1)} ${hGraph} L ${scaleX(pts[0].sec).toFixed(1)} ${hGraph} Z`;

      const circles = pts.filter((p) => p.dip || p.sec === 30).map((p) =>
        `<circle cx="${scaleX(p.sec).toFixed(1)}" cy="${scaleY(p.pct).toFixed(1)}" r="4" fill="${p.dip ? 'var(--warn, #f59e0b)' : 'var(--accent)'}" stroke="var(--panel, #1e1e2e)" stroke-width="1.5">
          <title>${p.time} (${p.pct}%): ${p.annotation || p.section}</title>
        </circle>`).join('');

      const svgHtml = `<svg viewBox="0 0 ${w} ${hGraph}" class="retention-svg" style="width: 100%; height: 70px; overflow: visible; display: block;">
        <defs>
          <linearGradient id="retGrad-${script.id}" x1="0" y1="0" x2="0" y2="1">
            <stop offset="0%" stop-color="var(--accent)" stop-opacity="0.32"/>
            <stop offset="100%" stop-color="var(--accent)" stop-opacity="0.0"/>
          </linearGradient>
        </defs>
        <line x1="10" y1="${scaleY(50).toFixed(1)}" x2="${w - 10}" y2="${scaleY(50).toFixed(1)}" stroke="var(--border)" stroke-dasharray="3,3" stroke-width="1"/>
        <text x="${w - 12}" y="${(scaleY(50) - 3).toFixed(1)}" text-anchor="end" font-size="8" fill="var(--muted)">50% YouTube threshold</text>
        <path d="${areaD}" fill="url(#retGrad-${script.id})"/>
        <path d="${pathD}" fill="none" stroke="var(--accent)" stroke-width="2.2" stroke-linecap="round"/>
        ${circles}
      </svg>`;

      retentionSection = h('div', { class: 'retention-card', style: { marginBottom: '14px', background: 'var(--panel-2)', padding: '10px 12px', borderRadius: '8px' } },
        h('div', { class: 'row', style: { justifyContent: 'space-between', marginBottom: '6px' } },
          h('strong', { class: 'small' }, 'Audience Retention Curve'),
          pill(st.retention.grade === 'A+' || st.retention.grade === 'A' ? 'ok' : 'WARN', `${st.retention.predicted_apv}% APV (${st.retention.grade})`)),
        h('div', { html: svgHtml }),
        h('p', { class: 'muted small', style: { margin: '6px 0 0 0', fontSize: '0.82rem', lineHeight: '1.35' } }, st.retention.verdict));
    }

    structureBox.append(
      retentionSection,
      h('div', { class: 'st-bar', 'aria-hidden': 'true' }, st.sections.map((s, i) => h('span', {
        class: `st-seg st-c${i % 4}`, style: { flexGrow: String(Math.max(s.seconds, 1)) }, title: `${s.title} · ${clock(s.seconds)}`,
      }))),
      h('ol', { class: 'st-list' }, st.sections.map((s) => h('li', { onclick: () => jumpToLine(s.line), title: `Go to line ${s.line}` },
        h('span', { class: 'st-time' }, clock(s.start)), h('span', { class: 'st-title' }, s.title), h('span', { class: 'muted' }, clock(s.seconds))))),
      h('div', { class: 'muted small' }, `About ${clock(total)} spoken, at 150 words a minute.`),
      st.flags.length
        ? h('ul', { class: 'lint-list' }, st.flags.map((f) => h('li', { class: 'lint-item WARN', onclick: () => jumpToLine(f.line), title: `Go to line ${f.line}` },
          h('div', { class: 'lint-rule' }, f.message))))
        : h('div', { class: 'lint-clean' }, icon('check', 16), 'Pacing looks right.'),
      st.chapters ? h('button', { class: 'btn ghost sm', onclick: () => copyText(st.chapters, 'Estimated chapters copied. Swap in real times after filming.') }, icon('copy', 15), 'Copy chapter timestamps') : null);
  }

  const lintSoon = debounce(async () => {
    try {
      const r = await api('POST', 'scripts/lint', { text: textarea.value });
      drawLint(r.findings, r.has_hard);
      drawStructure(r.structure);
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
  drawStructure(data.structure);

  // ---- past work (Memory) ----
  const pastBox = h('div', {}, skList(3, { thumb: null, label: 'related work' }));
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
        h('h3', {}, 'Structure'),
        h('p', { class: 'muted small' }, 'Timing per section and where viewers tend to drop. Click to jump.'),
        structureBox),
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
  const broll = brollPanel(script);
  let feedCard = null; // built on first visit, then kept so her title list survives tab switches
  function drawPackage() {
    feedCard ??= feedPreview(script, {
      onUseTitle: (t) => { titleInput.value = t; change('title', t); saveSoon.flush(); toast('Title updated.'); },
    });
    const outBox = h('div');
    clear(packagePane).append(
      h('div', { class: 'card tone-accent', style: { marginBottom: '16px', display: 'flex', justifyContent: 'space-between', alignItems: 'center', background: 'var(--panel-2)' } },
        h('div', {},
          h('strong', {}, '1-Click Omnichannel Master Distribution'),
          h('div', { class: 'muted small' }, 'Turn this script into native LinkedIn carousels, X threads, TikTok vertical scripts, and code cards in one pipeline.')),
        h('button', { class: 'btn primary sm', onclick: () => omnichannelDialog(script) }, icon('sparkle', 14), 'Distribute everywhere')),
      h('div', { class: 'card' },
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
    feedCard,
    thumbStudio,
    broll);
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
      h('div', { class: 'row', style: { marginBottom: '12px' } },
        cleanAudioButton(script.id),
        h('span', { class: 'muted small' }, 'Noisy room or traffic? Get a clean MP3 of your voice to drop into your edit.')),
      h('p', { class: 'muted small' }, 'Captions are fetched from the public video. If YouTube blocks it, copy the transcript from YouTube (⋯ → Show transcript) and paste it here.'),
      ta));
  }

  // ---- publish kit: real chapter times, everything to paste into YouTube Studio, a checklist ----
  const CHECKS = [
    ['title', 'Title chosen (Package → Feed preview)'], ['thumb', 'Thumbnail uploaded'], ['desc', 'Description pasted'],
    ['chapters', 'Chapters pasted (first one at 0:00)'], ['tags', 'Tags added'], ['captions', 'Auto-captions checked'],
    ['end', 'End screen and a card added'], ['pinned', 'Pinned comment posted'], ['link', 'Video link added below'],
  ];
  const checkKey = `publish-check-${script.id}`;
  const readChecks = () => { try { return JSON.parse(localStorage.getItem(checkKey) || '{}'); } catch { return {}; } };
  function publishKitCard() {
    const chaptersBox = h('div', {}, skLines(5, 'chapters'));
    api('GET', `scripts/${script.id}/chapters`).then((c) => {
      clear(chaptersBox).append(c.chapters
        ? [h('p', { class: 'muted small' }, c.source === 'transcript'
          ? `Times from your recording (${c.matched} of ${c.sections - 1} sections found). Check them once in the player.`
          : 'Estimated from the script at 150 words a minute. Upload your recording on the Transcript tab for real times.'),
        h('pre', { class: 'chapters-pre' }, c.chapters),
        h('button', { class: 'btn ghost sm', onclick: () => copyText(c.chapters, 'Chapters copied.') }, icon('copy', 15), 'Copy chapters')]
        : h('p', { class: 'muted small' }, 'YouTube needs at least 3 chapters of 10+ seconds. Add ## headings for each part of the script.'));
    }).catch(() => clear(chaptersBox).append(h('p', { class: 'muted small' }, 'Chapters unavailable.')));

    const kitBox = h('div', { class: 'doc-body' }, mdBlock(script.publish_kit || '', 'Generate the kit: title, description with chapters, tags, pinned comment and end-screen picks, written from what you said.'));
    const genBtn = h('button', {
      class: 'btn primary sm',
      onclick: () => busy(genBtn, 'Writing…', async () => {
        await saveSoon.flush();
        const live = liveMarkdown();
        clear(kitBox).append(live.el);
        try {
          const r = await apiStream(`scripts/${script.id}/publish-kit`, {}, { onDelta: live.append });
          script.publish_kit = r.publish_kit;
          clear(kitBox).append(mdBlock(script.publish_kit));
          toast('Publish kit ready.');
        } catch (err) {
          clear(kitBox).append(mdBlock(script.publish_kit || '', ''));
          throw err;
        }
      }),
    }, icon('sparkle', 15), script.publish_kit ? 'Regenerate' : 'Generate kit');

    const done = readChecks();
    const checklist = h('ul', { class: 'checklist publish-checklist' }, CHECKS.map(([k, label]) => {
      const cb = h('input', {
        type: 'checkbox', checked: !!done[k],
        onchange: () => { done[k] = cb.checked; try { localStorage.setItem(checkKey, JSON.stringify(done)); } catch { /* not remembered */ } },
      });
      return h('li', {}, h('label', { class: 'inline-check' }, cb, label));
    }));

    return h('div', { class: 'card' },
      h('div', { class: 'card-header' }, h('h2', {}, 'Publish kit'),
        h('div', { class: 'row' },
          h('button', { class: 'btn ghost sm', onclick: () => copyText(script.publish_kit || '', 'Kit copied.') }, icon('copy', 15), 'Copy'),
          link(`/youtube?script=${script.id}`, { class: 'btn ghost sm', title: 'Upload the finished video with this kit' }, icon('external', 15), 'Upload to YouTube'),
          genBtn)),
      h('div', { class: 'grid grid-sidebar tight' },
        h('div', {}, kitBox),
        h('div', {}, h('h3', {}, 'Chapters'), chaptersBox, h('h3', { style: { marginTop: '16px' } }, 'Before you publish'), checklist)));
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
          if (script.status === 'published' && v.youtube_url) celebrate('published');
        });
      },
    },
    h('div', { class: 'grid grid-2 tight' },
      field('YouTube link', h('input', { type: 'url', name: 'youtube_url', value: script.youtube_url || '' })),
      field('Published on', h('input', { type: 'date', name: 'published_on', value: isoDay(script.published_on) }))),
    h('p', { class: 'muted small' }, 'Adding the link marks the script published. Link it to a week on the Goals page so it counts on the scoreboard (check-in also finds it automatically).'),
    h('div', { class: 'row' }, h('button', { type: 'submit', class: 'btn primary' }, 'Save')));

    // Her website link tagged with this video: a lead that arrives through it is credited to it.
    const vid = (script.youtube_url || '').match(/(?:youtu\.be\/|v=|\/shorts\/|\/live\/)([\w-]{11})/)?.[1];
    const siteLink = h('div', { class: 'row small', style: { gap: '8px', marginTop: '10px' } });
    api('POST', 'landing/tracked-link', { ref: vid ? `yt-${vid}` : 'yt' }).then((r) => siteLink.append(
      h('span', { class: 'muted' }, 'Your site link for this video:'),
      h('code', { class: 'clamp-1' }, r.url),
      h('button', { type: 'button', class: 'btn ghost sm', onclick: () => copyText(r.url, 'Tracked link copied.') }, icon('copy', 13), 'Copy'),
      vid ? null : h('span', { class: 'muted' }, '(add the YouTube link to tag it with this video)'))).catch(() => {});

    clear(publishPane).append(
      publishKitCard(),
      h('div', { class: 'card' }, h('h2', {}, 'Publishing'), form, siteLink),
      h('div', { class: 'card' },
        h('div', { class: 'card-header' }, h('h2', {}, 'Export everything'),
          exportMenu(`scripts/${script.id}?parts=outline,body,review,fact_check,transcript,substack,edit_brief,publish_kit`, {
            label: 'Download',
            getPrint: () => ({ title: script.title, markdown: [script.body, script.review, script.fact_check, script.substack, script.edit_brief].filter(Boolean).join('\n\n---\n\n') }),
          })),
        h('p', { class: 'muted small' }, 'Plan, script, review, fact-check, transcript, Substack post and editor brief in one document. Send the Word file to your editor.')),
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

  // ---- AI checks: the engines (retention, titles, hooks, claims, sponsor) on this script ----
  const checks = aiChecksPane(script, {
    before: () => saveSoon.flush(),
    selection: () => textarea.value.slice(textarea.selectionStart, textarea.selectionEnd),
  });

  // ---- tabs ----
  const tabs = [
    { key: 'write', label: 'Write', pane: writePane },
    { key: 'preview', label: 'Preview', pane: previewPane, onShow: () => clear(previewPane).append(mdBlock(script.body, 'Nothing written yet.')) },
    { key: 'review', label: 'Review', pane: reviewPane, onShow: drawReview },
    { key: 'facts', label: 'Facts', pane: factsPane, onShow: drawFacts },
    { key: 'checks', label: 'AI checks', pane: checks.pane, onShow: checks.draw },
    { key: 'package', label: 'Package', pane: packagePane, onShow: drawPackage },
    { key: 'transcript', label: 'Transcript', pane: transcriptPane, onShow: drawTranscript },
    { key: 'substack', label: 'Substack', pane: docTab('substack', { title: 'Substack post', generate: { path: `scripts/${script.id}/substack` }, empty: 'Generate a Substack post from the transcript (or the script, if there is no transcript yet).', help: 'Best after filming: paste the transcript first so the post reflects what you actually said.' }) },
    { key: 'brief', label: 'Edit brief', pane: docTab('edit_brief', { title: '1-Click Editor Handoff Pack', generate: { path: `scripts/${script.id}/edit-brief-pack` }, empty: 'Generate a structured 1-Click Handoff Pack for your editor with Google Drive folder scaffolding, timecoded shot list, and 48h SLA from the script’s [VISUAL] notes.' }) },
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

  // ---- filming and history: beside the tabs, so the title keeps its width ----
  const prompterBtn = h('button', {
    class: 'btn ghost sm', title: 'Read it on camera: full-screen teleprompter (from your selection, if any)',
    onclick: () => {
      const from = textarea.selectionEnd > textarea.selectionStart ? textarea.selectionStart : 0;
      openPrompter(textarea.value.slice(from), { title: script.title }).then(() => textarea.focus());
    },
  }, icon('prompter', 15), 'Teleprompter');
  const historyBtn = h('button', {
    class: 'btn ghost sm', title: 'Earlier versions of this script',
    onclick: () => versionsDialog(script, {
      beforeOpen: () => saveSoon.flush(),
      onRestored: (r) => {
        script.body = r.script.body || '';
        textarea.value = script.body;
        drawStats();
        drawLint(r.findings, r.has_hard);
        lintSoon();
        saveState.textContent = 'Version restored';
        showTab('write');
      },
    }).catch((err) => toast(err.message, 'err')),
  }, icon('history', 15), 'History');

  function omnichannelDialog(sc) {
    return modal(`Omnichannel Distribution: ${sc.title}`, (close) => {
      const box = h('div', { class: 'omni-dialog' });
      const targets = [
        { platform: 'linkedin', format: 'carousel', label: 'LinkedIn PDF Carousel', checked: true },
        { platform: 'linkedin', format: 'post', label: 'LinkedIn Text Post', checked: true },
        { platform: 'x', format: 'thread', label: 'X (Twitter) Technical Thread', checked: true },
        { platform: 'tiktok', format: 'video', label: 'TikTok Vertical Video Script', checked: true },
        { platform: 'instagram', format: 'reel', label: 'Instagram Reel (Beats & Visuals)', checked: true },
        { platform: 'threads', format: 'post', label: 'Threads Conversation Starter', checked: false },
      ];

      const checkList = h('div', { class: 'grid grid-2', style: { gap: '10px', marginBottom: '16px' } },
        targets.map((t) => {
          const cb = h('input', { type: 'checkbox', checked: t.checked });
          cb.addEventListener('change', () => { t.checked = cb.checked; });
          return h('label', { class: 'row', style: { background: 'var(--panel-2)', padding: '8px 12px', borderRadius: '6px', cursor: 'pointer' } },
            cb, h('span', { class: 'small' }, t.label));
        }));

      const resultsBox = h('div', { style: { marginTop: '16px' } });
      const genBtn = h('button', {
        class: 'btn primary',
        type: 'button',
        onclick: () => busy(genBtn, 'Generating master suite…', async () => {
          const selected = targets.filter((t) => t.checked).map((t) => ({ platform: t.platform, format: t.format }));
          if (!selected.length) { toast('Select at least one platform format.', 'warn'); return; }
          const res = await api('POST', 'social/repurpose', {
            title: sc.title,
            source: sc.body || sc.title,
            script_id: sc.id,
            targets: selected,
          });
          const posts = res.posts || [];
          toast(`Created ${posts.length} native drafts.`);
          clear(resultsBox).append(
            h('div', { class: 'card', style: { background: 'var(--panel-2)', marginTop: '12px' } },
              h('div', { class: 'row', style: { justifyContent: 'space-between', marginBottom: '10px' } },
                h('strong', {}, `Generated ${posts.length} Multi-Platform Drafts`),
                link('/calendar', { class: 'btn sm ghost', onclick: () => close() }, icon('calendar', 13), 'View in Calendar')),
              h('div', { style: { display: 'flex', flexDirection: 'column', gap: '8px' } },
                posts.map((p) => h('div', { class: 'row', style: { justifyContent: 'space-between', padding: '8px 12px', background: 'var(--panel)', borderRadius: '6px' } },
                  h('div', {},
                    h('span', { class: 'pf-chip', style: { marginRight: '8px' } }, p.platform.toUpperCase()),
                    h('strong', { class: 'small' }, p.title || p.format)),
                  h('div', { class: 'row' },
                    h('button', { class: 'btn sm ghost', onclick: () => { copyText(p.body || p.parts?.join('\n\n') || ''); toast('Copied'); } }, icon('copy', 12), 'Copy'),
                    link(`/posts/${p.id}`, { class: 'btn sm primary', onclick: () => close() }, icon('pen', 12), 'Open draft'))))),
              h('div', { class: 'row', style: { marginTop: '14px', paddingTop: '10px', borderTop: '1px solid var(--border)', justifyContent: 'space-between' } },
                h('span', { class: 'muted small' }, 'Have code snippets in this script?'),
                link('/snippets', { class: 'btn sm ghost', onclick: () => close() }, icon('code', 13), 'Render Code Cards ↗'))));
        }),
      }, icon('sparkle', 14), 'Generate All Native Drafts');

      box.append(
        h('p', { class: 'muted small', style: { margin: '0 0 14px 0' } }, 'Generate a synchronized multi-platform distribution suite rewritten natively for each platform algorithm from this script.'),
        checkList,
        h('div', { class: 'row', style: { justifyContent: 'flex-end' } }, genBtn),
        resultsBox);

      return box;
    });
  }

  const omniBtn = h('button', {
    class: 'btn ghost sm', title: '1-click master omnichannel distribution suite (LinkedIn, X, TikTok, IG, Newsletter)',
    onclick: () => omnichannelDialog(script),
  }, icon('sparkle', 15), 'Distribute');

  view.append(
    h('div', { class: 'editor-head' },
      link('/scripts', { class: 'btn ghost sm' }, icon('back', 15), 'Scripts'),
      h('div', { class: 'editor-title' }, titleInput, h('h1', { class: 'sr-only' }, script.title)),
      h('div', { class: 'row' }, saveState, statusSelect,
        exportMenu(`scripts/${script.id}?parts=outline,body`, { getPrint: () => ({ title: script.title, markdown: script.body }) }))),
    script.idea ? h('p', { class: 'muted small', style: { margin: '-8px 0 14px' } }, 'From idea: ', h('strong', {}, script.idea.title), script.idea.premise ? ` · ${script.idea.premise}` : '') : null,
    script.outline?.chapters?.length ? h('details', { class: 'plan-box' },
      h('summary', {}, `Plan: ${script.outline.chapters.length} chapters · ~${script.outline.minutes || 10} min`),
      h('ol', {}, script.outline.chapters.map((c) => h('li', {}, h('strong', {}, c.title), c.beat ? h('span', { class: 'muted' }, ` · ${c.beat}`) : null)))) : null,
    h('div', { class: 'tabs-row' }, tabBar, h('div', { class: 'row tabs-actions' }, prompterBtn, historyBtn, omniBtn,
      link(`/repurpose?script=${script.id}`, { class: 'btn ghost sm', title: 'Turn this into LinkedIn, Instagram, X and Shorts posts' }, icon('repeat', 15), 'Repurpose'))),
    paneBox);

  // ?tab=review|package... (from the dashboard's next step) opens that tab.
  const params = new URLSearchParams(location.search);
  const startTab = params.get('tab');
  showTab(tabs.some((t) => t.key === startTab) ? startTab : 'write');
  if (startTab && !params.has('draft')) history.replaceState({}, '', `/scripts/${script.id}`);

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
      lintSoon();
      saveState.textContent = 'Draft saved';
      if (loveOn()) celebrate('draft'); else toast('Draft ready. Read it out loud and make it yours.');
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

  // ---- Copilot: what she's looking at, and live updates when she applies its edits ----
  setCopilotContext({
    title: script.title,
    script_id: script.id,
    getSelection: () => (document.activeElement === textarea || textarea.selectionEnd > textarea.selectionStart
      ? textarea.value.slice(textarea.selectionStart, textarea.selectionEnd) : ''),
  });
  const onBeforeApply = () => saveSoon.flush();
  const onApplied = (e) => {
    const s = e.detail?.script;
    if (!s || s.id !== script.id) return;
    script.body = s.body || '';
    script.title = s.title;
    textarea.value = script.body;
    titleInput.value = script.title;
    drawStats();
    lintSoon();
    saveState.textContent = e.detail.undone ? 'Change undone' : 'Updated by Copilot';
  };
  const onInsert = (e) => {
    const text = e.detail?.text;
    if (!text) return;
    showTab('write');
    textarea.focus();
    textarea.setRangeText(text, textarea.selectionStart, textarea.selectionEnd, 'end');
    change('body', textarea.value);
    drawStats();
    lintSoon();
  };
  window.addEventListener('copilot:before-apply', onBeforeApply);
  window.addEventListener('copilot:applied', onApplied);
  window.addEventListener('copilot:insert', onInsert);
  const onUnload = (e) => { if (Object.keys(pending).length) { saveSoon.flush(); e.preventDefault(); } };
  document.addEventListener('keydown', onKey);
  window.addEventListener('beforeunload', onUnload);

  return () => {
    draftAbort?.abort();
    window.removeEventListener('copilot:before-apply', onBeforeApply);
    window.removeEventListener('copilot:applied', onApplied);
    window.removeEventListener('copilot:insert', onInsert);
    document.removeEventListener('keydown', onKey);
    window.removeEventListener('beforeunload', onUnload);
    lintSoon.cancel();
    saveSoon.flush();
  };
}
