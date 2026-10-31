// Social: LinkedIn, Instagram, X and YouTube (Shorts, community) posts. Draft and preview them
// here, copy them out to each app, then mark them posted with the link and the numbers.
// Nothing is ever posted for her: there are no sign-ins to her accounts.
import {
  h, api, busy, toast, pageHead, emptyState, clear, pill, modal, confirmDialog, field, navigate, link,
  timeAgo, debounce, copyText, slug, compact,
} from '../core.js';
import { icon } from '../icons.js';
import { carouselCard } from '../carousel.js';

const PLATFORM_ICONS = { linkedin: 'briefcase', instagram: 'camera', x: 'xmark', tiktok: 'music', threads: 'at', youtube: 'play' };
const STATUS_PILL = { idea: 'new', draft: 'WARN', scheduled: 'picked', posted: 'ok' };
const STATUS_LABEL = { idea: 'Idea', draft: 'Draft', scheduled: 'Scheduled', posted: 'Posted' };
const PROFILE_URL = {
  linkedin: (h) => `https://www.linkedin.com/in/${h}`,
  instagram: (h) => `https://www.instagram.com/${h}`,
  x: (h) => `https://x.com/${h}`,
  tiktok: (h) => `https://www.tiktok.com/@${h}`,
  threads: (h) => `https://www.threads.com/@${h}`,
};
const ABOUT = {
  linkedin: 'Text posts and PDF carousels. The hook has to land before “see more” (about 140 characters on phones).',
  instagram: 'Carousels, Reels, single posts and Stories. Lead the caption with the hook; keep to 3 to 5 specific hashtags.',
  x: 'Posts and threads, 280 characters each (25,000 with Premium, switch it on in Settings). Links count as 23.',
  tiktok: 'Videos and photo carousels (up to 35 images). Hook in the first second; only the start of the caption shows over the video.',
  threads: 'Posts and threads, 500 characters each. Conversational wins; one topic tag per post.',
};

let metaCache = null;
async function meta(force = false) {
  if (!metaCache || force) metaCache = await api('GET', 'social/meta');
  return metaCache;
}

