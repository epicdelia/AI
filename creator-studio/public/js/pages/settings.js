// Settings: profile & brand voice, AI engine, API keys, research thresholds, diagnostics, usage.
import { h, api, busy, toast, pageHead, clear, field, compact, timeAgo } from '../core.js';
import { icon } from '../icons.js';

const TIMEZONES = (() => {
  try { return Intl.supportedValuesOf('timeZone'); } catch { return ['Europe/London', 'Europe/Brussels', 'Europe/Amsterdam', 'America/New_York', 'America/Los_Angeles', 'UTC']; }
})();

export async function renderSettings(view) {
  const res = await api('GET', 'settings');
  const s = res.settings;
  const secrets = res.secrets;
  const engines = res.engines;

  const inputs = {};
  const text = (name, props = {}) => (inputs[name] = h('input', { type: 'text', name, value: s[name] ?? '', ...props }));
  const area = (name, rows = 3, props = {}) => (inputs[name] = h('textarea', { name, rows, ...props }, s[name] ?? ''));
  const num = (name, props = {}) => (inputs[name] = h('input', { type: 'number', name, value: s[name] ?? '', ...props }));

  const clearFlags = {};
  function secretField(name, label, hint, linkUrl) {
    const input = h('input', { type: 'password', name, autocomplete: 'off', placeholder: secrets[name] ? '•••••••• saved. Type to replace.' : 'Paste your key' });
    inputs[name] = input;
    const clearBox = h('input', { type: 'checkbox' });
    clearBox.addEventListener('change', () => { clearFlags[name] = clearBox.checked; });
    return h('div', { class: 'field' },
      h('label', { for: `f-${name}` }, label, ' ', secrets[name] ? h('span', { class: 'pill ok' }, 'saved') : h('span', { class: 'pill' }, 'not set'),
        linkUrl ? h('a', { href: linkUrl, target: '_blank', rel: 'noopener', class: 'hint' }, ' get a key ↗') : null),
      Object.assign(input, { id: `f-${name}` }),
      h('div', { class: 'row-between' },
        hint ? h('span', { class: 'hint' }, hint) : h('span'),
        secrets[name] ? h('label', { class: 'inline-check' }, clearBox, 'remove saved key') : null));
  }

  // ---- AI engine ----
  const currentEngine = engines.find((e) => e.value === (s.llm_engine || res.current_engine)) || engines[0];
  const engineSelect = (inputs.llm_engine = h('select', { name: 'llm_engine' },
    engines.map((e) => h('option', { value: e.value, selected: e.value === currentEngine.value }, e.label))));
  const modelInput = text('llm_model');
  const baseInput = text('llm_base_url');
  const baseField = field('Base URL', baseInput, 'only for a custom OpenAI-compatible server');
  const keyFieldBox = h('div');

  function syncEngine(changed) {
    const e = engines.find((x) => x.value === engineSelect.value);
    modelInput.placeholder = e.default_model ? `default: ${e.default_model}` : 'model name';
    baseInput.placeholder = e.base_url || 'https://your-server/v1';
    baseField.hidden = e.value !== 'custom';
    if (changed) {
      modelInput.value = '';
      if (e.value !== 'custom') baseInput.value = '';
    }
    clear(keyFieldBox).append(secretField('llm_api_key', `${e.label} API key`,
      e.value === 'assemblyai' ? 'optional: your AssemblyAI key below is used if this is empty' : null, e.key_url));
  }
  engineSelect.addEventListener('change', () => syncEngine(true));
  syncEngine(false);

  // ---- form ----
  const saveBtn = h('button', { type: 'submit', class: 'btn primary' }, icon('check'), 'Save settings');
  const form = h('form', {
    class: 'settings-form',
    onsubmit: (e) => {
      e.preventDefault();
      busy(saveBtn, 'Saving…', async () => {
        const payload = {};
        for (const [name, el] of Object.entries(inputs)) {
          if (el.type === 'password') {
            if (el.value.trim()) payload[name] = el.value.trim();
          } else {
            payload[name] = el.value;
          }
        }
        for (const [name, on] of Object.entries(clearFlags)) if (on) payload[`clear_${name}`] = true;
        const r = await api('PUT', 'settings', payload);
        toast(r.message);
        const fresh = await api('GET', 'settings');
        Object.assign(secrets, fresh.secrets);
        clear(view);
        await renderSettings(view);
      });
    },
  },
  section('Your channel', 'Used in every AI prompt so the output sounds like you, about your channel.',
    h('div', { class: 'grid grid-2 tight' },
      field('Your name', text('creator_name')),
      field('Your channel', text('channel_id', { placeholder: '@yourhandle or UC… ID' }), 'for check-ins'),
      field('Niche', text('niche', { placeholder: 'personal finance for students' })),
      field('Audience', text('audience', { placeholder: 'uni students in the UK, 18–24' })),
      h('div', { class: 'span-2' }, field('Your angle / unfair advantage', area('angle', 2, { placeholder: 'Ex-bank analyst, explains money like a big sister' }))))),

  section('Brand voice', 'How the AI should write for you. Real excerpts beat descriptions.',
    h('div', { class: 'grid grid-2 tight' },
      field('Tone of voice', text('tone_of_voice', { placeholder: 'warm, funny, a bit chaotic, never preachy' })),
      field('Language', text('language', { placeholder: 'English' })),
      h('div', { class: 'span-2' }, field('Standing instructions', area('specific_instructions', 2, { placeholder: 'Always British spelling. Never say “guys”. Sign off with “see you Sunday”.' }))),
      h('div', { class: 'span-2' }, field('Voice excerpts', area('voice_excerpts', 7, { placeholder: 'Paste a few minutes of real transcripts from your best videos. The AI imitates the rhythm.' }), 'up to ~9,000 characters are used')))),

  section('AI engine', 'One provider powers ideas, scripts, reviews, the Toolkit, chat and dictation cleanup. Switch any time.',
    h('div', { class: 'grid grid-2 tight' },
      field('Provider', engineSelect),
      field('Model', modelInput, 'leave empty for the default'),
      h('div', { class: 'span-2' }, baseField),
      h('div', { class: 'span-2' }, keyFieldBox))),

  section('YouTube & dictation keys', null,
    h('div', { class: 'grid grid-2 tight' },
      secretField('youtube_api_key', 'YouTube Data API key', 'for breakout scans and check-ins (free quota)', 'https://console.cloud.google.com/apis/library/youtube.googleapis.com'),
      secretField('assemblyai_api_key', 'AssemblyAI key', 'for live dictation and recording transcripts', 'https://www.assemblyai.com/dashboard/api-keys'),
      field('Transcribe recordings with', (inputs.transcription_engine = h('select', { name: 'transcription_engine' },
        [['auto', 'Automatic (AssemblyAI if set, else OpenAI Whisper)'], ['assemblyai', 'AssemblyAI (full video files)'], ['openai', 'OpenAI Whisper (25 MB max)']]
          .map(([v, l]) => h('option', { value: v, selected: v === (s.transcription_engine || 'auto') }, l))))))),

  section('Web search', 'Live web results for script fact-checks and the producer chat’s “Web” toggle.',
    h('div', { class: 'grid grid-2 tight' },
      field('Search with', (inputs.search_provider = h('select', { name: 'search_provider' },
        [['auto', 'Automatic (Serper, else Perplexity)'], ['serper', 'Serper (Google results)'], ['perplexity', 'Perplexity (sonar)'], ['off', 'Off']]
          .map(([v, l]) => h('option', { value: v, selected: v === (s.search_provider || 'auto') }, l))))),
      h('div'),
      secretField('serper_api_key', 'Serper key', '2,500 free searches to start', 'https://serper.dev/api-key'),
      secretField('perplexity_api_key', 'Perplexity key', 'optional; reuses your AI key if Perplexity is your engine', 'https://www.perplexity.ai/settings/api'))),

  section('Memory', 'Search across your own scripts, transcripts, Library and notes. Works with keywords out of the box; an embeddings key makes it understand meaning.',
    h('div', { class: 'grid grid-2 tight' },
      secretField('embedding_api_key', 'OpenAI key for Memory (optional)', 'only needed if your AI engine has no embeddings (Claude, DeepSeek, xAI…)', 'https://platform.openai.com/api-keys'),
      field('Embedding model', text('embedding_model', { placeholder: 'default for your provider' }), 'leave empty unless you know you need another'))),

  section('Automation', 'Daily breakout scan, feed refresh every 3 hours and a Monday check-in, in your timezone.',
    h('div', { class: 'grid grid-2 tight' },
      field('Scheduled jobs', (inputs.schedule_enabled = h('select', { name: 'schedule_enabled' },
        [['1', 'On'], ['0', 'Paused']].map(([v, l]) => h('option', { value: v, selected: v === (s.schedule_enabled ?? '1') }, l)))),
      'runs while `php artisan schedule:work` is running'))),

  section('Breakout research', 'What counts as a breakout when scanning competitor channels.',
    h('div', { class: 'grid grid-3 tight' },
      field('Lookback (days)', num('lookback_days', { min: 1, max: 30 })),
      field('Min outlier score', num('min_outlier_score', { min: 1, step: 0.5 }), '× channel median'),
      field('Min views', num('min_views', { min: 0, step: 1000 })))),

  section('Goals & mentor', null,
    h('div', { class: 'grid grid-3 tight' },
      field('Commitment', text('commitment')),
      field('Baseline views at 7 days', num('baseline_views', { min: 0 }), 'your typical long-form'),
      field('Timezone', (inputs.timezone = h('select', { name: 'timezone' }, TIMEZONES.map((tz) => h('option', { value: tz, selected: tz === s.timezone }, tz))))))),

  h('div', { class: 'sticky-save' }, saveBtn));

  // ---- diagnostics ----
  const testOut = h('div');
  const testBtn = h('button', {
    class: 'btn',
    onclick: () => busy(testBtn, 'Testing…', async () => {
      const r = await api('POST', 'settings/test');
      const labels = { youtube: 'YouTube', llm: 'AI engine', assemblyai: 'AssemblyAI' };
      clear(testOut).append(h('ul', { class: 'checklist' }, Object.entries(r.checks).map(([k, c]) => h('li', { class: c.ok ? 'done' : 'fail' },
        h('span', { class: 'check' }, c.ok ? icon('check', 14) : icon('x', 14)),
        h('div', {}, h('strong', {}, labels[k] || k), h('div', { class: 'muted small' }, c.message))))));
    }),
  }, icon('refresh'), 'Test connections');

  view.append(
    pageHead('Settings', 'Your profile, voice and keys. Keys are stored encrypted in the local database and never sent to the browser.'),
    h('div', { class: 'grid grid-sidebar' },
      form,
      h('div', {},
        h('div', { class: 'card' }, h('h3', {}, 'Diagnostics'), h('p', { class: 'muted small' }, 'Save first, then test. Uses a tiny amount of quota.'), testBtn, testOut),
        await scheduleCard(),
        await memoryCard(),
        await usageCard())));
}

