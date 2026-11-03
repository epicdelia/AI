// The dashboard composer: "Let's create…" for every network, in the same input box as the
// Copilot (sbcp-box). Type, dictate or attach (audio is transcribed, images described), pick
// the networks, send: native drafts come back for each one. Nothing is posted.
import { h, api, apiUpload, toast, clear, link } from './core.js';
import { icon } from './icons.js';
import { PLATFORM_ICONS, postCard } from './pages/social.js';
import { skCards } from './skeleton.js';

const MAX = 40000;
const PREFS = 'studio-composer';
const AUDIO_ACCEPT = '.mp3,.wav,.m4a,.mp4,.aac,.ogg,.oga,.opus,.flac,.weba,.webm,audio/*,video/webm';
const SVG = {
  plus: '<path d="M12 5.75v12.5M5.75 12h12.5"></path>',
  mic: '<rect x="9" y="3.75" width="6" height="10.5" rx="3"></rect><path d="M5.75 11a6.25 6.25 0 0 0 12.5 0M12 17.25v3"></path>',
  send: '<path d="M12 18.25V5.75M6.75 11L12 5.75 17.25 11"></path>',
};
const svg = (name) => h('span', { html: `<svg viewBox="0 0 24 24" aria-hidden="true">${SVG[name]}</svg>`, style: { display: 'contents' } });

function loadPrefs() { try { return JSON.parse(localStorage.getItem(PREFS) || '{}'); } catch { return {}; } }
function savePrefs(p) { try { localStorage.setItem(PREFS, JSON.stringify({ ...loadPrefs(), ...p })); } catch { /* not remembered */ } }

/** A popover in the Copilot's menu style (Esc / outside click closes, arrow keys move). */
function popover(anchor, button, build, { wide = false } = {}) {
  const pop = h('div', { class: `bw-tc-menu ms-pop${wide ? ' ms-pop-wide' : ''}`, role: 'menu', hidden: true });
  const close = () => {
    pop.hidden = true;
    button.setAttribute('aria-expanded', 'false');
    document.removeEventListener('mousedown', outside);
    document.removeEventListener('keydown', keys);
  };
  const outside = (e) => { if (!anchor.contains(e.target)) close(); };
  const keys = (e) => {
    if (e.key === 'Escape') { close(); button.focus(); return; }
    if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
      const items = [...pop.querySelectorAll('[role=menuitem], input, textarea')];
      const i = items.indexOf(document.activeElement);
      items[(i + (e.key === 'ArrowDown' ? 1 : -1) + items.length) % items.length]?.focus();
      e.preventDefault();
    }
  };
  button.addEventListener('click', () => {
    if (!pop.hidden) { close(); return; }
    clear(pop).append(build(close));
    pop.classList.remove('ms-pop-down');
    pop.hidden = false;
    // Open downward when there isn't room above (the composer sits near the top of the page).
    if (pop.getBoundingClientRect().top < 8) pop.classList.add('ms-pop-down');
    button.setAttribute('aria-expanded', 'true');
    document.addEventListener('mousedown', outside);
    document.addEventListener('keydown', keys);
    pop.querySelector('[role=menuitem], input')?.focus();
  });
  anchor.append(pop);
  return close;
}

/** Downscale an image file to a JPEG data URL (max 1280px) so uploads stay small. */
function imageToDataUrl(file) {
  return new Promise((resolve, reject) => {
    const img = new Image();
    img.onload = () => {
      const scale = Math.min(1, 1280 / Math.max(img.width, img.height));
      const c = document.createElement('canvas');
      c.width = Math.round(img.width * scale);
      c.height = Math.round(img.height * scale);
      c.getContext('2d').drawImage(img, 0, 0, c.width, c.height);
      URL.revokeObjectURL(img.src);
      resolve(c.toDataURL('image/jpeg', 0.85));
    };
    img.onerror = () => reject(new Error(`Couldn’t read ${file.name}.`));
    img.src = URL.createObjectURL(file);
  });
}