const plain = (s) => String(s || '').replace(/\s+/g, ' ').trim();
const chars = (s) => [...String(s || '')].length;
const xChars = (s) => chars(String(s || '').replace(/https?:\/\/\S+/g, 'x'.repeat(23)));
const THREADY = ['x', 'threads'];
const pad = (n) => String(n).padStart(2, '0');
const toLocalInput = (iso) => {
  if (!iso) return '';
  const d = new Date(iso);
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
};
const when = (iso) => new Date(iso).toLocaleString([], { weekday: 'short', day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' });
const handleOf = (m, p) => (m.platforms[p]?.handle || '').replace(/^@/, '');

function setActiveNav(to) {
  for (const a of document.querySelectorAll('.nav-item')) a.classList.toggle('active', a.dataset.to === to);
}

export function platformChip(p, m) {
  return h('span', { class: `pf-chip pf-${p}` }, icon(PLATFORM_ICONS[p], 13), m?.platforms[p]?.label || p);
}

function statusPill(s) {
  return pill(STATUS_PILL[s] || 'new', STATUS_LABEL[s] || s);
}

function formatLabel(m, p, f) {
  return m.platforms[p]?.formats[f]?.label || f;
}

/** Pick platform + format, create a draft, open it. */
export async function newPostDialog(platform = null, extra = {}) {
  const m = await meta();
  return modal('New post', (close) => {
    let chosenP = platform || 'linkedin';
    const formats = h('div', { class: 'choice-grid' });
    const platforms = h('div', { class: 'choice-row' });
    function draw() {
      clear(platforms).append(Object.keys(m.platforms).map((p) => h('button', {
        class: `btn sm${p === chosenP ? ' primary' : ''}`, onclick: () => { chosenP = p; draw(); },
      }, icon(PLATFORM_ICONS[p], 15), m.platforms[p].label)));
      clear(formats).append(Object.entries(m.platforms[chosenP].formats).map(([f, info]) => {
        const b = h('button', {
          class: 'choice',
          onclick: () => busy(b, 'Creating…', async () => {
            const r = await api('POST', 'social/posts', { platform: chosenP, format: f, ...extra });
            close(true);
            navigate(`/posts/${r.post.id}`);
          }),
        }, h('strong', {}, info.label), info.parts ? h('span', { class: 'muted small' }, `with ${info.parts.toLowerCase()}`) : null);
        return b;
      }));
    }
    draw();
    return h('div', {}, platforms, h('p', { class: 'muted small' }, 'Pick a format:'), formats);
  });
}

function postCard(post, m) {
  const text = plain(post.body) || plain((post.parts || [])[0]) || 'Empty draft';
  const date = post.status === 'posted' && post.posted_at ? `Posted ${when(post.posted_at)}`
    : post.scheduled_for ? `Scheduled ${when(post.scheduled_for)}` : `Edited ${timeAgo(post.updated_at)}`;
  return link(`/posts/${post.id}`, { class: 'post-card' },
    h('div', { class: 'post-card-top' }, platformChip(post.platform, m), h('span', { class: 'muted small' }, formatLabel(m, post.platform, post.format)), statusPill(post.status)),
    post.title ? h('strong', { class: 'clamp-1' }, post.title) : null,
    h('div', { class: 'clamp-2 muted' }, text),
    h('div', { class: 'post-card-foot muted small' }, date,
      post.engagement ? h('span', {}, ` · ${compact(post.engagement.total)} engagements${post.engagement.rate !== null ? ` (${post.engagement.rate}%)` : ''}`) : null));
}

function progressChips(progress, only = null) {
  const rows = Object.entries(progress).filter(([p, v]) => (only ? p === only : v.target || v.posted || v.scheduled));
  if (!rows.length) return null;
  return h('div', { class: 'progress-chips' }, rows.map(([p, v]) => {
    const done = v.posted;
    const pct = v.target ? Math.min(100, Math.round((done / v.target) * 100)) : 0;
    return h('div', { class: `progress-chip pf-${p}` },
      h('div', { class: 'row' }, icon(PLATFORM_ICONS[p], 15), h('strong', {}, v.label),
        h('span', { class: 'muted small' }, v.target ? `${done} of ${v.target} this week` : `${done} posted this week`)),
      v.target ? h('div', { class: 'progress' }, h('div', { class: 'progress-bar', style: { width: `${pct}%` } })) : null,
      v.scheduled ? h('div', { class: 'muted small' }, `${v.scheduled} more scheduled`) : null);
  }));
}

// ------------------------------------------------------------------
// Platform pages: /linkedin, /instagram, /x
// ------------------------------------------------------------------

export async function renderPlatform(view, platform) {
  const [m, list, stats] = await Promise.all([
    meta(true), api('GET', `social/posts?platform=${platform}`), api('GET', `social/stats?platform=${platform}`),
  ]);
  const info = m.platforms[platform];
  const handle = handleOf(m, platform);
  let filter = 'all';
  const listBox = h('div');
  const tabBar = h('div', { class: 'tabs' });

  function draw() {
    const counts = list.counts || {};
    clear(tabBar).append(['all', ...m.statuses].map((s) => h('button', {
      class: `tab${filter === s ? ' active' : ''}`, onclick: () => { filter = s; draw(); },
    }, s === 'all' ? 'All' : STATUS_LABEL[s], h('span', { class: 'count' }, s === 'all' ? list.posts.length : (counts[s] || 0)))));
    const posts = list.posts.filter((p) => filter === 'all' || p.status === filter);
    clear(listBox).append(posts.length
      ? h('div', { class: 'post-grid' }, posts.map((p) => postCard(p, m)))
      : emptyState('✍️', filter === 'all' ? `No ${info.label} posts yet` : `Nothing ${STATUS_LABEL[filter].toLowerCase()}`,
        'Write one from scratch, or turn a script into posts with Repurpose.'));
  }
  draw();

  const statsCard = h('div', { class: 'card' },
    h('h3', {}, 'Your numbers'),
    stats.logged ? [
      h('div', { class: 'stat-row' },
        h('div', {}, h('div', { class: 'num' }, stats.posted_30d), h('div', { class: 'muted small' }, 'posted, 30 days')),
        h('div', {}, h('div', { class: 'num' }, stats.avg_rate !== null ? `${stats.avg_rate}%` : '–'), h('div', { class: 'muted small' }, 'avg engagement'))),
      Object.keys(stats.by_format).length ? h('ul', { class: 'mini-list' }, Object.entries(stats.by_format).map(([f, v]) => h('li', {},
        h('span', {}, formatLabel(m, platform, f)), h('span', { class: 'muted' }, `${v.avg_engagement} avg · ${v.posts} posts`)))) : null,
      h('h4', {}, 'Best posts'),
      h('ul', { class: 'mini-list' }, stats.top.map((p) => h('li', {},
        link(`/posts/${p.id}`, { class: 'clamp-1' }, p.title || plain(p.body).slice(0, 60) || 'Post'),
        h('span', { class: 'muted' }, compact(p.engagement.total))))),
    ] : h('p', { class: 'muted small' }, 'Mark posts as posted and add their likes, comments and so on (from the app’s insights). This is where you’ll see what works.'),
    stats.missing_numbers ? h('p', { class: 'small warn-text' }, `${stats.missing_numbers} posted ${stats.missing_numbers === 1 ? 'post has' : 'posts have'} no numbers yet.`) : null);

  view.append(
    pageHead(info.label, ABOUT[platform] || '',
      handle ? h('a', { class: 'btn ghost', href: PROFILE_URL[platform](handle), target: '_blank', rel: 'noopener' }, `@${handle}`) : link('/settings', { class: 'btn ghost' }, 'Add your handle'),
      link(`/repurpose`, { class: 'btn' }, icon('repeat', 16), 'Repurpose'),
      h('button', { class: 'btn primary', onclick: () => newPostDialog(platform).catch((e) => toast(e.message, 'err')) }, icon('plus', 16), 'New post')),
    progressChips(m.progress, platform),
    h('div', { class: 'grid grid-sidebar', style: { marginTop: '16px' } },
      h('div', {}, tabBar, listBox),
      h('aside', {}, statsCard, info.target ? null : h('div', { class: 'card' },
        h('h3', {}, 'Weekly target'), h('p', { class: 'muted small' }, `Set how many ${info.label} posts a week you’re aiming for in Settings → Social, and track it here and on the calendar.`),
        link('/settings', { class: 'btn sm' }, 'Open Settings')))));
}

// ------------------------------------------------------------------
// Post composer: /posts/:id
// ------------------------------------------------------------------

/** Break long text into posts under the limit, on sentence then word boundaries. */
export function splitThread(text, limit = 270) {
  const out = [];
  let cur = '';
  const push = () => { if (cur.trim()) out.push(cur.trim()); cur = ''; };
  for (const para of String(text).split(/\n\s*\n/)) {
    for (const sentence of para.match(/[^.!?]+[.!?]*\s*/g) || []) {
      if (xChars(cur + sentence) <= limit) { cur += sentence; continue; }
      push();
      if (xChars(sentence) <= limit) { cur = sentence; continue; }
      for (const word of sentence.split(/\s+/)) {
        if (xChars(`${cur} ${word}`) > limit) push();
        cur += `${cur ? ' ' : ''}${word}`;
      }
    }
    push();
  }
  return out;
}

export async function renderPost(view, id) {
  const [m, data] = await Promise.all([meta(), api('GET', `social/posts/${id}`)]);
  const post = data.post;
  post.parts = post.parts || [];
  setActiveNav(post.platform === 'youtube' ? '/calendar' : `/${post.platform}`);

  const pending = {};
  const saveState = h('span', { class: 'save-state muted' }, 'Saved');
  const checksBox = h('div', { class: 'lint-box' });
  const preview = h('div', { class: 'sp-preview' });
  const slidesBox = h('div');
  let checks = data.checks;

  async function save() {
    const fields = { ...pending };
    if (!Object.keys(fields).length) return;
    for (const k of Object.keys(fields)) delete pending[k];
    saveState.textContent = 'Saving…';
    try {
      const r = await api('PATCH', `social/posts/${post.id}`, fields);
      Object.assign(post, r.post, { parts: r.post.parts || [] });
      checks = r.checks;
      drawChecks();
      drawStatus();
      saveState.textContent = `Saved ${new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}`;
    } catch (err) {
      Object.assign(pending, fields);
      saveState.textContent = 'Not saved';
      toast(err.message, 'err');
    }
  }
  const saveSoon = debounce(save, 900);
  function change(k, v, now = false) {
    post[k] = v;
    pending[k] = v;
    saveState.textContent = 'Unsaved changes…';
    drawPreview();
    if (now) saveSoon.flush(); else saveSoon();
  }

  const fmtInfo = () => m.platforms[post.platform].formats[post.format];
  const limit = () => m.platforms[post.platform].limit;
  const count = (t) => (post.platform === 'x' ? xChars(t) : chars(t));
  const thready = () => THREADY.includes(post.platform);

  // ---- checks ----
  function drawChecks() {
    clear(checksBox);
    if (!checks.length) { checksBox.append(h('div', { class: 'lint-clean' }, icon('check', 16), 'Ready to post.')); return; }
    checksBox.append(h('ul', { class: 'lint-list' }, checks.map((c) => h('li', { class: `lint-item ${c.severity}` },
      h('div', { class: 'lint-rule' }, h('span', { class: `sev ${c.severity}` }, c.severity), c.message)))));
  }

  // ---- text ----
  const counter = h('span', { class: 'char-count' });
  const bodyArea = h('textarea', { class: 'sp-body', rows: 9, placeholder: post.platform === 'instagram' ? 'Caption: hook first…' : 'Write the post…' }, post.body || '');
  function drawCounter() {
    const n = count(bodyArea.value);
    counter.textContent = `${n.toLocaleString()} / ${limit().toLocaleString()}`;
    counter.classList.toggle('over', n > limit());
  }
  bodyArea.addEventListener('input', () => { change('body', bodyArea.value); drawCounter(); });
  const bodyHead = h('h3');
  const bodyHint = h('p', { class: 'muted small' });
  function syncBody() {
    const thread = thready() && post.format === 'thread';
    const story = post.format === 'story';
    bodyHead.textContent = thread ? 'Scratchpad' : story ? 'Notes' : post.platform === 'instagram' || post.format === 'short' ? 'Caption' : 'Text';
    bodyHint.textContent = thread ? 'Write freely here, then “Split text into posts” below. Only the posts are published.' : story ? 'Not published; the frames are.' : '';
    bodyHint.hidden = !bodyHint.textContent;
    bodyArea.rows = thread || story ? 4 : 9;
    counter.hidden = thread || story;
  }

  // ---- parts: thread posts, slides, beats ----
  const partsBox = h('div', { class: 'sp-parts' });
  function setParts(parts) { post.parts = parts; change('parts', [...parts]); drawParts(); }
  function drawParts() {
    clear(partsBox);
    const label = fmtInfo().parts;
    if (!label) return;
    const single = label.replace(/s$/, '');
    partsBox.append(h('div', { class: 'card-header' }, h('h3', {}, label),
      h('div', { class: 'row' },
        thready() && post.body?.trim() ? h('button', {
          class: 'btn ghost sm', title: 'Split the text above into posts',
          onclick: () => { setParts(splitThread(post.body, Math.min(post.platform === 'x' ? 270 : 470, limit() - 10))); bodyArea.value = ''; change('body', ''); drawCounter(); },
        }, 'Split text into posts') : null,
        h('button', { class: 'btn ghost sm', onclick: () => copyText(post.parts.join('\n\n'), `All ${label.toLowerCase()} copied.`) }, icon('copy', 15), 'Copy all'))));
    post.parts.forEach((p, i) => {
      const ta = h('textarea', { rows: label === 'Posts' ? 3 : 2 }, p);
      const c = h('span', { class: 'char-count' });
      const drawC = () => {
        if (thready()) { const n = count(ta.value); c.textContent = `${n} / ${limit()}`; c.classList.toggle('over', n > limit()); }
        else c.textContent = `${ta.value.split(/\s+/).filter(Boolean).length} words`;
      };
      drawC();
      ta.addEventListener('input', () => { post.parts[i] = ta.value; change('parts', [...post.parts]); drawC(); });
      const move = (d) => { const a = [...post.parts]; [a[i], a[i + d]] = [a[i + d], a[i]]; setParts(a); };
      partsBox.append(h('div', { class: 'sp-part' },
        h('div', { class: 'sp-part-head' }, h('strong', {}, `${single} ${i + 1}`), c,
          h('div', { class: 'row' },
            h('button', { class: 'btn ghost sm', title: 'Copy', onclick: () => copyText(ta.value) }, icon('copy', 14)),
            i > 0 ? h('button', { class: 'btn ghost sm', title: 'Move up', onclick: () => move(-1) }, '↑') : null,
            i < post.parts.length - 1 ? h('button', { class: 'btn ghost sm', title: 'Move down', onclick: () => move(1) }, '↓') : null,
            h('button', { class: 'btn ghost sm danger', title: 'Remove', onclick: () => setParts(post.parts.filter((_, j) => j !== i)) }, icon('x', 14)))),
        ta));
    });
    partsBox.append(h('button', { class: 'btn sm', onclick: () => { setParts([...post.parts, '']); partsBox.querySelector('.sp-part:last-of-type textarea')?.focus(); } }, icon('plus', 15), `Add ${single.toLowerCase()}`));
    drawSlides();
  }

  function drawSlides() {
    clear(slidesBox);
    if (fmtInfo().parts === 'Slides' && post.parts.length) {
      slidesBox.append(carouselCard(post.parts, {
        name: post.title || 'carousel', color: m.brand_color, handle: handleOf(m, post.platform), pdf: post.platform === 'linkedin',
      }));
    }
  }

  // ---- live preview, per platform ----
  const name = window.__studioName || 'You';
  let expanded = false;
  function folded(text, fold) {
    const t = String(text || '');
    if (!fold || expanded || chars(t) <= fold) return [t];
    return [[...t].slice(0, fold).join('').trimEnd(), h('button', { class: 'sp-more', onclick: () => { expanded = true; drawPreview(); } }, post.platform === 'linkedin' ? '…see more' : '… more')];
  }
  function drawPreview() {
    const handle = handleOf(m, post.platform) || slug(name);
    const parts = post.parts || [];
    const fold = m.platforms[post.platform].fold;
    let el;
    if (thready()) {
      const tweets = post.format === 'thread' ? parts : [post.body || ''];
      el = h('div', { class: 'pv-x' }, tweets.map((t, i) => h('div', { class: 'pv-tweet' },
        h('div', { class: 'pv-avatar' }, name.slice(0, 1).toUpperCase()),
        h('div', { class: 'pv-tweet-main' },
          h('div', {}, h('strong', {}, name), h('span', { class: 'muted' }, ` @${handle} · now`)),
          h('div', { class: 'pv-text' }, t || h('span', { class: 'muted' }, 'Empty')),
          h('div', { class: `muted small${count(t) > limit() ? ' error-text' : ''}` }, `${count(t)}/${limit()}${tweets.length > 1 ? ` · ${i + 1}/${tweets.length}` : ''}`)))));
    } else if (post.platform === 'linkedin') {
      el = h('div', { class: 'pv-li' },
        h('div', { class: 'pv-head' }, h('div', { class: 'pv-avatar' }, name.slice(0, 1).toUpperCase()), h('div', {}, h('strong', {}, name), h('div', { class: 'muted small' }, 'now · 🌐'))),
        h('div', { class: 'pv-text' }, folded(post.body, fold)),
        post.format === 'carousel' && parts.length ? h('div', { class: 'pv-slide', style: { background: m.brand_color } }, h('span', {}, plain(parts[0])), h('span', { class: 'pv-slide-count' }, `1 / ${parts.length}`)) : null);
    } else if (post.platform === 'instagram') {
      const visual = post.format === 'carousel' || post.format === 'story' ? plain(parts[0]) : post.format === 'reel' ? plain(parts[0]) : post.title || 'Your image';
      el = h('div', { class: 'pv-ig' },
        h('div', { class: 'pv-head' }, h('div', { class: 'pv-avatar ring' }, name.slice(0, 1).toUpperCase()), h('strong', {}, handle)),
        h('div', { class: `pv-media ${post.format === 'reel' || post.format === 'story' ? 'tall' : ''}`, style: { background: m.brand_color } },
          h('span', {}, visual || ' '),
          post.format === 'carousel' && parts.length > 1 ? h('div', { class: 'pv-dots' }, parts.slice(0, 20).map((_, i) => h('i', { class: i === 0 ? 'on' : '' }))) : null,
          post.format === 'reel' ? h('span', { class: 'pv-badge' }, '▶ Reel') : null),
        post.format !== 'story' ? h('div', { class: 'pv-text' }, h('strong', {}, `${handle} `), folded(post.body, fold)) : null);
    } else if (post.platform === 'tiktok') {
      el = h('div', { class: 'pv-short pv-tiktok', style: { background: m.brand_color } },
        h('span', {}, plain(parts[0]) || (post.format === 'photo' ? 'Your first photo' : 'Your video')),
        post.format === 'photo' && parts.length > 1 ? h('div', { class: 'pv-dots' }, parts.slice(0, 35).map((_, i) => h('i', { class: i === 0 ? 'on' : '' }))) : null,
        h('div', { class: 'pv-short-title' }, h('strong', {}, `@${handle}`), h('div', {}, folded(post.body, fold))));
    } else {
      el = post.format === 'short'
        ? h('div', { class: 'pv-short', style: { background: m.brand_color } }, h('span', {}, plain(parts[0]) || 'Your Short'), h('div', { class: 'pv-short-title' }, post.title || ''))
        : h('div', { class: 'pv-li' }, h('div', { class: 'pv-head' }, h('div', { class: 'pv-avatar' }, name.slice(0, 1).toUpperCase()), h('strong', {}, name)), h('div', { class: 'pv-text' }, post.body || ''));
    }
    clear(preview).append(el);
  }

  // ---- header: label, format, status ----
  const titleIn = h('input', { type: 'text', class: 'title-input small', value: post.title || '', placeholder: 'Label (only you see this)', maxlength: 200, 'aria-label': 'Label' });
  titleIn.addEventListener('input', () => change('title', titleIn.value || null));
  const formatSel = h('select', { class: 'inline-select', 'aria-label': 'Format' },
    Object.entries(m.platforms[post.platform].formats).map(([f, info]) => h('option', { value: f, selected: f === post.format }, info.label)));
  formatSel.addEventListener('change', () => { change('format', formatSel.value, true); drawParts(); drawCounter(); syncBody(); });

  // ---- status, schedule, posted link and numbers ----
  const statusBox = h('div');
  function drawStatus() {
    const statusSel = h('select', { 'aria-label': 'Status' }, m.statuses.map((s) => h('option', { value: s, selected: s === post.status }, STATUS_LABEL[s])));
    statusSel.addEventListener('change', () => change('status', statusSel.value, true));
    const at = h('input', { type: 'datetime-local', value: toLocalInput(post.scheduled_for) });
    at.addEventListener('change', () => {
      if (!at.value) { change('scheduled_for', null, true); return; }
      pending.scheduled_for = new Date(at.value).toISOString();
      if (post.status !== 'posted') pending.status = 'scheduled';
      saveSoon.flush();
    });
    const parts = [
      field('Status', statusSel),
      field('Planned for', at, 'drag it on the Calendar too'),
    ];
    if (post.status === 'posted') {
      const url = h('input', { type: 'url', value: post.url || '', placeholder: 'Link to the live post' });
      url.addEventListener('change', () => change('url', url.value || null, true));
      const metrics = post.metrics || {};
      const nums = ['impressions', 'likes', 'comments', 'shares', 'saves'].map((k) => {
        const inp = h('input', { type: 'number', min: 0, value: metrics[k] ?? '', placeholder: '–' });
        inp.addEventListener('change', () => {
          const next = { ...(post.metrics || {}) };
          if (inp.value === '') delete next[k]; else next[k] = Number(inp.value);
          change('metrics', next, true);
        });
        return field(k[0].toUpperCase() + k.slice(1), inp);
      });
      parts.push(field('Live link', url), h('div', { class: 'grid grid-5 tight' }, nums),
        post.engagement ? h('p', { class: 'small' }, h('strong', {}, compact(post.engagement.total)), ' engagements', post.engagement.rate !== null ? ` · ${post.engagement.rate}% of impressions` : '') : h('p', { class: 'muted small' }, 'Add the numbers from the app’s insights after a day or two.'));
    } else {
      parts.push(h('button', { class: 'btn sm', onclick: () => { change('status', 'posted', true); } }, icon('check', 15), 'Mark as posted'));
    }
    clear(statusBox).append(parts);
  }

  // ---- copy out / open the app ----
  const fullText = () => [post.body, ...(fmtInfo().parts === 'Posts' ? [] : post.parts || [])].filter((x) => String(x || '').trim()).join('\n\n');
  function openApp() {
    const text = thready() ? (post.format === 'thread' ? post.parts[0] : post.body) || '' : post.body || '';
    copyText(thready() ? text : fullText(), 'Copied. Paste it in the app.');
    const url = post.platform === 'x' ? `https://twitter.com/intent/tweet?text=${encodeURIComponent(text)}`
      : post.platform === 'threads' ? `https://www.threads.net/intent/post?text=${encodeURIComponent(text)}`
      : post.platform === 'tiktok' ? 'https://www.tiktok.com/upload'
      : post.platform === 'linkedin' ? `https://www.linkedin.com/feed/?shareActive=true&text=${encodeURIComponent(text)}`
        : post.platform === 'youtube' ? 'https://studio.youtube.com/' : 'https://www.instagram.com/';
    window.open(url, '_blank', 'noopener');
  }

  const dupBtn = h('select', { class: 'btn-select', 'aria-label': 'Copy to another platform' },
    h('option', { value: '' }, 'Copy to…'),
    Object.entries(m.platforms).flatMap(([p, info]) => Object.entries(info.formats).map(([f, fi]) => h('option', { value: `${p}.${f}` }, `${info.label}: ${fi.label}`))));
  dupBtn.addEventListener('change', async () => {
    const [p, f] = dupBtn.value.split('.');
    dupBtn.value = '';
    if (!p) return;
    try {
      await saveSoon.flush();
      const r = await api('POST', `social/posts/${post.id}/duplicate`, { platform: p, format: f });
      toast('Copied. Rewrite it for that feed.');
      navigate(`/posts/${r.post.id}`);
    } catch (err) { toast(err.message, 'err'); }
  });

  const back = post.platform === 'youtube' ? '/calendar' : `/${post.platform}`;
  view.append(
    h('div', { class: 'editor-head' },
      link(back, { class: 'btn ghost sm' }, icon('back', 15), m.platforms[post.platform].label),
      formatSel,
      h('div', { class: 'editor-title' }, titleIn, h('h1', { class: 'sr-only' }, post.title || `${m.platforms[post.platform].label} post`)),
      h('div', { class: 'row' }, saveState,
        h('button', { class: 'btn sm', onclick: () => copyText(fullText(), 'Post copied.') }, icon('copy', 15), 'Copy'),
        h('button', { class: 'btn sm primary', onclick: openApp, title: 'Copy the text and open the app (nothing is posted for you)' }, icon('arrow', 15), `Open ${m.platforms[post.platform].label}`))),
    post.script ? h('p', { class: 'muted small', style: { margin: '-8px 0 14px' } }, 'From script: ', link(`/scripts/${post.script.id}`, {}, post.script.title)) : null,
    h('div', { class: 'sp-layout' },
      h('div', { class: 'sp-main' },
        h('div', { class: 'card' },
          h('div', { class: 'card-header' }, bodyHead, counter),
          bodyHint, bodyArea),
        h('div', { class: 'card' }, partsBox),
        slidesBox),
      h('aside', { class: 'sp-side' },
        h('div', { class: 'card' }, h('h3', {}, 'Preview'), preview, h('p', { class: 'muted small' }, 'Roughly how it shows in the feed. Cut-offs are typical phone values; check in the app before posting.')),
        h('div', { class: 'card' }, h('h3', {}, 'Checks'), checksBox),
        h('div', { class: 'card' }, h('h3', {}, 'Publishing'), statusBox),
        h('div', { class: 'card' },
          h('div', { class: 'row' }, dupBtn,
            h('button', {
              class: 'btn ghost sm danger',
              onclick: async () => {
                if (!(await confirmDialog('Delete this post?'))) return;
                saveSoon.cancel();
                try { await api('DELETE', `social/posts/${post.id}`); toast('Post deleted.'); navigate(back); } catch (err) { toast(err.message, 'err'); }
              },
            }, icon('trash', 15), 'Delete'))))));

  drawCounter();
  syncBody();
  drawParts();
  if (!fmtInfo().parts) partsBox.closest('.card').hidden = true;
  formatSel.addEventListener('change', () => { partsBox.closest('.card').hidden = !fmtInfo().parts; });
  drawPreview();
  drawChecks();
  drawStatus();

  const onKey = (e) => { if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 's') { e.preventDefault(); saveSoon.flush(); } };
  const onUnload = (e) => { if (Object.keys(pending).length) { saveSoon.flush(); e.preventDefault(); } };
  document.addEventListener('keydown', onKey);
  window.addEventListener('beforeunload', onUnload);
  return () => {
    document.removeEventListener('keydown', onKey);
    window.removeEventListener('beforeunload', onUnload);
    saveSoon.flush();
  };
}

// ------------------------------------------------------------------
// Calendar: /calendar
// ------------------------------------------------------------------

function mondayOf(d) {
  const x = new Date(d.getFullYear(), d.getMonth(), d.getDate());
  x.setDate(x.getDate() - ((x.getDay() + 6) % 7));
  return x;
}
const dayKey = (d) => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;

export async function renderCalendar(view) {
  const m = await meta(true);
  let start = mondayOf(new Date());
  let data = null;
  const head = h('div', { class: 'cal-head' });
  const grid = h('div', { class: 'cal-grid' });
  const side = h('div');
  const progressBox = h('div');

  /** Put a post on a day, keeping its time of day (9:00 if it had none). */
  async function scheduleOn(postId, day, time = null) {
    const p = [...data.posts, ...data.unscheduled].find((x) => x.id === postId);
    const old = p?.scheduled_for ? new Date(p.scheduled_for) : null;
    const at = new Date(day);
    if (time) at.setHours(...time.split(':').map(Number), 0, 0);
    else at.setHours(old ? old.getHours() : 9, old ? old.getMinutes() : 0, 0, 0);
    await api('PATCH', `social/posts/${postId}`, { scheduled_for: at.toISOString(), status: 'scheduled' });
    toast(`Scheduled for ${when(at.toISOString())}.`);
    await load();
  }

  function scheduleDialog(post) {
    return modal('Schedule', (close) => {
      const at = h('input', { type: 'datetime-local', value: toLocalInput(post.scheduled_for) || `${dayKey(new Date())}T09:00`, required: true });
      const form = h('form', {
        onsubmit: (e) => {
          e.preventDefault();
          busy(form.querySelector('[type=submit]'), 'Saving…', async () => {
            const [d, t] = at.value.split('T');
            await scheduleOn(post.id, new Date(`${d}T00:00`), t);
            close(true);
          });
        },
      }, field('When', at), h('div', { class: 'row', style: { justifyContent: 'flex-end' } }, h('button', { type: 'submit', class: 'btn primary' }, 'Schedule')));
      return form;
    });
  }

  function item(post) {
    const t = post.status === 'posted' ? post.posted_at : post.scheduled_for;
    const el = link(`/posts/${post.id}`, {
      class: `cal-item pf-${post.platform}${post.status === 'posted' ? ' posted' : ''}`,
      draggable: post.status === 'posted' ? 'false' : 'true',
      title: `${m.platforms[post.platform].label} · ${formatLabel(m, post.platform, post.format)} · ${STATUS_LABEL[post.status]}`,
    },
    h('span', { class: 'cal-time' }, t ? new Date(t).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) : ''),
    icon(PLATFORM_ICONS[post.platform], 13),
    h('span', { class: 'clamp-2' }, post.title || plain(post.body) || plain(post.parts?.[0]) || formatLabel(m, post.platform, post.format)),
    post.status === 'posted' ? icon('check', 13) : null);
    el.addEventListener('dragstart', (e) => { e.dataTransfer.setData('text/plain', String(post.id)); e.dataTransfer.effectAllowed = 'move'; });
    return el;
  }

  function draw() {
    const end = new Date(start); end.setDate(end.getDate() + 6);
    const today = dayKey(new Date());
    clear(head).append(
      h('div', { class: 'row' },
        h('button', { class: 'btn sm', 'aria-label': 'Previous week', onclick: () => { start.setDate(start.getDate() - 7); load(); } }, icon('chevron-left', 16)),
        h('button', { class: 'btn sm', onclick: () => { start = mondayOf(new Date()); load(); } }, 'This week'),
        h('button', { class: 'btn sm', 'aria-label': 'Next week', onclick: () => { start.setDate(start.getDate() + 7); load(); } }, icon('chevron-right', 16))),
      h('strong', {}, `${start.toLocaleDateString([], { day: 'numeric', month: 'short' })} – ${end.toLocaleDateString([], { day: 'numeric', month: 'short', year: 'numeric' })}`));

    const byDay = {};
    for (const p of data.posts) {
      const t = p.status === 'posted' && p.posted_at ? p.posted_at : p.scheduled_for;
      if (!t) continue;
      (byDay[dayKey(new Date(t))] ||= []).push(p);
    }
    const ytByDay = {};
    for (const y of data.youtube) (ytByDay[y.date] ||= []).push(y);

    clear(grid);
    for (let i = 0; i < 7; i++) {
      const d = new Date(start); d.setDate(d.getDate() + i);
      const key = dayKey(d);
      const posts = (byDay[key] || []).sort((a, b) => new Date(a.scheduled_for || a.posted_at) - new Date(b.scheduled_for || b.posted_at));
      const col = h('div', { class: `cal-day${key === today ? ' today' : ''}`, dataset: { day: key } },
        h('div', { class: 'cal-date' }, h('span', {}, d.toLocaleDateString([], { weekday: 'short' })), h('strong', {}, d.getDate())),
        (ytByDay[key] || []).map((y) => link(y.link, { class: `cal-item pf-youtube yt-${y.kind}` }, icon('play', 13),
          h('span', { class: 'clamp-2' }, y.kind === 'due' ? `Due: ${y.title}` : `Published: ${y.title}`))),
        posts.map(item),
        h('button', { class: 'cal-add', title: 'New post on this day', onclick: () => newPostDialog(null, { scheduled_for: new Date(`${key}T09:00`).toISOString(), status: 'scheduled' }).catch((e) => toast(e.message, 'err')) }, icon('plus', 14)));
      col.addEventListener('dragover', (e) => { e.preventDefault(); col.classList.add('drop'); });
      col.addEventListener('dragleave', () => col.classList.remove('drop'));
      col.addEventListener('drop', (e) => {
        e.preventDefault();
        col.classList.remove('drop');
        const pid = Number(e.dataTransfer.getData('text/plain'));
        if (pid) scheduleOn(pid, new Date(`${key}T00:00`)).catch((err) => toast(err.message, 'err'));
      });
      grid.append(col);
    }

    clear(side).append(h('div', { class: 'card' },
      h('div', { class: 'card-header' }, h('h3', {}, 'Not scheduled yet'),
        h('button', { class: 'btn ghost sm', onclick: () => newPostDialog().catch((e) => toast(e.message, 'err')) }, icon('plus', 15), 'New')),
      h('p', { class: 'muted small' }, 'Drag one onto a day, or press Schedule.'),
      data.unscheduled.length ? h('div', { class: 'cal-unscheduled' }, data.unscheduled.map((p) => h('div', { class: 'cal-u' },
        item(p), h('button', { class: 'btn ghost sm', onclick: () => scheduleDialog(p).catch((e) => toast(e.message, 'err')) }, 'Schedule'))))
        : h('p', { class: 'muted small' }, 'All caught up. Repurpose a script for a fresh batch.')));
    clear(progressBox).append(progressChips(data.progress) || h('p', { class: 'muted small' }, 'Set weekly targets per platform in Settings → Social to track them here.'));
  }

  async function load() {
    const end = new Date(start); end.setDate(end.getDate() + 7);
    data = await api('GET', `social/calendar?from=${encodeURIComponent(start.toISOString())}&to=${encodeURIComponent(end.toISOString())}`);
    draw();
  }
  await load();

  view.append(
    pageHead('Calendar', 'Everything you’re publishing this week, on every platform. YouTube dates come from your weekly plan and scripts.',
      link('/repurpose', { class: 'btn' }, icon('repeat', 16), 'Repurpose'),
      h('button', { class: 'btn primary', onclick: () => newPostDialog().catch((e) => toast(e.message, 'err')) }, icon('plus', 16), 'New post')),
    progressBox,
    head,
    h('div', { class: 'cal-layout' }, grid, side));
}

