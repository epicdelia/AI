// Feed preview: her title and thumbnail in a YouTube-style grid next to real breakouts from
// her niche, at desktop or phone width, so she judges the packaging where it's actually seen.
import { h, api, clear, compact, timeAgo, pill } from './core.js';
import { icon } from './icons.js';

// Where YouTube cuts titles off, roughly: search and home on desktop, then phones.
const LIMITS = [[70, 'bad', 'cut off almost everywhere'], [60, 'WARN', 'may be cut on phones']];

function lengthPill(title) {
  const n = [...title].length;
  const hit = LIMITS.find(([max]) => n > max);
  return hit ? pill(hit[1], `${n} chars · ${hit[2]}`) : pill('ok', `${n} chars`);
}

function shuffle(list) {
  const a = [...list];
  for (let i = a.length - 1; i > 0; i--) { const j = Math.floor(Math.random() * (i + 1)); [a[i], a[j]] = [a[j], a[i]]; }
  return a;
}

/** The preview card for the Package tab. `onUseTitle(title)` renames the script. */
export function feedPreview(script, { onUseTitle } = {}) {
  const state = { titles: [script.title], pick: 0, image: null, thumbText: '', device: 'desktop', blind: false, rivals: [], mockups: [] };
  let localUrl = null;

  const feed = h('div', { class: 'fp-feed' });
  const candidates = h('ul', { class: 'fp-candidates' });
  const thumbs = h('div', { class: 'fp-thumbs' });
  const titlesIn = h('textarea', { rows: 3, placeholder: 'One title per line, to compare' }, script.title);
  const thumbTextIn = h('input', { type: 'text', maxlength: 40, placeholder: 'Thumbnail text, if there’s no image yet' });

  titlesIn.addEventListener('input', () => {
    state.titles = titlesIn.value.split('\n').map((t) => t.trim()).filter(Boolean);
    state.pick = Math.min(state.pick, Math.max(0, state.titles.length - 1));
    draw();
  });
  thumbTextIn.addEventListener('input', () => { state.thumbText = thumbTextIn.value; draw(); });

  // A local image never leaves her browser: it's only drawn here.
  const fileIn = h('input', {
    type: 'file', accept: 'image/*', hidden: true,
    onchange: () => {
      const f = fileIn.files[0];
      if (!f) return;
      if (localUrl) URL.revokeObjectURL(localUrl);
      localUrl = URL.createObjectURL(f);
      state.image = localUrl;
      drawThumbs();
      draw();
    },
  });

  function card(v, mine) {
    const img = mine
      ? (state.image ? h('img', { src: state.image, alt: '' }) : h('div', { class: 'fp-fallback' }, state.thumbText || 'Your thumbnail'))
      : h('img', { src: `https://i.ytimg.com/vi/${v.video_id}/mqdefault.jpg`, alt: '', loading: 'lazy', referrerpolicy: 'no-referrer' });
    const name = mine ? (window.__studioName || 'You') : (v.channel_title || 'Channel');
    return h('div', { class: `fp-card${mine && !state.blind ? ' mine' : ''}` },
      h('div', { class: 'fp-thumb' }, img, h('span', { class: 'fp-dur' }, mine ? '12:00' : '')),
      h('div', { class: 'fp-meta' },
        h('div', { class: 'fp-avatar' }, name.slice(0, 1).toUpperCase()),
        h('div', { class: 'fp-text' },
          h('div', { class: 'fp-title' }, mine ? (state.titles[state.pick] || script.title) : v.title),
          h('div', { class: 'fp-sub' }, name),
          h('div', { class: 'fp-sub' }, mine ? 'New' : `${compact(v.views)} views · ${timeAgo(v.published_at)}`))));
  }

  function draw() {
    const rivals = state.rivals.map((v) => card(v, false));
    const mine = card(null, true);
    const cards = state.blind ? shuffle([...rivals, mine]) : [...rivals.slice(0, 1), mine, ...rivals.slice(1)];
    clear(feed).append(cards);
    feed.className = `fp-feed ${state.device}`;

    clear(candidates).append(state.titles.map((t, i) => h('li', { class: i === state.pick ? 'active' : '' },
      h('button', { class: 'fp-cand', onclick: () => { state.pick = i; draw(); } }, h('span', {}, t)),
      lengthPill(t),
      onUseTitle && t !== script.title ? h('button', { class: 'btn ghost sm', onclick: () => { onUseTitle(t); draw(); } }, 'Use as title') : null)));
  }

  function drawThumbs() {
    clear(thumbs).append(
      h('button', { class: `fp-pick${!state.image ? ' active' : ''}`, onclick: () => { state.image = null; drawThumbs(); draw(); } }, 'Text only'),
      state.mockups.map((m) => h('button', { class: `fp-pick img${state.image === m.url ? ' active' : ''}`, title: m.title || 'Mockup', onclick: () => { state.image = m.url; drawThumbs(); draw(); } },
        h('img', { src: m.url, alt: '' }))),
      localUrl ? h('button', { class: `fp-pick img${state.image === localUrl ? ' active' : ''}`, onclick: () => { state.image = localUrl; drawThumbs(); draw(); } }, h('img', { src: localUrl, alt: '' })) : null,
      h('button', { class: 'btn ghost sm', onclick: () => fileIn.click() }, icon('plus', 15), 'Try an image'));
  }

  const deviceBtns = ['desktop', 'phone'].map((d) => h('button', {
    class: `btn ghost sm${state.device === d ? ' active' : ''}`,
    onclick: (e) => { state.device = d; deviceBtns.forEach((b) => b.classList.toggle('active', b === e.currentTarget)); draw(); },
  }, d === 'desktop' ? 'Desktop' : 'Phone'));
  const blindIn = h('input', { type: 'checkbox', onchange: () => { state.blind = blindIn.checked; draw(); } });
  const note = h('p', { class: 'muted small' });

  Promise.all([
    api('GET', 'channels').catch(() => ({ outliers: [] })),
    api('GET', `media?kind=thumbnail&script_id=${script.id}`).catch(() => ({ media: [] })),
  ]).then(([c, m]) => {
    state.rivals = [...(c.outliers || [])].sort((a, b) => b.outlier_score - a.outlier_score).slice(0, 5);
    state.mockups = (m.media || []).slice(0, 8);
    if (state.mockups[0]) state.image = state.mockups[0].url;
    note.textContent = state.rivals.length
      ? 'Next to the top breakouts from channels you watch. Blind mode shuffles it in: can you spot yours, and would you click it?'
      : 'Add competitor channels on Breakouts and run a scan to see yours next to real videos from your niche.';
    drawThumbs();
    draw();
  });

  drawThumbs();
  draw();
  return h('div', { class: 'card' },
    h('div', { class: 'card-header' },
      h('h2', {}, 'Feed preview'),
      h('div', { class: 'row' }, deviceBtns, h('label', { class: 'row small' }, blindIn, 'Blind'))),
    note,
    h('div', { class: 'grid grid-2 tight' },
      h('div', {}, h('div', { class: 'fp-label' }, 'Titles'), titlesIn, candidates),
      h('div', {}, h('div', { class: 'fp-label' }, 'Thumbnail'), thumbs, thumbTextIn, fileIn)),
    feed);
}
