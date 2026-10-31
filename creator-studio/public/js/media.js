// Read-aloud, thumbnail mockups and Shorts (Vizard) for the script editor.
// Ported features: MagicAI's TTSController, OpenAI/fal image services and Vizard package.
import { h, api, apiUpload, busy, toast, clear, confirmDialog, link, timeAgo, modal, field } from './core.js';
import { icon } from './icons.js';
import { outputPanel } from './components.js';
import { skLines } from './skeleton.js';

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
  const box = h('div', {}, skLines(3, 'thumbnail mockups'));
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
          h('button', { class: 'btn ghost sm', title: 'Get a critique', onclick: () => thumbnailReviewDialog({ mediaId: m.id, mediaUrl: m.url, title: script.title }) }, icon('eye', 13)),
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
      h('div', { class: 'row' }, gen,
        h('button', { class: 'btn', type: 'button', onclick: () => thumbnailReviewDialog({ title: script.title }) }, icon('eye', 15), 'Review a thumbnail')),
      gallery);
    drawPhotos();

    api('GET', `media?kind=thumbnail&script_id=${script.id}`).then((r) => gallery.append(...r.media.map(thumbTile))).catch(() => {});
  }).catch((e) => clear(box).append(h('p', { class: 'error-text' }, e.message)));

  return card;
}

// ------------------------------------------------------------------
// B-roll clips (fal.ai Kling / Veo queue, ported from MagicAI's Packages\FalAI).
// ------------------------------------------------------------------

/** The script's [B-ROLL: ...] notes, as ready-made prompts. */
function brollCues(body) {
  return [...String(body || '').matchAll(/\[B-ROLL:?\s*([^\]]+)\]/gi)].map((m) => m[1].trim()).filter(Boolean).slice(0, 12);
}

export function brollPanel(script) {
  const box = h('div', {}, skLines(3, 'b-roll clips'));
  const card = h('div', { class: 'card' }, h('h2', {}, 'B-roll clips'), box);
  let timer = null;

  capabilities().then(async (c) => {
    if (!c.broll.available) { clear(box).append(needKey('B-roll clips', 'a fal.ai key')); return; }
    const models = c.broll.models;
    const prompt = h('textarea', { rows: 2, placeholder: 'Slow push-in on a messy desk covered in receipts, warm morning light' });
    const model = h('select', {}, models.map((m) => h('option', { value: m.id }, m.label)));
    const duration = h('select');
    const aspect = h('select', {}, [['16:9', '16:9'], ['9:16', '9:16 (Shorts)'], ['1:1', '1:1']].map(([v, l]) => h('option', { value: v }, l)));
    const frame = h('select', {}, h('option', { value: '' }, 'start frame: none'));
    const syncDur = () => {
      const m = models.find((x) => x.id === model.value);
      clear(duration).append(m.durations.map((d) => h('option', { value: d, selected: d === m.default }, d.endsWith('s') ? d : `${d}s`)));
    };
    model.addEventListener('change', syncDur);
    syncDur();
    api(`GET`, `media?kind=thumbnail&script_id=${script.id}`).then((r) => {
      [...r.media, ...c.images.photos].forEach((m) => frame.append(h('option', { value: m.id }, `start from: ${m.kind === 'photo' ? 'your photo' : m.title || 'mockup'} #${m.id}`)));
    }).catch(() => {});

    const cues = brollCues(script.body);
    const list = h('div', { class: 'broll-grid' });

    const draw = async () => {
      const r = await api('GET', `broll?script_id=${script.id}`);
      clear(list).append(r.jobs.map((j) => h('div', { class: 'broll' },
        j.status === 'done' && j.media ? h('video', { src: j.media.url, controls: true, preload: 'metadata', playsinline: true })
          : h('div', { class: `broll-pending ${j.status}` }, j.status === 'failed' ? '✕ failed' : h('span', {}, h('span', { class: 'spinner' }), ` ${j.status}…`)),
        h('div', { class: 'muted small clamp-2' }, j.prompt),
        j.error ? h('div', { class: 'error-text' }, j.error) : null,
        h('div', { class: 'row' },
          j.media ? h('a', { href: `${j.media.url}?download=1`, class: 'btn ghost sm' }, icon('download', 13), 'mp4') : null,
          h('button', { class: 'btn ghost sm danger', title: 'Delete', onclick: async () => { if (await confirmDialog('Delete this clip?')) { await api('DELETE', `broll/${j.id}`); draw(); } } }, icon('trash', 13))))));
      clearTimeout(timer);
      const pending = r.jobs.filter((j) => j.status === 'queued' || j.status === 'running');
      if (pending.length) {
        timer = setTimeout(async () => {
          if (!card.isConnected) return;
          await Promise.all(pending.map((j) => api('POST', `broll/${j.id}/refresh`).catch(() => {})));
          draw().catch(() => {});
        }, 20000);
      }
    };

    const go = h('button', {
      class: 'btn primary',
      onclick: () => busy(go, 'Queueing…', async () => {
        if (!prompt.value.trim()) throw new Error('Describe the shot first.');
        const r = await api('POST', 'broll', { prompt: prompt.value, model: model.value, duration: duration.value, aspect: aspect.value, media_id: frame.value || null, script_id: script.id });
        toast(r.message);
        await draw();
      }),
    }, icon('play', 14), 'Make clip');

    clear(box).append(
      h('p', { class: 'muted small' }, 'Short generated shots for your [B-ROLL] notes: cutaways, establishing shots, abstract visuals. No people who need to look like you; film those yourself.'),
      cues.length ? h('div', { class: 'tool-chips', style: { marginBottom: '8px' } }, cues.map((cue) => h('button', { class: 'btn ghost sm', type: 'button', onclick: () => { prompt.value = cue; } }, cue.length > 48 ? `${cue.slice(0, 48)}…` : cue))) : null,
      h('div', { class: 'field' }, prompt),
      h('div', { class: 'row tight-row' }, model, duration, aspect, frame, go),
      list);
    draw().catch(() => {});
  }).catch((e) => clear(box).append(h('p', { class: 'error-text' }, e.message)));

  return card;
}

