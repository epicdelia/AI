// Dashboard card for the content feed on her public landing page: what each network shows
// (her real videos and posts, kept fresh hourly by studio:landing), what visitors open, and a
// manager to feature (pin) or hide items. Changes are live on the page straight away.
import { h, api, link, modal, toast, timeAgo, compact } from './core.js';
import { icon } from './icons.js';

const NETS = [
  ['youtube', 'brand:youtube', 'YouTube'],
  ['instagram', 'brand:instagram', 'Instagram'],
  ['tiktok', 'brand:tiktok', 'TikTok'],
  ['twitter', 'brand:x', 'X'],
  ['threads', 'brand:threads', 'Threads'],
];

const SOURCE_HINT = {
  live: 'Your latest, updated hourly',
  sample: 'Curated videos until YouTube is connected (Settings → YouTube API key)',
  none: 'Nothing yet: posts you publish (with their link) appear automatically',
};

function netChip([id, ic, label], summary) {
  const src = summary.sources?.[id] || 'none';
  const n = summary.counts?.[id] || 0;
  return h('span', { class: `website-net is-${src}`, title: SOURCE_HINT[src] },
    icon(ic, 14), label, h('b', {}, src === 'none' ? '-' : n));
}

export function websiteCard(summary, onChange) {
  if (!summary) return null;
  const card = h('div', { class: 'card website-card' },
    h('div', { class: 'card-header' },
      h('div', {},
        h('h2', {}, 'Your website feed'),
        h('p', { class: 'muted small', style: { margin: '2px 0 0' } }, `Updated ${timeAgo(summary.updated_at)} · refreshes hourly`)),
      h('div', { class: 'row' },
        link('/landing', { class: 'btn ghost sm' }, icon('eye', 14), 'Preview'),
        h('button', { class: 'btn sm', onclick: () => manageFeed().then((changed) => changed && onChange?.()) }, icon('sliders', 14), 'Manage'))),
    h('div', { class: 'website-nets' }, NETS.map((n) => netChip(n, summary))),
    h('div', { class: 'website-stats' },
      h('div', { class: 'website-stat' }, h('strong', {}, compact(summary.clicks_7d || 0)), h('span', { class: 'muted small' }, 'opens this week')),
      summary.top?.length
        ? h('ol', { class: 'website-top' }, summary.top.map((t) => h('li', {},
          h('a', { href: t.url, target: '_blank', rel: 'noopener', class: 'clamp-1' }, t.title),
          h('span', { class: 'muted small' }, `${t.clicks} ${t.clicks === 1 ? 'open' : 'opens'}`))))
        : h('p', { class: 'muted small', style: { margin: 0 } }, 'When visitors open your videos and posts from the site, the favourites show here.')));
  return card;
}

/** Feature or hide items per network. Resolves true when something changed. */
export function manageFeed() {
  return modal('Website feed', (close) => {
    let data = null;
    let hidden = new Set();
    let pinned = [];
    let net = 'youtube';
    let dirty = false;
    const body = h('div', { class: 'website-manage' }, h('p', { class: 'muted' }, 'Loading…'));
    const saveBtn = h('button', { class: 'btn primary', disabled: true, onclick: save }, 'Save');
    const refreshBtn = h('button', { class: 'btn ghost', onclick: refresh }, icon('refresh', 14), 'Refresh now');

    function load(res) {
      data = res;
      hidden = new Set(Object.values(res.platforms).flat().filter((i) => i.hidden).map((i) => i.key));
      pinned = Object.values(res.platforms).flat().filter((i) => i.pinned).map((i) => i.key);
      dirty = false;
      saveBtn.disabled = true;
      draw();
    }

    function draw() {
      const items = data.platforms[net] || [];
      const src = data.sources[net];
      body.replaceChildren(
        h('div', { class: 'website-tabs', role: 'tablist' }, NETS.map(([id, ic, label]) => h('button', {
          type: 'button', role: 'tab', class: `website-tab${id === net ? ' on' : ''}`, 'aria-selected': String(id === net),
          onclick: () => { net = id; draw(); },
        }, icon(ic, 14), label, h('span', { class: 'muted small' }, (data.platforms[id] || []).length)))),
        h('p', { class: 'muted small' }, SOURCE_HINT[src]),
        items.length
          ? h('ul', { class: 'website-items' }, items.map((it) => {
            const isHidden = hidden.has(it.key);
            const isPinned = pinned.includes(it.key);
            return h('li', { class: `website-item${isHidden ? ' is-hidden' : ''}` },
              it.thumb ? h('img', { src: it.thumb, alt: '', loading: 'lazy' }) : h('span', { class: 'website-item-ph' }, icon(NETS.find((n) => n[0] === net)[1], 16)),
              h('div', { class: 'website-item-main' },
                h('a', { href: it.url, target: '_blank', rel: 'noopener', class: 'clamp-1' }, it.title || it.text),
                h('span', { class: 'muted small' }, [it.date ? timeAgo(it.date) : null, it.views_label, `${it.clicks_30d || 0} opens in 30 days`].filter(Boolean).join(' · '))),
              h('button', {
                type: 'button', class: `btn sm ${isPinned ? 'primary' : 'ghost'}`, 'aria-pressed': String(isPinned), disabled: isHidden,
                title: 'Featured items show first', onclick: () => toggle('pin', it.key),
              }, icon('pin', 13), isPinned ? 'Featured' : 'Feature'),
              h('button', {
                type: 'button', class: 'btn sm ghost', 'aria-pressed': String(isHidden), onclick: () => toggle('hide', it.key),
              }, icon(isHidden ? 'eye' : 'x', 13), isHidden ? 'Show' : 'Hide'));
          }))
          : h('div', { class: 'empty small' }, h('p', {}, 'Nothing published here yet.'),
            h('p', { class: 'muted small' }, 'Mark a post as posted with its link (or connect the account) and it appears on your site within the hour.')));
    }

    function toggle(kind, key) {
      if (kind === 'hide') {
        if (hidden.has(key)) hidden.delete(key); else { hidden.add(key); pinned = pinned.filter((k) => k !== key); }
      } else {
        pinned = pinned.includes(key) ? pinned.filter((k) => k !== key) : [...pinned, key];
      }
      dirty = true;
      saveBtn.disabled = false;
      draw();
    }

    async function save() {
      saveBtn.disabled = true;
      try {
        load(await api('PUT', 'landing/content', { hidden: [...hidden], pinned }));
        toast('Your website feed is updated.');
        close(true);
      } catch (err) {
        saveBtn.disabled = false;
        toast(err.message || 'Could not save.', 'err');
      }
    }

    async function refresh() {
      refreshBtn.disabled = true;
      try {
        const keepDirty = dirty;
        const res = await api('POST', 'landing/content/refresh');
        if (!keepDirty) load(res); else { data = res; draw(); }
        toast('Pulled your latest videos and posts.');
      } catch (err) {
        toast(err.message || 'Could not refresh.', 'err');
      } finally {
        refreshBtn.disabled = false;
      }
    }

    api('GET', 'landing/content').then(load).catch((err) => body.replaceChildren(h('p', { class: 'error' }, err.message)));

    return h('div', {},
      body,
      h('div', { class: 'row', style: { justifyContent: 'space-between', marginTop: '16px' } },
        refreshBtn,
        h('div', { class: 'row' }, h('button', { class: 'btn ghost', onclick: () => close(false) }, 'Cancel'), saveBtn)));
  }, { wide: true });
}
