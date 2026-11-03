// Dictate (Flow): hold Space (or the button), ramble, release; get a clean note.
// Audio streams straight from the browser to AssemblyAI with a short-lived token minted by
// the server, so the API key never reaches the page.
import { h, api, busy, toast, pageHead, clear, mdBlock, copyText, timeAgo, confirmDialog, navigate } from '../core.js';
import { icon } from '../icons.js';
import { celebrate } from '../love.js';

const SAMPLE_RATE = 16000;

// Runs on the audio thread: downmix to mono, box-filter downsample to 16 kHz,
// convert to 16-bit PCM and post 50 ms chunks (800 samples).
const WORKLET = `
class Pcm16 extends AudioWorkletProcessor {
  constructor() { super(); this.ratio = sampleRate / ${SAMPLE_RATE}; this.t = 0; this.acc = 0; this.cnt = 0;
                  this.out = new Int16Array(800); this.n = 0; }
  process(inputs) {
    const ch = inputs[0] && inputs[0][0];
    if (!ch) return true;
    for (let i = 0; i < ch.length; i++) {
      this.acc += ch[i]; this.cnt++; this.t += 1;
      if (this.t >= this.ratio) {
        this.t -= this.ratio;
        const s = Math.max(-1, Math.min(1, this.acc / this.cnt));
        this.acc = 0; this.cnt = 0;
        this.out[this.n++] = s < 0 ? s * 0x8000 : s * 0x7fff;
        if (this.n === this.out.length) {
          this.port.postMessage(this.out.buffer, [this.out.buffer]);
          this.out = new Int16Array(800); this.n = 0;
        }
      }
    }
    return true;
  }
}
registerProcessor("pcm16", Pcm16);`;
let workletUrl = null;

const STYLES = [
  ['auto', 'Auto'], ['idea', 'Video idea'], ['notes', 'Notes'], ['message', 'Message'], ['email', 'Email'],
];
const REWRITES = [['shorter', 'Shorter'], ['friendly', 'Friendlier'], ['formal', 'More formal'], ['grammar', 'Fix grammar only']];