export function homeComposer() {
  const prefs = loadPrefs();
  const state = { meta: null, info: null, targets: new Set(prefs.targets || []), mode: prefs.mode || 'draft', images: [], busy: false, recording: null };

  const ta = h('textarea', {
    class: 'sbcp-input', rows: 1, enterkeyhint: 'send', maxlength: MAX, 'data-testid': 'composer-input',
    'aria-label': 'What do you want to post about?', placeholder: 'Let’s create… a post for any of your networks',
  });
  const left = h('span', { class: 'sbcp-left', 'aria-live': 'polite' });
  const sendBtn = h('button', { type: 'button', class: 'sbcp-send', disabled: true, 'aria-label': 'Send', title: 'Send (Enter)' }, svg('send'));
  const attachments = h('div', { class: 'ms-attachments', hidden: true });
  const thread = h('div', { class: 'ms-thread', 'aria-live': 'polite' });
  const providerLabel = h('span', { class: 'bw-tc-label' }, 'Networks');

  const fileAudio = h('input', { type: 'file', hidden: true, accept: AUDIO_ACCEPT, 'aria-label': 'Upload audio file' });
  const fileImage = h('input', { type: 'file', hidden: true, accept: 'image/*', multiple: true, 'aria-label': 'Upload image file' });
  const fileCapture = h('input', { type: 'file', hidden: true, accept: 'image/*', capture: 'environment', 'aria-label': 'Capture photo' });

  // ---- grows with the text, Enter sends, the counter shows in the last tenth ----
  function sync() {
    ta.style.height = 'auto';
    ta.style.height = `${Math.min(220, Math.max(44, ta.scrollHeight))}px`;
    const n = ta.value.length;
    left.textContent = n >= MAX * 0.9 ? `${(MAX - n).toLocaleString()} left` : '';
    left.classList.toggle('is-low', n >= MAX * 0.97);
    sendBtn.disabled = state.busy || ta.value.trim().length < 3 || !state.targets.size;
    const names = [...new Set([...state.targets].map((k) => state.meta?.platforms[k.split('.')[0]]?.label).filter(Boolean))];
    providerLabel.textContent = !state.meta ? 'Networks' : names.length > 2 ? `${names.length} networks` : names.join(' + ') || 'Pick networks';
  }
  ta.addEventListener('input', sync);
  ta.addEventListener('keydown', (e) => {
    if (e.key === 'Enter' && !e.shiftKey && !e.isComposing) { e.preventDefault(); if (!sendBtn.disabled) send(); }
  });

  function drawAttachments() {
    clear(attachments).append(state.images.map((src, i) => h('span', { class: 'ms-chip' },
      h('img', { src, alt: `Attached image ${i + 1}` }),
      h('button', { type: 'button', class: 'ms-chip-x', 'aria-label': 'Remove image', onclick: () => { state.images.splice(i, 1); drawAttachments(); } }, '×'))));
    attachments.hidden = !attachments.children.length;
  }

  // ---- audio and dictation go through the transcription endpoint ----
  async function transcribeInto(blob, name) {
    const chip = h('span', { class: 'sbcp-chip-ctx' }, `Transcribing ${name}…`);
    attachments.hidden = false;
    attachments.append(chip);
    try {
      const fd = new FormData();
      fd.append('file', blob, name);
      const r = await apiUpload('transcribe', fd);
      ta.value = `${ta.value.trim()}${ta.value.trim() ? '\n\n' : ''}${r.transcript}`.slice(0, MAX);
      sync();
      ta.focus();
    } catch (err) {
      toast(err.message, 'err');
    } finally {
      chip.remove();
      attachments.hidden = !attachments.children.length;
    }
  }
  fileAudio.addEventListener('change', () => { const f = fileAudio.files[0]; if (f) transcribeInto(f, f.name); fileAudio.value = ''; });
  const addImages = async (files) => {
    for (const f of [...files].slice(0, 4 - state.images.length)) {
      try { state.images.push(await imageToDataUrl(f)); } catch (err) { toast(err.message, 'err'); }
    }
    if (files.length + state.images.length > 4) toast('Up to 4 images per request.');
    drawAttachments();
  };
  fileImage.addEventListener('change', () => { addImages(fileImage.files); fileImage.value = ''; });
  fileCapture.addEventListener('change', () => { addImages(fileCapture.files); fileCapture.value = ''; });

  const voiceBtn = h('button', { type: 'button', class: 'sbcp-icon sbcp-voice', 'aria-pressed': 'false', 'aria-label': 'Start voice recording', title: 'Start voice recording' }, svg('mic'));
  const setRec = (on) => {
    voiceBtn.classList.toggle('is-rec', on);
    voiceBtn.setAttribute('aria-pressed', String(on));
    voiceBtn.title = on ? 'Stop and transcribe' : 'Start voice recording';
    voiceBtn.setAttribute('aria-label', voiceBtn.title);
  };
  voiceBtn.addEventListener('click', async () => {
    if (state.recording) { state.recording.stop(); return; }
    if (!navigator.mediaDevices?.getUserMedia || !window.MediaRecorder) { toast('Dictation needs a browser with microphone access (on http://localhost or HTTPS).', 'err'); return; }
    try {
      const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
      const rec = new MediaRecorder(stream);
      const chunks = [];
      rec.ondataavailable = (e) => { if (e.data.size) chunks.push(e.data); };
      rec.onstop = () => {
        stream.getTracks().forEach((t) => t.stop());
        state.recording = null;
        setRec(false);
        if (chunks.length) transcribeInto(new Blob(chunks, { type: rec.mimeType || 'audio/webm' }), 'dictation.webm');
      };
      rec.start();
      state.recording = rec;
      setRec(true);
    } catch (err) {
      toast(err.name === 'NotAllowedError' ? 'Microphone access was blocked. Allow it in the browser to dictate.' : err.message, 'err');
    }
  });

  // ---- attach menu ----
  const attachBtn = h('button', { type: 'button', class: 'sbcp-icon sbcp-attach', 'aria-haspopup': 'menu', 'aria-expanded': 'false', 'aria-label': 'Add audio or image', title: 'Add audio or image' }, svg('plus'));
  const attachAnchor = h('div', { class: 'bw-tc-anchor' }, attachBtn);
  popover(attachAnchor, attachBtn, (close) => h('div', { class: 'bw-tc-list ms-pop-list' },
    h('button', { type: 'button', role: 'menuitem', class: 'bw-tc-item', onclick: () => { close(); fileAudio.click(); } }, h('span', {}, 'Upload audio'), h('small', {}, 'transcribed into the box')),
    h('button', { type: 'button', role: 'menuitem', class: 'bw-tc-item', onclick: () => { close(); fileImage.click(); } }, h('span', {}, 'Add images'), h('small', {}, 'up to 4, described for the drafts')),
    h('button', { type: 'button', role: 'menuitem', class: 'bw-tc-item', onclick: () => { close(); fileCapture.click(); } }, h('span', {}, 'Take a photo'), h('small', {}, 'phone camera'))));

  // ---- network picker, in the model-picker style ----
  const providerBtn = h('button', {
    type: 'button', class: 'bw-btn bw-btn-ghost bw-tc-provider', 'aria-haspopup': 'menu', 'aria-expanded': 'false', title: 'Which networks and formats to write for',
  },
    h('i', { class: 'bw-tc-mark', 'aria-hidden': 'true', html: '<svg viewBox="0 0 24 24" width="14" height="14" fill="currentColor" aria-hidden="true"><path d="M12 2l2.4 6.6L21 11l-5.4 4.5L17 22l-5-3.8-5 3.8 1.4-6.5L3 11l6.6-2.4z"></path></svg>' }),
    providerLabel,
    h('span', { class: 'bw-tc-caret-wrap', html: '<svg class="bw-tc-caret" viewBox="0 0 24 24" width="12" height="12" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2"><path d="m7 10 5 5 5-5"></path></svg>' }));
  const providerAnchor = h('div', { class: 'bw-tc-anchor' }, providerBtn);
  popover(providerAnchor, providerBtn, () => {
    if (!state.meta) return h('p', { class: 'bw-tc-empty' }, 'Loading…');
    const direction = h('textarea', { class: 'bw-tc-search ms-direction', rows: 2, placeholder: 'Standing direction, e.g. “playful, mention the newsletter”' }, prefs.direction || '');
    direction.addEventListener('input', () => { prefs.direction = direction.value; savePrefs({ direction: direction.value.trim() }); });
    return [
      h('div', { class: 'bw-tc-list' }, Object.entries(state.meta.platforms).map(([p, info]) => [
        h('div', { class: 'bw-tc-group' }, icon(PLATFORM_ICONS[p], 13), info.label),
        Object.entries(info.formats).map(([f, fi]) => {
          const key = `${p}.${f}`;
          const cb = h('input', {
            type: 'checkbox', checked: state.targets.has(key),
            onchange: () => { if (cb.checked) state.targets.add(key); else state.targets.delete(key); savePrefs({ targets: [...state.targets] }); sync(); },
          });
          return h('label', { class: `bw-tc-item ms-check${state.targets.has(key) ? ' is-on' : ''}`, onchange: (e) => e.currentTarget.classList.toggle('is-on', cb.checked) }, h('span', { class: 'row' }, cb, fi.label), fi.parts ? h('small', {}, `with ${fi.parts.toLowerCase()}`) : null);
        }),
      ])),
      h('div', { class: 'bw-tc-custom ms-pop-foot' }, direction),
      h('p', { class: 'bw-tc-empty' }, `Written by ${state.info.engine} · ${state.info.model}. `, link('/settings', {}, 'Change in Settings')),
    ];
  }, { wide: true });

  // ---- mode: full drafts, or quick ideas to flesh out later ----
  const MODES = [
    ['draft', 'Drafts', 'Drafts: full posts, ready to edit and schedule'],
    ['idea', 'Ideas', 'Ideas: lighter posts saved as ideas for later'],
    ['week', 'Week', 'Week: a week of posts from your weekly targets, each scheduled in its next good slot'],
  ];
  const modeBtns = MODES.map(([m, label, title]) => h('button', {
    type: 'button', 'aria-pressed': String(state.mode === m), title,
    onclick: () => { state.mode = m; savePrefs({ mode: m }); modeBtns.forEach((b, i) => b.setAttribute('aria-pressed', String(MODES[i][0] === m))); sync(); },
  }, label));

  // ---- send: one request, a draft per network, shown inline ----
  async function send() {
    const prompt = ta.value.trim();
    if (prompt.length < 3) return;
    const images = [...state.images];
    const turn = h('div', { class: 'ms-turn' },
      h('div', { class: 'ms-bubble' }, prompt.length > 600 ? `${prompt.slice(0, 600)}…` : prompt,
        images.length ? h('div', { class: 'ms-bubble-images' }, images.map((src) => h('img', { src, alt: '' }))) : null),
      h('div', { class: 'ms-answer' }, h('p', { class: 'muted small' }, state.mode === 'week' ? 'Planning your week and finding a good slot for each post…' : `Writing for ${providerLabel.textContent}…`), skCards(3, 'your drafts')));
    thread.append(turn);
    turn.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    ta.value = '';
    state.images = [];
    drawAttachments();
    state.busy = true;
    sync();
    try {
      const targets = [...state.targets].map((k) => { const [platform, format] = k.split('.'); return { platform, format }; });
      const extra = (loadPrefs().direction || '').trim() || null;
      const r = state.mode === 'week'
        // The week follows her weekly targets; the picked formats decide which format per network.
        ? await api('POST', 'social/plan-week', { brief: prompt, extra, formats: Object.fromEntries(targets.map((t) => [t.platform, t.format])) })
        : await api('POST', 'social/compose', { prompt, images, extra, status: state.mode, targets });
      clear(turn.querySelector('.ms-answer')).append(
        h('div', { class: 'ms-answer-head' }, h('strong', {}, r.message),
          link('/calendar', { class: 'btn sm' }, icon('calendar', 15), 'Schedule them')),
        r.note ? h('p', { class: 'small warn-text' }, r.note) : null,
        h('div', { class: 'post-grid' }, r.posts.map((p) => {
          const hard = p.checks.filter((c) => c.severity === 'HARD').length;
          return h('div', {}, postCard(p, state.meta), hard ? h('p', { class: 'small error-text' }, `${hard} thing(s) to fix before posting`) : null);
        })));
    } catch (err) {
      clear(turn.querySelector('.ms-answer')).append(h('p', { class: 'error-text' }, err.message,
        /key|api/i.test(err.message) ? [' ', link('/settings', {}, 'Open Settings')] : null));
      ta.value = prompt;
    } finally {
      state.busy = false;
      sync();
    }
  }
  sendBtn.addEventListener('click', send);

  Promise.all([api('GET', 'social/meta'), api('GET', 'social/composer')]).then(([meta, info]) => {
    state.meta = meta;
    state.info = info;
    if (!state.targets.size) info.targets.forEach((t) => state.targets.add(t));
    if (!info.ai_ready) providerBtn.title = 'Add an AI key in Settings to start creating';
    sync();
  }).catch(() => { providerLabel.textContent = 'Unavailable'; });
  sync();

  return h('section', { class: 'card ms-card', 'aria-label': 'Create posts' },
    thread,
    h('div', { class: 'sbcp-box', 'data-testid': 'composer-card' },
      attachments, ta,
      h('div', { class: 'sbcp-bar' },
        h('div', { class: 'sbcp-bar-l' }, attachAnchor, fileAudio, fileImage, fileCapture,
          h('div', { class: 'sbcp-model' }, providerAnchor),
          h('div', { class: 'sbcp-mode', role: 'group', 'aria-label': 'Mode' }, modeBtns)),
        h('div', { class: 'sbcp-bar-r' }, left, voiceBtn, sendBtn))),
    h('div', { class: 'ms-disclaimer' },
      h('span', { class: 'ms-disclaimer-badge', 'aria-hidden': 'true' }, 'AI'),
      h('span', {}, 'AI systems on this platform are operated in line with the EU AI Act. Content generated with AI is labelled where required, and human oversight applies to high-impact decisions. ',
        h('em', {}, 'AI can make mistakes - check important information.')),
      h('a', { href: 'https://digital-strategy.ec.europa.eu/en/policies/regulatory-framework-ai', target: '_blank', rel: 'noopener noreferrer' }, 'EU AI Act framework ', icon('external', 14))));
}
