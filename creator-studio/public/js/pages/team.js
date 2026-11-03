// Team & Production: my tasks, the production board, payouts, the publishing QA checklist,
// a thumbnail text check and the team (invites by email). Her and her staff both use it;
// only she sets, approves and pays payouts and invites or removes people.
import { h, api, clear, field, toast, modal, confirmDialog, loading, emptyState, copyText, fmtDay } from '../core.js';
import { icon } from '../icons.js';
import { taskDetail, briefTemplatesDialog, ratesDialog } from '../team-task.js';

const STAGES = [
  ['idea', 'Idea'], ['script', 'Script & research'], ['filming', 'Filming & B-roll'], ['editing', 'Editing'],
  ['review', 'Review & QA'], ['scheduled', 'Scheduled'], ['published', 'Published / done'],
];
const STAGE_LABEL = Object.fromEntries(STAGES);
const money = (n, cur = 'USD') => `${cur === 'EUR' ? '€' : cur === 'GBP' ? '£' : '$'}${Number(n || 0).toLocaleString(undefined, { maximumFractionDigits: 2 })}`;
const nextStage = (s) => STAGES[Math.min(STAGES.findIndex(([id]) => id === s) + 1, STAGES.length - 1)][0];

export async function render(view) {
  const container = h('div', { class: 'team-page' });
  clear(view).append(container);
  let data = null;
  let tab = 'mine';
  let payoutMonth = new Date().toISOString().slice(0, 7);

  async function load() {
    clear(container).append(loading('Loading the team…'));
    try {
      data = await api('GET', 'team');
      if (tab === 'mine' && !data.metrics.mine && data.me.admin) tab = 'board';
    } catch (err) {
      clear(container).append(h('div', { class: 'card callout warn' }, err.message || 'Could not load the team.'));
      return;
    }
    draw();
  }

  function draw() {
    const { metrics, me } = data;
    const tabs = [
      ['mine', `My tasks (${metrics.mine})`, 'check'],
      ['board', 'Production board', 'flow'],
      ['payouts', 'Payouts', 'credit-card'],
      ['qa', 'Publishing QA', 'check'],
      ['thumb', 'Thumbnail text check', 'eye'],
      ['team', `Team (${data.members.length})`, 'users'],
    ];
    const body = tab === 'mine' ? taskList(data.tasks.filter((t) => t.mine && !t.completed), 'Nothing assigned to you right now.')
      : tab === 'board' ? board()
        : tab === 'payouts' ? payouts()
          : tab === 'qa' ? qaList()
            : tab === 'thumb' ? thumbCheck()
              : teamList();
    clear(container).append(
      h('header', { class: 'team-header' },
        h('div', { class: 'team-header-main' }, h('h1', {}, 'Team & Production'), h('p', { class: 'muted' }, 'Who is doing what, by when, and what it pays.')),
        h('div', { class: 'team-header-actions' },
          h('button', { type: 'button', class: 'btn secondary', onclick: repurposeModal }, icon('repeat', 14), ' Repurpose a video'),
          h('button', { type: 'button', class: 'btn primary', onclick: () => taskModal() }, icon('plus', 14), ' New task'),
          me.admin ? h('button', { type: 'button', class: 'btn secondary', onclick: inviteModal }, icon('user', 14), ' Invite') : null,
          me.admin ? h('button', { type: 'button', class: 'btn ghost', onclick: briefTemplatesDialog }, icon('file-text', 14), ' Brief templates') : null,
          me.admin ? h('button', { type: 'button', class: 'btn ghost', onclick: ratesDialog }, icon('credit-card', 14), ' Rates') : null)),
      h('div', { class: 'team-metrics-grid' },
        metric('Open tasks', metrics.total_tasks, `${metrics.in_progress} in production`),
        metric('Overdue', metrics.overdue, metrics.overdue ? 'Past their due date' : 'All on time', metrics.overdue ? 'warn' : ''),
        metric('Done this month', metrics.completed_this_month, `${metrics.ready_to_schedule} waiting for review`),
        metric(me.admin ? 'Payouts to approve or pay' : 'Your unpaid payouts', money(metrics.payout_pending), `${money(metrics.payout_paid_month)} paid this month`)),
      h('div', { class: 'team-tabs', role: 'tablist' }, tabs.map(([id, label, ic]) => h('button', {
        type: 'button', role: 'tab', 'aria-selected': String(tab === id), class: `team-tab ${tab === id ? 'is-active' : ''}`,
        onclick: () => { tab = id; draw(); },
      }, icon(ic, 14), ` ${label}`))),
      body);
  }

  function metric(label, value, sub, tone = '') {
    return h('div', { class: `team-metric-card ${tone}` }, h('span', { class: 'team-metric-label' }, label), h('strong', { class: 'team-metric-val' }, String(value)), h('small', { class: 'muted' }, sub));
  }

  function card(t) {
    return h('div', { class: `team-task-card ${t.completed ? 'is-done' : ''}` },
      h('div', { class: 'team-task-header' },
        t.overdue ? h('span', { class: 'team-task-pill urgent' }, 'Overdue') : h('span', { class: 'team-task-pill' }, STAGE_LABEL[t.stage] || t.stage),
        h('div', { class: 'team-task-meta-right' },
          t.payout_amount ? h('span', { class: 'team-payout-badge', title: `Payout: ${t.payout_status}` }, money(t.payout_amount, t.payout_currency)) : null,
          t.role_label ? h('span', { class: 'team-task-role' }, t.role_label) : null)),
      h('h4', { class: 'team-task-title' }, t.title),
      t.script_title ? h('p', { class: 'small' }, h('a', { href: `/scripts/${t.script_id}` }, icon('doc', 11), ` ${t.script_title}`)) : null,
      t.notes ? h('p', { class: 'team-task-notes', style: { whiteSpace: 'pre-wrap' } }, t.notes) : null,
      t.minutes_logged || t.timer_started_at ? h('p', { class: 'small muted' }, icon('clock', 11), ` ${Math.floor(t.minutes_logged / 60)}h ${t.minutes_logged % 60}m${t.timer_started_at ? ' · timer running' : ''}${t.hourly ? ' · hourly' : ''}`) : null,
      h('div', { class: 'team-task-foot' },
        h('div', { class: 'team-task-assignee' }, h('span', { class: 'team-task-avatar' }, (t.assignee_name[0] || 'U').toUpperCase()), h('span', { class: 'team-task-name' }, t.assignee_name)),
        t.due_on ? h('div', { class: 'team-task-date' }, icon('clock', 12), ' ', fmtDay(t.due_on)) : null),
      h('div', { class: 'team-task-actions row' },
        !t.completed && t.stage !== 'published' ? h('button', { type: 'button', class: 'btn sm ghost', onclick: () => patch(t, { stage: nextStage(t.stage) }) }, `→ ${STAGE_LABEL[nextStage(t.stage)]}`) : null,
        !t.completed ? h('button', { type: 'button', class: 'btn sm ghost', onclick: () => patch(t, { completed: true }) }, icon('check', 12), ' Done') : h('span', { class: 'small muted' }, icon('check', 12), ' Done'),
        h('button', { type: 'button', class: 'btn sm ghost', onclick: () => taskDetail(t, data.me, load) }, 'Open'),
        h('button', { type: 'button', class: 'btn sm ghost', onclick: () => taskModal(t) }, 'Edit')));
  }

  async function patch(t, changes) {
    try {
      await api('PATCH', `team/tasks/${t.id}`, changes);
      toast(changes.completed ? 'Marked done.' : 'Updated.');
      load();
    } catch (err) { toast(err.message, 'err'); }
  }

  function taskList(list, empty) {
    return list.length ? h('div', { class: 'team-col-tasks', style: { maxWidth: '760px' } }, list.map(card)) : emptyState(icon('check', 28), 'All clear', empty);
  }

  function board() {
    return h('div', { class: 'team-board' }, STAGES.filter(([id]) => id !== 'idea' || data.tasks.some((t) => t.stage === 'idea')).map(([id, label]) => {
      const list = data.tasks.filter((t) => t.stage === id && (id === 'published' || !t.completed));
      return h('div', { class: 'team-col' },
        h('div', { class: 'team-col-head' }, h('span', { class: 'team-col-title' }, label), h('span', { class: 'team-col-badge' }, list.length)),
        h('div', { class: 'team-col-tasks' }, list.length ? list.slice(0, 30).map(card) : h('div', { class: 'team-col-empty' }, 'Nothing here')));
    }));
  }

  function payouts() {
    const wrap = h('div', {}, loading());
    const monthInput = h('input', { type: 'month', class: 'input', value: payoutMonth, onchange: (e) => { payoutMonth = e.target.value; fill(); } });
    async function fill() {
      let p;
      try { p = await api('GET', `team/payouts?month=${payoutMonth}`); } catch (err) { clear(wrap).append(h('p', { class: 'muted' }, err.message)); return; }
      const rows = p.tasks.map((t) => h('tr', {},
        h('td', {}, t.title), h('td', {}, t.assignee_name), h('td', {}, t.completed_at ? fmtDay(t.completed_at.slice(0, 10)) : 'Not done'),
        h('td', { class: 'num' }, money(t.payout_amount, t.payout_currency)),
        h('td', {}, h('span', { class: `tag ${t.payout_status === 'paid' ? 'ok' : t.payout_status === 'approved' ? '' : 'warn'}` }, t.payout_status), t.paid_ref ? h('span', { class: 'muted small' }, ` ${t.paid_ref}`) : null),
        h('td', {}, data.me.admin && t.payout_status === 'pending' ? h('button', { type: 'button', class: 'btn sm', onclick: async () => { await api('POST', `team/tasks/${t.id}/approve-payout`); toast('Approved.'); fill(); load(); } }, 'Approve') : null,
          data.me.admin && t.payout_status === 'approved' ? h('button', { type: 'button', class: 'btn sm primary', onclick: () => payModal(t, fill) }, 'Mark paid') : null)));
      clear(wrap).append(
        p.by_person.length ? h('div', { class: 'grid grid-3', style: { marginBottom: '12px' } }, p.by_person.map((b) => h('div', { class: 'card' },
          h('strong', {}, b.name), h('p', { class: 'small muted' }, `To approve ${money(b.pending)} · approved ${money(b.approved)} · paid ${money(b.paid)}`)))) : null,
        p.tasks.length ? h('div', { class: 'table-wrap' }, h('table', { class: 'table' },
          h('thead', {}, h('tr', {}, ['Task', 'Who', 'Done', 'Amount', 'Status', ''].map((c) => h('th', {}, c)))), h('tbody', {}, rows)))
          : emptyState(icon('credit-card', 28), 'No payouts this month', 'Tasks with a payout appear here once they’re done.'));
    }
    fill();
    return h('div', {},
      h('div', { class: 'row', style: { marginBottom: '12px', flexWrap: 'wrap' } }, field('Month', monthInput),
        h('a', { class: 'btn ghost sm', href: `/api/team/payouts?month=${payoutMonth}&format=csv`, download: '' }, icon('download', 13), ' Statement (CSV)')),
      h('p', { class: 'muted small' }, 'A finished task with a payout waits for approval; once approved it can be marked paid with a reference (bank transfer, PayPal…). Nothing is sent from here.'),
      wrap);
  }

  async function payModal(t, after) {
    const ref = h('input', { class: 'input', maxlength: 120, placeholder: 'e.g. Wise #12345' });
    await modal(`Mark paid: ${money(t.payout_amount, t.payout_currency)} to ${t.assignee_name}`, (close) => h('div', {},
      field('Payment reference (optional)', ref),
      h('div', { class: 'row-end', style: { marginTop: '12px' } }, h('button', {
        type: 'button', class: 'btn primary',
        onclick: async () => { try { await api('POST', `team/tasks/${t.id}/paid`, { reference: ref.value || null }); toast('Marked paid; they got an email.'); close(); after(); load(); } catch (err) { toast(err.message, 'err'); } },
      }, 'Mark paid'))));
  }

  function qaList() {
    return h('div', { class: 'team-qa-wrap' },
      h('div', { class: 'team-qa-banner' }, h('div', {}, h('h3', {}, 'Before you publish'), h('p', { class: 'muted' }, 'Tick each check for the video going out next.')),
        h('span', { class: 'team-qa-score' }, `${data.qa.filter((q) => q.passed).length} / ${data.qa.length}`)),
      h('div', { class: 'team-qa-list' }, data.qa.map((item) => h('div', { class: `team-qa-item ${item.passed ? 'is-passed' : ''}` },
        h('button', {
          type: 'button', class: `team-qa-toggle ${item.passed ? 'passed' : ''}`, 'aria-pressed': String(item.passed), 'aria-label': item.title,
          onclick: async () => { data.qa = (await api('POST', 'team/qa/toggle', { key: item.key })).qa; draw(); },
        }, icon(item.passed ? 'check' : 'x', 14)),
        h('div', { class: 'team-qa-body' }, h('strong', { class: 'team-qa-title' }, item.title), h('p', { class: 'team-qa-desc' }, item.desc))))));
  }

  function thumbCheck() {
    const text = h('input', { class: 'input', placeholder: 'The words on the thumbnail', maxlength: 100 });
    const face = h('input', { type: 'checkbox' });
    const out = h('div');
    return h('div', { class: 'card', style: { maxWidth: '640px' } },
      h('h3', {}, 'Thumbnail text check'),
      h('p', { class: 'muted small' }, 'A quick rule of thumb on how much text fits a phone feed. For the real image, use the Feed preview on a script’s Package tab.'),
      field('Thumbnail text', text), h('label', { class: 'row small', style: { gap: '6px' } }, face, 'Has a clear face or focal object'),
      h('div', { class: 'row-end' }, h('button', {
        type: 'button', class: 'btn primary',
        onclick: async () => {
          if (!text.value.trim()) return toast('Type the thumbnail text first.', 'err');
          const r = await api('POST', 'team/thumbnails/simulate', { title: text.value, has_face: face.checked, has_text: true });
          clear(out).append(h('p', {}, h('strong', {}, `${r.metrics.mobile_readability_score}/100 · `), r.metrics.text_density_verdict),
            h('ul', {}, r.recommendations.map((tip) => h('li', {}, tip))), h('p', { class: 'muted small' }, r.basis));
        },
      }, 'Check')), out);
  }

  function teamList() {
    const admin = data.me.admin;
    return h('div', {},
      h('div', { class: 'team-roster-grid' }, data.members.map((m) => h('div', { class: 'team-member-card' },
        h('div', { class: 'team-member-avatar' }, (m.name[0] || 'S').toUpperCase()),
        h('div', { class: 'team-member-details' },
          h('strong', {}, m.name), m.email ? h('span', { class: 'muted small' }, m.email) : null,
          h('span', { class: 'team-member-badge' }, `${m.role_label} · ${m.open_tasks} open`),
          admin && m.role === 'staff' ? h('button', {
            type: 'button', class: 'btn ghost sm',
            onclick: async () => { if (await confirmDialog(`Remove ${m.name}'s access? Their open tasks become unassigned.`, { ok: 'Remove access' })) { await api('DELETE', `team/staff/${m.id}`); toast('Access removed.'); load(); } },
          }, 'Remove access') : null)))),
      admin && data.invites.length ? h('div', { class: 'card', style: { marginTop: '16px' } },
        h('h3', {}, 'Pending invites'),
        h('div', { class: 'stack' }, data.invites.map((i) => h('div', { class: 'row-between' },
          h('span', {}, `${i.name} · ${i.email}${i.role_label ? ` · ${i.role_label}` : ''}`),
          h('button', { type: 'button', class: 'btn ghost sm', onclick: async () => { await api('DELETE', `team/invites/${i.id}`); load(); } }, 'Withdraw'))))) : null);
  }

  async function taskModal(t = null) {
    const title = h('input', { class: 'input', required: true, maxlength: 200, value: t?.title || '' });
    const stage = h('select', { class: 'input' }, STAGES.map(([id, label]) => h('option', { value: id, selected: (t?.stage || 'editing') === id }, label)));
    const assignee = h('select', { class: 'input' }, h('option', { value: '' }, 'Unassigned'),
      data.members.map((m) => h('option', { value: m.id, selected: t?.assignee_id === m.id }, `${m.name} (${m.role_label})`)));
    const role = h('select', { class: 'input' }, h('option', { value: '' }, '—'), data.roles_available.map((r) => h('option', { value: r, selected: t?.role_label === r }, r)));
    const due = h('input', { type: 'date', class: 'input', value: t?.due_on || '' });
    const payout = h('input', { type: 'number', class: 'input', min: 0, step: '0.01', value: t?.payout_amount ?? '' });
    const currency = h('select', { class: 'input' }, ['USD', 'EUR', 'GBP'].map((c) => h('option', { value: c, selected: (t?.payout_currency || 'USD') === c }, c)));
    const notes = h('textarea', { class: 'input', rows: 3, maxlength: 2000 }, t?.notes || '');
    const hourly = h('input', { type: 'checkbox', checked: !!t?.hourly });
    await modal(t ? 'Edit task' : 'New task', (close) => h('form', {
      onsubmit: async (e) => {
        e.preventDefault();
        const body = { title: title.value, stage: stage.value, assignee_id: assignee.value ? Number(assignee.value) : null, role_label: role.value || null, due_on: due.value || null, notes: notes.value || null };
        if (data.me.admin) { body.payout_amount = Number(payout.value || 0); body.payout_currency = currency.value; body.hourly = hourly.checked; }
        try {
          if (t) await api('PATCH', `team/tasks/${t.id}`, body); else await api('POST', 'team/tasks', body);
          toast(t ? 'Saved.' : 'Task added; they got an email.');
          close();
          load();
        } catch (err) { toast(err.message, 'err'); }
      },
    },
    field('Task', title),
    h('div', { class: 'installer-form-row' }, field('Stage', stage), field('Due', due)),
    h('div', { class: 'installer-form-row' }, field('Assignee', assignee), field('Role', role)),
    data.me.admin ? h('div', { class: 'installer-form-row' }, field('Payout', payout, 'Paid once the task is done and you approve it'), field('Currency', currency)) : null,
    data.me.admin ? h('label', { class: 'row small', style: { gap: '6px' } }, hourly, 'Billed hourly (hours × their rate, set under Rates)') : null,
    field('Notes', notes),
    h('div', { class: 'modal-actions' },
      t ? h('button', { type: 'button', class: 'btn ghost', onclick: async () => { if (await confirmDialog('Delete this task?')) { try { await api('DELETE', `team/tasks/${t.id}`); close(); load(); } catch (err) { toast(err.message, 'err'); } } } }, 'Delete') : null,
      h('button', { type: 'submit', class: 'btn primary' }, t ? 'Save' : 'Add task'))), { wide: true });
  }

  async function repurposeModal() {
    const title = h('input', { class: 'input', required: true, maxlength: 200 });
    const url = h('input', { type: 'url', class: 'input', placeholder: 'https://youtube.com/watch?v=…' });
    const stamps = h('textarea', { class: 'input', rows: 2, placeholder: '00:00 Intro, 03:12 Demo…' });
    await modal('Repurpose a video', (close) => h('form', {
      onsubmit: async (e) => {
        e.preventDefault();
        try {
          const r = await api('POST', 'team/tasks/auto-repurpose', { title: title.value, video_url: url.value || null, timestamps: stamps.value || null });
          toast(r.message);
          close();
          load();
        } catch (err) { toast(err.message, 'err'); }
      },
    },
    h('p', { class: 'muted small' }, 'Adds four tasks: two Shorts, a thumbnail pack and a carousel + thread. Each goes to whoever last did that role (or stays unassigned); set payouts on each task.'),
    field('Video title', title), field('Video link (optional)', url), field('Chapter times (optional)', stamps),
    h('div', { class: 'modal-actions' }, h('button', { type: 'submit', class: 'btn primary' }, 'Add tasks'))));
  }

  async function inviteModal() {
    const name = h('input', { class: 'input', required: true, maxlength: 100 });
    const email = h('input', { type: 'email', class: 'input', required: true });
    const role = h('select', { class: 'input' }, h('option', { value: '' }, '—'), data.roles_available.map((r) => h('option', { value: r }, r)));
    await modal('Invite a team member', (close) => h('form', {
      onsubmit: async (e) => {
        e.preventDefault();
        try {
          const r = await api('POST', 'team/staff', { name: name.value, email: email.value, role_label: role.value || null });
          toast(r.message);
          if (r.link) copyText(r.link, 'Invite link copied too.');
          close();
          load();
        } catch (err) { toast(err.message, 'err'); }
      },
    },
    h('p', { class: 'muted small' }, 'They get an email to set their own password. Team members work on scripts, ideas, social and media; your money, website, settings, keys and backups stay yours.'),
    field('Name', name), field('Email', email), field('Role', role),
    h('div', { class: 'modal-actions' }, h('button', { type: 'submit', class: 'btn primary' }, 'Send invite'))));
  }

  await load();
}

