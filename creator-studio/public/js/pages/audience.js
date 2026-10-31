// My channel: her videos against her own median, and what viewers ask for in the comments.
import { h, api, busy, toast, pageHead, emptyState, clear, compact, fmtDay, link, pill, confirmDialog, timeAgo, modal, copyText } from '../core.js';
import { icon } from '../icons.js';
import { skList } from '../skeleton.js';
import { bioCard } from '../social-tools.js';

const TYPES = {
  question: ['new', 'Question'],
  request: ['picked', 'Request'],
  confusion: ['WARN', 'Confused'],
  objection: ['bad', 'Pushback'],
  praise: ['ok', 'Loved'],
};

/** One video's viewer needs, each with a one-click "Make it an idea". */
export function insightCard(insight, { onRemove } = {}) {
  const items = insight.items || [];
  return h('div', { class: 'card insight' },
    h('div', { class: 'card-header' },
      h('div', {},
        h('a', { href: insight.url, target: '_blank', rel: 'noopener' }, h('strong', {}, insight.video_title || insight.url)),
        h('div', { class: 'muted small' },
          insight.own ? pill('ok', 'your video') : pill('new', 'another channel'), ' ',
          `${insight.comment_count} comments read · ${timeAgo(insight.updated_at)}`,
          insight.used ? [' ', pill('used', 'used for ideas')] : null)),
      onRemove ? h('button', {
        class: 'btn ghost sm', title: 'Remove',
        onclick: async () => {
          if (!(await confirmDialog('Remove these viewer notes?', { ok: 'Remove' }))) return;
          try { await api('DELETE', `audience/insights/${insight.id}`); onRemove(); } catch (err) { toast(err.message, 'err'); }
        },
      }, icon('x', 16)) : null),
    insight.summary ? h('p', {}, insight.summary) : null,
    items.length ? h('ul', { class: 'insight-list' }, items.map((it) => {
      const [cls, label] = TYPES[it.type] || ['new', it.type];
      const ideaBtn = it.type === 'praise' ? null : h('button', {
        class: 'btn sm',
        onclick: () => busy(ideaBtn, 'Adding…', async () => {
          await api('POST', 'ideas', {
            title: (it.idea || it.text).slice(0, 200),
            premise: it.text,
            notes: `Viewers asked for this in the comments on “${insight.video_title}” (${it.mentions} mention${it.mentions === 1 ? '' : 's'}).${it.quote ? `\n\n“${it.quote}”` : ''}`,
            source_url: insight.url,
          });
          ideaBtn.replaceWith(link('/ideas', { class: 'btn sm ghost' }, icon('check', 15), 'In Ideas'));
          toast('Added to your Picked ideas.');
        }),
      }, icon('bulb', 15), 'Make it an idea');
      return h('li', {},
        h('div', { class: 'insight-main' },
          h('div', {}, pill(cls, label), ' ', h('strong', {}, it.text), h('span', { class: 'muted small' }, ` · ${it.mentions} mention${it.mentions === 1 ? '' : 's'}`)),
          it.quote ? h('blockquote', { class: 'insight-quote' }, `“${it.quote}”`) : null,
          it.idea ? h('div', { class: 'muted small' }, 'Video: ', h('em', {}, it.idea)) : null),
        ideaBtn);
    })) : h('p', { class: 'muted' }, 'Nothing in these comments was worth a video.'));
}

/** Read a video's comments in a dialog (used on Breakouts for competitors' videos). */
export function commentsDialog(url, title) {
  return modal(`What viewers ask: ${title || 'video'}`, () => {
    const box = h('div', {}, h('p', { class: 'muted small' }, 'Reading the comments… (1 YouTube quota unit)'), skList(5, { label: 'viewer requests' }));
    api('POST', 'audience/comments', { url, title })
      .then((r) => clear(box).append(insightCard(r.insight), h('p', { class: 'muted small' }, 'Saved on My channel. Questions feed your next idea batch.')))
      .catch((err) => clear(box).append(h('p', { class: 'error-text' }, err.message)));
    return box;
  }, { wide: true });
}

function scoreCell(v) {
  if (v.score === null || v.score === undefined) return h('span', { class: 'muted' }, '–');
  const cls = v.score >= 1.5 ? 'ok' : v.score <= 0.6 ? 'bad' : '';
  return h('span', {},
    h('span', { class: `score ${cls ? `score-${cls}` : ''}` }, `${Number(v.score).toFixed(1)}×`),
    v.early ? [' ', pill('new', 'early')] : null);
}

