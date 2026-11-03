// Weekly goals: the commitment scoreboard and the blunt-but-kind mentor check-in.
import { h, api, busy, toast, pageHead, emptyState, clear, pill, modal, confirmDialog, field, formData, fmtDay, isoDay, daysUntil, compact, link, mdBlock } from '../core.js';
import { icon } from '../icons.js';
import { celebrate } from '../love.js';
import { sprintCard } from '../ai-checks.js';

/** The row of week squares, also used on the dashboard. */
export function weekDots(weeks, currentId) {
  return h('div', { class: 'dots' }, weeks.map((w) => h('div', {
    class: `dot ${w.status}${w.id === currentId ? ' current' : ''}`,
    title: `Week ${w.number} (${fmtDay(w.start_date)}–${fmtDay(w.due_date)}): ${w.status}${w.video_title ? ` · ${w.video_title}` : ''}`,
  }, w.number)));
}

function nextMonday() {
  const d = new Date();
  d.setDate(d.getDate() + ((8 - d.getDay()) % 7 || 7));
  return d.toISOString().slice(0, 10);
}

function planForm(replace, onDone) {
  return (close) => {
    const form = h('form', {
      onsubmit: async (e) => {
        e.preventDefault();
        const v = formData(form);
        if (replace && !(await confirmDialog('This deletes the current scoreboard, notes and verdicts. Start over?', { ok: 'Replace plan' }))) return;
        await busy(form.querySelector('[type=submit]'), 'Planning…', async () => {
          const r = await api('POST', 'goals/plan', { start: v.start, count: Number(v.count), replace });
          toast(r.message);
          close?.(true);
          onDone();
        });
      },
    },
    h('div', { class: 'grid grid-2 tight' },
      field('First week starts', h('input', { type: 'date', name: 'start', required: true, value: nextMonday() })),
      field('Number of weeks', h('input', { type: 'number', name: 'count', min: 1, max: 52, value: 12, required: true }))),
    h('p', { class: 'muted small' }, 'Each week is due 6 days after it starts. A week counts as shipped only if a long-form video goes public by its due date.'),
    h('div', { class: 'row' }, h('button', { type: 'submit', class: 'btn primary' }, icon('calendar'), replace ? 'Replace plan' : 'Create plan')));
    return form;
  };
}

function editWeek(week, scripts, onSaved) {
  return modal(`Week ${week.number} · ${fmtDay(week.start_date)}–${fmtDay(week.due_date)}`, (close) => {
    const form = h('form', {
      onsubmit: async (e) => {
        e.preventDefault();
        const v = formData(form);
        const payload = {
          status: v.status,
          script_id: v.script_id ? Number(v.script_id) : null,
          video_title: v.video_title || null,
          youtube_url: v.youtube_url || null,
          published_on: v.published_on || null,
          views_7d: v.views_7d === '' ? null : Number(v.views_7d),
          note: v.note || null,
        };
        await busy(form.querySelector('[type=submit]'), 'Saving…', async () => {
          const r = await api('PATCH', `goals/${week.id}`, payload);
          toast(r.message);
          close(true);
          onSaved();
        });
      },
    },
    h('div', { class: 'grid grid-2 tight' },
      field('Status', h('select', { name: 'status' }, ['pending', 'shipped', 'late', 'missed'].map((s) => h('option', { value: s, selected: s === week.status }, s)))),
      field('Script', h('select', { name: 'script_id' },
        h('option', { value: '' }, '— none —'),
        scripts.map((s) => h('option', { value: s.id, selected: s.id === week.script_id }, s.title)))),
      field('Video title', h('input', { type: 'text', name: 'video_title', value: week.video_title || '' }), 'fills from the script if empty'),
      field('YouTube link', h('input', { type: 'url', name: 'youtube_url', value: week.youtube_url || '' })),
      field('Published on', h('input', { type: 'date', name: 'published_on', value: isoDay(week.published_on) })),
      field('Views at 7 days', h('input', { type: 'number', name: 'views_7d', min: 0, value: week.views_7d ?? '' }), 'check-in fills this'),
      h('div', { class: 'span-2' }, field('Your note', h('textarea', { name: 'note', rows: 2, placeholder: 'Anything the mentor should know (said once, then it moves on).' }, week.note || '')))),
    h('div', { class: 'row', style: { justifyContent: 'flex-end' } },
      h('button', { type: 'button', class: 'btn', onclick: () => close(false) }, 'Cancel'),
      h('button', { type: 'submit', class: 'btn primary' }, 'Save week')));
    return form;
  }, { wide: true });
}

