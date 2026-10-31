// Breakouts: the competitor watchlist and the videos doing far better than their channel's median.
import { h, api, busy, toast, pageHead, emptyState, compact, timeAgo, fmtDay, link, confirmDialog, clear, pill } from '../core.js';
import { icon } from '../icons.js';
import { youtubeDialog } from '../components.js';

export async function renderBreakouts(view) {
  let data = await api('GET', 'channels');
  const feeds = await feedSection();

  const channelsBox = h('div');
  const outliersBox = h('div');
  const freshLabel = h('span', { class: 'muted' });

  function drawChannels() {
    clear(channelsBox);
    if (!data.channels.length) {
      channelsBox.append(h('p', { class: 'muted' }, 'No channels yet. Add the creators in your niche whose videos you’d want to beat.'));
      return;
    }
    channelsBox.append(h('ul', { class: 'channel-list' }, data.channels.map((c) => h('li', { class: c.active ? '' : 'inactive' },
      h('div', { class: 'item-main' },
        h('a', {
          href: /^UC[\w-]{22}$/.test(c.handle) ? `https://youtube.com/channel/${c.handle}` : `https://youtube.com/@${c.handle}`,
          target: '_blank', rel: 'noopener',
        }, h('strong', {}, c.title || `@${c.handle}`)),
        h('div', { class: 'muted' },
          c.title ? `@${c.handle} · ` : '',
          `${c.outliers_count} breakout${c.outliers_count === 1 ? '' : 's'} · scanned ${timeAgo(c.last_scanned_at)}`),
        c.last_error ? h('div', { class: 'error-text' }, c.last_error) : null),
      h('div', { class: 'row' },
        h('label', { class: 'switch', title: c.active ? 'Included in scans' : 'Paused' },
          h('input', {
            type: 'checkbox',
            checked: c.active,
            onchange: async (e) => {
              try {
                const res = await api('PATCH', `channels/${c.id}/toggle`);
                Object.assign(c, res.channel);
              } catch (err) { toast(err.message, 'err'); e.target.checked = c.active; }
            },
          }),
          h('span', { class: 'slider' })),
        h('button', {
          class: 'btn ghost sm danger', 'aria-label': `Remove ${c.handle}`,
          onclick: async () => {
            if (!(await confirmDialog(`Remove ${c.title || '@' + c.handle} from the watchlist? Its breakouts stay.`, { ok: 'Remove' }))) return;
            try {
              await api('DELETE', `channels/${c.id}`);
              data.channels = data.channels.filter((x) => x.id !== c.id);
              drawChannels();
            } catch (err) { toast(err.message, 'err'); }
          },
        }, icon('trash', 16)))))));
  }

  function drawOutliers() {
    clear(outliersBox);
    freshLabel.textContent = data.fresh_count !== undefined ? `${data.fresh_count} not yet used for ideas` : '';
    if (!data.outliers.length) {
      outliersBox.append(emptyState('📈', 'No breakouts yet',
        'Run a scan. A breakout is a recent long-form video doing at least your minimum multiple of its channel’s median views.'));
      return;
    }
    outliersBox.append(h('div', { class: 'table-wrap' }, h('table', {},
      h('thead', {}, h('tr', {},
        h('th', {}, 'Video'), h('th', { class: 'num' }, 'Score'), h('th', { class: 'num' }, 'Views'),
        h('th', { class: 'num hide-sm' }, 'Median'), h('th', { class: 'hide-sm' }, 'Published'), h('th', {}, ''))),
      h('tbody', {}, data.outliers.map((o) => h('tr', {},
        h('td', {},
          h('a', { href: o.url, target: '_blank', rel: 'noopener' }, h('strong', {}, o.title)),
          h('div', { class: 'muted' }, o.channel_title || '', ' ', o.used ? pill('used', 'used for ideas') : pill('new', 'fresh'))),
        h('td', { class: 'num' }, h('span', { class: 'score' }, `${Number(o.outlier_score).toFixed(1)}×`)),
        h('td', { class: 'num' }, compact(o.views)),
        h('td', { class: 'num hide-sm' }, compact(o.channel_median)),
        h('td', { class: 'hide-sm' }, o.published_at ? fmtDay(o.published_at) : '', h('div', { class: 'muted' }, `${o.age_days} days old`)),
        h('td', { class: 'num nowrap' }, h('button', {
          class: 'btn ghost sm', title: 'Why did it work?',
          onclick: () => youtubeDialog({ url: o.url, title: o.title, action: 'breakdown' }).catch((e) => toast(e.message, 'err')),
        }, icon('search', 16)), h('button', {
          class: 'btn ghost sm', title: 'Dismiss',
          onclick: async () => {
            try {
              await api('DELETE', `breakouts/${o.id}`);
              data.outliers = data.outliers.filter((x) => x.id !== o.id);
              if (!o.used && data.fresh_count) data.fresh_count--;
              drawOutliers();
            } catch (err) { toast(err.message, 'err'); }
          },
        }, icon('x', 16)))))))));
  }

  const handlesInput = h('textarea', {
    rows: 2, name: 'handles',
    placeholder: '@mkbhd, @aliabdaal, https://youtube.com/@thomasfrank\n(handles, URLs or UC… channel IDs; separate with commas or new lines)',
  });
  const addBtn = h('button', { class: 'btn primary', type: 'submit' }, icon('plus'), 'Add channels');
  const addForm = h('form', {
    onsubmit: (e) => {
      e.preventDefault();
      if (!handlesInput.value.trim()) return;
      busy(addBtn, 'Adding…', async () => {
        const res = await api('POST', 'channels', { handles: handlesInput.value });
        data.channels = res.channels;
        handlesInput.value = '';
        drawChannels();
        toast(res.message);
      });
    },
  }, handlesInput, h('div', { class: 'row', style: { marginTop: '10px' } }, addBtn));

  const scanBtn = h('button', {
    class: 'btn primary',
    onclick: () => busy(scanBtn, 'Scanning channels… (about 3 quota units each)', async () => {
      const res = await api('POST', 'breakouts/scan');
      data = { ...data, channels: res.channels, outliers: res.outliers };
      const fresh = await api('GET', 'channels');
      data.fresh_count = fresh.fresh_count;
      drawChannels();
      drawOutliers();
      toast(res.message);
    }),
  }, icon('search'), 'Scan for breakouts');

  view.append(
    pageHead('Breakouts', 'Videos in your niche that are massively outperforming their channel right now. They feed the idea generator.',
      scanBtn, link('/ideas', { class: 'btn' }, icon('sparkle'), 'Turn into ideas')),
    h('div', { class: 'grid grid-sidebar' },
      h('div', {},
        h('div', { class: 'card' },
          h('div', { class: 'card-header' }, h('h2', {}, 'Breakout videos'), freshLabel),
          outliersBox),
        feeds.itemsCard),
      h('div', {},
        h('div', { class: 'card' },
          h('h2', {}, 'Watchlist'),
          addForm,
          h('div', { style: { marginTop: '16px' } }, channelsBox)),
        feeds.feedsCard,
        h('div', { class: 'card' },
          h('h3', {}, 'How scoring works'),
          h('p', { class: 'muted' }, 'Score = a video’s views ÷ the median views of that channel’s older long-form uploads. Shorts are ignored.'),
          h('p', { class: 'muted' }, 'Lookback window, minimum score and minimum views live in ', link('/settings', {}, 'Settings'), '.')))));

  drawChannels();
  drawOutliers();
}

