// Staff & Team Production Pipeline: Editorial workflow, employee task assignments, and QA checklist.
import { h, api, clear, field, toast } from '../core.js';
import { icon } from '../icons.js';

export async function render(view) {
  const container = h('div', { class: 'team-page' });
  clear(view).append(container);

  let data = null;
  let loading = true;
  let activeTab = 'board'; // 'board' | 'checklist' | 'members'

  async function loadData() {
    try {
      data = await api('GET', 'team');
    } catch (err) {
      toast(err.message || 'Failed to load team data', 'err');
    } finally {
      loading = false;
      renderContent();
    }
  }

  function renderContent() {
    clear(container);

    if (loading) {
      container.append(h('div', { class: 'card callout' }, icon('refresh', 16), ' Loading team production pipeline...'));
      return;
    }

    if (!data) {
      container.append(h('div', { class: 'card callout warn' }, 'Unable to load production pipeline.'));
      return;
    }

    const { members, tasks, qa, metrics, roles_available } = data;

    // Page header
    const head = h('header', { class: 'team-header' },
      h('div', { class: 'team-header-main' },
        h('h1', {}, 'Team & Production Pipeline'),
        h('p', { class: 'muted' }, 'Coordinate Delia’s creator staff: video editors, thumbnail designers, copywriters, and publishing QA.')),
      h('div', { class: 'team-header-actions' },
        h('button', {
          type: 'button', class: 'btn secondary', onclick: () => openTaskModal(roles_available, members),
        }, icon('plus', 14), ' New Production Task'),
        h('button', {
          type: 'button', class: 'btn primary', onclick: () => openStaffModal(),
        }, icon('user', 14), ' Add Staff Member')));

    // Metrics summary grid
    const metricsGrid = h('div', { class: 'team-metrics-grid' },
      h('div', { class: 'team-metric-card' },
        h('span', { class: 'team-metric-label' }, 'Active Production Tasks'),
        h('strong', { class: 'team-metric-val' }, metrics.total_tasks),
        h('small', { class: 'muted' }, `${metrics.in_progress} in active production`)),
      h('div', { class: 'team-metric-card accent' },
        h('span', { class: 'team-metric-label' }, 'Awaiting Review & QA'),
        h('strong', { class: 'team-metric-val' }, metrics.ready_to_schedule),
        h('small', { class: 'muted' }, 'Needs Delia or Lead approval')),
      h('div', { class: 'team-metric-card' },
        h('span', { class: 'team-metric-label' }, 'Staff Team Members'),
        h('strong', { class: 'team-metric-val' }, members.length),
        h('small', { class: 'muted' }, 'Editors, designers, copywriters')),
      h('div', { class: 'team-metric-card' },
        h('span', { class: 'team-metric-label' }, 'Monthly Velocity'),
        h('strong', { class: 'team-metric-val' }, `${metrics.velocity_monthly} vids`),
        h('small', { class: 'muted' }, 'High-performing production pace')));

    // Sub-nav tabs
    const tabs = h('div', { class: 'team-tabs' },
      h('button', {
        class: `team-tab ${activeTab === 'board' ? 'is-active' : ''}`,
        onclick: () => { activeTab = 'board'; renderContent(); },
      }, icon('grid', 14), ' Production Pipeline'),
      h('button', {
        class: `team-tab ${activeTab === 'checklist' ? 'is-active' : ''}`,
        onclick: () => { activeTab = 'checklist'; renderContent(); },
      }, icon('check', 14), ' Publishing QA Checklist'),
      h('button', {
        class: `team-tab ${activeTab === 'members' ? 'is-active' : ''}`,
        onclick: () => { activeTab = 'members'; renderContent(); },
      }, icon('users', 14), ` Staff Roster (${members.length})`));

    let tabBody = null;

    if (activeTab === 'board') {
      tabBody = renderKanbanBoard(tasks);
    } else if (activeTab === 'checklist') {
      tabBody = renderQaChecklist(qa);
    } else {
      tabBody = renderStaffRoster(members);
    }

    container.append(head, metricsGrid, tabs, tabBody);
  }

  function renderKanbanBoard(tasks) {
    const stages = [
      { id: 'script', label: 'Scripting & Research', icon: 'doc' },
      { id: 'filming', label: 'Filming & B-Roll', icon: 'play' },
      { id: 'editing', label: 'Video Editing', icon: 'film' },
      { id: 'review', label: 'Review & Thumbnail QA', icon: 'eye' },
      { id: 'published', label: 'Scheduled / Published', icon: 'check' },
    ];

    const board = h('div', { class: 'team-board' });

    stages.forEach((st) => {
      const colTasks = tasks.filter((t) => t.stage === st.id);
      const col = h('div', { class: 'team-col' },
        h('div', { class: 'team-col-head' },
          h('span', { class: 'team-col-title' }, icon(st.icon, 14), st.label),
          h('span', { class: 'team-col-badge' }, colTasks.length)),
        h('div', { class: 'team-col-tasks' },
          colTasks.length ? colTasks.map((t) => renderTaskCard(t)) : h('div', { class: 'team-col-empty' }, 'No tasks in this stage')));
      board.append(col);
    });

    return board;
  }

  function renderTaskCard(t) {
    const isUrgent = t.priority === 'urgent';
    const isHigh = t.priority === 'high';

    return h('div', { class: `team-task-card ${t.completed ? 'is-done' : ''}` },
      h('div', { class: 'team-task-header' },
        h('span', { class: `team-task-pill ${isUrgent ? 'urgent' : isHigh ? 'high' : ''}` }, t.priority),
        h('span', { class: 'team-task-role' }, t.role_specialty)),
      h('h4', { class: 'team-task-title' }, t.title),
      t.notes ? h('p', { class: 'team-task-notes' }, t.notes) : null,
      h('div', { class: 'team-task-foot' },
        h('div', { class: 'team-task-assignee' },
          h('span', { class: 'team-task-avatar' }, (t.assignee_name[0] || 'U').toUpperCase()),
          h('span', { class: 'team-task-name' }, t.assignee_name)),
        h('div', { class: 'team-task-date' }, icon('clock', 12), ' ', t.due_date)),
      h('div', { class: 'team-task-actions' },
        t.stage !== 'published' ? h('button', {
          type: 'button', class: 'btn sm ghost team-task-adv-btn',
          onclick: async () => {
            const nextStage = getNextStage(t.stage);
            await api('PATCH', `team/tasks/${t.id}`, { stage: nextStage });
            toast(`Moved to ${nextStage}`, 'ok');
            loadData();
          },
        }, 'Advance Stage ', icon('arrow', 12)) : h('span', { class: 'team-task-published' }, icon('check', 12), ' Published')));
  }

  function getNextStage(current) {
    const flow = ['idea', 'script', 'filming', 'editing', 'review', 'published'];
    const idx = flow.indexOf(current);
    return idx >= 0 && idx < flow.length - 1 ? flow[idx + 1] : 'published';
  }

  function renderQaChecklist(qa) {
    const list = h('div', { class: 'team-qa-list' },
      qa.map((item) => h('div', { class: `team-qa-item ${item.passed ? 'is-passed' : ''}` },
        h('button', {
          type: 'button',
          class: `team-qa-toggle ${item.passed ? 'passed' : ''}`,
          onclick: async () => {
            const res = await api('POST', 'team/qa/toggle', { key: item.key });
            data.qa = res.qa;
            renderContent();
          },
        }, icon(item.passed ? 'check' : 'x', 14)),
        h('div', { class: 'team-qa-body' },
          h('strong', { class: 'team-qa-title' }, item.title),
          h('p', { class: 'team-qa-desc' }, item.desc)))));

    return h('div', { class: 'team-qa-wrap' },
      h('div', { class: 'team-qa-banner' },
        h('div', {},
          h('h3', {}, 'Content Quality Assurance & Retention Audit'),
          h('p', { class: 'muted' }, 'Every video and post must pass these quality gates before Delia or the editor triggers publishing.')),
        h('span', { class: 'team-qa-score' }, `${qa.filter((q) => q.passed).length} / ${qa.length} Passed`)),
      list);
  }

  function renderStaffRoster(members) {
    return h('div', { class: 'team-roster' },
      h('div', { class: 'team-roster-grid' },
        members.map((m) => h('div', { class: 'team-member-card' },
          h('div', { class: 'team-member-avatar' }, (m.name[0] || 'S').toUpperCase()),
          h('div', { class: 'team-member-details' },
            h('strong', {}, m.name),
            h('span', { class: 'muted small' }, m.email),
            h('span', { class: 'team-member-badge' }, m.role_label))))));
  }

  function openTaskModal(roles, members) {
    const titleInput = h('input', { type: 'text', class: 'input', required: true, placeholder: 'e.g. Cut 3 High-Energy Shorts from Cursor AI' });
    const stageSelect = h('select', { class: 'input' },
      h('option', { value: 'script' }, 'Scripting & Research'),
      h('option', { value: 'filming' }, 'Filming & B-Roll'),
      h('option', { value: 'editing', selected: true }, 'Video Editing'),
      h('option', { value: 'review' }, 'Review & Thumbnail QA'));

    const assigneeSelect = h('select', { class: 'input' },
      members.map((m) => h('option', { value: m.name }, `${m.name} (${m.role_label})`)));

    const roleSelect = h('select', { class: 'input' },
      roles.map((r) => h('option', { value: r }, r)));

    const prioritySelect = h('select', { class: 'input' },
      h('option', { value: 'urgent' }, 'Urgent'),
      h('option', { value: 'high', selected: true }, 'High Priority'),
      h('option', { value: 'medium' }, 'Medium Priority'),
      h('option', { value: 'normal' }, 'Normal'));

    const notesText = h('textarea', { class: 'input', rows: 2, placeholder: 'Key timestamps, edit notes, or hook requirements...' });

    const modal = h('div', { class: 'modal-overlay' },
      h('div', { class: 'modal-card' },
        h('div', { class: 'modal-head' },
          h('h3', {}, 'Assign Production Task'),
          h('button', { type: 'button', class: 'btn ghost sm', onclick: () => modal.remove() }, icon('x', 14))),
        h('form', {
          onsubmit: async (e) => {
            e.preventDefault();
            try {
              await api('POST', 'team/tasks', {
                title: titleInput.value,
                stage: stageSelect.value,
                assignee_name: assigneeSelect.value,
                role_specialty: roleSelect.value,
                priority: prioritySelect.value,
                notes: notesText.value,
              });
              modal.remove();
              toast('Task assigned to production queue', 'ok');
              loadData();
            } catch (err) {
              toast(err.message, 'err');
            }
          },
        },
          field('Task Title', titleInput),
          h('div', { class: 'installer-form-row' },
            field('Initial Stage', stageSelect),
            field('Priority', prioritySelect)),
          h('div', { class: 'installer-form-row' },
            field('Assignee', assigneeSelect),
            field('Role Specialty', roleSelect)),
          field('Production Notes', notesText),
          h('div', { class: 'modal-actions' },
            h('button', { type: 'button', class: 'btn secondary', onclick: () => modal.remove() }, 'Cancel'),
            h('button', { type: 'submit', class: 'btn primary' }, 'Create Task')))));

    document.body.append(modal);
  }

  function openStaffModal() {
    const nameInput = h('input', { type: 'text', class: 'input', required: true, placeholder: 'Alex Rivers' });
    const emailInput = h('input', { type: 'email', class: 'input', required: true, placeholder: 'alex@creatorteam.com' });
    const passInput = h('input', { type: 'password', class: 'input', required: true, minlength: 8, placeholder: 'Temporary password' });

    const modal = h('div', { class: 'modal-overlay' },
      h('div', { class: 'modal-card' },
        h('div', { class: 'modal-head' },
          h('h3', {}, 'Provision Staff Member Account'),
          h('button', { type: 'button', class: 'btn ghost sm', onclick: () => modal.remove() }, icon('x', 14))),
        h('p', { class: 'muted small' }, 'Staff accounts grant your video editors, thumbnail designers, and copywriters direct access to YouTube scripts, production pipelines, and social tools.'),
        h('form', {
          onsubmit: async (e) => {
            e.preventDefault();
            try {
              await api('POST', 'team/staff', {
                name: nameInput.value,
                email: emailInput.value,
                password: passInput.value,
              });
              modal.remove();
              toast(`Staff member ${nameInput.value} provisioned!`, 'ok');
              loadData();
            } catch (err) {
              toast(err.message, 'err');
            }
          },
        },
          field('Employee Name', nameInput),
          field('Work Email', emailInput),
          field('Password', passInput, 'They can change this upon initial login'),
          h('div', { class: 'modal-actions' },
            h('button', { type: 'button', class: 'btn secondary', onclick: () => modal.remove() }, 'Cancel'),
            h('button', { type: 'submit', class: 'btn primary' }, 'Create Staff Account')))));

    document.body.append(modal);
  }

  await loadData();
}

