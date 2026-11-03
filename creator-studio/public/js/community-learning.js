// Courses and live events in the Community Hub (members) and their editors (her).
import { h, api, toast, busy, modal, field, clear, emptyState, loading, confirmDialog, markdown } from './core.js';
import { icon } from './icons.js';

const when = (iso) => new Date(iso).toLocaleString([], { weekday: 'short', day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' });
const localInput = (iso) => { if (!iso) return ''; const d = new Date(iso); d.setMinutes(d.getMinutes() - d.getTimezoneOffset()); return d.toISOString().slice(0, 16); };

// ---- members ------------------------------------------------------------------------------

export async function coursesTab(box, upgrade) {
  clear(box).append(loading());
  const { courses } = await api('GET', 'portal/community/courses');
  clear(box).append(courses.length ? h('div', { class: 'community-kits-grid' }, courses.map((c) => h('div', { class: `community-kit-card${c.locked ? ' is-locked' : ''}` },
    h('div', { class: 'community-kit-head' }, c.tier === 'pro' ? h('span', { class: 'tag' }, icon('lock', 10), ' Pro') : null, h('span', { class: 'muted small' }, `${c.lessons} lessons`)),
    h('h3', { class: 'community-kit-title' }, c.title),
    c.description ? h('p', { class: 'muted small' }, c.description) : null,
    h('div', { class: 'meter', role: 'progressbar', 'aria-valuenow': c.completed, 'aria-valuemax': c.lessons }, h('span', { style: { width: `${c.lessons ? (c.completed / c.lessons) * 100 : 0}%` } })),
    h('div', { class: 'community-kit-foot' }, h('span', { class: 'small muted' }, `${c.completed}/${c.lessons} done`),
      h('button', { type: 'button', class: 'btn sm primary', onclick: () => openCourse(c.id, upgrade) }, c.completed ? 'Continue' : 'Start'))))) : emptyState(icon('book', 28), 'No courses yet', 'Courses appear here when they’re published.'));
}

async function openCourse(id, upgrade) {
  const d = await api('GET', `portal/community/courses/${id}`);
  let idx = Math.max(0, d.lessons.findIndex((l) => !l.done && !l.locked));
  const body = h('div');
  function draw() {
    const l = d.lessons[idx];
    clear(body).append(
      h('div', { class: 'grid grid-2', style: { gridTemplateColumns: 'minmax(180px, 1fr) 3fr' } },
        h('ol', { class: 'stack small' }, d.lessons.map((x, i) => h('li', {},
          h('button', { type: 'button', class: `btn ghost sm${i === idx ? ' picked' : ''}`, onclick: () => { idx = i; draw(); } }, x.done ? '✓ ' : '', x.locked ? '🔒 ' : '', x.title)))),
        l ? h('div', {},
          h('h3', {}, l.title, l.minutes ? h('span', { class: 'muted small' }, ` · ${l.minutes} min`) : null),
          l.locked ? h('div', { class: 'card callout' }, h('p', {}, 'This lesson is for Pro members.'), upgrade?.() || null)
            : [l.video_url ? h('p', {}, h('a', { href: l.video_url, target: '_blank', rel: 'noopener', class: 'btn' }, icon('play', 13), ' Watch the video')) : null,
              l.body ? h('div', { class: 'markdown-body', html: markdown(l.body) }) : null,
              h('label', { class: 'row', style: { gap: '6px', marginTop: '12px' } }, h('input', {
                type: 'checkbox', checked: l.done,
                onchange: async (e) => { try { await api('POST', `portal/community/lessons/${l.id}/complete`, { done: e.target.checked }); l.done = e.target.checked; if (l.done && idx < d.lessons.length - 1) idx++; draw(); } catch (err) { toast(err.message, 'err'); } },
              }), 'Mark as done (+3 points)')]) : h('p', { class: 'muted' }, 'No lessons yet.')));
  }
  draw();
  await modal(d.course.title, () => body, { wide: true });
}

export async function eventsTab(box) {
  clear(box).append(loading());
  const d = await api('GET', 'portal/community/events');
  const row = (e) => h('div', { class: 'card row-between', style: { alignItems: 'flex-start', gap: '12px' } },
    h('div', {},
      h('div', { class: 'row small', style: { gap: '6px' } }, e.live ? h('span', { class: 'tag ok' }, 'Live now') : null, e.tier === 'pro' ? h('span', { class: 'tag' }, icon('lock', 10), ' Pro') : null, h('span', { class: 'muted' }, `${when(e.starts_at)} · ${e.minutes} min · ${e.count} going`)),
      h('strong', {}, e.title), e.description ? h('p', { class: 'muted small', style: { whiteSpace: 'pre-wrap' } }, e.description) : null),
    h('div', { class: 'row', style: { flexShrink: 0 } },
      e.past ? (e.replay_url ? h('a', { class: 'btn sm', href: e.replay_url, target: '_blank', rel: 'noopener' }, icon('play', 12), ' Replay') : h('span', { class: 'muted small' }, 'Replay coming'))
        : e.locked ? h('span', { class: 'muted small' }, 'For Pro members') : [
          h('button', { type: 'button', class: `btn sm ${e.going ? '' : 'primary'}`, onclick: (ev) => busy(ev.currentTarget, '…', async () => { const r = await api('POST', `portal/community/events/${e.id}/rsvp`); Object.assign(e, { going: r.going, count: r.count }); eventsTab(box); }) }, e.going ? '✓ Going' : 'RSVP'),
          h('a', { class: 'btn ghost sm', href: `/api/portal/community/events/${e.id}/ics`, download: '' }, icon('calendar', 12), ' Calendar'),
          e.live && e.join_url ? h('a', { class: 'btn primary sm', href: e.join_url, target: '_blank', rel: 'noopener' }, 'Join') : null]));
  clear(box).append(
    h('h3', {}, 'Coming up'), d.upcoming.length ? h('div', { class: 'stack' }, d.upcoming.map(row)) : h('p', { class: 'muted' }, 'No live sessions planned right now.'),
    d.past.length ? [h('h3', {}, 'Past sessions'), h('div', { class: 'stack' }, d.past.map(row))] : null,
    h('p', { class: 'muted small' }, 'RSVP and you get an email the day before and an hour before.'));
}

// ---- her side -----------------------------------------------------------------------------

export async function adminCoursesTab(box) {
  clear(box).append(loading());
  const d = await api('GET', 'community/admin/courses');
  const reload = () => adminCoursesTab(box);
  async function editCourse(c = null) {
    const title = h('input', { value: c?.title || '', maxlength: 200, required: true });
    const desc = h('textarea', { rows: 3 }, c?.description || '');
    const tier = h('select', {}, h('option', { value: 'free' }, 'Everyone'), h('option', { value: 'pro', selected: c?.tier === 'pro' }, 'Pro members'));
    const status = h('select', {}, h('option', { value: 'draft' }, 'Draft'), h('option', { value: 'published', selected: c?.status === 'published' }, 'Published'));
    await modal(c ? 'Edit course' : 'New course', (close) => h('form', { onsubmit: async (e) => { e.preventDefault(); const b = { title: title.value, description: desc.value || null, tier: tier.value, status: status.value };
      try { await api(c ? 'PATCH' : 'POST', c ? `community/admin/courses/${c.id}` : 'community/admin/courses', b); close(); reload(); } catch (err) { toast(err.message, 'err'); } } },
    field('Title', title), field('Description', desc), h('div', { class: 'grid grid-2' }, field('Who sees it', tier), field('Status', status)),
    h('div', { class: 'row-end' }, h('button', { type: 'submit', class: 'btn primary' }, 'Save'))));
  }
  async function editLesson(courseId, l = null) {
    const title = h('input', { value: l?.title || '', maxlength: 200, required: true });
    const video = h('input', { type: 'url', value: l?.video_url || '', placeholder: 'https://youtube.com/… (optional)' });
    const mins = h('input', { type: 'number', min: 0, value: l?.minutes || '' });
    const tier = h('select', {}, [['free', 'Same as the course'], ['free_preview', 'Free preview (in a Pro course)'], ['pro', 'Pro only']].map(([v, t]) => h('option', { value: v, selected: (l?.tier || 'free') === v }, t)));
    const body = h('textarea', { rows: 10, placeholder: 'Markdown' }, l?.body || '');
    await modal(l ? 'Edit lesson' : 'New lesson', (close) => h('form', { onsubmit: async (e) => { e.preventDefault(); const b = { title: title.value, video_url: video.value || null, minutes: mins.value ? Number(mins.value) : null, tier: tier.value, body: body.value || null };
      try { await api(l ? 'PATCH' : 'POST', l ? `community/admin/lessons/${l.id}` : `community/admin/courses/${courseId}/lessons`, b); close(); reload(); } catch (err) { toast(err.message, 'err'); } } },
    field('Title', title), h('div', { class: 'grid grid-3' }, field('Video link', video), field('Minutes', mins), field('Access', tier)), field('Lesson', body),
    h('div', { class: 'row-end' }, h('button', { type: 'submit', class: 'btn primary' }, 'Save'))), { wide: true });
  }
  clear(box).append(
    h('div', { class: 'row-end', style: { marginBottom: '10px' } }, h('button', { type: 'button', class: 'btn primary sm', onclick: () => editCourse() }, icon('plus', 12), ' Course')),
    d.courses.length ? h('div', { class: 'stack' }, d.courses.map((c) => h('div', { class: 'card' },
      h('div', { class: 'row-between' }, h('div', {}, h('strong', {}, c.title), ' ', h('span', { class: `tag ${c.status === 'published' ? 'ok' : ''}` }, c.status), c.tier === 'pro' ? h('span', { class: 'tag' }, ' Pro') : null),
        h('div', { class: 'row' },
          h('button', { type: 'button', class: 'btn sm', onclick: () => editLesson(c.id) }, icon('plus', 12), ' Lesson'),
          h('button', { type: 'button', class: 'btn ghost sm', onclick: () => editCourse(c) }, 'Edit'),
          h('button', { type: 'button', class: 'btn ghost sm', 'aria-label': 'Delete course', onclick: async () => { if (await confirmDialog(`Delete “${c.title}” and its lessons?`)) { await api('DELETE', `community/admin/courses/${c.id}`); reload(); } } }, icon('trash', 12)))),
      c.lessons.length ? h('ol', { class: 'stack small' }, c.lessons.map((l) => h('li', { class: 'row-between' }, h('span', {}, l.title, h('span', { class: 'muted' }, ` · ${l.completions} done${l.tier !== 'free' ? ` · ${l.tier}` : ''}`)),
        h('div', { class: 'row' }, h('button', { type: 'button', class: 'btn ghost sm', onclick: () => editLesson(c.id, l) }, 'Edit'),
          h('button', { type: 'button', class: 'btn ghost sm', 'aria-label': 'Delete lesson', onclick: async () => { await api('DELETE', `community/admin/lessons/${l.id}`); reload(); } }, icon('trash', 12)))))) : h('p', { class: 'muted small' }, 'No lessons yet.')))) : emptyState(icon('book', 28), 'No courses yet', 'A short course is the clearest reason to join Pro.'));
}

export async function adminEventsTab(box) {
  clear(box).append(loading());
  const d = await api('GET', 'community/admin/events');
  const reload = () => adminEventsTab(box);
  async function edit(e = null) {
    const title = h('input', { value: e?.title || '', maxlength: 200, required: true });
    const desc = h('textarea', { rows: 3 }, e?.description || '');
    const starts = h('input', { type: 'datetime-local', value: localInput(e?.starts_at), required: true });
    const mins = h('input', { type: 'number', min: 5, max: 600, value: e?.minutes || 60 });
    const join = h('input', { type: 'url', value: e?.join_url || '', placeholder: 'https://zoom.us/… or https://youtube.com/live/…' });
    const replay = h('input', { type: 'url', value: e?.replay_url || '', placeholder: 'Paste after the session: it becomes a post' });
    const tier = h('select', {}, h('option', { value: 'free' }, 'Everyone'), h('option', { value: 'pro', selected: e?.tier === 'pro' }, 'Pro members'));
    await modal(e ? 'Edit event' : 'New live session', (close) => h('form', { onsubmit: async (ev) => { ev.preventDefault();
      const b = { title: title.value, description: desc.value || null, starts_at: new Date(starts.value).toISOString(), minutes: Number(mins.value), join_url: join.value || null, replay_url: replay.value || null, tier: tier.value };
      try { await api(e ? 'PATCH' : 'POST', e ? `community/admin/events/${e.id}` : 'community/admin/events', b); close(); reload(); } catch (err) { toast(err.message, 'err'); } } },
    field('Title', title), field('Description', desc), h('div', { class: 'grid grid-3' }, field('Starts', starts), field('Minutes', mins), field('Who can join', tier)),
    field('Join link', join), field('Replay link', replay),
    h('div', { class: 'row-end' }, h('button', { type: 'submit', class: 'btn primary' }, 'Save'))), { wide: true });
  }
  clear(box).append(
    h('div', { class: 'row-end', style: { marginBottom: '10px' } }, h('button', { type: 'button', class: 'btn primary sm', onclick: () => edit() }, icon('plus', 12), ' Live session')),
    d.events.length ? h('div', { class: 'stack' }, d.events.map((e) => h('div', { class: 'card row-between' },
      h('div', {}, h('strong', {}, e.title), h('div', { class: 'muted small' }, `${when(e.starts_at)} · ${e.count} RSVP${e.count === 1 ? '' : 's'}${e.past ? (e.replay_url ? ' · replay posted' : ' · add the replay') : ''}`)),
      h('div', { class: 'row' }, h('button', { type: 'button', class: 'btn ghost sm', onclick: () => edit(e) }, 'Edit'),
        h('button', { type: 'button', class: 'btn ghost sm', 'aria-label': 'Delete event', onclick: async () => { if (await confirmDialog(`Delete “${e.title}”?`)) { await api('DELETE', `community/admin/events/${e.id}`); reload(); } } }, icon('trash', 12)))))) : emptyState(icon('calendar', 28), 'No live sessions yet', 'Members RSVP and get reminders; the replay becomes a post.'));
}
