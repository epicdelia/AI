// Upload & test: send a video to YouTube from the studio (title, description, tags, thumbnail,
// scheduled publish), run Pre-Flight quality & monetization checks, and run Bayesian Multi-Variant A/B/C tests.
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
  const abTestsBox = h('div');
  const preflightBox = h('div', { class: 'preflight-card-container' });
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

  // ---- Pre-Flight Checks button ----
  const preflightBtn = h('button', {
    class: 'btn', type: 'button', onclick: () => busy(preflightBtn, 'Checking…', async () => {
      if (!title.value.trim()) { toast('Enter a title before running checks.', 'err'); return; }
      const res = await api('POST', 'youtube/pre-flight', {
        title: title.value.trim(),
        description: desc.value,
        body: script?.body || '',
        script_id: script?.id || null,
      }).catch((e) => { toast(e.message, 'err'); return null; });
      if (res?.check) {
        drawPreflightCheck(res.check);
        toast('Pre-Flight checks complete.');
      }
    }),
  }, icon('shield', 15), 'Run Pre-Flight Checks');

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

  function drawPreflightCheck(check) {
    const toneLufs = check.lufs_status === 'pass' ? 'ok' : check.lufs_status === 'warning' ? 'late' : 'bad';
    const toneAd = check.monetization_rating === 'green' ? 'ok' : check.monetization_rating === 'yellow' ? 'late' : 'bad';
    clear(preflightBox).append(h('div', { class: 'callout', style: { marginTop: '14px', padding: '12px', border: '1px solid var(--border)' } },
      h('div', { class: 'row-between' },
        h('strong', {}, icon('shield', 14), ' Pre-Flight Quality Guard'),
        h('div', { class: 'row', style: { gap: '6px' } },
          h('span', { class: `pill ${toneLufs}` }, `Audio: ${check.integrated_lufs} LUFS`),
          h('span', { class: `pill ${toneAd}` }, `Ad Suitability: ${check.monetization_score}/100`))),
      h('p', { class: 'small muted', style: { margin: '6px 0 4px' } }, check.loudness_summary),
      check.flagged_guidelines?.length ? h('ul', { class: 'small', style: { margin: '4px 0 0 16px', color: 'var(--text-muted)' } },
        check.flagged_guidelines.map((g) => h('li', {}, h('strong', {}, g.guideline), ': ', g.advice))) : null,
      h('div', { class: 'row', style: { gap: '10px', marginTop: '6px', fontSize: '11px', color: 'var(--text-muted)' } },
        h('span', {}, check.technical_notes?.is_uhd_ready ? '✓ 4K UHD Ready' : '✓ 1080p Ready'),
        h('span', {}, check.has_paid_promotion ? '✓ Paid Promotion Synced' : 'Standard Upload'))));
  }

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

  // ---- Multi-Variant Bayesian A/B/C testing ----
  async function newAbTest() {
    const list = await api('GET', 'youtube/studio/videos').catch((e) => { toast(e.message, 'err'); return null; });
    if (!list) return;
    const pick = h('select', {}, list.videos.map((v) => h('option', { value: v.id }, v.title)));
    const t0 = h('input', { type: 'text', maxlength: 100, value: list.videos[0]?.title || '', placeholder: 'Original Title (Variant A Control)' });
    pick.onchange = () => {
      const found = list.videos.find((x) => x.id === pick.value);
      if (found) t0.value = found.title;
    };
    const t1 = h('input', { type: 'text', maxlength: 100, placeholder: 'Challenger Title (Variant B)' });
    const t2 = h('input', { type: 'text', maxlength: 100, placeholder: 'Challenger Title (Variant C, optional)' });

    const { modal } = await import('../core.js');
    const ok = await modal('New Bayesian A/B/C Multi-Variant Test', (close) => h('div', { class: 'ib-form' },
      h('p', { class: 'muted small' }, 'Google Test & Compare Parity: Tests Thumbnail + Title variants simultaneously. Computes Bayesian Beta-Binomial posterior win probabilities and watch time share without time-decay bias.'),
      field('Video to test', pick),
      field('Variant A (Original Control)', t0),
      field('Variant B (Challenger)', t1),
      field('Variant C (Optional Challenger)', t2),
      h('div', { class: 'row', style: { justifyContent: 'flex-end', marginTop: '12px' } },
        h('button', { class: 'btn', onclick: () => close(false) }, 'Cancel'),
        h('button', { class: 'btn primary', onclick: (e) => busy(e.currentTarget, 'Creating…', async () => {
          const variants = [{ title: t1.value.trim() }];
          if (t2.value.trim()) variants.push({ title: t2.value.trim() });
          await api('POST', 'youtube/ab-tests', {
            video_id: pick.value,
            original_title: t0.value.trim() || list.videos[0]?.title,
            variants: variants,
          });
          close(true);
        }) }, 'Launch Test & Compare'))), { wide: true });
    if (ok) { toast('Bayesian Multi-Variant Test started.'); refresh(); }
  }

  function drawAbTests() {
    const abTests = d.ab_tests || [];
    clear(abTestsBox).append(abTests.length ? abTests.map((t) => {
      const isDone = t.status === 'concluded';
      const tone = isDone ? 'ok' : t.status === 'running' ? 'new' : 'bad';
      return h('div', { class: 'yt-test', style: { marginBottom: '16px' } },
        h('div', { class: 'row-between' },
          h('strong', { class: 'clamp-1' }, t.original_title),
          h('span', { class: `pill ${tone}` }, isDone ? 'Concluded' : t.status === 'running' ? 'Active' : t.status)),
        h('ul', { class: 'yt-results' }, (t.variants || []).map((v, i) => {
          const isWinner = t.winner_index === i;
          const stats = (t.statistical_summary || []).find((s) => s.variant_id === i);
          const winProb = stats ? stats.win_probability : (100 / (t.variants?.length || 1)).toFixed(1);
          const ctr = stats ? `${stats.posterior_ctr}% CTR` : '0% CTR';
          const wt = stats ? `${stats.watch_time_share}% watch` : '';
          return h('li', { class: `${isWinner ? 'win' : ''}` },
            h('span', { class: 'clamp-1' }, `Variant ${String.fromCharCode(65 + i)}: `, v.title, isWinner ? icon('trophy', 13) : ''),
            h('span', { class: 'yt-bar' }, h('span', { style: { width: `${winProb}%` } })),
            h('strong', { class: 'small' }, `${winProb}% win `, h('em', { class: 'muted' }, `(${ctr}, ${wt})`)));
        })),
        t.status === 'running' ? h('div', { class: 'row', style: { gap: '8px', marginTop: '8px' } },
          h('button', { class: 'btn ghost sm', onclick: async () => {
            if (await confirmDialog('Conclude test and lock highest-performing variant?', { ok: 'Conclude' })) {
              await api('POST', `youtube/ab-tests/${t.id}/conclude`); refresh();
            }
          } }, 'Conclude Winner'),
          h('button', { class: 'btn ghost sm', onclick: async () => {
            if (await confirmDialog('Stop test and revert to original title?', { ok: 'Stop' })) {
              await api('POST', `youtube/ab-tests/${t.id}/stop`); refresh();
            }
          } }, 'Stop & Revert')) : null);
    }) : h('p', { class: 'muted small' }, 'No Bayesian A/B/C tests running yet.'));
  }

  // ---- translated titles & descriptions (YouTube localizations) ----
  async function translateDialog() {
    const list = await api('GET', 'youtube/studio/videos').catch((e) => { toast(e.message, 'err'); return null; });
    if (!list) return;
    const pick = h('select', {}, list.videos.map((v) => h('option', { value: v.id }, v.title)));
    const box = h('div');
    async function load() {
      const v = list.videos.find((x) => x.id === pick.value);
      const d = await api('GET', `localize/${pick.value}`);
      const langs = h('div', { class: 'row', style: { flexWrap: 'wrap', gap: '6px' } }, Object.entries(d.langs).map(([k, l]) => h('label', { class: 'pill sm' }, h('input', { type: 'checkbox', value: k, checked: d.drafts.some((x) => x.lang === k) }), ` ${l}`)));
      const gloss = h('input', { value: d.glossary || '', placeholder: 'Words to keep as-is: brand names, your name, product names' });
      clear(box).append(
        field('Languages', langs), field('Glossary', gloss),
        h('button', { type: 'button', class: 'btn', onclick: (e) => busy(e.currentTarget, 'Translating…', async () => {
          const chosen = [...langs.querySelectorAll('input:checked')].map((x) => x.value);
          if (!chosen.length) return toast('Pick at least one language.', 'err');
          await api('POST', `localize/${pick.value}/translate`, { langs: chosen, title: v.title, description: v.description || '', glossary: gloss.value });
          load();
        }) }, icon('sparkle', 13), ' Translate'),
        d.drafts.length ? h('div', { class: 'stack', style: { marginTop: '12px' } }, d.drafts.map((x) => {
          const t = h('input', { value: x.title, maxlength: 100, 'aria-label': `${x.lang} title` });
          const desc = h('textarea', { rows: 3, 'aria-label': `${x.lang} description` }, x.description || '');
          return h('div', { class: 'card' }, h('div', { class: 'row-between' }, h('strong', {}, d.langs[x.lang] || x.lang), h('span', { class: 'muted small' }, x.pushed_at ? 'on YouTube' : x.error ? x.error : 'not sent yet')),
            t, desc, h('div', { class: 'row-end' },
              h('button', { type: 'button', class: 'btn ghost sm', onclick: async () => { await api('PUT', `localize/${pick.value}/${x.lang}`, { title: t.value, description: desc.value }); toast('Saved.'); } }, 'Save'),
              h('button', { type: 'button', class: 'btn ghost sm', 'aria-label': 'Remove', onclick: async () => { await api('DELETE', `localize/${pick.value}/${x.lang}`); load(); } }, icon('trash', 12))));
        })) : null,
        d.drafts.length ? h('div', { class: 'row-end', style: { marginTop: '12px' } }, h('button', { type: 'button', class: 'btn primary', onclick: (e) => busy(e.currentTarget, 'Sending…', async () => {
          try { toast((await api('POST', `localize/${pick.value}/push`)).message); } catch (err) { toast(err.message, 'err'); } load();
        }) }, 'Send to YouTube')) : null);
    }
    pick.onchange = load;
    await load();
    await modal('Titles & descriptions in other languages', () => h('div', {}, field('Video', pick), box,
      h('p', { class: 'muted small' }, 'Viewers see the title and description in their own language. Review each one before sending.')), { wide: true });
  }

  // ---- legacy title tests ----
  async function newTest() {
    const list = await api('GET', 'youtube/studio/videos').catch((e) => { toast(e.message, 'err'); return null; });
    if (!list) return;
    const pick = h('select', {}, list.videos.map((v) => h('option', { value: v.id }, v.title)));
    const t1 = h('input', { type: 'text', maxlength: 100, placeholder: 'Title B' });
    const t2 = h('input', { type: 'text', maxlength: 100, placeholder: 'Title C (optional)' });
    const hours = h('select', {}, [[24, 'A day each (recommended)'], [12, '12 hours each'], [48, 'Two days each']].map(([v, l]) => h('option', { value: v }, l)));
    const rounds = h('select', {}, [[2, 'Twice each (fairer)'], [1, 'Once each'], [3, 'Three times each']].map(([v, l]) => h('option', { value: v }, l)));
    const { modal } = await import('../core.js');
    const ok = await modal('Sequential title rotator', (close) => h('div', { class: 'ib-form' },
      h('p', { class: 'muted small' }, 'Sequential rotator: titles take turns over 12-48h windows.'),
      field('Video', pick), field('Title B', t1), field('Title C', t2), h('div', { class: 'grid grid-2 tight' }, field('Each turn', hours), field('Turns', rounds)),
      h('div', { class: 'row', style: { justifyContent: 'flex-end', marginTop: '12px' } },
        h('button', { class: 'btn', onclick: () => close(false) }, 'Cancel'),
        h('button', { class: 'btn primary', onclick: (e) => busy(e.currentTarget, 'Starting…', async () => {
          await api('POST', 'youtube/tests', { video_id: pick.value, titles: [t1.value, t2.value].filter((x) => x.trim()), hours: Number(hours.value), rounds: Number(rounds.value) });
          close(true);
        }) }, 'Start test'))), { wide: true });
    if (ok) { toast('Test started.'); refresh(); }
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
        t.impact ? h('p', { class: 'small muted' }, icon('chart', 12), ' ', t.impact.ages.map((a) => a.views == null ? `Day ${a.day}: not reached yet`
          : a.median == null ? `Day ${a.day}: ${a.views.toLocaleString()} views (need ${3} older videos to compare)`
            : `Day ${a.day}: ${a.views.toLocaleString()} views, ${a.change_pct >= 0 ? '+' : ''}${a.change_pct}% vs your usual ${a.median.toLocaleString()}`).join(' · ')) : null,
        t.error ? h('p', { class: 'error-text small' }, t.error) : null,
        t.status === 'running' ? h('button', { class: 'btn ghost sm', onclick: async () => { if (await confirmDialog('Stop the test and put the original title back?', { ok: 'Stop' })) { await api('POST', `youtube/tests/${t.id}/stop`); refresh(); } } }, 'Stop & restore title') : null);
    }) : h('p', { class: 'muted small' }, 'No legacy tests yet.'));
  }

  async function refresh() {
    d = await api('GET', 'youtube/studio');
    drawUploads();
    drawAbTests();
    drawTests();
    if (d.checks?.[0]) drawPreflightCheck(d.checks[0]);
  }

  view.append(
    pageHead('YouTube Studio & Pre-Flight Quality Guard', 'Upload with broadcast -14 LUFS checks, Ad Suitability certification, and Google Test & Compare Bayesian A/B/C testing.'),
    d.connected ? null : h('div', { class: 'card callout warn' }, 'Connect YouTube first: ', link('/connections', {}, 'Connections → YouTube'), '. (If it was connected before this update, reconnect once so the studio may upload.)'),
    h('div', { class: 'grid grid-2' },
      h('div', { class: 'card' },
        h('div', { class: 'card-header' }, h('h2', {}, script ? `Upload “${script.title}”` : 'Upload a video')),
        field('Video file', file), field('Title', title, 'up to 100 characters'), field('Description', desc), field('Tags', tags, 'comma separated'),
        h('div', { class: 'grid grid-2 tight' }, field('Visibility', privacy), whenField),
        h('div', { class: 'field' }, h('label', {}, 'Thumbnail'), thumbs),
        preflightBox,
        h('div', { class: 'row', style: { gap: '8px', marginTop: '12px' } }, preflightBtn, send),
        bar),
      h('div', {},
        h('div', { class: 'card' },
          h('div', { class: 'card-header' },
            h('h2', {}, 'Test & Compare (A/B/C)'),
            h('button', { class: 'btn sm primary', disabled: !d.connected, onclick: newAbTest }, icon('plus', 14), 'New A/B/C Test')),
          abTestsBox),
        h('div', { class: 'card' }, h('div', { class: 'card-header' }, h('h2', {}, 'Uploads queue')), uploadsBox),
        h('div', { class: 'card' }, h('div', { class: 'card-header' }, h('h2', {}, 'Sequential Rotator (Legacy)'), h('div', { class: 'row' }, h('button', { class: 'btn sm', disabled: !d.connected, onclick: translateDialog, title: 'Titles and descriptions in other languages' }, icon('compass', 14), 'Translate'), h('button', { class: 'btn sm', disabled: !d.connected, onclick: newTest }, icon('plus', 14), 'New turn test'))), testsBox))));

  drawUploads();
  drawAbTests();
  drawTests();
  if (d.checks?.[0]) drawPreflightCheck(d.checks[0]);
  if (d.uploads.some((u) => ['queued', 'uploading'].includes(u.status))) startPump();
  return () => { clearInterval(pump); };
}
