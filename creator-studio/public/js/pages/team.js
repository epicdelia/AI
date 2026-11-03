// Staff & Team Production Pipeline: Editorial workflow, employee task assignments, QA checklist,
// 1-Click Auto-Repurpose engine, Freelancer Payout tracker, and Mobile Thumbnail A/B Simulator.
import { h, api, clear, field, toast } from '../core.js';
import { icon } from '../icons.js';

export async function render(view) {
  const container = h('div', { class: 'team-page' });
  clear(view).append(container);

  let data = null;
  let loading = true;
  let activeTab = 'board'; // 'board' | 'checklist' | 'simulator' | 'members'
  let simState = {
    title: 'How I Built an Autonomous AI Dev in 24 Hours',
    wordsCount: 4,
    hasFace: true,
    hasText: true,
    result: null,
    loading: false,
  };

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
          type: 'button', class: 'btn primary team-repurpose-btn', onclick: () => openAutoRepurposeModal(),
        }, icon('bolt', 14), ' 1-Click Auto-Repurpose'),
        h('button', {
          type: 'button', class: 'btn secondary', onclick: () => openTaskModal(roles_available, members),
        }, icon('plus', 14), ' New Task'),
        h('button', {
          type: 'button', class: 'btn secondary', onclick: () => openStaffModal(),
        }, icon('user', 14), ' Add Staff')));

    // Metrics summary grid
    const metricsGrid = h('div', { class: 'team-metrics-grid' },
      h('div', { class: 'team-metric-card' },
        h('span', { class: 'team-metric-label' }, 'Active Production Tasks'),
        h('strong', { class: 'team-metric-val' }, metrics.total_tasks),
        h('small', { class: 'muted' }, `${metrics.in_progress} in active production`)),
      h('div', { class: 'team-metric-card accent' },
        h('span', { class: 'team-metric-label' }, 'Awaiting Review & QA'),
        h('strong', { class: 'team-metric-val' }, metrics.ready_to_schedule),
        h('small', { class: 'muted' }, 'Needs Delia or Lead signoff')),
      h('div', { class: 'team-metric-card' },
        h('span', { class: 'team-metric-label' }, 'Freelancer Payout Budget'),
        h('strong', { class: 'team-metric-val' }, `$${(metrics.payout_committed || 0).toFixed(0)}`),
        h('small', { class: 'muted' }, `$${(metrics.payout_pending || 0).toFixed(0)} pending completion`)),
      h('div', { class: 'team-metric-card' },
        h('span', { class: 'team-metric-label' }, 'Staff Velocity'),
        h('strong', { class: 'team-metric-val' }, `${metrics.velocity_monthly} vids`),
        h('small', { class: 'muted' }, `${members.length} team members active`)));

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
        class: `team-tab ${activeTab === 'simulator' ? 'is-active' : ''}`,
        onclick: () => { activeTab = 'simulator'; renderContent(); },
      }, icon('eye', 14), ' Thumbnail Mobile Simulator'),
      h('button', {
        class: `team-tab ${activeTab === 'members' ? 'is-active' : ''}`,
        onclick: () => { activeTab = 'members'; renderContent(); },
      }, icon('users', 14), ` Staff Roster (${members.length})`));

    let tabBody = null;

    if (activeTab === 'board') {
      tabBody = renderKanbanBoard(tasks);
    } else if (activeTab === 'checklist') {
      tabBody = renderQaChecklist(qa);
    } else if (activeTab === 'simulator') {
      tabBody = renderThumbnailSimulator();
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
        h('div', { class: 'team-task-meta-right' },
          t.payout_amount ? h('span', { class: 'team-payout-badge' }, `$${t.payout_amount}`) : null,
          h('span', { class: 'team-task-role' }, t.role_specialty))),
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

  function renderThumbnailSimulator() {
    const wrap = h('div', { class: 'team-sim-wrap' });

    // Header callout
    const banner = h('div', { class: 'team-sim-banner' },
      h('div', {},
        h('h3', {}, 'Mobile Feed 120px Thumbnail & Contrast Simulator'),
        h('p', { class: 'muted' }, '72% of Delia’s 519K audience browses YouTube on mobile devices. Test thumbnail readability at 120px scale with dark & light mode contrast scores.')),
      h('div', { class: 'team-sim-preset-tags' },
        h('span', { class: 'team-sim-tag', onclick: () => triggerPreset('Cursor AI vs Copilot: The Truth') }, 'Preset: Cursor AI'),
        h('span', { class: 'team-sim-tag', onclick: () => triggerPreset('Stop Writing Unit Tests Like This') }, 'Preset: Unit Tests'),
        h('span', { class: 'team-sim-tag', onclick: () => triggerPreset('System Design Interview in 30 Mins') }, 'Preset: System Design')));

    // Interactive simulator inputs
    const titleInput = h('input', { type: 'text', class: 'input', value: simState.title, placeholder: 'Enter thumbnail title text (e.g. AI Coding Secrets)' });
    const wordsInput = h('input', { type: 'number', class: 'input', min: 1, max: 10, value: simState.wordsCount });
    const faceCheck = h('input', { type: 'checkbox', checked: simState.hasFace });
    const textCheck = h('input', { type: 'checkbox', checked: simState.hasText });

    async function runSim() {
      simState.title = titleInput.value;
      simState.wordsCount = parseInt(wordsInput.value, 10) || 3;
      simState.hasFace = faceCheck.checked;
      simState.hasText = textCheck.checked;
      simState.loading = true;
      renderContent();

      try {
        const res = await api('POST', 'team/thumbnails/simulate', {
          title: simState.title,
          text_words_count: simState.wordsCount,
          has_face: simState.hasFace,
          has_text: simState.hasText,
        });
        simState.result = res;
      } catch (err) {
        toast(err.message, 'err');
      } finally {
        simState.loading = false;
        renderContent();
      }
    }

    function triggerPreset(title) {
      simState.title = title;
      simState.wordsCount = title.split(' ').length;
      runSim();
    }

    const form = h('div', { class: 'card team-sim-form' },
      h('div', { class: 'installer-form-row' },
        field('Thumbnail Focus Text', titleInput, 'The bold 2-4 words rendered directly on the thumbnail canvas'),
        field('Word Count on Graphic', wordsInput, 'Under 5 words recommended for mobile clarity')),
      h('div', { class: 'team-sim-options' },
        h('label', { class: 'team-sim-opt' }, faceCheck, ' Expressive Face Included'),
        h('label', { class: 'team-sim-opt' }, textCheck, ' High Contrast Text Badge'),
        h('button', {
          type: 'button', class: 'btn primary team-sim-btn', onclick: runSim, disabled: simState.loading,
        }, icon('bolt', 14), simState.loading ? ' Simulating...' : ' Run Simulation & Contrast Check')));

    // Preview area: Dark Mode & Light Mode 120px scale comparison
    const previewGrid = h('div', { class: 'team-sim-previews' },
      // Dark Mode Feed
      h('div', { class: 'team-sim-preview-box dark-feed' },
        h('div', { class: 'team-sim-preview-head' },
          h('span', {}, 'YouTube Dark Mode (Mobile Feed 120px)'),
          h('span', { class: 'badge sm' }, '74% of Views')),
        h('div', { class: 'team-sim-feed-item' },
          h('div', { class: 'team-sim-thumb-box' },
            h('span', { class: 'team-sim-thumb-face' }, simState.hasFace ? '👩‍💻' : '⚡'),
            simState.hasText ? h('span', { class: 'team-sim-thumb-text' }, simState.title.slice(0, 24)) : null,
            h('span', { class: 'team-sim-thumb-dur' }, '14:28')),
          h('div', { class: 'team-sim-feed-meta' },
            h('h5', {}, simState.title),
            h('span', { class: 'muted' }, 'Delia Lazarescu • 48K views • 2 hours ago')))),

      // Light Mode Feed
      h('div', { class: 'team-sim-preview-box light-feed' },
        h('div', { class: 'team-sim-preview-head' },
          h('span', {}, 'YouTube Light Mode (Mobile Feed 120px)'),
          h('span', { class: 'badge sm' }, '26% of Views')),
        h('div', { class: 'team-sim-feed-item' },
          h('div', { class: 'team-sim-thumb-box' },
            h('span', { class: 'team-sim-thumb-face' }, simState.hasFace ? '👩‍💻' : '⚡'),
            simState.hasText ? h('span', { class: 'team-sim-thumb-text' }, simState.title.slice(0, 24)) : null,
            h('span', { class: 'team-sim-thumb-dur' }, '14:28')),
          h('div', { class: 'team-sim-feed-meta' },
            h('h5', {}, simState.title),
            h('span', { class: 'muted' }, 'Delia Lazarescu • 48K views • 2 hours ago')))));

    // Results metrics
    let metricsCard = null;
    const res = simState.result;
    if (res) {
      metricsCard = h('div', { class: 'card team-sim-results' },
        h('div', { class: 'team-sim-score-row' },
          h('div', { class: 'team-sim-score-item' },
            h('span', { class: 'muted small' }, 'Mobile Readability'),
            h('strong', { class: `team-sim-score-val ${res.metrics.mobile_readability_score > 80 ? 'ok' : 'warn'}` }, `${res.metrics.mobile_readability_score}/100`),
            h('small', {}, res.metrics.text_density_verdict)),
          h('div', { class: 'team-sim-score-item' },
            h('span', { class: 'muted small' }, 'Dark Theme Contrast'),
            h('strong', { class: 'team-sim-score-val ok' }, res.metrics.contrast_dark_mode)),
          h('div', { class: 'team-sim-score-item' },
            h('span', { class: 'muted small' }, 'Light Theme Contrast'),
            h('strong', { class: 'team-sim-score-val ok' }, res.metrics.contrast_light_mode)),
          h('div', { class: 'team-sim-score-item' },
            h('span', { class: 'muted small' }, 'Predicted CTR Tier'),
            h('strong', { class: 'team-sim-score-val accent' }, res.metrics.predicted_ctr_tier))),
        h('div', { class: 'team-sim-tips' },
          h('strong', {}, 'Audit Recommendations:'),
          h('ul', {}, res.recommendations.map((tip) => h('li', {}, tip)))));
    }

    wrap.append(banner, form, previewGrid, metricsCard);
    return wrap;
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

  function openAutoRepurposeModal() {
    const titleInput = h('input', { type: 'text', class: 'input', required: true, placeholder: 'e.g. Building an Autonomous Agent Swarm with Claude & Python' });
    const urlInput = h('input', { type: 'url', class: 'input', placeholder: 'https://youtube.com/watch?v=...' });
    const timestampsInput = h('textarea', { class: 'input', rows: 2, placeholder: '00:00 Intro, 03:12 Agent Architecture, 07:45 Live Demo' });

    const modal = h('div', { class: 'modal-overlay' },
      h('div', { class: 'modal-card' },
        h('div', { class: 'modal-head' },
          h('h3', {}, icon('bolt', 16), ' 1-Click Multi-Platform Auto-Repurpose'),
          h('button', { type: 'button', class: 'btn ghost sm', onclick: () => modal.remove() }, icon('x', 14))),
        h('p', { class: 'muted small' }, 'Automatically cascade a single long-form video into 4 assigned staff production tasks: 2 vertical Shorts cuts, 2 thumbnail concepts, and a LinkedIn carousel/X thread with transparent freelance payouts.'),
        h('form', {
          onsubmit: async (e) => {
            e.preventDefault();
            try {
              const res = await api('POST', 'team/tasks/auto-repurpose', {
                title: titleInput.value,
                video_url: urlInput.value || null,
                timestamps: timestampsInput.value || null,
              });
              modal.remove();
              toast(res.message || 'Repurposing cascade queued!', 'ok');
              loadData();
            } catch (err) {
              toast(err.message, 'err');
            }
          },
        },
          field('Long-Form Video Title', titleInput),
          field('Source Video URL (Optional)', urlInput),
          field('Key Chapter Timestamps (Optional)', timestampsInput),
          h('div', { class: 'team-repurpose-preview-box' },
            h('strong', {}, 'Automated Production Tasks Generated:'),
            h('ul', { class: 'small muted' },
              h('li', {}, '• Short #1 (Hook & Setup) — Video Editor ($75 payout)'),
              h('li', {}, '• Short #2 (Gold Insight & Code Highlight) — Video Editor ($75 payout)'),
              h('li', {}, '• Thumbnail A/B Test Pack (2 Concepts) — Thumbnail Artist ($80 payout)'),
              h('li', {}, '• LinkedIn Carousel & X Thread — Copywriter ($60 payout)'))),
          h('div', { class: 'modal-actions' },
            h('button', { type: 'button', class: 'btn secondary', onclick: () => modal.remove() }, 'Cancel'),
            h('button', { type: 'submit', class: 'btn primary' }, 'Dispatch Production Cascade')))));

    document.body.append(modal);
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

    const payoutInput = h('input', { type: 'number', class: 'input', min: 0, step: 5, value: 75, placeholder: '75' });
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
                payout_amount: parseFloat(payoutInput.value) || 0,
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
          h('div', { class: 'installer-form-row' },
            field('Freelancer Payout ($)', payoutInput, 'Earned upon task delivery and signoff')),
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