async function scheduleCard() {
  let st;
  try { st = await api('GET', 'schedule'); } catch { return h('div'); }
  const box = h('div');
  const draw = () => {
    const seen = st.scheduler_seen ? (Date.now() - new Date(st.scheduler_seen).getTime()) / 60000 : null;
    clear(box).append(
      h('p', { class: 'muted small' },
        !st.enabled ? 'Paused in settings.'
          : seen !== null && seen < 10 ? `Scheduler running (seen ${timeAgo(st.scheduler_seen)}).`
            : h('span', {}, 'Scheduler not running. Start it with ', h('code', {}, 'php artisan schedule:work'), ' (or a cron calling ', h('code', {}, 'schedule:run'), ').')),
      h('ul', { class: 'task-list' }, st.tasks.map((t) => {
        const run = h('button', {
          class: 'btn ghost sm', title: `Run ${t.command} now`,
          onclick: () => busy(run, '…', async () => {
            try {
              st = await api('POST', `schedule/${t.key}/run`);
              toast(st.message.split('\n')[0]);
            } catch (e) {
              if (e.data?.tasks) st = e.data;
              throw e;
            } finally { draw(); }
          }),
        }, icon('play', 13), 'Run');
        return h('li', {},
          h('div', { class: 'item-main' }, h('strong', {}, t.label), h('div', { class: 'muted small' }, `${t.when} · last ${timeAgo(t.last_run)}`),
            t.last_error ? h('div', { class: 'error-text' }, t.last_error) : null),
          run);
      })));
  };
  draw();
  return h('div', { class: 'card' }, h('h3', {}, 'Automation'), box);
}

