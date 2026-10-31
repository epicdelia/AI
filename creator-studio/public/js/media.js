// Read-aloud, thumbnail mockups and Shorts (Vizard) for the script editor.
// Ported features: MagicAI's TTSController, OpenAI/fal image services and Vizard package.
import { h, api, apiUpload, busy, toast, clear, confirmDialog, link, timeAgo } from './core.js';
import { icon } from './icons.js';

let caps = null;
async function capabilities(force = false) {
  if (!caps || force) caps = await api('GET', 'media/capabilities');
  return caps;
}

const needKey = (what, keys) => h('p', { class: 'muted small' }, `${what} needs ${keys}. Add one in `, link('/settings', {}, 'Settings'), '.');

// ------------------------------------------------------------------
// Read-aloud: hear the script (or a selection) to check pacing.
// ------------------------------------------------------------------

export function readAloudCard(script, getSelection) {
  const box = h('div', {}, h('p', { class: 'muted small' }, 'Loading voices…'));
  const card = h('div', { class: 'card' }, h('h3', {}, 'Listen'), box);

  capabilities().then((c) => {
    const t = c.tts;
    if (!t.providers.length) { clear(box).append(needKey('Read-aloud', 'an OpenAI or ElevenLabs key')); return; }

    const provider = h('select', { 'aria-label': 'Voice provider' }, t.providers.map((p) => h('option', { value: p, selected: p === t.default }, p === 'openai' ? 'OpenAI' : 'ElevenLabs')));
    const voice = h('select', { 'aria-label': 'Voice' });
    const speed = h('select', { 'aria-label': 'Speed' }, [0.9, 1, 1.1, 1.25].map((s) => h('option', { value: s, selected: s === 1 }, `${s}×`)));
    const fillVoices = () => {
      const list = provider.value === 'openai'
        ? t.openai_voices.map((v) => [v, v])
        : t.eleven_voices.map((v) => [v.id, `${v.name}${v.labels.length ? ` · ${v.labels.slice(0, 2).join(', ')}` : ''}`]);
      clear(voice).append(list.map(([v, l]) => h('option', { value: v, selected: v === t.voice }, l)));
    };
    provider.addEventListener('change', fillVoices);
    fillVoices();

    const player = h('div', { class: 'audio-box' });
    const play = (clip, autoplay = true) => {
      clear(player).append(
        h('audio', { controls: true, src: clip.url, preload: 'metadata', autoplay }),
        h('div', { class: 'row-between' },
          h('span', { class: 'muted small' }, `${clip.meta?.words || '?'} words · ~${Math.round((clip.meta?.est_seconds || 0) / 60 * 10) / 10} min`),
          h('a', { href: `${clip.url}?download=1`, class: 'btn ghost sm' }, icon('download', 13), 'mp3')));
    };

    const run = (btn, selection) => busy(btn, 'Recording…', async () => {
      const text = selection ? getSelection() : null;
      if (selection && !text) throw new Error('Select some text in the script first.');
      const r = await api('POST', 'tts', { script_id: script.id, text, provider: provider.value, voice: voice.value, speed: Number(speed.value) });
      toast(r.message);
      play(r.audio);
    });
    const allBtn = h('button', { class: 'btn primary sm', onclick: (e) => run(e.currentTarget, false) }, icon('play', 13), 'Read script');
    const selBtn = h('button', { class: 'btn sm', onclick: (e) => run(e.currentTarget, true) }, 'Read selection');

    clear(box).append(
      h('p', { class: 'muted small' }, 'Hear it before you film: awkward lines and slow stretches jump out. Cues and headings are skipped.'),
      h('div', { class: 'row tight-row' }, provider, voice, speed),
      h('div', { class: 'row', style: { marginTop: '8px' } }, allBtn, selBtn),
      player);

    // Show the last recording of this script, without playing it.
    api('GET', `media?kind=audio&script_id=${script.id}`).then((r) => { if (r.media[0]) play(r.media[0], false); }).catch(() => {});
  }).catch((e) => clear(box).append(h('p', { class: 'error-text' }, e.message)));

  return card;
}

// ------------------------------------------------------------------
// Thumbnail mockups (with her reference photos).
// ------------------------------------------------------------------