/** A tiny views-over-age line from the daily snapshots. */
function sparkline(series) {
  if (!series || series.length < 2) return null;
  const NS = 'http://www.w3.org/2000/svg';
  const w = 84;
  const ht = 24;
  const xs = series.map((p) => p.age_days);
  const ys = series.map((p) => p.views);
  const [x0, x1, y0, y1] = [Math.min(...xs), Math.max(...xs), Math.min(...ys), Math.max(...ys)];
  const pts = series.map((p) => `${((p.age_days - x0) / Math.max(0.01, x1 - x0) * (w - 4) + 2).toFixed(1)},${(ht - 2 - (p.views - y0) / Math.max(1, y1 - y0) * (ht - 4)).toFixed(1)}`).join(' ');
  const svg = document.createElementNS(NS, 'svg');
  svg.setAttribute('viewBox', `0 0 ${w} ${ht}`);
  svg.setAttribute('width', w);
  svg.setAttribute('height', ht);
  svg.setAttribute('class', 'sparkline');
  svg.setAttribute('aria-hidden', 'true');
  const line = document.createElementNS(NS, 'polyline');
  line.setAttribute('points', pts);
  svg.append(line);
  return svg;
}

/** Views vs her usual at the same age (from the daily snapshots). */
function sameAgeCell(cmp) {
  const pace = cmp?.pace;
  const spark = sparkline(cmp?.series);
  if (!pace) return h('span', { class: 'muted small', title: 'Needs a few days of daily numbers' }, spark || '–');
  const cls = pace.ratio >= 1.5 ? 'ok' : pace.ratio <= 0.6 ? 'bad' : '';
  return h('span', { class: 'same-age' },
    h('span', { class: `score ${cls ? `score-${cls}` : ''}`, title: `Views at day ${pace.day} against your median at day ${pace.day}` }, `${pace.ratio.toFixed(1)}×`),
    h('span', { class: 'muted small' }, ` day ${pace.day}`), spark);
}

/** Draft replies to the newest unanswered comments; she edits, copies and posts each one. */
export function repliesDialog(video) {
  return modal(`Reply drafts: ${video.title}`, () => {
    const box = h('div', {}, h('p', { class: 'muted small' }, 'Reading the newest comments… (1 YouTube quota unit)'), skList(5, { label: 'reply drafts' }));
    api('POST', 'audience/replies', { url: video.url, title: video.title })
      .then((r) => clear(box).append(
        h('p', { class: 'muted small' }, 'Newest comments with no reply yet (yours and spam left out). Edit, copy, then reply on YouTube: “Open” jumps to the comment.'),
        h('ul', { class: 'reply-list' }, r.replies.map((c) => {
          const ta = h('textarea', { rows: 2 }, c.reply);
          return h('li', {},
            h('div', { class: 'reply-comment' }, `“${c.text}”`, c.likes ? h('span', { class: 'muted small' }, ` · ${c.likes} likes`) : null),
            ta,
            h('div', { class: 'row' },
              h('button', { class: 'btn sm', onclick: () => copyText(ta.value, 'Reply copied.') }, icon('copy', 14), 'Copy'),
              h('a', { class: 'btn ghost sm', href: `https://www.youtube.com/watch?v=${r.video_id}&lc=${encodeURIComponent(c.id)}`, target: '_blank', rel: 'noopener' }, icon('arrow', 14), 'Open')));
        }))))
      .catch((err) => clear(box).append(h('p', { class: 'error-text' }, err.message)));
    return box;
  }, { wide: true });
}