async function memoryCard() {
  let m;
  try { m = await api('GET', 'memory'); } catch { return h('div'); }
  const box = h('div');
  const draw = () => {
    const sources = Object.entries(m.sources || {}).map(([k, v]) => `${v} ${k}${v === 1 ? '' : 's'}`).join(', ');
    clear(box).append(
      h('p', { class: 'muted small' }, m.semantic ? `Meaning search on (${m.model}). ${m.semantic_chunks} of ${m.chunks} passages have embeddings.` : 'Keyword search (add an embeddings key above for meaning search).'),
      h('p', { class: 'small' }, m.chunks ? `Indexed: ${sources}.` : 'Nothing indexed yet. It fills up as you write.'),
      reindex);
  };
  const reindex = h('button', {
    class: 'btn sm',
    onclick: () => busy(reindex, 'Indexing…', async () => {
      m = await api('POST', 'memory/reindex', { fresh: true });
      toast(m.message);
      draw();
    }),
  }, icon('refresh', 14), 'Rebuild index');
  draw();
  return h('div', { class: 'card' }, h('h3', {}, 'Memory'), box);
}

function section(title, desc, body) {
  return h('section', { class: 'card' }, h('h2', {}, title), desc ? h('p', { class: 'muted small' }, desc) : null, body);
}