/**
 * Signed out: /join-team/<token> lets an invited team member set a password.
 */
export async function renderJoinTeam(view, token) {
  const invite = await api('GET', `team-invite/${token}`);
  const pass = h('input', { type: 'password', class: 'input', minlength: 10, required: true, autocomplete: 'new-password' });
  const msg = h('p', { class: 'muted' });
  clear(view).append(h('div', { class: 'card', style: { maxWidth: '440px', margin: '48px auto' } },
    h('h1', {}, 'Join the team'),
    h('p', {}, `Hi ${invite.name}! Set a password for ${invite.email}${invite.role_label ? ` (${invite.role_label})` : ''}.`),
    h('form', {
      onsubmit: async (e) => {
        e.preventDefault();
        try {
          const r = await api('POST', `team-invite/${token}`, { password: pass.value });
          clear(view).append(h('div', { class: 'card', style: { maxWidth: '440px', margin: '48px auto' } },
            h('h1', {}, 'You’re in'), h('p', {}, r.message), h('a', { class: 'btn primary', href: '/login' }, 'Sign in')));
        } catch (err) { msg.textContent = err.message; }
      },
    }, field('Password (10+ characters)', pass), msg, h('button', { type: 'submit', class: 'btn primary block' }, 'Create my account'))));
}