export function thumbnailStudio(script) {
  const box = h('div', {}, h('p', { class: 'muted small' }, 'Loading…'));
  const card = h('div', { class: 'card' }, h('h2', {}, 'Thumbnail mockups'), box);

  capabilities().then((c) => {
    const img = c.images;
    if (!img.providers.length) { clear(box).append(needKey('Thumbnail mockups', 'an OpenAI key (or fal.ai)')); return; }

    const concept = h('textarea', { rows: 3, placeholder: 'Me holding five phones, shocked face, a £300 receipt flying out of one of them' });
    const text = h('input', { type: 'text', maxlength: 60, placeholder: 'Thumbnail text, e.g. “£300 GONE”' });
    const style = h('select', {}, ['', 'photorealistic', 'bright pop', 'cinematic', 'minimal', 'cartoon'].map((s) => h('option', { value: s }, s || 'style: auto')));
    const quality = h('select', {}, [['medium', 'quality: medium'], ['low', 'low (cheapest)'], ['high', 'high']].map(([v, l]) => h('option', { value: v }, l)));
    const usePhotos = h('input', { type: 'checkbox', checked: img.photos.length > 0 });
    const gallery = h('div', { class: 'thumb-grid' });
    const photos = h('div', { class: 'photo-row' });

    const drawPhotos = () => {
      clear(photos).append(
        img.photos.map((p) => h('div', { class: 'photo' },
          h('img', { src: p.url, alt: 'Reference photo', loading: 'lazy' }),
          h('button', {
            class: 'photo-x', 'aria-label': 'Remove photo',
            onclick: async () => {
              if (!(await confirmDialog('Remove this reference photo?', { ok: 'Remove' }))) return;
              await api('DELETE', `media/${p.id}`);
              img.photos = img.photos.filter((x) => x.id !== p.id);
              drawPhotos();
            },
          }, '✕'))),
        uploadPhoto);
    };
    const photoInput = h('input', { type: 'file', accept: 'image/jpeg,image/png,image/webp', hidden: true });
    const uploadPhoto = h('button', { class: 'photo add', type: 'button', title: 'Add a photo of yourself', onclick: () => photoInput.click() }, icon('plus', 18));
    photoInput.addEventListener('change', async () => {
      const f = photoInput.files[0];
      photoInput.value = '';
      if (!f) return;
      try {
        const fd = new FormData();
        fd.append('photo', f);
        const r = await apiUpload('media/photos', fd);
        img.photos.unshift(r.photo);
        usePhotos.checked = true;
        drawPhotos();
        toast(r.message);
      } catch (e) { toast(e.message, 'err'); }
    });

    const thumbTile = (m) => h('figure', { class: 'thumb' },
      h('a', { href: m.url, target: '_blank', rel: 'noopener' }, h('img', { src: m.url, alt: m.title || 'Thumbnail mockup', loading: 'lazy' })),
      h('figcaption', { class: 'row-between' },
        h('span', { class: 'muted small clamp-1' }, m.title || ''),
        h('span', { class: 'row' },
          h('a', { href: `${m.url}?download=1`, class: 'btn ghost sm', title: 'Download' }, icon('download', 13)),
          h('button', {
            class: 'btn ghost sm danger', title: 'Delete',
            onclick: async (e) => {
              if (!(await confirmDialog('Delete this mockup?'))) return;
              await api('DELETE', `media/${m.id}`);
              e.target.closest('figure').remove();
            },
          }, icon('trash', 13)))));

    const gen = h('button', {
      class: 'btn primary',
      onclick: () => busy(gen, 'Painting… (up to a minute)', async () => {
        if (!concept.value.trim()) throw new Error('Describe the scene first.');
        const r = await api('POST', 'thumbnails', {
          script_id: script.id, concept: concept.value, text: text.value || null, style: style.value || null,
          quality: quality.value, use_photos: usePhotos.checked,
        });
        gallery.prepend(thumbTile(r.image));
        toast(r.message);
      }),
    }, icon('sparkle', 15), 'Make a mockup');

    clear(box).append(
      h('p', { class: 'muted small' }, 'Rough 16:9 mockups to test ideas before a real shoot. Use the Thumbnails tool above for concepts, then paint the best one here.'),
      h('div', { class: 'field' }, h('label', {}, 'Scene'), concept),
      h('div', { class: 'grid grid-3 tight' },
        h('div', { class: 'field' }, h('label', {}, 'Text on image'), text),
        h('div', { class: 'field' }, h('label', {}, 'Style'), style),
        h('div', { class: 'field' }, h('label', {}, 'Quality'), quality)),
      h('div', { class: 'field' },
        h('label', { class: 'inline-check' }, usePhotos, 'Use my photos (so the mockup has your face)'),
        photos, photoInput),
      h('div', { class: 'row' }, gen),
      gallery);
    drawPhotos();

    api('GET', `media?kind=thumbnail&script_id=${script.id}`).then((r) => gallery.append(...r.media.map(thumbTile))).catch(() => {});
  }).catch((e) => clear(box).append(h('p', { class: 'error-text' }, e.message)));

  return card;
}

