// One production task, opened: its brief (from her role template), time tracking (timer or
// hours by hand) and files with review notes at exact seconds ("send back for changes").
// Plus her brief templates and hourly rates.
import { h, api, apiUpload, toast, busy, modal, field, clear, timeAgo } from './core.js';
import { icon } from './icons.js';
import { reviewPlayer } from './review-player.js';

const hours = (min) => `${Math.floor(min / 60)}h ${String(min % 60).padStart(2, '0')}m`;

export async function taskDetail(task, me, onChange = () => {}) {
  const briefBox = h('div');
  const timeBox = h('div');
  const filesBox = h('div');

  async function drawBrief() {
    const ta = h('textarea', { rows: 10, maxlength: 20000, 'aria-label': 'Brief' }, task.brief || '');
    clear(briefBox).append(
      ta,
      h('div', { class: 'row', style: { justifyContent: 'space-between', marginTop: '8px' } },
        h('button', { type: 'button', class: 'btn sm', onclick: (e) => busy(e.currentTarget, '…', async () => {
          try { task.brief = (await api('POST', `team/tasks/${task.id}/brief/apply`)).brief; drawBrief(); } catch (err) { toast(err.message, 'err'); }
        }) }, icon('sparkle', 12), `Fill from the ${task.role_label || 'role'} template`),
        h('button', { type: 'button', class: 'btn primary sm', onclick: (e) => busy(e.currentTarget, 'Saving…', async () => {
          task.brief = (await api('PUT', `team/tasks/${task.id}/brief`, { brief: ta.value })).brief; toast('Brief saved.');
        }) }, 'Save brief')));
  }

  async function drawTime() {
    const t = await api('GET', `team/tasks/${task.id}/time`);
    const mine = me.admin || task.mine;
    const add = h('input', { type: 'number', min: 1, max: 1440, placeholder: 'Minutes', style: { width: '110px' } });
    const note = h('input', { maxlength: 200, placeholder: 'What for (optional)' });
    clear(timeBox).append(
      h('p', {}, h('strong', {}, hours(t.minutes_logged)), ' logged',
        t.hourly ? h('span', { class: 'muted small' }, t.hourly_rate != null ? ` · billed hourly at ${t.hourly_rate}/h ≈ ${t.estimate}` : ' · billed hourly') : null),
      mine ? h('div', { class: 'row', style: { flexWrap: 'wrap' } },
        h('button', { type: 'button', class: `btn ${t.timer_started_at ? 'danger' : 'primary'} sm`, onclick: (e) => busy(e.currentTarget, '…', async () => { await api('POST', `team/tasks/${task.id}/timer`); drawTime(); onChange(); }) },
          icon(t.timer_started_at ? 'pause' : 'play', 12), t.timer_started_at ? ` Stop (running since ${timeAgo(t.timer_started_at)})` : ' Start timer'),
        add, note,
        h('button', { type: 'button', class: 'btn sm', onclick: (e) => busy(e.currentTarget, '…', async () => {
          if (!Number(add.value)) return;
          try { await api('POST', `team/tasks/${task.id}/time`, { minutes: Number(add.value), note: note.value || null }); drawTime(); onChange(); } catch (err) { toast(err.message, 'err'); }
        }) }, 'Log')) : null,
      t.entries.length ? h('ul', { class: 'stack small', style: { marginTop: '8px' } }, t.entries.map((x) => h('li', {}, `${hours(x.minutes)} · ${x.name || ''}${x.note ? ` · ${x.note}` : ''} · ${timeAgo(x.created_at)}`))) : null);
  }

  async function drawFiles() {
    const [{ files }, { annotations }] = await Promise.all([api('GET', `team/tasks/${task.id}/files`), api('GET', `team/tasks/${task.id}/notes`)]);
    const upload = h('label', { class: 'btn sm' }, icon('download', 12), 'Upload a cut or file', h('input', {
      type: 'file', hidden: true,
      onchange: async (e) => {
        const fd = new FormData();
        fd.append('file', e.target.files[0]);
        try { await apiUpload(`team/tasks/${task.id}/files`, fd); toast('Uploaded.'); drawFiles(); onChange(); } catch (err) { toast(err.message, 'err'); }
      },
    }));
    const player = reviewPlayer({ files, streamBase: `team/tasks/${task.id}/files`, annotationsPath: `team/tasks/${task.id}/notes`, annotations, onChange: () => {} });
    const open = annotations.filter((a) => a.status === 'open').length;
    clear(filesBox).append(
      h('div', { class: 'row', style: { justifyContent: 'space-between' } }, upload,
        open ? h('button', { type: 'button', class: 'btn primary sm', onclick: (e) => busy(e.currentTarget, 'Sending…', async () => {
          await api('POST', `team/tasks/${task.id}/send-back`); toast('Sent back with your notes.'); onChange();
        }) }, `Send back for changes (${open})`) : null),
      files.length ? h('ul', { class: 'stack small' }, files.map((f) => h('li', {}, h('a', { href: `/api/team/tasks/${task.id}/files/${f.id}/stream`, target: '_blank', rel: 'noopener' }, f.name), ` · ${f.by || ''} · ${timeAgo(f.created_at)}`))) : h('p', { class: 'muted small' }, 'No files yet. Uploading a cut moves the task to review.'),
      player || '');
  }

  drawBrief();
  drawTime();
  drawFiles();
  await modal(task.title, () => h('div', {},
    h('h3', {}, icon('file-text', 14), ' Brief'), briefBox,
    h('h3', {}, icon('clock', 14), ' Time'), timeBox,
    h('h3', {}, icon('play', 14), ' Files & review'), filesBox), { wide: true });
}

export async function briefTemplatesDialog() {
  const d = await api('GET', 'team/brief-templates');
  let role = d.roles[0];
  const ta = h('textarea', { rows: 12, maxlength: 8000 });
  const pick = h('select', { onchange: (e) => { role = e.target.value; ta.value = d.templates[role] || ''; } }, d.roles.map((r) => h('option', { value: r }, r)));
  ta.value = d.templates[role] || '';
  await modal('Brief templates', () => h('div', {},
    field('Role', pick), field('Template', ta, `Placeholders: ${d.placeholders.map((p) => `{${p}}`).join(' ')}`),
    h('div', { class: 'row-end' }, h('button', { type: 'button', class: 'btn primary', onclick: (e) => busy(e.currentTarget, 'Saving…', async () => {
      d.templates = (await api('PUT', 'team/brief-templates', { role, template: ta.value })).templates; toast('Saved.');
    }) }, 'Save')),
    h('p', { class: 'muted small' }, 'New tasks for this role (and "Repurpose a video") start with this brief filled in.')), { wide: true });
}

export async function ratesDialog() {
  const d = await api('GET', 'team/rates');
  await modal('Hourly rates', () => h('div', { class: 'stack' },
    h('p', { class: 'muted small' }, 'Only you and that person see their rate. Hourly tasks pay hours × rate when they’re done.'),
    ...d.members.map((m) => {
      const input = h('input', { type: 'number', min: 0, step: '0.5', value: m.hourly_rate, style: { width: '120px' }, 'aria-label': `Rate for ${m.name}` });
      return h('div', { class: 'row-between' }, h('span', {}, m.name), h('div', { class: 'row' }, input, h('button', {
        type: 'button', class: 'btn sm', onclick: (e) => busy(e.currentTarget, '…', async () => { await api('PUT', `team/rates/${m.id}`, { hourly_rate: Number(input.value || 0) }); toast('Saved.'); }),
      }, 'Save')));
    })));
}
