// Upload & test: send a video to YouTube from the studio (title, description, tags, thumbnail,
// scheduled publish) and run title tests on live videos. Uploads go up in pieces, so a big file
// or a dropped connection doesn't mean starting over.
import { h, api, busy, toast, pageHead, clear, field, timeAgo, link, confirmDialog, emptyState } from '../core.js';
import { icon } from '../icons.js';

const STATUS = { receiving: ['new', 'Uploading to the studio'], queued: ['new', 'Waiting to send'], uploading: ['late', 'Sending to YouTube'], done: ['ok', 'On YouTube'], failed: ['bad', 'Failed'] };

export async function renderYouTubeStudio(view) {
  let d = await api('GET', 'youtube/studio');
  const params = new URLSearchParams(location.search);
  const scriptId = params.get('script');
  let script = null;
  if (scriptId) script = (await api('GET', `scripts/${scriptId}`).catch(() => null))?.script || null;

  const uploadsBox = h('div');
  const testsBox = h('div');
  let pump = null;

  // ---- upload form ----
  const file = h('input', { type: 'file', accept: 'video/*', 'aria-label': 'Video file' });
  const title = h('input', { type: 'text', maxlength: 100, value: script?.title || '', placeholder: 'Video title' });
  const desc = h('textarea', { rows: 6, placeholder: 'Description (chapters, links)…' });
  desc.value = script?.publish_kit ? String(script.publish_kit).slice(0, 5000) : '';
  const tags = h('input', { type: 'text', placeholder: 'ai tools, productivity, …' });
  const privacy = h('select', {}, [['private', 'Private'], ['unlisted', 'Unlisted'], ['public', 'Public now'], ['scheduled', 'Schedule a time']].map(([v, l]) => h('option', { value: v }, l)));
  const when = h('input', { type: 'datetime-local' });
  const whenField = field('Goes live', when);
  whenField.hidden = true;
  privacy.onchange = () => { whenField.hidden = privacy.value !== 'scheduled'; };
  let thumb = null;
  const thumbs = h('div', { class: 'yt-thumbs' }, d.thumbnails.length ? d.thumbnails.map((m) => h('button', {
    type: 'button', class: 'yt-thumb', 'aria-pressed': 'false', title: m.title || 'Mockup',
    onclick: (e) => { thumb = thumb === m.id ? null : m.id; thumbs.querySelectorAll('.yt-thumb').forEach((b) => b.setAttribute('aria-pressed', String(b === e.currentTarget && thumb === m.id))); },
  }, h('img', { src: `/api/media/${m.id}/file`, alt: m.title || 'Thumbnail', loading: 'lazy' }))) : h('p', { class: 'muted small' }, 'No mockups yet: make one on a script (Thumbnail), or set it later in YouTube Studio.'));
  const bar = h('div', { class: 'yt-progress', hidden: true }, h('span'));
  const send = h('button', {
    class: 'btn primary', disabled: !d.connected, onclick: () => busy(send, 'Uploading…', async () => {
      const f = file.files[0];
      if (!f) { toast('Pick the video file first.', 'err'); return; }
      if (!title.value.trim()) { toast('Give it a title.', 'err'); return; }
      const r = await api('POST', 'youtube/uploads', {
        title: title.value.trim(), description: desc.value, tags: tags.value.split(',').map((t) => t.trim()).filter(Boolean),
        privacy: privacy.value, publish_at: privacy.value === 'scheduled' && when.value ? new Date(when.value).toISOString() : null,
        script_id: script?.id || null, thumbnail_media_id: thumb, bytes: f.size, mime: f.type || 'video/mp4',
      });
      bar.hidden = false;
      // Her browser → studio, in pieces.
      for (let offset = 0; offset < f.size; offset += r.chunk) {
        const res = await fetch(`/api/youtube/uploads/${r.upload.id}/chunk?offset=${offset}`, {
          method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/octet-stream', Accept: 'application/json' }, body: f.slice(offset, offset + r.chunk),
        });
        if (!res.ok) throw new Error((await res.json().catch(() => ({}))).error || 'Upload interrupted. Try again.');
        bar.firstChild.style.width = `${Math.round(((offset + r.chunk) / f.size) * 50)}%`;
      }
      toast('Got it. Sending to YouTube now; you can leave this page, it carries on in the background.');
      file.value = '';
      await refresh();
      startPump();
    }),
  }, icon('external', 15), 'Upload to YouTube');

  function startPump() {
    if (pump) return;
    pump = setInterval(async () => {
      if (!view.isConnected) { clearInterval(pump); pump = null; return; }
      const r = await api('POST', 'youtube/uploads/drain').catch(() => null);
      if (r) { d.uploads = r.uploads; drawUploads(); }
      if (!d.uploads.some((u) => ['queued', 'uploading'].includes(u.status))) { clearInterval(pump); pump = null; }
    }, 3000);
  }

  function drawUploads() {
    clear(uploadsBox).append(d.uploads.length ? h('ul', { class: 'item-list' }, d.uploads.map((u) => {
      const [tone, label] = STATUS[u.status] || ['', u.status];
      return h('li', {},
        h('div', { class: 'item-main' }, h('strong', {}, u.title),
          h('span', { class: 'muted small' }, u.privacy === 'scheduled' && u.publish_at ? `Goes live ${new Date(u.publish_at).toLocaleString()}` : u.privacy, ' · ', timeAgo(u.created_at)),
          ['receiving', 'queued', 'uploading'].includes(u.status) ? h('div', { class: 'yt-progress' }, h('span', { style: { width: `${u.progress}%` } })) : null,
          u.error ? h('span', { class: 'small error-text' }, u.error) : null),
        h('span', { class: `pill ${tone}` }, label),
        u.video_id ? h('a', { class: 'btn ghost sm', href: `https://studio.youtube.com/video/${u.video_id}/edit`, target: '_blank', rel: 'noopener' }, icon('external', 13), 'Studio') : null,
        u.status !== 'done' ? h('button', { class: 'btn ghost sm', 'aria-label': 'Cancel', onclick: async () => { if (await confirmDialog(`Cancel “${u.title}”?`, { ok: 'Cancel upload' })) { await api('DELETE', `youtube/uploads/${u.id}`); refresh(); } } }, icon('x', 13)) : null);
    })) : h('p', { class: 'muted small' }, 'Nothing uploaded from here yet.'));
  }

  // ---- title tests ----
  async function newTest() {
    const list = await api('GET', 'youtube/studio/videos').catch((e) => { toast(e.message, 'err'); return null; });
    if (!list) return;
    const pick = h('select', {}, list.videos.map((v) => h('option', { value: v.id }, v.title)));
    const t1 = h('input', { type: 'text', maxlength: 100, placeholder: 'Title B' });
    const t2 = h('input', { type: 'text', maxlength: 100, placeholder: 'Title C (optional)' });
    const hours = h('select', {}, [[24, 'A day each (recommended)'], [12, '12 hours each'], [48, 'Two days each']].map(([v, l]) => h('option', { value: v }, l)));
    const rounds = h('select', {}, [[2, 'Twice each (fairer)'], [1, 'Once each'], [3, 'Three times each']].map(([v, l]) => h('option', { value: v }, l)));
    const { modal } = await import('../core.js');
    const ok = await modal('Test titles', (close) => h('div', { class: 'ib-form' },
      h('p', { class: 'muted small' }, 'Titles take turns on the video; whichever gets the most views per hour wins and stays. Best on videos that are a few days old (steady traffic). The current title is always one of the contenders.'),
      field('Video', pick), field('Title B', t1), field('Title C', t2), h('div', { class: 'grid grid-2 tight' }, field('Each turn', hours), field('Turns', rounds)),
      h('div', { class: 'row', style: { justifyContent: 'flex-end', marginTop: '12px' } },
        h('button', { class: 'btn', onclick: () => close(false) }, 'Cancel'),
        h('button', { class: 'btn primary', onclick: (e) => busy(e.currentTarget, 'Starting…', async () => {
          await api('POST', 'youtube/tests', { video_id: pick.value, titles: [t1.value, t2.value].filter((x) => x.trim()), hours: Number(hours.value), rounds: Number(rounds.value) });
          close(true);
        }) }, 'Start test'))), { wide: true });
    if (ok) { toast('Test started. The first switch happens after the first turn.'); refresh(); }
  }

  function drawTests() {
    clear(testsBox).append(d.tests.length ? d.tests.map((t) => {
      const best = Math.max(...t.results.map((r) => r.views_per_hour), 0.0001);
      return h('div', { class: 'yt-test' },
        h('div', { class: 'row-between' }, h('strong', { class: 'clamp-1' }, t.original_title),
          h('span', { class: `pill ${t.status === 'done' ? 'ok' : t.status === 'running' ? 'new' : t.status === 'failed' ? 'bad' : ''}` }, t.status === 'running' ? 'Running' : t.status === 'done' ? 'Done' : t.status)),
        h('ul', { class: 'yt-results' }, t.results.map((r, i) => h('li', { class: `${t.winner === i ? 'win' : ''}${t.status === 'running' && t.current === i ? ' live' : ''}` },
          h('span', { class: 'clamp-1' }, r.title, t.status === 'running' && t.current === i ? h('em', { class: 'muted small' }, ' (live now)') : null, t.winner === i ? icon('trophy', 13) : ''),
          h('span', { class: 'yt-bar', role: 'img', 'aria-label': `${r.views_per_hour} views per hour` }, h('span', { style: { width: `${(r.views_per_hour / best) * 100}%` } })),
          h('strong', { class: 'small' }, `${r.views_per_hour}/h`)))),
        t.error ? h('p', { class: 'error-text small' }, t.error) : null,
        t.status === 'running' ? h('button', { class: 'btn ghost sm', onclick: async () => { if (await confirmDialog('Stop the test and put the original title back?', { ok: 'Stop' })) { await api('POST', `youtube/tests/${t.id}/stop`); refresh(); } } }, 'Stop & restore title') : null);
    }) : h('p', { class: 'muted small' }, 'No title tests yet.'));
  }

  async function refresh() { d = await api('GET', 'youtube/studio'); drawUploads(); drawTests(); }

  view.append(
    pageHead('Upload & test', 'Send videos to YouTube from here, scheduled and with your thumbnail, and let titles compete on live videos.'),
    d.connected ? null : h('div', { class: 'card callout warn' }, 'Connect YouTube first: ', link('/connections', {}, 'Connections → YouTube'), '. (If it was connected before this update, reconnect once so the studio may upload.)'),
    h('div', { class: 'grid grid-2' },
      h('div', { class: 'card' },
        h('div', { class: 'card-header' }, h('h2', {}, script ? `Upload “${script.title}”` : 'Upload a video')),
        field('Video file', file), field('Title', title, 'up to 100 characters'), field('Description', desc), field('Tags', tags, 'comma separated'),
        h('div', { class: 'grid grid-2 tight' }, field('Visibility', privacy), whenField),
        h('div', { class: 'field' }, h('label', {}, 'Thumbnail'), thumbs),
        bar, send),
      h('div', {},
        h('div', { class: 'card' }, h('div', { class: 'card-header' }, h('h2', {}, 'Uploads')), uploadsBox),
        h('div', { class: 'card' }, h('div', { class: 'card-header' }, h('h2', {}, 'Title tests'), h('button', { class: 'btn sm', disabled: !d.connected, onclick: newTest }, icon('plus', 14), 'New test')), testsBox))));
  drawUploads();
  drawTests();
  if (d.uploads.some((u) => ['queued', 'uploading'].includes(u.status))) startPump();
  return () => { clearInterval(pump); };
}