export async function renderAudience(view) {
  let data = await api('GET', 'audience');
  const reportBox = h('div');
  const insightsBox = h('div');

  function upsert(insight) {
    data.insights = [insight, ...data.insights.filter((x) => x.video_id !== insight.video_id)];
    drawInsights();
  }

  const recordBtn = h('button', {
    class: 'btn ghost sm', title: 'Record today’s views, likes and comments (also runs daily at 06:30)',
    onclick: () => busy(recordBtn, 'Recording…', async () => {
      const r = await api('POST', 'audience/snapshot');
      toast(r.message);
      data = await api('GET', 'audience');
      drawReport();
    }),
  }, icon('calendar', 15), 'Record today’s numbers');

  function drawReport() {
    clear(reportBox);
    const r = data.report;
    if (!r) {
      reportBox.append(h('div', { class: 'card' }, emptyState('📺', 'Connect your channel',
        data.error || 'Add your channel and a YouTube key in Settings.',
        link('/settings', { class: 'btn primary' }, icon('gear', 16), 'Open Settings'))));
      return;
    }
    const scored = r.videos.filter((v) => !v.early);
    reportBox.append(h('div', { class: 'card' },
      h('div', { class: 'card-header' },
        h('h2', {}, r.title || 'Your videos'),
        h('div', { class: 'row' }, h('span', { class: 'muted small' }, `Updated ${timeAgo(r.fetched_at)}`), recordBtn)),
      h('p', { class: 'muted' }, r.median
        ? [`Your usual long-form video: `, h('strong', {}, compact(r.median)), ` views (median of ${scored.length} videos at least ${r.mature_days} days old). Lifetime views, so older videos have had longer to grow; videos under ${r.mature_days} days are marked early.`]
        : `Your usual needs at least 3 long-form videos older than ${r.mature_days} days, so scores will appear as you publish.`),
      h('p', { class: 'muted small' }, data.tracking_since
        ? `Same age compares each video with your others at the same day (1, 3, 7 or 28), from daily numbers recorded since ${fmtDay(data.tracking_since)}.`
        : 'Same age needs daily numbers: they’re recorded every morning at 06:30 while the scheduler runs, or press “Record today’s numbers”. YouTube has no history to fill in, so it starts today.'),
      r.videos.length ? h('div', { class: 'table-wrap' }, h('table', {},
        h('thead', {}, h('tr', {},
          h('th', {}, 'Video'), h('th', { class: 'num' }, 'vs usual'), h('th', { class: 'num hide-sm' }, 'Same age'), h('th', { class: 'num' }, 'Views'),
          h('th', { class: 'num hide-sm' }, 'Comments'), h('th', {}, ''))),
        h('tbody', {}, r.videos.map((v) => {
          const asked = data.insights.some((i) => i.video_id === v.id);
          const btn = h('button', {
            class: 'btn ghost sm', title: 'What are viewers asking for in the comments?',
            disabled: !v.comments,
            onclick: () => busy(btn, 'Reading…', async () => {
              const res = await api('POST', 'audience/comments', { url: v.url, title: v.title });
              upsert(res.insight);
              toast(res.message);
              insightsBox.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }),
          }, icon('chat', 15), asked ? 'Read again' : 'Viewer asks');
          return h('tr', {},
            h('td', {},
              h('a', { href: v.url, target: '_blank', rel: 'noopener' }, h('strong', {}, v.title)),
              h('div', { class: 'muted' }, `${fmtDay(v.published_at)} · ${v.age_days} days`)),
            h('td', { class: 'num nowrap' }, scoreCell(v)),
            h('td', { class: 'num nowrap hide-sm' }, sameAgeCell(data.same_age?.[v.id])),
            h('td', { class: 'num' }, compact(v.views)),
            h('td', { class: 'num hide-sm' }, compact(v.comments)),
            h('td', { class: 'num nowrap' }, btn,
              h('button', { class: 'btn ghost sm', title: 'Draft replies to the newest comments', disabled: !v.comments, onclick: () => repliesDialog(v) }, icon('pen', 15), 'Replies')));
        })))) : h('p', { class: 'muted' }, 'No long-form uploads found yet.')));
  }

  function drawInsights() {
    clear(insightsBox).append(h('h2', { class: 'section-title' }, 'What viewers are asking for'));
    if (!data.insights.length) {
      insightsBox.append(h('div', { class: 'card' }, emptyState('💬', 'No comments read yet',
        'Press “Viewer asks” on one of your videos, or paste any video link below. Questions and requests become ideas, and feed your next idea batch automatically.')));
      return;
    }
    for (const ins of data.insights) {
      insightsBox.append(insightCard(ins, {
        onRemove: () => { data.insights = data.insights.filter((x) => x.id !== ins.id); drawInsights(); },
      }));
    }
  }

  const urlIn = h('input', { type: 'url', required: true, placeholder: 'https://youtube.com/watch?v=… (yours or a competitor’s)' });
  const goBtn = h('button', { type: 'submit', class: 'btn primary' }, icon('chat', 16), 'Read comments');
  const anyForm = h('form', {
    class: 'row',
    onsubmit: (e) => {
      e.preventDefault();
      busy(goBtn, 'Reading…', async () => {
        const res = await api('POST', 'audience/comments', { url: urlIn.value.trim() });
        urlIn.value = '';
        upsert(res.insight);
        toast(res.message);
      });
    },
  }, h('div', { style: { flex: '1', minWidth: '240px' } }, urlIn), goBtn);

  const refreshBtn = h('button', {
    class: 'btn',
    onclick: () => busy(refreshBtn, 'Refreshing…', async () => {
      data = await api('GET', 'audience?refresh=1');
      if (data.error) toast(data.error, 'err');
      drawReport();
      drawInsights();
    }),
  }, icon('refresh', 16), 'Refresh');

  drawReport();
  drawInsights();
  view.append(
    pageHead('My channel', 'How each video did against your own usual, and what your viewers are asking for next.', refreshBtn),
    reportBox,
    h('div', { class: 'card' },
      h('h3', {}, 'Read any video’s comments'),
      h('p', { class: 'muted small' }, 'A competitor’s breakout is a great one: their viewers’ unanswered questions are videos you can make. Costs 1 YouTube quota unit.'),
      anyForm),
    insightsBox,
    bioCard('youtube'));
}