/** News & blog feeds: a second idea signal (ported from MagicAI's RSS reader). */
async function feedSection() {
  let state = await api('GET', 'feeds');
  const listBox = h('div');
  const itemsBox = h('div');
  const freshText = h('span', { class: 'muted' });

  function drawFeeds() {
    clear(listBox);
    if (!state.feeds.length) {
      listBox.append(h('p', { class: 'muted small' }, 'Add RSS or Atom feeds from news sites, blogs, newsletters or subreddits (add .rss to a subreddit URL).'));
      return;
    }
    listBox.append(h('ul', { class: 'channel-list' }, state.feeds.map((f) => h('li', { class: f.active ? '' : 'inactive' },
      h('div', { class: 'item-main' },
        h('strong', { class: 'clamp-1' }, f.title || f.url),
        h('div', { class: 'muted small' }, `${f.items_count} item${f.items_count === 1 ? '' : 's'} · fetched ${timeAgo(f.last_fetched_at)}`),
        f.last_error ? h('div', { class: 'error-text' }, f.last_error) : null),
      h('div', { class: 'row' },
        h('label', { class: 'switch', title: f.active ? 'Included' : 'Paused' },
          h('input', {
            type: 'checkbox', checked: f.active,
            onchange: async (e) => {
              try { Object.assign(f, (await api('PATCH', `feeds/${f.id}/toggle`)).feed); } catch (err) { toast(err.message, 'err'); e.target.checked = f.active; }
            },
          }), h('span', { class: 'slider' })),
        h('button', {
          class: 'btn ghost sm danger', 'aria-label': 'Remove feed',
          onclick: async () => {
            if (!(await confirmDialog(`Remove “${f.title || f.url}” and its items?`, { ok: 'Remove' }))) return;
            try { await api('DELETE', `feeds/${f.id}`); state = await api('GET', 'feeds'); drawFeeds(); drawItems(); } catch (err) { toast(err.message, 'err'); }
          },
        }, icon('trash', 16)))))));
  }

  function drawItems() {
    clear(itemsBox);
    freshText.textContent = `${state.fresh_count} not yet used for ideas`;
    if (!state.items.length) {
      itemsBox.append(h('p', { class: 'muted' }, 'Nothing yet. Add a feed on the right, then refresh.'));
      return;
    }
    itemsBox.append(h('ul', { class: 'item-list' }, state.items.map((it) => h('li', {},
      h('div', { class: 'item-main' },
        it.link ? h('a', { href: it.link, target: '_blank', rel: 'noopener' }, h('strong', {}, it.title)) : h('strong', {}, it.title),
        h('div', { class: 'muted small' }, it.feed?.title || '', it.published_at ? ` · ${timeAgo(it.published_at)}` : '', ' ', it.used ? pill('used', 'used for ideas') : pill('new', 'fresh')),
        it.summary ? h('div', { class: 'muted small clamp-2' }, it.summary) : null),
      h('button', {
        class: 'btn ghost sm', title: 'Dismiss',
        onclick: async () => {
          try { await api('DELETE', `feed-items/${it.id}`); state.items = state.items.filter((x) => x.id !== it.id); if (!it.used) state.fresh_count--; drawItems(); } catch (err) { toast(err.message, 'err'); }
        },
      }, icon('x', 16))))));
  }

  const urlIn = h('input', { type: 'url', placeholder: 'https://example.com/feed', required: true });
  const addBtn = h('button', { class: 'btn sm', type: 'submit' }, icon('plus', 15), 'Add feed');
  const form = h('form', {
    class: 'row',
    onsubmit: (e) => {
      e.preventDefault();
      busy(addBtn, 'Checking…', async () => {
        state = await api('POST', 'feeds', { url: urlIn.value.trim() });
        toast(state.message);
        urlIn.value = '';
        drawFeeds();
        drawItems();
      });
    },
  }, h('div', { style: { flex: '1', minWidth: '180px' } }, urlIn), addBtn);

  const refreshBtn = h('button', {
    class: 'btn ghost sm',
    onclick: () => busy(refreshBtn, 'Refreshing…', async () => {
      state = await api('POST', 'feeds/refresh');
      toast(state.message);
      drawFeeds();
      drawItems();
    }),
  }, icon('refresh', 15), 'Refresh');

  drawFeeds();
  drawItems();
  return {
    feedsCard: h('div', { class: 'card' },
      h('div', { class: 'card-header' }, h('h2', {}, 'News feeds'), refreshBtn),
      form, h('div', { style: { marginTop: '12px' } }, listBox)),
    itemsCard: h('div', { class: 'card' },
      h('div', { class: 'card-header' }, h('h2', {}, 'News & blog signals'), freshText),
      h('p', { class: 'muted small' }, 'Fresh items from the last two weeks go into the next idea batch, next to the breakout videos.'),
      itemsBox),
  };
}