export async function renderGoals(view) {
  const box = h('div');
  const sprint = sprintCard(); // built once, below the plan, so a redraw keeps the plan she asked for
  view.append(box, h('div', { style: { marginTop: '18px' } }, sprint));

  async function draw() {
    const [g, s] = await Promise.all([api('GET', 'goals'), api('GET', 'scripts')]);
    const scripts = s.scripts.data;
    clear(box);

    if (!g.weeks.length) {
      box.append(
        pageHead('Weekly goals', `Your contract: ${g.commitment}. The scoreboard is the truth, and the mentor keeps it honest.`),
        h('div', { class: 'grid grid-2' },
          h('div', { class: 'card' }, h('h2', {}, 'Make a plan'), planForm(false, draw)()),
          h('div', { class: 'card' }, emptyState(icon('target', 30), 'One video a week',
            'Consistency beats intensity. Plan 12 weeks, ship one long-form video each week, and check in every Monday. The mentor scores each week from your real uploads.'))));
      return;
    }

    const weeks = g.weeks;
    const current = g.current;
    const scored = weeks.filter((w) => w.status !== 'pending');
    const shipped = weeks.filter((w) => w.status === 'shipped').length;
    const late = weeks.filter((w) => w.status === 'late').length;
    const missed = weeks.filter((w) => w.status === 'missed').length;
    const latestVerdict = [...weeks].reverse().find((w) => w.verdict);

    const checkBtn = h('button', {
      class: 'btn primary',
      onclick: () => busy(checkBtn, 'Checking your uploads…', async () => {
        const before = weeks.filter((w) => w.status === 'shipped').length;
        const r = await api('POST', 'goals/checkin');
        toast(r.message);
        if (r.weeks.filter((w) => w.status === 'shipped').length > before) celebrate('shipped');
        await draw();
      }),
    }, icon('refresh'), 'Check in');

    box.append(pageHead('Weekly goals', `Your contract: ${g.commitment}.${g.baseline_views ? ` Baseline: ${compact(g.baseline_views)} views at 7 days.` : ''}`,
      checkBtn,
      h('button', { class: 'btn ghost', onclick: () => modal('Replace plan', (close) => planForm(true, draw)(close)) }, 'Re-plan')));

    box.append(h('div', { class: 'grid grid-4' },
      h('div', { class: 'card stat-box tone-good' }, h('div', { class: 'num' }, `${shipped}/${scored.length || 0}`), h('div', { class: 'lbl' }, 'shipped of scored weeks')),
      h('div', { class: 'card stat-box tone-warn' }, h('div', { class: 'num' }, late), h('div', { class: 'lbl' }, 'late')),
      h('div', { class: 'card stat-box tone-bad' }, h('div', { class: 'num' }, missed), h('div', { class: 'lbl' }, 'missed')),
      h('div', { class: 'card stat-box tone-accent' }, h('div', { class: 'num' }, weeks.length - scored.length), h('div', { class: 'lbl' }, 'weeks to go'))));

    if (current) {
      const left = daysUntil(current.due_date);
      box.append(h('div', { class: 'card current-week' },
        h('div', { class: 'row-between wrap' },
          h('div', {},
            h('div', { class: 'muted' }, `This week · Week ${current.number}`),
            h('h2', {}, current.video_title || 'Video not picked yet'),
            h('div', { class: 'muted' }, `Due ${fmtDay(current.due_date, { weekday: 'long', month: 'long', day: 'numeric' })}`,
              left !== null ? ` · ${left > 0 ? `${left} day${left === 1 ? '' : 's'} left` : left === 0 ? 'today!' : `${-left} days ago`}` : '')),
          h('div', { class: 'row' },
            pill(current.status),
            h('button', { class: 'btn sm', onclick: () => editWeek(current, scripts, draw) }, icon('pen', 15), current.video_title ? 'Edit' : 'Pick the video'))),
        !current.video_title ? h('p', { class: 'callout warn', style: { marginTop: '12px' } }, 'The mentor’s first rule: if this week’s video isn’t picked, that’s the first job. ', link('/ideas', {}, 'Pick from your ideas →')) : null));
    }

    if (latestVerdict) {
      box.append(h('div', { class: 'card mentor-card' },
        h('div', { class: 'mentor-head' }, h('div', { class: 'mentor-avatar' }, icon('compass', 22)), h('div', {}, h('h3', {}, 'Mentor verdict'), h('div', { class: 'muted' }, `on week ${latestVerdict.number}`))),
        mdBlock(latestVerdict.verdict)));
    }

    box.append(h('div', { class: 'card' },
      h('div', { class: 'card-header' }, h('h2', {}, 'Scoreboard'), weekDots(weeks, current?.id)),
      h('div', { class: 'table-wrap' }, h('table', {},
        h('thead', {}, h('tr', {}, h('th', {}, '#'), h('th', {}, 'Dates'), h('th', {}, 'Video'), h('th', {}, 'Status'), h('th', { class: 'num' }, '7-day views'), h('th', {}, ''))),
        h('tbody', {}, weeks.map((w) => h('tr', { class: w.id === current?.id ? 'highlight' : '' },
          h('td', {}, h('strong', {}, w.number)),
          h('td', { class: 'muted nowrap' }, `${fmtDay(w.start_date)} – ${fmtDay(w.due_date)}`),
          h('td', {},
            w.youtube_url ? h('a', { href: w.youtube_url, target: '_blank', rel: 'noopener' }, w.video_title || w.youtube_url) : (w.video_title || h('span', { class: 'muted' }, '—')),
            w.script ? h('div', {}, link(`/scripts/${w.script.id}`, { class: 'small' }, icon('doc', 13), ' script')) : null,
            w.note ? h('div', { class: 'muted small' }, `“${w.note}”`) : null,
            w.verdict && w !== latestVerdict ? h('details', { class: 'small' }, h('summary', {}, 'verdict'), mdBlock(w.verdict)) : null),
          h('td', {}, pill(w.status)),
          h('td', { class: 'num' }, w.views_7d !== null && w.views_7d !== undefined ? compact(w.views_7d) : h('span', { class: 'muted' }, '—')),
          h('td', { class: 'num' }, h('button', { class: 'btn ghost sm', 'aria-label': `Edit week ${w.number}`, onclick: () => editWeek(w, scripts, draw) }, icon('pen', 15))))))))));

    box.append(h('p', { class: 'muted small' }, 'Check-in reads your channel’s uploads (set your channel in Settings): a long-form video published inside a week marks it shipped; a pending week past its due date becomes missed. Then the mentor writes a verdict.'));
  }

  await draw();
}