// ------------------------------------------------------------------
// Repurpose: /repurpose
// ------------------------------------------------------------------

const DEFAULT_TARGETS = ['linkedin.post', 'x.thread', 'instagram.carousel'];

export async function renderRepurpose(view) {
  const params = new URLSearchParams(location.search);
  const [m, scripts, docs, notes] = await Promise.all([
    meta(), api('GET', 'scripts'), api('GET', 'documents').catch(() => ({ documents: { data: [] } })), api('GET', 'notes').catch(() => ({ notes: [] })),
  ]);
  const scriptList = scripts.scripts.data;
  const docList = docs.documents?.data || docs.documents || [];
  let source = params.get('script') ? 'script' : scriptList.length ? 'script' : 'text';
  const chosen = new Set(DEFAULT_TARGETS);

  const scriptSel = h('select', {}, scriptList.map((s) => h('option', { value: s.id, selected: String(s.id) === params.get('script') }, `${s.title} (${s.status})`)));
  const docSel = h('select', {}, docList.map((d) => h('option', { value: d.id }, d.title)));
  const noteSel = h('select', {}, notes.notes.map((n) => h('option', { value: n.id }, plain(n.polished).slice(0, 80))));
  const textTitle = h('input', { type: 'text', placeholder: 'What is it? (e.g. “Talk at the meetup”)', maxlength: 200 });
  const textArea = h('textarea', { rows: 8, placeholder: 'Paste anything: notes, a blog post, a transcript…' });
  const extra = h('input', { type: 'text', maxlength: 1000, placeholder: 'Optional direction, e.g. “aim at founders”, “keep it funny”, “mention the free template”' });
  const sourceBox = h('div');
  const targetsBox = h('div', { class: 'target-grid' });
  const results = h('div');

  const SOURCES = [
    ['script', 'Script', scriptList.length], ['transcript', 'What you said (transcript)', scriptList.length],
    ['document', 'Library document', docList.length], ['note', 'Dictated note', notes.notes.length], ['text', 'Paste text', 1],
  ];
  function drawSource() {
    clear(sourceBox).append(
      h('div', { class: 'choice-row' }, SOURCES.filter(([, , n]) => n).map(([k, label]) => h('button', {
        class: `btn sm${source === k ? ' primary' : ''}`, onclick: () => { source = k; drawSource(); },
      }, label))),
      source === 'script' || source === 'transcript' ? field(source === 'transcript' ? 'Script (its transcript is used; the script if there is none)' : 'Script', scriptSel)
        : source === 'document' ? field('Document', docSel)
          : source === 'note' ? field('Note', noteSel)
            : h('div', {}, field('Title', textTitle), field('Text', textArea)));
  }

  function drawTargets() {
    clear(targetsBox).append(Object.entries(m.platforms).map(([p, info]) => h('div', { class: `target-col pf-${p}` },
      h('div', { class: 'target-head' }, icon(PLATFORM_ICONS[p], 15), h('strong', {}, info.label)),
      Object.entries(info.formats).map(([f, fi]) => {
        const key = `${p}.${f}`;
        const cb = h('input', { type: 'checkbox', checked: chosen.has(key), onchange: () => { if (cb.checked) chosen.add(key); else chosen.delete(key); } });
        return h('label', { class: 'target-opt' }, cb, fi.label);
      }))));
  }

  const go = h('button', { class: 'btn primary', type: 'button' }, icon('sparkle', 16), 'Write the drafts');
  go.addEventListener('click', () => busy(go, 'Writing drafts…', async () => {
    if (!chosen.size) throw new Error('Pick at least one format.');
    const body = {
      source, extra: extra.value.trim() || null,
      targets: [...chosen].map((k) => { const [platform, format] = k.split('.'); return { platform, format }; }),
    };
    if (source === 'script' || source === 'transcript') body.id = Number(scriptSel.value);
    if (source === 'document') body.id = Number(docSel.value);
    if (source === 'note') body.id = Number(noteSel.value);
    if (source === 'text') Object.assign(body, { text: textArea.value, title: textTitle.value || null });
    const r = await api('POST', 'social/repurpose', body);
    toast(r.message);
    clear(results).append(h('div', { class: 'card' },
      h('div', { class: 'card-header' }, h('h2', {}, 'Your drafts'), link('/calendar', { class: 'btn sm' }, icon('calendar', 15), 'Schedule them')),
      h('p', { class: 'muted small' }, 'Saved as drafts. Open each one to edit, preview and copy it out.'),
      h('div', { class: 'post-grid' }, r.posts.map((p) => {
        const hard = p.checks.filter((c) => c.severity === 'HARD').length;
        return h('div', {}, postCard(p, m), hard ? h('p', { class: 'small error-text' }, `${hard} thing(s) to fix before posting`) : null);
      }))));
    results.scrollIntoView({ behavior: 'smooth', block: 'start' });
  }));

  drawSource();
  drawTargets();
  view.append(
    pageHead('Repurpose', 'One video, many posts. Each one is rewritten for its own feed in your voice, not pasted four times.'),
    h('div', { class: 'card' }, h('h2', {}, '1. Start from'), sourceBox),
    h('div', { class: 'card' }, h('h2', {}, '2. Turn it into'), targetsBox),
    h('div', { class: 'card' }, h('h2', {}, '3. Anything to steer it?'), extra, h('div', { class: 'row', style: { marginTop: '14px' } }, go)),
    results);
}

/** Dashboard strip: this week on each platform with a target or activity. */
export function socialStrip(progress) {
  const chips = progressChips(progress || {});
  return chips ? h('div', { class: 'card' },
    h('div', { class: 'card-header' }, h('h2', {}, 'This week on social'), link('/calendar', { class: 'btn ghost sm' }, 'Calendar', icon('arrow', 14))),
    chips) : null;
}