// ------------------------------------------------------------------
// Shorts cut from the long-form by Vizard.
// ------------------------------------------------------------------

export function shortsPanel(script) {
  const box = h('div', {}, h('p', { class: 'muted small' }, 'Loading…'));
  const card = h('div', { class: 'card' }, h('h2', {}, 'Cut Shorts automatically'), box);
  let timer = null;

  const draw = async () => {
    const r = await api('GET', `clips?script_id=${script.id}`);
    if (!r.available) { clear(box).append(needKey('Cutting Shorts', 'a Vizard API key')); return; }

    const url = h('input', { type: 'url', value: script.youtube_url || '', placeholder: 'YouTube link or direct .mp4 link' });
    const length = h('select', {}, [[0, 'length: auto'], [1, 'under 30s'], [2, '30–60s'], [3, '60–90s'], [4, '90s–3 min']].map(([v, l]) => h('option', { value: v }, l)));
    const ratio = h('select', {}, [[1, '9:16 (Shorts/Reels)'], [3, '4:5'], [2, '1:1'], [4, '16:9']].map(([v, l]) => h('option', { value: v }, l)));
    const max = h('select', {}, [3, 6, 10].map((n) => h('option', { value: n, selected: n === 6 }, `${n} clips`)));
    const submit = h('button', {
      class: 'btn primary',
      onclick: () => busy(submit, 'Sending to Vizard…', async () => {
        const res = await api('POST', 'clips', { url: url.value, script_id: script.id, length: Number(length.value), ratio: Number(ratio.value), max: Number(max.value) });
        toast(res.message);
        await draw();
      }),
    }, icon('play', 14), 'Cut Shorts');

    const projects = r.projects.map((p) => {
      const refresh = h('button', {
        class: 'btn ghost sm',
        onclick: () => busy(refresh, 'Checking…', async () => { await api('POST', `clips/${p.id}/refresh`); await draw(); }),
      }, icon('refresh', 13), 'Check now');
      return h('div', { class: 'clip-project' },
        h('div', { class: 'row-between' },
          h('div', {}, h('strong', {}, p.status === 'done' ? `${(p.clips || []).length} clips ready` : p.status === 'failed' ? 'Failed' : 'Vizard is cutting…'),
            h('div', { class: 'muted small' }, `${p.source_url} · ${timeAgo(p.created_at)}`),
            p.error ? h('div', { class: 'error-text' }, p.error) : null),
          h('div', { class: 'row' },
            p.status === 'processing' ? refresh : null,
            h('button', {
              class: 'btn ghost sm danger', title: 'Remove',
              onclick: async () => { if (await confirmDialog('Remove this Shorts project from the list?', { ok: 'Remove' })) { await api('DELETE', `clips/${p.id}`); await draw(); } },
            }, icon('trash', 13)))),
        p.status === 'done' ? h('div', { class: 'clip-grid' }, (p.clips || []).map((c) => h('div', { class: 'clip' },
          c.url ? h('video', { src: c.url, controls: true, preload: 'none', playsinline: true }) : null,
          h('strong', { class: 'clamp-2' }, c.title),
          h('div', { class: 'muted small' }, [c.seconds ? `${c.seconds}s` : null, c.viral_score !== null ? `viral score ${c.viral_score}/10` : null].filter(Boolean).join(' · ')),
          c.viral_reason ? h('div', { class: 'muted small clamp-3' }, c.viral_reason) : null,
          h('div', { class: 'row' },
            c.url ? h('a', { href: c.url, class: 'btn ghost sm', target: '_blank', rel: 'noopener', download: '' }, icon('download', 13), 'Download') : null,
            c.editor_url ? h('a', { href: c.editor_url, class: 'btn ghost sm', target: '_blank', rel: 'noopener' }, icon('pen', 13), 'Edit in Vizard') : null)))) : null);
    });

    clear(box).append(
      h('p', { class: 'muted small' }, 'Vizard finds the strongest moments in the published video and cuts vertical clips with captions. It takes a few minutes; this page checks back on its own.'),
      h('div', { class: 'field' }, h('label', {}, 'Video'), url),
      h('div', { class: 'row' }, length, ratio, max, submit),
      projects.length ? h('div', { style: { marginTop: '14px' } }, projects) : null);

    clearTimeout(timer);
    if (r.projects.some((p) => p.status === 'processing') && card.isConnected !== false) {
      timer = setTimeout(() => { if (card.isConnected) draw().catch(() => {}); }, 30000);
    }
  };
  draw().catch((e) => clear(box).append(h('p', { class: 'error-text' }, e.message)));

  return card;
}
