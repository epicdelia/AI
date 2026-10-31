// Social: LinkedIn, Instagram, X and YouTube (Shorts, community) posts. Draft and preview them
// here, copy them out to each app, then mark them posted with the link and the numbers.
// Nothing is ever posted for her: there are no sign-ins to her accounts.
import {
  h, api, apiUpload, busy, toast, pageHead, emptyState, clear, pill, modal, confirmDialog, field, navigate, link,
  timeAgo, debounce, copyText, slug, compact,
} from '../core.js';
import { icon } from '../icons.js';
import { carouselCard } from '../carousel.js';
import { bioCard, importDialog, demoControl } from '../social-tools.js';
import { scoreCard } from '../growth.js';

export const PLATFORM_ICONS = { linkedin: 'brand:linkedin', instagram: 'brand:instagram', x: 'brand:x', tiktok: 'brand:tiktok', threads: 'brand:threads', youtube: 'brand:youtube' };
const STATUS_PILL = { idea: 'new', draft: 'WARN', scheduled: 'picked', posted: 'ok' };
export const STATUS_LABEL = { idea: 'Idea', draft: 'Draft', scheduled: 'Scheduled', posted: 'Posted' };
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

export function postCard(post, m) {
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

export function progressChips(progress, only = null) {
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
      : emptyState(icon('pen', 30), filter === 'all' ? `No ${info.label} posts yet` : `Nothing ${STATUS_LABEL[filter].toLowerCase()}`,
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
      h('button', { class: 'btn', onclick: async () => { if (await importDialog(m.platforms, platform)) navigate(location.pathname); } }, icon('download', 16), 'Import numbers'),
      link(`/repurpose`, { class: 'btn' }, icon('repeat', 16), 'Repurpose'),
      h('button', { class: 'btn primary', onclick: () => newPostDialog(platform).catch((e) => toast(e.message, 'err')) }, icon('plus', 16), 'New post')),
    m.demo ? demoControl(m.demo) : null,
    progressChips(m.progress, platform),
    h('div', { class: 'grid grid-sidebar', style: { marginTop: '16px' } },
      h('div', {}, tabBar, listBox),
      h('aside', {}, statsCard, bioCard(platform), info.target ? null : h('div', { class: 'card' },
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

/** The text to paste for a post (thread posts are pasted one by one, so just the first). */
export function postText(post, m) {
  const label = m.platforms[post.platform]?.formats[post.format]?.parts;
  if (THREADY.includes(post.platform) && post.format === 'thread') return post.parts?.[0] || '';
  return [post.body, ...(label === 'Posts' ? [] : post.parts || [])].filter((x) => String(x || '').trim()).join('\n\n');
}

/** Copy the post and open the network's composer (nothing is posted for her). */
export function openInApp(post, m) {
  const text = postText(post, m);
  copyText(text, 'Copied. Paste it in the app.');
  const short = THREADY.includes(post.platform) ? text : (post.body || '');
  const url = post.platform === 'x' ? `https://twitter.com/intent/tweet?text=${encodeURIComponent(short)}`
    : post.platform === 'threads' ? `https://www.threads.net/intent/post?text=${encodeURIComponent(short)}`
    : post.platform === 'tiktok' ? 'https://www.tiktok.com/upload'
    : post.platform === 'linkedin' ? `https://www.linkedin.com/feed/?shareActive=true&text=${encodeURIComponent(short)}`
      : post.platform === 'youtube' ? 'https://studio.youtube.com/' : 'https://www.instagram.com/';
  window.open(url, '_blank', 'noopener');
}

export async function renderPost(view, id) {
  const [m, data] = await Promise.all([meta(), api('GET', `social/posts/${id}`)]);
  const post = data.post;
  post.parts = post.parts || [];
  setActiveNav(post.platform === 'youtube' ? '/calendar' : `/${post.platform}`);

  const pending = {};
  // Live post score; "Use" in the hook lab swaps the opening line of the text (or of the first part).
  const score = post.status === 'posted' ? null : scoreCard(
    () => ({ platform: post.platform, format: post.format, body: post.body || '', parts: post.parts || [], scheduled_for: post.scheduled_for || null,
      pillar: post.pillar || null, media: (post.media || []).length, post_id: post.id }),
    (hook) => {
      const swap = (t) => { const i = (t || '').indexOf('\n'); return i < 0 ? hook : hook + t.slice(i); };
      if ((post.body || '').trim()) {
        bodyArea.value = swap(post.body);
        change('body', bodyArea.value, true);
        drawCounter();
      } else if (post.parts?.length) {
        setParts([swap(post.parts[0]), ...post.parts.slice(1)]);
      }
    });
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
    score?.refresh();
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
        name: post.title || 'carousel', color: m.brand_color, brand: m.brand, handle: handleOf(m, post.platform), pdf: post.platform === 'linkedin',
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
        h('div', { class: 'pv-head' }, h('div', { class: 'pv-avatar' }, name.slice(0, 1).toUpperCase()), h('div', {}, h('strong', {}, name), h('div', { class: 'muted small' }, 'now · ', icon('globe', 11)))),
        h('div', { class: 'pv-text' }, folded(post.body, fold)),
        post.format === 'carousel' && parts.length ? h('div', { class: 'pv-slide', style: { background: m.brand_color } }, h('span', {}, plain(parts[0])), h('span', { class: 'pv-slide-count' }, `1 / ${parts.length}`)) : null);
    } else if (post.platform === 'instagram') {
      const visual = post.format === 'carousel' || post.format === 'story' ? plain(parts[0]) : post.format === 'reel' ? plain(parts[0]) : post.title || 'Your image';
      const firstImg = (post.media || []).find((x) => String(x.mime).startsWith('image/'));
      el = h('div', { class: 'pv-ig' },
        h('div', { class: 'pv-head' }, h('div', { class: 'pv-avatar ring' }, name.slice(0, 1).toUpperCase()), h('strong', {}, handle)),
        h('div', { class: `pv-media ${post.format === 'reel' || post.format === 'story' ? 'tall' : ''}`, style: { background: m.brand_color } },
          firstImg ? h('img', { class: 'pv-photo', src: firstImg.url, alt: firstImg.alt || '' }) : h('span', {}, visual || ' '),
          post.format === 'carousel' && parts.length > 1 ? h('div', { class: 'pv-dots' }, parts.slice(0, 20).map((_, i) => h('i', { class: i === 0 ? 'on' : '' }))) : null,
          post.format === 'reel' ? h('span', { class: 'pv-badge' }, icon('play', 11), ' Reel') : null),
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
    const slotNote = h('p', { class: 'muted small slot-note' }, post.notes && post.status === 'scheduled' ? post.notes : '');
    const slotBtn = post.status === 'posted' ? null : h('button', {
      class: 'btn ghost sm', title: 'The next good, free time on this network',
      onclick: () => busy(slotBtn, 'Finding…', async () => {
        const r = await api('POST', `social/posts/${post.id}/auto-schedule`);
        Object.assign(post, r.post, { parts: r.post.parts || [], notes: r.reason });
        toast(r.message);
        drawStatus();
      }),
    }, icon('calendar', 14), 'Next good slot');
    const parts = [
      field('Status', statusSel),
      field('Planned for', at, 'drag it on the Calendar too'),
      slotBtn ? h('div', {}, slotBtn, slotNote) : null,
    ];
    if (m.pillars?.length) {
      const pillarSel = h('select', { 'aria-label': 'Pillar' }, h('option', { value: '' }, '—'), m.pillars.map((p) => h('option', { value: p, selected: p === post.pillar }, p)));
      pillarSel.addEventListener('change', () => change('pillar', pillarSel.value || null, true));
      parts.push(field('Pillar', pillarSel));
    }
    if (fmtInfo().parts === 'Beats') {
      parts.push(h('a', { class: 'btn ghost sm', href: `/api/social/posts/${post.id}/srt`, title: 'Captions timed at speaking pace, for the video editor or upload' }, icon('download', 14), 'Captions .srt'));
    }
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
  const openApp = () => openInApp(post, m);

  // ---- media: images and videos to post with it ----
  const mediaBox = h('div');
  const mediaIn = h('input', { type: 'file', accept: 'image/*,video/*', multiple: true, hidden: true });
  const setPost = (p) => { Object.assign(post, p, { parts: p.parts || [] }); drawMedia(); drawPreview(); };
  mediaIn.addEventListener('change', async () => {
    for (const f of [...mediaIn.files]) {
      const fd = new FormData();
      fd.append('file', f);
      try { setPost((await apiUpload(`social/posts/${post.id}/media`, fd)).post); } catch (err) { toast(err.message, 'err'); }
    }
    mediaIn.value = '';
  });
  async function pickMockup() {
    const list = (await api('GET', 'media?kind=thumbnail').catch(() => ({ media: [] }))).media;
    if (!list.length) { toast('No thumbnail mockups yet: make one on a script’s Package tab.'); return; }
    await modal('Add one of your mockups', (close) => h('div', { class: 'mockup-pick' }, list.map((mf) => h('button', {
      class: 'mockup-tile', title: mf.title || 'Mockup',
      onclick: async () => {
        try { setPost((await api('POST', `social/posts/${post.id}/media/attach`, { media_id: mf.id })).post); close(true); } catch (err) { toast(err.message, 'err'); }
      },
    }, h('img', { src: mf.url, alt: mf.title || 'Mockup' })))), { wide: true });
  }
  function drawMedia() {
    const items = post.media || [];
    clear(mediaBox).append(
      h('div', { class: 'card-header' }, h('h3', {}, 'Media'),
        h('div', { class: 'row' },
          h('button', { class: 'btn ghost sm', onclick: () => pickMockup().catch((e) => toast(e.message, 'err')) }, icon('image', 15), 'From mockups'),
          h('button', { class: 'btn sm', onclick: () => mediaIn.click() }, icon('plus', 15), 'Upload'))),
      items.length ? h('ul', { class: 'media-list' }, items.map((it) => {
        const isImg = String(it.mime).startsWith('image/');
        const alt = h('textarea', { rows: 2, placeholder: 'Alt text: what’s in the image, for screen readers' }, it.alt || '');
        alt.addEventListener('change', async () => { try { setPost((await api('POST', `social/posts/${post.id}/media/${it.id}/alt`, { alt: alt.value })).post); } catch (err) { toast(err.message, 'err'); } });
        const aiBtn = h('button', {
          class: 'btn ghost sm',
          onclick: () => busy(aiBtn, 'Writing…', async () => { setPost((await api('POST', `social/posts/${post.id}/media/${it.id}/alt`)).post); }),
        }, icon('sparkle', 14), 'Write alt text');
        return h('li', { class: 'media-item' },
          isImg ? h('img', { src: it.url, alt: it.alt || '' }) : h('video', { src: it.url, controls: true, preload: 'metadata' }),
          h('div', { class: 'media-meta' },
            h('div', { class: 'row' }, h('strong', { class: 'clamp-1' }, it.name || 'File'),
              h('a', { class: 'btn ghost sm', href: `${it.url}?download=1`, title: 'Download to upload it' }, icon('download', 14)),
              h('button', {
                class: 'btn ghost sm danger', title: 'Remove',
                onclick: async () => { try { setPost((await api('DELETE', `social/posts/${post.id}/media/${it.id}`)).post); } catch (err) { toast(err.message, 'err'); } },
              }, icon('x', 14))),
            isImg ? [alt, aiBtn] : h('p', { class: 'muted small' }, 'Video: upload it in the app with the caption.')));
      })) : h('p', { class: 'muted small' }, post.platform === 'instagram' || post.platform === 'tiktok' ? 'Instagram and TikTok need an image or video. Upload it here so everything for this post is in one place.' : 'Optional: an image or video to post with it.'),
      mediaIn);
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
        h('div', { class: 'card' }, mediaBox),
        slidesBox),
      h('aside', { class: 'sp-side' },
        h('div', { class: 'card' }, h('h3', {}, 'Preview'), preview, h('p', { class: 'muted small' }, 'Roughly how it shows in the feed. Cut-offs are typical phone values; check in the app before posting.')),
        score,
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
  drawMedia();
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