export async function renderFlow(view) {
  const notesRes = await api('GET', 'notes');
  let notes = notesRes.notes;

  let session = null;
  let busyFinishing = false;
  let polished = '';
  let rawText = '';
  let style = localStorageGet('flow-style') || 'idea';

  // ---- UI ----
  const statusBadge = h('span', { class: 'badge' }, 'idle');
  const latencyBadge = h('span', { class: 'badge', hidden: true });
  const rawPane = h('div', { class: 'speech-pane raw', 'aria-live': 'polite' }, h('span', { class: 'muted' }, 'Your words appear here as you speak.'));
  const polishedPane = h('div', { class: 'speech-pane polished', 'aria-live': 'polite' }, h('span', { class: 'muted' }, 'The clean version lands here.'));
  const errorBox = h('div', { class: 'callout bad', hidden: true, role: 'alert' });
  const talkBtn = h('button', { class: 'talk-button', type: 'button' }, icon('mic', 26), h('span', {}, 'Hold Space or click to talk'));

  const styleSelect = h('div', { class: 'segmented', role: 'radiogroup', 'aria-label': 'Output style' }, STYLES.map(([key, label]) => h('button', {
    class: `seg${key === style ? ' active' : ''}`, type: 'button', role: 'radio', 'aria-checked': String(key === style),
    onclick: (e) => {
      style = key;
      localStorageSet('flow-style', key);
      for (const b of styleSelect.children) { b.classList.toggle('active', b === e.currentTarget); b.setAttribute('aria-checked', String(b === e.currentTarget)); }
    },
  }, label)));

  const actionsRow = h('div', { class: 'row flow-actions' });
  const typed = h('textarea', { rows: 4, placeholder: 'No mic handy? Type or paste messy text here and polish it.' });
  const typedBtn = h('button', {
    class: 'btn sm',
    onclick: () => busy(typedBtn, 'Polishing…', async () => {
      if (!typed.value.trim()) throw new Error('Type something first.');
      rawText = typed.value.trim();
      clear(rawPane).append(rawText);
      await polish({ text: rawText, style });
    }),
  }, icon('sparkle', 15), 'Polish text');

  const notesBox = h('div');

  function showError(msg) {
    errorBox.hidden = !msg;
    errorBox.textContent = msg || '';
  }
  function setStatus(text, good) {
    statusBadge.textContent = text;
    statusBadge.classList.toggle('good', !!good);
  }
  function setRecordingUi(on) {
    talkBtn.classList.toggle('recording', on);
    talkBtn.lastChild.textContent = on ? 'Listening… release to finish' : 'Hold Space or click to talk';
  }

  function renderRaw(turns) {
    const keys = Object.keys(turns).map(Number).sort((a, b) => a - b);
    clear(rawPane);
    if (!keys.length) { rawPane.append(h('span', { class: 'muted' }, 'Listening…')); return; }
    for (const k of keys) rawPane.append(h('span', { class: turns[k].final ? '' : 'partial' }, turns[k].text, ' '));
    rawPane.scrollTop = rawPane.scrollHeight;
  }

  function drawActions() {
    clear(actionsRow);
    if (!polished) return;
    actionsRow.append(
      h('button', { class: 'btn primary sm', onclick: () => copyText(polished) }, icon('copy', 15), 'Copy'),
      h('button', { class: 'btn sm', onclick: (e) => saveNote(e.currentTarget, false) }, icon('check', 15), 'Save note'),
      h('button', { class: 'btn sm', onclick: (e) => saveNote(e.currentTarget, true) }, icon('bulb', 15), 'Save as idea'),
      h('span', { class: 'divider' }),
      REWRITES.map(([key, label]) => {
        const b = h('button', { class: 'btn ghost sm', onclick: () => busy(b, '…', () => polish({ text: polished, rewrite: key })) }, label);
        return b;
      }));
  }

  async function polish(payload) {
    showError('');
    setStatus('polishing…');
    const t0 = performance.now();
    clear(polishedPane).append(h('span', { class: 'loading' }, h('span', { class: 'spinner' }), 'Polishing…'));
    try {
      const r = await api('POST', 'dictate/polish', payload);
      polished = r.polished;
      clear(polishedPane).append(mdBlock(polished));
      latencyBadge.hidden = false;
      latencyBadge.textContent = `polish ${Math.round(performance.now() - t0)} ms`;
      setStatus('done', true);
    } catch (err) {
      // Never lose a take: fall back to the raw words so they can still be copied.
      if (!polished && rawText) {
        polished = rawText;
        clear(polishedPane).append(mdBlock(rawText));
      } else if (polished) {
        clear(polishedPane).append(mdBlock(polished));
      }
      setStatus('idle');
      showError(`${err.message}${rawText ? ' Your raw transcript is kept, so you can still copy it.' : ''}`);
    }
    drawActions();
  }

  async function saveNote(btn, asIdea) {
    await busy(btn, 'Saving…', async () => {
      const r = await api('POST', 'notes', { raw: rawText || null, polished, style });
      notes = [r.note, ...notes];
      if (asIdea) {
        const ir = await api('POST', `notes/${r.note.id}/to-idea`);
        toast(ir.message);
        celebrate('idea');
      } else {
        toast('Note saved.');
      }
      drawNotes();
    });
  }

  function drawNotes() {
    clear(notesBox);
    if (!notes.length) {
      notesBox.append(h('p', { class: 'muted' }, 'Saved notes show up here.'));
      return;
    }
    notesBox.append(h('div', { class: 'notes-grid' }, notes.map((n) => h('article', { class: 'note-card' },
      h('div', { class: 'note-meta' }, h('span', { class: 'pill' }, n.style), h('span', { class: 'muted' }, timeAgo(n.created_at))),
      h('div', { class: 'note-body' }, mdBlock(n.polished)),
      h('div', { class: 'row' },
        h('button', { class: 'btn ghost sm', onclick: () => copyText(n.polished) }, icon('copy', 14), 'Copy'),
        h('button', {
          class: 'btn ghost sm',
          onclick: async () => {
            try { const r = await api('POST', `notes/${n.id}/to-idea`); toast(r.message); } catch (e) { toast(e.message, 'err'); }
          },
        }, icon('bulb', 14), 'To idea'),
        h('button', {
          class: 'btn ghost sm danger',
          onclick: async () => {
            if (!(await confirmDialog('Delete this note?'))) return;
            try { await api('DELETE', `notes/${n.id}`); notes = notes.filter((x) => x.id !== n.id); drawNotes(); } catch (e) { toast(e.message, 'err'); }
          },
        }, icon('trash', 14)))))));
  }

  // ---- streaming ----
  function teardown(s) {
    s.stream?.getTracks().forEach((t) => t.stop());
    s.ctx?.close().catch(() => {});
  }

  function connect(s, tok) {
    const params = new URLSearchParams({ sample_rate: SAMPLE_RATE, encoding: 'pcm_s16le', format_turns: 'false', token: tok.token });
    const ws = new WebSocket(`${tok.stream_url}?${params}`);
    ws.binaryType = 'arraybuffer';
    s.ws = ws;
    s.closed = new Promise((resolve) => ws.addEventListener('close', resolve));
    // Ready = AssemblyAI sent Begin. Audio captured before then is queued and flushed.
    s.ready = new Promise((resolve, reject) => {
      ws.onerror = () => reject(new Error("Couldn't connect to AssemblyAI streaming."));
      ws.onclose = (e) => {
        reject(new Error(`Streaming closed (${e.code}) ${e.reason || ''}`));
        if (e.code !== 1000 && e.code !== 1005 && session === s && s.live) showError(`Streaming closed (${e.code}) ${e.reason || ''}`);
      };
      ws.onmessage = (e) => {
        const m = JSON.parse(e.data);
        if (m.type === 'Begin') {
          s.live = true;
          s.pending.forEach((c) => ws.send(c));
          s.pending = [];
          resolve();
        } else if (m.type === 'Turn') {
          s.turns[m.turn_order] = { text: m.transcript, final: m.end_of_turn };
          s.openTurn = !m.end_of_turn;
          if (m.end_of_turn && s.onFinal) s.onFinal();
          renderRaw(s.turns);
        } else if (m.type === 'Error') {
          showError(`AssemblyAI: ${m.error}`);
        }
      };
    });
    s.ready.catch(() => {});
  }

  async function start() {
    if (session || busyFinishing) return;
    showError('');
    polished = '';
    rawText = '';
    drawActions();
    clear(polishedPane).append(h('span', { class: 'muted' }, 'The clean version lands here.'));
    latencyBadge.hidden = true;

    const s = { turns: {}, openTurn: false, onFinal: null, pending: [], live: false };
    session = s;
    setRecordingUi(true);
    setStatus('connecting…');
    renderRaw(s.turns);

    // The mic starts right away and queues audio while the token and socket connect.
    s.opened = api('GET', 'dictate/token').then((tok) => { connect(s, tok); return s.ready; })
      .then(() => { if (session === s) setStatus('listening', true); });
    s.opened.catch(() => {});

    try {
      if (!navigator.mediaDevices?.getUserMedia) throw new Error('This browser has no microphone access here. Use http://localhost or https.');
      s.stream = await navigator.mediaDevices.getUserMedia({ audio: { channelCount: 1, echoCancellation: true, noiseSuppression: true } });
      if (session !== s) { teardown(s); return; }
      s.ctx = new AudioContext();
      workletUrl ??= URL.createObjectURL(new Blob([WORKLET], { type: 'application/javascript' }));
      await s.ctx.audioWorklet.addModule(workletUrl);
      const source = s.ctx.createMediaStreamSource(s.stream);
      const node = new AudioWorkletNode(s.ctx, 'pcm16');
      node.port.onmessage = (e) => {
        if (s.live && s.ws?.readyState === WebSocket.OPEN) s.ws.send(e.data); else s.pending.push(e.data);
      };
      const mute = s.ctx.createGain();
      mute.gain.value = 0; // keeps the graph pulling audio without playing it back
      source.connect(node).connect(mute).connect(s.ctx.destination);
      s.micOn = true;
      await s.opened;
    } catch (err) {
      if (session !== s) return;
      teardown(s);
      try { s.ws?.close(); } catch { /* already closed */ }
      session = null;
      setRecordingUi(false);
      setStatus('idle');
      showError(err.message || String(err));
    }
  }

  async function stop() {
    const s = session;
    if (!s) return;
    session = null;
    busyFinishing = true;
    setRecordingUi(false);
    const stoppedAt = performance.now();
    teardown(s);

    try {
      if (!s.micOn) throw new Error("The microphone hadn't started yet, so nothing was recorded. Allow mic access and try again.");
      setStatus('finalizing…');
      await Promise.race([s.opened, new Promise((_, rej) => setTimeout(() => rej(new Error('Couldn’t reach the transcription service. Check your AssemblyAI key in Settings.')), 30000))]);
      // Ask AssemblyAI to close the current turn now instead of waiting for silence.
      const gotFinal = new Promise((resolve) => { s.onFinal = resolve; });
      s.ws.send(JSON.stringify({ type: 'ForceEndpoint' }));
      await Promise.race([gotFinal, new Promise((r) => setTimeout(r, s.openTurn ? 2500 : 900))]);
      s.ws.send(JSON.stringify({ type: 'Terminate' }));
      await Promise.race([s.closed, new Promise((r) => setTimeout(r, 1500))]);

      rawText = Object.keys(s.turns).map(Number).sort((a, b) => a - b).map((k) => s.turns[k].text).join(' ').trim();
      for (const k of Object.keys(s.turns)) s.turns[k].final = true;
      renderRaw(s.turns);
      latencyBadge.hidden = false;
      latencyBadge.textContent = `final ${Math.round(performance.now() - stoppedAt)} ms`;
      if (!rawText) throw new Error("Didn't catch any speech. Check your mic and try again.");
      await polish({ text: rawText, style });
    } catch (err) {
      setStatus('idle');
      showError(err.message || String(err));
    } finally {
      try { s.ws?.close(); } catch { /* already closed */ }
      busyFinishing = false;
    }
  }

  // Click toggles; Space is push-to-talk (ignored while typing in a field).
  talkBtn.addEventListener('click', () => { if (session) stop(); else start(); });
  const isTyping = (e) => ['INPUT', 'TEXTAREA', 'SELECT'].includes(e.target.tagName) || e.target.isContentEditable;
  const onDown = (e) => {
    if (e.code !== 'Space' || e.repeat || isTyping(e) || document.querySelector('.modal-overlay')) return;
    e.preventDefault();
    start();
  };
  const onUp = (e) => {
    if (e.code !== 'Space' || isTyping(e)) return;
    e.preventDefault();
    stop();
  };
  document.addEventListener('keydown', onDown);
  document.addEventListener('keyup', onUp);

  view.append(
    pageHead('Dictate', 'Ramble an idea out loud. You get the raw words live, then a clean note you can save, copy or turn into a video idea.'),
    h('div', { class: 'card flow-card' },
      h('div', { class: 'row-between wrap', style: { marginBottom: '14px' } }, styleSelect, h('div', { class: 'row' }, statusBadge, latencyBadge)),
      talkBtn,
      errorBox,
      h('div', { class: 'dictate-box', style: { marginTop: '18px' } },
        h('div', {}, h('div', { class: 'pane-label' }, 'Raw'), rawPane),
        h('div', {}, h('div', { class: 'pane-label' }, 'Polished'), polishedPane)),
      actionsRow),
    h('details', { class: 'card' }, h('summary', {}, h('strong', {}, 'Type instead')),
      h('div', { style: { marginTop: '12px' } }, typed, h('div', { class: 'row', style: { marginTop: '8px' } }, typedBtn))),
    h('div', { class: 'card' },
      h('div', { class: 'card-header' }, h('h2', {}, 'Saved notes'), h('button', { class: 'btn ghost sm', onclick: () => navigate('/ideas') }, 'Ideas', icon('arrow', 14))),
      notesBox));

  drawNotes();

  return () => {
    document.removeEventListener('keydown', onDown);
    document.removeEventListener('keyup', onUp);
    if (session) { teardown(session); try { session.ws?.close(); } catch { /* closed */ } session = null; }
  };
}

function localStorageGet(k) { try { return localStorage.getItem(k); } catch { return null; } }
function localStorageSet(k, v) { try { localStorage.setItem(k, v); } catch { /* private mode */ } }
