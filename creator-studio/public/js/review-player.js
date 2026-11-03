// Video review: play a cut, pause where something should change, leave a note at that exact
// second. Notes show as markers on the timeline; clicking a marker (or a note's time) seeks there.
// Used on a client's request page and on her Clients page. Video notes are stored as
// annotations with kind "video" and deliverable_index = the file's id.
import { h, api, clear, toast, busy } from './core.js';
import { icon } from './icons.js';

export const timecode = (sec) => {
  const s = Math.max(0, Math.floor(Number(sec) || 0));
  const hh = Math.floor(s / 3600);
  const mm = Math.floor((s % 3600) / 60);
  const ss = String(s % 60).padStart(2, '0');
  return hh ? `${hh}:${String(mm).padStart(2, '0')}:${ss}` : `${mm}:${ss}`;
};

/**
 * @param {object} o
 * @param {Array<{id:number,name:string,is_video:boolean}>} o.files
 * @param {string} o.streamBase   e.g. `portal/requests/12/files` (the API path, without /api)
 * @param {string} o.annotationsPath  e.g. `portal/requests/12/annotations`
 * @param {Array} o.annotations
 * @param {Function} o.onChange  called after a note is added or resolved
 */
export function reviewPlayer({ files, streamBase, annotationsPath, annotations, onChange }) {
  const videos = (files || []).filter((f) => f.is_video);
  if (!videos.length) return null;
  let current = videos[0];

  const video = h('video', { controls: true, preload: 'metadata', playsinline: true, class: 'review-video' });
  const track = h('div', { class: 'review-markers', 'aria-label': 'Notes on the timeline' });
  const list = h('div', { class: 'stack' });
  const note = h('textarea', { rows: 2, maxlength: 3000, placeholder: 'What should change here?', 'aria-label': 'Note at the current time' });
  const at = h('span', { class: 'pill sm picked' }, '0:00');
  const add = h('button', { class: 'btn primary sm', type: 'submit' }, icon('chat', 13), 'Add note at this time');

  const notesFor = () => annotations.filter((a) => a.kind === 'video' && a.deliverable_index === current.id && a.timecode_seconds != null)
    .sort((a, b) => a.timecode_seconds - b.timecode_seconds);

  function drawMarkers() {
    clear(track);
    const d = video.duration;
    if (!d || !isFinite(d)) return;
    for (const a of notesFor()) {
      track.append(h('button', {
        type: 'button', class: `review-marker${a.status === 'resolved' ? ' resolved' : ''}`,
        style: { left: `${Math.min(100, (a.timecode_seconds / d) * 100)}%` },
        title: `${timecode(a.timecode_seconds)} · ${a.body.slice(0, 80)}`, 'aria-label': `Note at ${timecode(a.timecode_seconds)}`,
        onclick: () => { video.currentTime = a.timecode_seconds; video.pause(); },
      }));
    }
  }

  function drawList() {
    const n = notesFor();
    clear(list).append(n.length ? n.map((a) => h('div', { class: `review-note${a.status === 'resolved' ? ' muted' : ''}` },
      h('button', { type: 'button', class: 'pill sm picked', onclick: () => { video.currentTime = a.timecode_seconds; video.play().catch(() => {}); } }, timecode(a.timecode_seconds)),
      h('div', { style: { flex: 1, minWidth: 0 } }, h('strong', { class: 'small' }, a.author), h('p', { style: { whiteSpace: 'pre-wrap', margin: '2px 0 0' } }, a.body)),
      a.status === 'resolved' ? h('span', { class: 'pill sm ok' }, 'Resolved') : h('button', {
        type: 'button', class: 'btn ghost sm',
        onclick: (e) => busy(e.currentTarget, '…', async () => { await api('POST', `${annotationsPath}/${a.id}/resolve`); onChange?.(); }),
      }, icon('check', 12), 'Resolve'))) : h('p', { class: 'muted small' }, 'No notes on this cut yet. Pause the video and add one.'));
  }

  function load(file) {
    current = file;
    video.src = `/api/${streamBase}/${file.id}/stream`;
    drawList();
  }

  video.addEventListener('loadedmetadata', drawMarkers);
  video.addEventListener('timeupdate', () => { at.textContent = timecode(video.currentTime); });
  video.addEventListener('pause', () => note.focus({ preventScroll: true }));

  const form = h('form', {
    class: 'stack',
    onsubmit: (e) => {
      e.preventDefault();
      if (note.value.trim().length < 3) return toast('Write a short note first.', 'err');
      const t = Math.round(video.currentTime * 100) / 100;
      busy(add, 'Saving…', async () => {
        const r = await api('POST', annotationsPath, { kind: 'video', deliverable_index: current.id, timecode_seconds: t, body: note.value.trim() });
        annotations.push(r.annotation || { kind: 'video', deliverable_index: current.id, timecode_seconds: t, body: note.value.trim(), author: 'You', status: 'open' });
        note.value = '';
        drawMarkers();
        drawList();
        toast(`Note saved at ${timecode(t)}.`);
        onChange?.();
      });
    },
  }, h('div', { class: 'row small' }, 'At ', at), note, h('div', { class: 'row-end' }, add));

  const picker = videos.length > 1 ? h('select', { 'aria-label': 'Which cut', onchange: (e) => load(videos.find((v) => String(v.id) === e.target.value)) },
    videos.map((v) => h('option', { value: v.id }, v.name))) : null;

  load(current);
  return h('div', { class: 'card review-player' },
    h('div', { class: 'card-header' }, h('h2', {}, icon('play', 15), ' Review the cut'), picker),
    h('div', { class: 'review-stage' }, video, track),
    form, list);
}
