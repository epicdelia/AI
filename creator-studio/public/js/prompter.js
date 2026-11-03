// Teleprompter: the script full-screen, scrolling at a spoken pace, for filming.
// Speed is in words per minute, so "time left" is a real estimate of the take.
import { h, clear } from './core.js';
import { icon } from './icons.js';

const PREFS_KEY = 'studio-prompter';
const DEFAULTS = { wpm: 150, size: 54, mirror: false, cues: false };
const READ_LINE = 0.33; // the reading line sits a third of the way down the screen

// The sections the script writer appends after the script itself (titles, chapters, claims).
const TAIL = /^#{1,3}\s*(title options|thumbnail( text)? options|chapters|claims list)\b/im;
// Directions for the editor, not words to say.
const CUE = /\[(?:VISUAL|B-?ROLL|SFX|MUSIC|CUT|GRAPHIC|ON[- ]?SCREEN|TEXT ON SCREEN|SCREEN|EDIT|ZOOM)\b[^\]]*\]/gi;

function plain(s) {
  return s
    .replace(/^\s*(?:[-*+]|\d+[.)])\s+/, '')
    .replace(/^\s*>\s?/, '')
    .replace(/\*\*|__|`/g, '')
    .replace(/(^|[\s(])[*_]([^*_\n]+)[*_](?=[\s.,!?;:)]|$)/g, '$1$2');
}

/** The script as blocks to read: {type: 'heading' | 'text' | 'cue', text}. */
export function prompterBlocks(md, { cues = false } = {}) {
  let text = String(md || '').replace(/\r\n/g, '\n');
  const tail = text.search(TAIL);
  if (tail > 0) text = text.slice(0, tail);

  const blocks = [];
  let para = [];
  const flush = () => {
    const t = para.join(' ').replace(/\s+/g, ' ').trim();
    if (t) blocks.push({ type: 'text', text: t });
    para = [];
  };
  for (const line of text.split('\n')) {
    const s = line.trim();
    if (!s || /^(-{3,}|\*{3,}|_{3,})$/.test(s)) { flush(); continue; }
    const head = s.match(/^#{1,6}\s+(.*)$/);
    if (head) { flush(); blocks.push({ type: 'heading', text: plain(head[1]) }); continue; }
    const spoken = plain(s.replace(CUE, ' ')).replace(/\s+/g, ' ').trim();
    if (cues) {
      for (const c of s.match(CUE) || []) { flush(); blocks.push({ type: 'cue', text: c }); }
    }
    if (!spoken) continue;
    // A list item reads as its own line, not run into the one before.
    if (/^(?:[-*+]|\d+[.)])\s+/.test(s)) { flush(); para.push(spoken); flush(); } else para.push(spoken);
  }
  flush();
  return blocks;
}

function loadPrefs() {
  try { return { ...DEFAULTS, ...JSON.parse(localStorage.getItem(PREFS_KEY) || '{}') }; } catch { return { ...DEFAULTS }; }
}
function savePrefs(p) {
  try { localStorage.setItem(PREFS_KEY, JSON.stringify(p)); } catch { /* private mode: prefs just aren't remembered */ }
}

const countWords = (s) => (s.match(/[\p{L}\p{N}'’-]+/gu) || []).length;
const clock = (sec) => `${Math.floor(sec / 60)}:${String(Math.max(0, Math.round(sec % 60))).padStart(2, '0')}`;

/** [YOUR STORY: …] and [VERIFY] stay visible and highlighted: she has to deal with them on camera. */
function renderText(text) {
  return text.split(/(\[[^\]]+\])/).filter(Boolean).map((part) => (part.startsWith('[') ? h('mark', { class: 'pp-mark' }, part) : part));
}

/** Open the teleprompter over the page. Resolves when it's closed. */
export function openPrompter(markdown, { title = '' } = {}) {
  return new Promise((resolve) => {
    const prefs = loadPrefs();
    let blocks = [];
    let words = 0;
    let pos = 0;
    let playing = false;
    let counting = null;
    let last = 0;
    let lastDraw = 0;
    let raf = 0;
    let wakeLock = null;
    let idleTimer = 0;

    const inner = h('div', { class: 'pp-inner' });
    const scroller = h('div', { class: 'pp-scroll' }, inner);
    const countdown = h('div', { class: 'pp-countdown', hidden: true });
    const info = h('span', { class: 'pp-info' });
    const wpmLabel = h('span', { class: 'pp-value' });
    const sizeLabel = h('span', { class: 'pp-value' });
    const progress = h('div', { class: 'pp-progress-bar' });
    const playBtn = h('button', { class: 'pp-btn pp-play', onclick: () => toggle(), title: 'Play / pause (Space)' });
    const mirrorBtn = h('button', { class: 'pp-btn', onclick: () => { prefs.mirror = !prefs.mirror; apply(); }, title: 'Mirror for a beam-splitter rig (M)' }, 'Mirror');
    const cuesBtn = h('button', { class: 'pp-btn', onclick: () => { prefs.cues = !prefs.cues; build(true); }, title: 'Show [VISUAL] and [B-ROLL] notes (C)' }, 'Cues');

    const bar = h('div', { class: 'pp-bar' },
      playBtn,
      h('div', { class: 'pp-group', title: 'Speed (↑ / ↓)' },
        h('button', { class: 'pp-btn', onclick: () => speed(-10), 'aria-label': 'Slower' }, '−'), wpmLabel,
        h('button', { class: 'pp-btn', onclick: () => speed(10), 'aria-label': 'Faster' }, '+')),
      h('div', { class: 'pp-group', title: 'Text size (− / +)' },
        h('button', { class: 'pp-btn', onclick: () => size(-6), 'aria-label': 'Smaller text' }, 'A−'), sizeLabel,
        h('button', { class: 'pp-btn', onclick: () => size(6), 'aria-label': 'Bigger text' }, 'A+')),
      mirrorBtn, cuesBtn,
      h('button', { class: 'pp-btn', onclick: restart, title: 'Back to the start (Home)' }, icon('refresh', 16)),
      info,
      h('span', { class: 'pp-title' }, title),
      h('button', { class: 'pp-btn', onclick: close, title: 'Close (Esc)', 'aria-label': 'Close teleprompter' }, icon('x', 18)));

    const overlay = h('div', { class: 'prompter', role: 'dialog', 'aria-modal': 'true', 'aria-label': 'Teleprompter' },
      bar, scroller, h('div', { class: 'pp-line', 'aria-hidden': 'true' }), countdown,
      h('div', { class: 'pp-progress' }, progress),
      h('div', { class: 'pp-keys' }, 'Space play · ↑↓ speed · ←→ previous/next paragraph · +− size · M mirror · Esc close'));

    function build(keepPlace = false) {
      const frac = keepPlace ? fraction() : 0;
      blocks = prompterBlocks(markdown, { cues: prefs.cues });
      words = blocks.filter((b) => b.type === 'text').reduce((n, b) => n + countWords(b.text), 0);
      clear(inner).append(...(blocks.length ? blocks : [{ type: 'heading', text: 'Nothing to read yet.' }]).map((b) => (
        b.type === 'heading' ? h('div', { class: 'pp-heading' }, b.text)
          : b.type === 'cue' ? h('div', { class: 'pp-cue' }, b.text)
            : h('p', { class: 'pp-text' }, renderText(b.text)))));
      apply();
      pos = frac * maxPos();
      scroller.scrollTop = pos;
      draw();
    }

    function apply() {
      inner.style.fontSize = `${prefs.size}px`;
      scroller.classList.toggle('mirrored', prefs.mirror);
      mirrorBtn.classList.toggle('on', prefs.mirror);
      cuesBtn.classList.toggle('on', prefs.cues);
      wpmLabel.textContent = `${prefs.wpm} wpm`;
      sizeLabel.textContent = `${prefs.size}`;
      savePrefs(prefs);
      draw();
    }

    const maxPos = () => Math.max(0, scroller.scrollHeight - scroller.clientHeight);
    const fraction = () => (maxPos() ? Math.min(1, pos / maxPos()) : 0);
    // Pixels of script per spoken word, so wpm maps onto scroll speed whatever the text size.
    const pxPerWord = () => (words ? maxPos() / words : 0);

    function draw() {
      const f = fraction();
      progress.style.width = `${(f * 100).toFixed(1)}%`;
      const left = words ? (words * (1 - f)) / prefs.wpm * 60 : 0;
      info.textContent = words ? `${clock(left)} left · ${words.toLocaleString()} words` : '';
      clear(playBtn).append(icon(playing ? 'pause' : 'play', 18), playing ? 'Pause' : 'Play');
      overlay.classList.toggle('playing', playing);
    }

    function frame(t) {
      const dt = last ? Math.min(0.1, (t - last) / 1000) : 0;
      last = t;
      if (playing) {
        pos = Math.min(maxPos(), pos + (prefs.wpm / 60) * pxPerWord() * dt);
        scroller.scrollTop = pos;
        if (pos >= maxPos()) { playing = false; draw(); }
        else if (t - lastDraw > 250) { lastDraw = t; draw(); } // refresh the clock ~4×/s
      }
      raf = requestAnimationFrame(frame);
    }

    function toggle() {
      if (counting) { clearInterval(counting); counting = null; countdown.hidden = true; return; }
      if (playing) { playing = false; draw(); return; }
      if (pos >= maxPos() - 1) pos = 0;
      // A 3-2-1 before a take from the top; resuming mid-script starts straight away.
      if (pos > 4) { playing = true; draw(); return; }
      let n = 3;
      countdown.hidden = false;
      countdown.textContent = n;
      counting = setInterval(() => {
        n -= 1;
        if (n > 0) { countdown.textContent = n; return; }
        clearInterval(counting);
        counting = null;
        countdown.hidden = true;
        playing = true;
        draw();
      }, 800);
    }

    function speed(d) { prefs.wpm = Math.max(60, Math.min(260, prefs.wpm + d)); apply(); }
    function size(d) {
      const f = fraction();
      prefs.size = Math.max(24, Math.min(120, prefs.size + d));
      apply();
      pos = f * maxPos(); // keep her place when the text reflows
      scroller.scrollTop = pos;
    }
    function restart() { playing = false; pos = 0; scroller.scrollTop = 0; draw(); }

    /** Jump to the previous / next paragraph: re-read a line after a flub. */
    function jump(dir) {
      const line = pos + scroller.clientHeight * READ_LINE;
      const tops = [...inner.children].map((el) => el.offsetTop);
      const target = dir < 0
        ? [...tops].reverse().find((t) => t < line - 8)
        : tops.find((t) => t > line + 8);
      if (target === undefined) return;
      pos = Math.max(0, Math.min(maxPos(), target - scroller.clientHeight * READ_LINE));
      scroller.scrollTop = pos;
      draw();
    }

    // Wheel or touch scrolling moves her place; our own scrolling is within a pixel of pos.
    scroller.addEventListener('scroll', () => {
      if (Math.abs(scroller.scrollTop - pos) > 2) { pos = scroller.scrollTop; draw(); }
    });
    scroller.addEventListener('click', () => toggle());

    const onKey = (e) => {
      if (e.ctrlKey || e.metaKey || e.altKey) return;
      const k = e.key;
      const handled = {
        ' ': toggle, k: toggle, K: toggle,
        ArrowUp: () => speed(10), ArrowDown: () => speed(-10),
        ArrowLeft: () => jump(-1), PageUp: () => jump(-1),
        ArrowRight: () => jump(1), PageDown: () => jump(1),
        '+': () => size(6), '=': () => size(6), '-': () => size(-6),
        m: () => { prefs.mirror = !prefs.mirror; apply(); }, M: () => { prefs.mirror = !prefs.mirror; apply(); },
        c: () => { prefs.cues = !prefs.cues; build(true); }, C: () => { prefs.cues = !prefs.cues; build(true); },
        Home: restart, Escape: close,
      }[k];
      if (!handled) return;
      e.preventDefault();
      e.stopPropagation();
      handled();
    };

    // The controls fade while it plays; moving the mouse brings them back.
    const onMove = () => {
      overlay.classList.add('show-controls');
      clearTimeout(idleTimer);
      idleTimer = setTimeout(() => overlay.classList.remove('show-controls'), 2200);
    };
    const onResize = () => { const f = fraction(); pos = f * maxPos(); scroller.scrollTop = pos; draw(); };

    async function lockScreen() {
      try { wakeLock = await navigator.wakeLock?.request('screen'); } catch { /* not supported or refused: the screen may dim */ }
    }
    const onVisible = () => { if (document.visibilityState === 'visible' && wakeLock?.released !== false) lockScreen(); };

    function close() {
      cancelAnimationFrame(raf);
      clearInterval(counting);
      clearTimeout(idleTimer);
      document.removeEventListener('keydown', onKey, true);
      document.removeEventListener('visibilitychange', onVisible);
      window.removeEventListener('resize', onResize);
      overlay.removeEventListener('mousemove', onMove);
      wakeLock?.release?.().catch(() => {});
      if (document.fullscreenElement === overlay) document.exitFullscreen?.().catch(() => {});
      overlay.remove();
      resolve();
    }

    document.addEventListener('keydown', onKey, true);
    document.addEventListener('visibilitychange', onVisible);
    window.addEventListener('resize', onResize);
    overlay.addEventListener('mousemove', onMove);
    document.body.append(overlay);
    overlay.requestFullscreen?.().catch(() => { /* fullscreen refused: the overlay still fills the window */ });
    lockScreen();
    build();
    playBtn.focus();
    raf = requestAnimationFrame(frame);
  });
}