/** "Clean the audio" (ElevenLabs voice isolator, ported from MagicAI's audioIsolator). */
export function cleanAudioButton(scriptId) {
  const fileInput = h('input', { type: 'file', accept: 'audio/*,video/*', hidden: true });
  const out = h('div', { class: 'audio-box' });
  const btn = h('button', { type: 'button', class: 'btn sm', onclick: () => fileInput.click() }, icon('sparkle', 15), 'Clean up audio');
  fileInput.addEventListener('change', async () => {
    const f = fileInput.files[0];
    fileInput.value = '';
    if (!f) return;
    try {
      const c = await capabilities();
      if (!c.cleanup) throw new Error('Audio clean-up needs an ElevenLabs key in Settings.');
      btn.disabled = true;
      const fd = new FormData();
      fd.append('file', f);
      if (scriptId) fd.append('script_id', scriptId);
      const r = await apiUpload('audio/clean', fd, { onProgress: (p) => { btn.lastChild.textContent = p < 1 ? `Uploading ${Math.round(p * 100)}%` : 'Removing noise…'; } });
      toast(r.message);
      clear(out).append(h('audio', { controls: true, src: r.audio.url }),
        h('a', { href: `${r.audio.url}?download=1`, class: 'btn ghost sm' }, icon('download', 13), 'Download cleaned MP3'));
    } catch (e) {
      toast(e.message, 'err');
    } finally {
      btn.disabled = false;
      btn.lastChild.textContent = 'Clean up audio';
    }
  });

  return h('span', { class: 'clean-audio' }, btn, fileInput, out);
}

// ------------------------------------------------------------------
// Thumbnail critic (AI vision, ported from MagicAI's completeWithImage).
// ------------------------------------------------------------------

export function thumbnailReviewDialog({ mediaId = null, mediaUrl = null, title = '' } = {}) {
  return modal('Thumbnail critic', () => {
    const file = h('input', { type: 'file', accept: 'image/jpeg,image/png,image/webp' });
    const yt = h('input', { type: 'url', placeholder: 'or a YouTube link to one of your videos' });
    const titleIn = h('input', { type: 'text', value: title, maxlength: 200, placeholder: 'The video title (helps judge the pair)' });
    const picks = h('div', { class: 'choice-list compact' }, h('p', { class: 'muted small' }, 'Loading breakouts…'));
    const out = h('div');

    api('GET', 'channels').then((r) => {
      const top = [...r.outliers].sort((a, b) => b.outlier_score - a.outlier_score).slice(0, 8);
      clear(picks).append(top.length ? top.map((o) => h('label', { class: 'choice' },
        h('input', { type: 'checkbox', value: `outlier:${o.id}`, onchange: (e) => {
          if (picks.querySelectorAll('input:checked').length > 3) { e.target.checked = false; toast('Compare with up to 3.', 'err'); }
        } }),
        h('img', { src: `https://i.ytimg.com/vi/${o.video_id}/mqdefault.jpg`, alt: '', class: 'mini-thumb', loading: 'lazy' }),
        h('div', {}, h('strong', { class: 'clamp-1' }, o.title), h('div', { class: 'muted small' }, `${o.channel_title} · ${Number(o.outlier_score).toFixed(1)}×`))))
        : h('p', { class: 'muted small' }, 'No breakouts yet: scan on the Breakouts page to compare against what’s working.'));
    }).catch(() => clear(picks));

    const go = h('button', {
      class: 'btn primary', type: 'button',
      onclick: () => busy(go, 'Looking closely…', async () => {
        const fd = new FormData();
        if (mediaId) fd.append('media_id', mediaId);
        else if (file.files[0]) fd.append('image', file.files[0]);
        else if (yt.value.trim()) fd.append('youtube_url', yt.value.trim());
        else throw new Error('Choose a thumbnail to review.');
        if (titleIn.value.trim()) fd.append('title', titleIn.value.trim());
        picks.querySelectorAll('input:checked').forEach((c, i) => fd.append(`compare[${i}]`, c.value));
        const r = await apiUpload('thumbnails/review', fd);
        clear(out).append(outputPanel(r.review, { title: `Thumbnail review${titleIn.value ? `: ${titleIn.value}` : ''}`, tool: 'vision:thumbnail' }));
      }),
    }, icon('eye', 15), 'Review');

    return h('div', {},
      mediaUrl ? h('img', { src: mediaUrl, alt: 'Thumbnail to review', class: 'review-subject' }) : h('div', { class: 'grid grid-2 tight' }, field('Your thumbnail', file), field('Or', yt)),
      field('Title', titleIn),
      h('div', { class: 'field' }, h('label', {}, 'Compare with breakouts ', h('span', { class: 'hint' }, 'optional, up to 3')), picks),
      h('div', { class: 'row' }, go),
      out);
  }, { wide: true });
}

// ------------------------------------------------------------------
// Shorts cut from the long-form by Vizard.
// ------------------------------------------------------------------

export function shortsPanel(script) {
  const box = h('div', {}, skLines(3, 'Shorts'));
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