async function usageCard() {
  let u;
  try { u = await api('GET', 'usage?days=30'); } catch { return h('div'); }
  const label = (f) => f.replace(/^tool:/, 'toolkit · ').replace(/^youtube:/, 'youtube · ').replace(/_/g, ' ');
  return h('div', { class: 'card' },
    h('h3', {}, 'AI usage · last 30 days'),
    h('div', { class: 'usage-stats' },
      h('div', {}, h('strong', {}, u.calls), h('span', { class: 'muted' }, ' calls')),
      h('div', {}, h('strong', {}, compact(u.input_tokens + u.output_tokens)), h('span', { class: 'muted' }, ' tokens')),
      h('div', {}, h('strong', {}, `${(u.avg_ms / 1000).toFixed(1)}s`), h('span', { class: 'muted' }, ' avg')),
      u.failed ? h('div', {}, h('strong', { class: 'error-text' }, u.failed), h('span', { class: 'muted' }, ' failed')) : null),
    u.by_feature.length
      ? h('table', { class: 'compact-table' },
        h('thead', {}, h('tr', {}, h('th', {}, 'Feature'), h('th', { class: 'num' }, 'Calls'), h('th', { class: 'num' }, 'Tokens'))),
        h('tbody', {}, u.by_feature.map((f) => h('tr', {}, h('td', {}, label(f.feature)), h('td', { class: 'num' }, f.calls), h('td', { class: 'num' }, compact(f.tokens))))))
      : h('p', { class: 'muted small' }, 'No AI calls yet.'),
    u.recent.length ? h('details', { class: 'small' }, h('summary', {}, 'Recent calls'),
      h('ul', { class: 'mini-log' }, u.recent.map((r) => h('li', { class: r.ok ? '' : 'fail' },
        h('span', {}, label(r.feature)), h('span', { class: 'muted' }, ` ${r.model || r.engine} · ${(r.duration_ms / 1000).toFixed(1)}s · ${timeAgo(r.created_at)}`),
        r.error ? h('div', { class: 'error-text' }, r.error) : null)))) : null,
    h('p', { class: 'muted small' }, 'Token counts are what the provider reports; check your provider’s dashboard for exact cost.'));
}
