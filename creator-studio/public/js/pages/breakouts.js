// Breakouts: the competitor watchlist and the videos doing far better than their channel's median.
import { h, api, busy, toast, pageHead, emptyState, compact, timeAgo, fmtDay, link, confirmDialog, clear, pill } from '../core.js';
import { icon } from '../icons.js';
import { youtubeDialog } from '../components.js';

export async function renderBreakouts(view) {
  let data = await api('GET', 'channels');

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
      h('div', { class: 'card' },
        h('div', { class: 'card-header' }, h('h2', {}, 'Breakout videos'), freshLabel),
        outliersBox),
      h('div', {},
        h('div', { class: 'card' },
          h('h2', {}, 'Watchlist'),
          addForm,
          h('div', { style: { marginTop: '16px' } }, channelsBox)),
        h('div', { class: 'card' },
          h('h3', {}, 'How scoring works'),
          h('p', { class: 'muted' }, 'Score = a video’s views ÷ the median views of that channel’s older long-form uploads. Shorts are ignored.'),
          h('p', { class: 'muted' }, 'Lookback window, minimum score and minimum views live in ', link('/settings', {}, 'Settings'), '.')))));

  drawChannels();
  drawOutliers();
}
