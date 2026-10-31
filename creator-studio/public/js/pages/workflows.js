// Workflows: automations as a vertical chain of nodes on a canvas (trigger → steps), built like
// the reference builder: dotted canvas, sticky node search, "+" between steps, a ⋯ menu per
// node (edit, test, add after, duplicate, move, disable, delete), a settings panel, zoom
// controls, keyboard shortcuts and undo. Saves as you go; Test runs everything with sample data
// and never sends.
import { h, api, busy, toast, pageHead, clear, timeAgo, modal, confirmDialog, link, navigate, emptyState, debounce } from '../core.js';
import { icon } from '../icons.js';
import { PLATFORM_ICONS } from './social.js';

const NET_LABEL = { linkedin: 'LinkedIn', instagram: 'Instagram', x: 'X', tiktok: 'TikTok', threads: 'Threads', youtube: 'YouTube' };
const VARS = ['{author}', '{name}', '{text}', '{platform}', '{post_title}', '{post_url}', '{post_text}'];
const RUN_PILL = { done: ['ok', 'Done'], waiting: ['late', 'Waiting'], queued: ['new', 'Queued'], running: ['new', 'Running'], stopped: ['', 'Stopped'], failed: ['bad', 'Failed'] };

// ---------------------------------------------------------------- shared helpers

function valueOf(def, node, key) {
  const f = def.fields.find((x) => x.key === key);
  const v = node.config?.[key];
  return v === undefined || v === null || v === '' ? f?.default : v;
}

function configured(def, node) {
  return def.fields.every((f) => {
    const needed = f.required || (f.required_if && valueOf(def, node, f.required_if[0]) === f.required_if[1]);
    if (!needed) return true;
    const v = valueOf(def, node, f.key);
    return !(v === undefined || v === null || v === '' || (Array.isArray(v) && !v.length));
  });
}

function optionLabel(def, key, value) {
  const f = def.fields.find((x) => x.key === key);
  return f?.options?.find((o) => String(o[0]) === String(value))?.[1] ?? value;
}

/** The one-line description under a node's title. */
function summary(def, node, agents) {
  if (!configured(def, node)) return 'Not configured yet';
  const short = (s, n = 70) => { s = String(s || '').replace(/\s+/g, ' ').trim(); return s.length > n ? `${s.slice(0, n - 1)}…` : s; };
  const map = {
    keywords: () => (valueOf(def, node, 'keywords') || []).map((k) => `“${k}”`).join(' or '),
    platforms: () => { const p = valueOf(def, node, 'platforms') || []; return p.length ? p.map((x) => NET_LABEL[x]).join(', ') : 'every network'; },
    multiplier: () => valueOf(def, node, 'multiplier'),
    every: () => optionLabel(def, 'every', valueOf(def, node, 'every')) + (valueOf(def, node, 'every') === 'week' ? ` on ${optionLabel(def, 'weekday', valueOf(def, node, 'weekday'))}` : ''),
    time: () => valueOf(def, node, 'time'),
    field: () => optionLabel(def, 'field', valueOf(def, node, 'field')),
    op: () => optionLabel(def, 'op', valueOf(def, node, 'op')),
    value: () => `“${valueOf(def, node, 'value')}”`,
    amount: () => valueOf(def, node, 'amount'),
    unit: () => valueOf(def, node, 'unit'),
    message: () => short(valueOf(def, node, 'message')),
    as: () => valueOf(def, node, 'as'),
    agent_name: () => agents.find((a) => String(a.id) === String(valueOf(def, node, 'agent_id')))?.name || 'the agent',
    'text|instructions': () => (valueOf(def, node, 'mode') === 'template' ? `“${short(valueOf(def, node, 'text'), 60)}”` : `AI in your voice${valueOf(def, node, 'instructions') ? ` (${short(valueOf(def, node, 'instructions'), 40)})` : ''}`),
  };
  let s = def.summary.replace(/\{([a-z_|]+)\}/g, (_, k) => (map[k] ? map[k]() : ''));
  if (def.fields.some((f) => f.key === 'platforms') && !def.summary.includes('{platforms}')) {
    const p = valueOf(def, node, 'platforms') || [];
    if (p.length) s += ` · ${p.map((x) => NET_LABEL[x]).join(', ')}`;
  }
  return s;
}

function toneIcon(def, size = 16) {
  return h('span', { class: `wf-ico wf-tone-${def.tone}`, 'aria-hidden': 'true' }, icon(def.icon, size));
}

// ---------------------------------------------------------------- list

export async function renderWorkflows(view) {
  const d = await api('GET', 'workflows');
  const cat = d.catalog;

  const create = (body) => api('POST', 'workflows', body).then((r) => navigate(`/workflows/${r.workflow.id}`));
  view.append(pageHead('Workflows', 'Automations that run on their own: when something happens (a comment, a DM, a post going live or taking off, a time of day), do these steps. Build them on the canvas; Test tries them with sample data and never sends anything.',
    h('button', { class: 'btn primary', onclick: (e) => busy(e.currentTarget, 'Creating…', () => create({ name: 'Untitled workflow' })) }, icon('plus', 15), 'New workflow')));

  if (d.workflows.length) {
    view.append(h('div', { class: 'wf-list' }, d.workflows.map((w) => {
      const trig = cat[w.trigger];
      const toggle = h('input', {
        type: 'checkbox', checked: w.active, 'aria-label': `${w.name} on/off`, onclick: (e) => e.stopPropagation(),
        onchange: async (e) => {
          try {
            await api('PUT', `workflows/${w.id}`, { active: e.target.checked });
            toast(e.target.checked ? `“${w.name}” is on.` : `“${w.name}” is off.`);
          } catch (err) { e.target.checked = !e.target.checked; toast(err.message, 'err', 9000); }
        },
      });
      return h('div', { class: `card wf-card${w.active ? ' on' : ''}` },
        link(`/workflows/${w.id}`, { class: 'wf-card-main' },
          h('div', { class: 'wf-chain', 'aria-hidden': 'true' }, [trig, ...w.steps.map((s) => cat[s])].filter(Boolean).slice(0, 6).map((def, i) => [i ? h('span', { class: 'wf-chain-line' }) : null, toneIcon(def, 14)])),
          h('div', {}, h('h2', {}, w.name),
            h('p', { class: 'muted small' }, trig ? trig.label : '', ' · ', `${w.steps.length} step${w.steps.length === 1 ? '' : 's'}`, ' · ',
              w.runs_count ? `${w.runs_count} run${w.runs_count === 1 ? '' : 's'}, last ${timeAgo(w.last_run_at)}` : 'not run yet',
              w.waiting ? ` · ${w.waiting} waiting` : ''),
            w.problems.length ? h('p', { class: 'small wf-warn-text' }, icon('x', 12), w.problems[0]) : null,
            w.failed_recent ? h('p', { class: 'small error-text' }, `${w.failed_recent} failed run${w.failed_recent === 1 ? '' : 's'} this week`) : null)),
        h('div', { class: 'row wf-card-actions' },
          h('label', { class: 'switch', title: w.active ? 'On' : 'Off' }, toggle, h('span', { class: 'slider' })),
          h('button', { class: 'btn ghost sm', title: 'Duplicate', 'aria-label': 'Duplicate', onclick: async () => { await api('POST', `workflows/${w.id}/duplicate`); navigate('/workflows'); } }, icon('copy', 14)),
          h('button', {
            class: 'btn ghost sm', title: 'Delete', 'aria-label': 'Delete', onclick: async () => {
              if (!(await confirmDialog(`Delete “${w.name}” and its run history?`))) return;
              await api('DELETE', `workflows/${w.id}`);
              navigate('/workflows');
            },
          }, icon('trash', 14))));
    })));
  }

  view.append(h('div', { class: 'card' },
    h('div', { class: 'card-header' }, h('h2', {}, d.workflows.length ? 'Start from a template' : 'Start with one of these'), h('span', { class: 'muted small' }, 'Everything is editable; nothing runs until you turn it on.')),
    h('div', { class: 'wf-templates' }, d.templates.map((t) => h('button', {
      class: 'wf-template', onclick: (e) => busy(e.currentTarget, 'Creating…', () => create({ template: t.id })),
    },
    h('div', { class: 'wf-chain', 'aria-hidden': 'true' }, t.nodes.map((n, i) => [i ? h('span', { class: 'wf-chain-line' }) : null, toneIcon(cat[n.type], 13)])),
    h('strong', {}, t.name), h('span', { class: 'muted small' }, t.desc))))));

  if (!d.workflows.length) view.append(emptyState(icon('flow', 28), 'No workflows yet', 'Pick a template above, or start from scratch.'));
}

// ---------------------------------------------------------------- builder

export async function renderWorkflow(view, id) {
  let d = await api('GET', `workflows/${id}`);
  const cat = d.catalog;
  const agents = d.agents;
  const wf = d.workflow;
  let nodes = structuredClone(wf.nodes);
  let selected = null;
  let zoom = 1;
  let panel = null; // { kind: 'config' | 'add' | 'runs', key?, index?, replaceTrigger? }
  const undo = [];
  const redo = [];
  let searchHits = [];
  let searchAt = 0;
  document.body.classList.add('wf-full');

  // ---- save ----
  const saveState = h('span', { class: 'wf-save muted small', 'aria-live': 'polite' }, 'Saved');
  let saving = Promise.resolve();
  const save = debounce(() => {
    saveState.textContent = 'Saving…';
    saving = api('PUT', `workflows/${wf.id}`, { name: nameInput.value.trim() || 'Untitled workflow', nodes })
      .then((r) => {
        const wasOn = wf.active;
        const { nodes: _saved, ...meta } = r.workflow; // the builder's nodes stay the source of truth
        Object.assign(wf, meta);
        saveState.textContent = 'Saved';
        if (wasOn && !wf.active) toast('Turned off: a step isn’t set up yet. Finish it and switch it back on.', 'err', 9000);
        drawBar();
      })
      .catch((e) => { saveState.textContent = 'Not saved'; toast(e.message, 'err', 8000); });
  }, 600);
  function changed({ history = true } = {}) {
    if (history) { undo.push(JSON.stringify(nodesBefore)); if (undo.length > 60) undo.shift(); redo.length = 0; }
    nodesBefore = structuredClone(nodes);
    saveState.textContent = 'Unsaved';
    save();
    draw();
  }
  let nodesBefore = structuredClone(nodes);
  function restore(from, to) {
    if (!from.length) return;
    to.push(JSON.stringify(nodes));
    nodes = JSON.parse(from.pop());
    if (selected && !nodes.some((n) => n.key === selected)) { selected = null; closePanel(); }
    changed({ history: false });
    if (panel?.kind === 'config') openConfig(panel.key);
  }

  // ---- top bar ----
  const nameInput = h('input', { class: 'wf-name', value: wf.name, maxlength: 120, 'aria-label': 'Workflow name', oninput: () => { saveState.textContent = 'Unsaved'; save(); } });
  const bar = h('div', { class: 'wf-bar' });
  function drawBar() {
    const problems = wf.problems || [];
    const activeInput = h('input', {
      type: 'checkbox', checked: wf.active, 'aria-label': 'Workflow on/off', onchange: async (e) => {
        await saving;
        try {
          const r = await api('PUT', `workflows/${wf.id}`, { active: e.target.checked });
          Object.assign(wf, r.workflow);
          toast(wf.active ? 'On: it runs by itself from now on.' : 'Off.');
        } catch (err) { e.target.checked = !e.target.checked; toast(err.message, 'err', 9000); }
        drawBar();
      },
    });
    clear(bar).append(
      link('/workflows', { class: 'btn ghost sm', 'aria-label': 'All workflows' }, icon('back', 15)),
      nameInput, saveState,
      problems.length ? h('button', { class: 'btn ghost sm wf-problems', title: problems.join('\n'), onclick: () => toast(problems.join(' '), 'err', 9000) }, icon('x', 13), `${problems.length} to fix`) : null,
      h('span', { class: 'grow' }),
      h('button', { class: 'btn ghost sm', title: 'Undo (Ctrl+Z)', 'aria-label': 'Undo', disabled: !undo.length, onclick: () => restore(undo, redo) }, icon('back', 14)),
      h('button', { class: 'btn ghost sm wf-redo', title: 'Redo (Ctrl+Shift+Z)', 'aria-label': 'Redo', disabled: !redo.length, onclick: () => restore(redo, undo) }, icon('back', 14)),
      h('button', { class: `btn ghost sm${panel?.kind === 'runs' ? ' active' : ''}`, onclick: () => (panel?.kind === 'runs' ? closePanel() : openRuns()) }, icon('history', 14), 'Runs', d.runs.length ? h('span', { class: 'count' }, d.runs.length) : null),
      nodes[0]?.type === 'manual' ? h('button', { class: 'btn sm', onclick: (e) => busy(e.currentTarget, 'Running…', () => runNow(false)) }, icon('play', 14), 'Run now') : null,
      h('button', { class: 'btn sm', title: 'Runs every step with sample data. Nothing is sent, published or saved.', onclick: (e) => busy(e.currentTarget, 'Testing…', () => runNow(true)) }, icon('sparkle', 14), 'Test'),
      h('label', { class: 'row wf-onoff' }, h('span', { class: 'switch' }, activeInput, h('span', { class: 'slider' })), h('span', { class: 'small' }, wf.active ? 'On' : 'Off')));
  }

  async function runNow(dry) {
    await saving;
    const r = await api('POST', `workflows/${wf.id}/run`, { dry });
    d.runs.unshift(r.run);
    toast(r.run.status === 'failed' ? `Stopped: ${r.run.error}` : dry ? 'Test finished: see what each step would do.' : 'Done.', r.run.status === 'failed' ? 'err' : 'ok');
    openRuns(r.run.id);
  }

  // ---- canvas ----
  const container = h('div', { class: 'wf-nodes', id: 'wf-nodes' });
  const searchInput = h('input', { type: 'text', placeholder: 'Search nodes…', 'aria-label': 'Search nodes', autocomplete: 'off', 'data-slash-search': '' });
  const searchCount = h('span', { class: 'wf-search-count' });
  const searchNav = h('div', { class: 'wf-search-nav', hidden: true }, searchCount,
    h('button', { type: 'button', title: 'Previous (↑)', 'aria-label': 'Previous match', onclick: () => jump(-1) }, icon('back', 11)),
    h('button', { type: 'button', class: 'wf-next', title: 'Next (↓)', 'aria-label': 'Next match', onclick: () => jump(1) }, icon('back', 11)));
  const search = h('div', { class: 'wf-search' }, icon('search', 13), searchInput, searchNav, h('kbd', {}, '/'));
  const zoomLabel = h('button', { type: 'button', class: 'wf-zoom-label', title: 'Reset zoom', onclick: () => setZoom(1) }, '100%');
  const zoomCtrls = h('div', { class: 'wf-zoom' },
    h('button', { type: 'button', title: 'Zoom out', 'aria-label': 'Zoom out', onclick: () => setZoom(zoom - 0.1) }, h('span', { 'aria-hidden': 'true' }, '−')),
    zoomLabel,
    h('button', { type: 'button', title: 'Zoom in', 'aria-label': 'Zoom in', onclick: () => setZoom(zoom + 0.1) }, h('span', { 'aria-hidden': 'true' }, '+')),
    h('span', { class: 'wf-zoom-sep' }),
    h('button', { type: 'button', title: 'Fit to screen', 'aria-label': 'Fit to screen', onclick: fit }, icon('eye', 12)));
  const canvas = h('main', { class: 'wf-canvas', tabindex: '0', 'aria-label': 'Workflow canvas' }, h('div', { class: 'wf-layer' }, search, container), zoomCtrls);
  const side = h('aside', { class: 'wf-panel', hidden: true, 'aria-label': 'Step settings' });

  function setZoom(z) {
    zoom = Math.round(Math.min(1.5, Math.max(0.5, z)) * 10) / 10;
    container.style.transform = `scale(${zoom})`;
    zoomLabel.textContent = `${Math.round(zoom * 100)}%`;
  }
  function fit() {
    const natural = container.scrollHeight / zoom;
    setZoom(Math.min(1, Math.max(0.5, Math.floor(((canvas.clientHeight - 60) / natural) * 10) / 10)));
    canvas.scrollTo({ top: 0, behavior: 'smooth' });
  }

  // search
  searchInput.addEventListener('input', () => runSearch());
  searchInput.addEventListener('keydown', (e) => {
    if (e.key === 'Enter' || e.key === 'ArrowDown') { e.preventDefault(); jump(1); }
    if (e.key === 'ArrowUp') { e.preventDefault(); jump(-1); }
    if (e.key === 'Escape') { searchInput.value = ''; runSearch(); canvas.focus(); }
  });
  function runSearch() {
    const q = searchInput.value.trim().toLowerCase();
    searchHits = q ? nodes.filter((n) => `${cat[n.type].label} ${summary(cat[n.type], n, agents)}`.toLowerCase().includes(q)).map((n) => n.key) : [];
    searchAt = 0;
    searchNav.hidden = !q;
    search.classList.toggle('active', !!q);
    searchCount.textContent = q ? (searchHits.length ? `1/${searchHits.length}` : 'No match') : '';
    draw();
    if (searchHits.length) container.querySelector(`[data-node-key="${searchHits[0]}"]`)?.scrollIntoView({ block: 'center', behavior: 'smooth' });
  }
  function jump(dir) {
    if (!searchHits.length) return;
    searchAt = (searchAt + dir + searchHits.length) % searchHits.length;
    searchCount.textContent = `${searchAt + 1}/${searchHits.length}`;
    draw();
    container.querySelector(`[data-node-key="${searchHits[searchAt]}"]`)?.scrollIntoView({ block: 'center', behavior: 'smooth' });
  }

  // ---- node operations ----
  const idx = (key) => nodes.findIndex((n) => n.key === key);
  function newKey(kind) { let i = 1; while (nodes.some((n) => n.key === `${kind}_${i}`)) i++; return `${kind}_${i}`; }
  function defaults(def) { const c = {}; for (const f of def.fields) if (f.default !== undefined) c[f.key] = f.default; return c; }
  function insert(type, at) {
    const def = cat[type];
    const node = { key: newKey(def.kind), type, config: defaults(def), enabled: true };
    nodes.splice(at, 0, node);
    selected = node.key;
    changed();
    openConfig(node.key);
  }
  function replaceTrigger(type) {
    nodes[0] = { key: nodes[0].key, type, config: defaults(cat[type]), enabled: true };
    selected = nodes[0].key;
    changed();
    openConfig(nodes[0].key);
  }
  function duplicate(key) {
    const i = idx(key);
    if (i < 1) return;
    const copy = { ...structuredClone(nodes[i]), key: newKey(cat[nodes[i].type].kind) };
    nodes.splice(i + 1, 0, copy);
    selected = copy.key;
    changed();
  }
  function move(key, dir) {
    const i = idx(key);
    const j = i + dir;
    if (i < 1 || j < 1 || j >= nodes.length) return;
    [nodes[i], nodes[j]] = [nodes[j], nodes[i]];
    changed();
    container.querySelector(`[data-node-key="${key}"]`)?.focus();
  }
  function remove(key) {
    const i = idx(key);
    if (i < 1) return;
    nodes.splice(i, 1);
    selected = nodes[Math.min(i, nodes.length - 1)]?.key ?? null;
    if (panel?.key === key) closePanel();
    changed();
    toast('Step deleted. Ctrl+Z brings it back.');
  }
  function toggleEnabled(key) {
    const n = nodes[idx(key)];
    if (!n || idx(key) < 1) return;
    n.enabled = !(n.enabled ?? true);
    changed();
  }
  async function testStep(key) {
    await saving;
    const r = await api('POST', `workflows/${wf.id}/test-step`, { key });
    toast(r.message, r.ok ? 'ok' : 'err', 10000);
    return r;
  }

  // ---- menu ----
  let openMenu = null;
  function closeMenu() {
    if (!openMenu) return;
    openMenu.menu.remove();
    openMenu.btn.setAttribute('aria-expanded', 'false');
    openMenu = null;
  }
  function menuFor(node, btn) {
    if (openMenu?.btn === btn) { closeMenu(); return; }
    closeMenu();
    const i = idx(node.key);
    const isTrigger = i === 0;
    const item = (label, iconName, fn, { kbd, danger, disabled, dir } = {}) => h('button', {
      type: 'button', role: 'menuitem', class: `wf-menu-item${danger ? ' danger' : ''}`, disabled,
      onclick: (e) => { e.stopPropagation(); closeMenu(); fn(); },
    }, iconName ? h('span', { class: dir ? `wf-menu-ico ${dir}` : 'wf-menu-ico' }, icon(iconName, 14)) : h('span', { class: 'wf-menu-gap' }), h('span', { class: 'wf-menu-label' }, label), kbd ? h('kbd', {}, kbd) : null);
    const sep = () => h('div', { class: 'wf-menu-sep', role: 'separator' });
    const head = (t) => h('p', { class: 'wf-menu-head' }, t);
    const menu = h('div', { class: 'wf-menu', role: 'menu', onclick: (e) => e.stopPropagation() },
      item('Edit', 'pen', () => openConfig(node.key), { kbd: '↵' }),
      isTrigger ? [item('Change trigger', 'repeat', () => openAdd(0, true)), sep(),
        item('Reset trigger', 'x', async () => { if (await confirmDialog('Reset the trigger’s settings?', { ok: 'Reset' })) { nodes[0].config = defaults(cat[nodes[0].type]); changed(); } }, { danger: true, kbd: '⌫' })] : [
        item('Test step', 'sparkle', () => testStep(node.key)),
        sep(), head('Arrange'),
        item('Add step after', 'plus', () => openAdd(i + 1)),
        item('Duplicate', 'copy', () => duplicate(node.key), { kbd: 'D' }),
        item('Move up', 'arrow', () => move(node.key, -1), { kbd: 'Alt ↑', disabled: i <= 1, dir: 'up' }),
        item('Move down', 'arrow', () => move(node.key, 1), { kbd: 'Alt ↓', disabled: i >= nodes.length - 1, dir: 'down' }),
        sep(), head('Step'),
        item(node.enabled === false ? 'Enable' : 'Disable', node.enabled === false ? 'play' : 'pause', () => toggleEnabled(node.key)),
        sep(),
        item('Delete step', 'trash', () => remove(node.key), { danger: true, kbd: '⌫' })]);
    btn.parentElement.append(menu);
    btn.setAttribute('aria-expanded', 'true');
    openMenu = { menu, btn };
    requestAnimationFrame(() => menu.classList.add('open'));
    menu.querySelector('.wf-menu-item:not([disabled])')?.focus();
    menu.addEventListener('keydown', (e) => {
      const items = [...menu.querySelectorAll('.wf-menu-item:not([disabled])')];
      const at = items.indexOf(document.activeElement);
      if (e.key === 'ArrowDown') { e.preventDefault(); items[(at + 1) % items.length].focus(); }
      if (e.key === 'ArrowUp') { e.preventDefault(); items[(at - 1 + items.length) % items.length].focus(); }
      if (e.key === 'Escape') { e.stopPropagation(); closeMenu(); btn.focus(); }
    });
  }
  const onDocClick = () => closeMenu();
  document.addEventListener('click', onDocClick);

  // ---- draw ----
  function addButton(at, last) {
    return h('button', { type: 'button', class: `wf-add${last ? ' last' : ''}`, title: 'Add step', 'aria-label': 'Add a step here', onclick: (e) => { e.stopPropagation(); openAdd(at); } },
      h('span', { class: 'wf-add-line' }), h('span', { class: 'wf-add-dot' }, icon('plus', 12)), last ? null : h('span', { class: 'wf-add-line' }));
  }
  function draw() {
    let order = 0;
    clear(container).append(nodes.map((n, i) => {
      const def = cat[n.type];
      const ok = configured(def, n);
      const off = n.enabled === false;
      if (i > 0 && !off) order++;
      const hit = searchHits.includes(n.key);
      const menuBtn = h('button', {
        type: 'button', class: 'wf-node-menu', 'aria-label': 'Step options', 'aria-haspopup': 'true', 'aria-expanded': 'false',
        onclick: (e) => { e.stopPropagation(); menuFor(n, e.currentTarget); },
      }, icon('dots', 13));
      const el = h('div', {
        class: `wf-node${selected === n.key ? ' selected' : ''}${off ? ' off' : ''}${hit ? ' hit' : ''}${hit && searchHits[searchAt] === n.key ? ' hit-now' : ''}${i === 0 ? ' trigger' : ''}`,
        tabindex: '0', role: 'button', 'aria-label': `${i === 0 ? 'Trigger' : `Step ${order}`}: ${def.label}. ${summary(def, n, agents)}`,
        'data-node-key': n.key, 'data-node-type': n.type,
        onclick: () => { selected = n.key; openConfig(n.key); },
        onfocus: () => { if (selected !== n.key) { selected = n.key; markSelected(); } },
      },
      h('div', { class: 'wf-node-head' },
        toneIcon(def, 16),
        h('p', { class: 'wf-node-title' }, def.label),
        ok ? null : h('span', { class: 'wf-node-warn', title: 'Not configured yet' }, icon('alert', 14)),
        off ? h('span', { class: 'wf-node-off' }, 'Off') : null,
        i > 0 && !off ? h('span', { class: 'wf-node-order', title: 'Execution order' }, order) : null,
        h('div', { class: 'wf-node-menu-wrap' }, menuBtn)),
      h('div', { class: 'wf-node-body' }, h('p', {}, i === 0 && !ok ? 'Choose when this runs' : summary(def, n, agents))));
      return h('div', { class: 'wf-step' }, el, addButton(i + 1, i === nodes.length - 1));
    }));
    drawBar();
  }
  function markSelected() {
    for (const el of container.querySelectorAll('.wf-node')) el.classList.toggle('selected', el.dataset.nodeKey === selected);
  }

  // ---- side panels ----
  function closePanel() {
    panel = null;
    side.hidden = true;
    clear(side);
    canvas.classList.remove('with-panel');
    drawBar();
  }
  function showPanel(title, sub, body, headIcon) {
    side.hidden = false;
    canvas.classList.add('with-panel');
    clear(side).append(
      h('div', { class: 'wf-panel-head' }, headIcon || null, h('div', { class: 'grow' }, h('h2', {}, title), sub ? h('p', { class: 'muted small' }, sub) : null),
        h('button', { class: 'btn ghost sm', 'aria-label': 'Close panel', onclick: closePanel }, icon('x', 15))),
      h('div', { class: 'wf-panel-body' }, body));
    drawBar();
  }

  function openAdd(at, replace = false) {
    panel = { kind: 'add', index: at, replaceTrigger: replace };
    const q = h('input', { type: 'search', placeholder: replace ? 'Search triggers…' : 'Search steps…', 'aria-label': 'Search steps' });
    const list = h('div', { class: 'wf-add-list' });
    const event = cat[nodes[0].type]?.event;
    const groups = replace ? [['trigger', 'Triggers']] : [['action', 'Actions'], ['logic', 'Logic']];
    const draw = () => {
      const term = q.value.trim().toLowerCase();
      clear(list).append(groups.map(([kind, title]) => {
        const items = Object.entries(cat).filter(([, def]) => def.kind === kind && `${def.label} ${def.summary}`.toLowerCase().includes(term));
        if (!items.length) return null;
        return [h('p', { class: 'wf-add-group' }, title), items.map(([type, def]) => {
          const blocked = (def.needs === 'item' && !['comment', 'dm'].includes(event)) || (def.needs === 'post' && !['post_published', 'post_performs'].includes(event));
          return h('button', {
            type: 'button', class: 'wf-add-item', disabled: blocked,
            title: blocked ? (def.needs === 'item' ? 'Needs a comment or DM trigger' : 'Needs a post trigger') : '',
            onclick: () => (replace ? replaceTrigger(type) : insert(type, at)),
          }, toneIcon(def, 15), h('span', {}, h('strong', {}, def.label), h('span', { class: 'muted small' },
            blocked ? (def.needs === 'item' ? 'Needs a comment or DM trigger' : 'Needs a post trigger') : def.summary.replace(/\{[^}]+\}/g, '…'))));
        })];
      }));
    };
    q.addEventListener('input', draw);
    q.addEventListener('keydown', (e) => { if (e.key === 'Enter') list.querySelector('.wf-add-item:not([disabled])')?.click(); });
    draw();
    showPanel(replace ? 'Change trigger' : 'Add a step', replace ? 'What starts this workflow' : `Goes in position ${at}`, [q, list]);
    q.focus();
  }

  function openConfig(key) {
    const node = nodes[idx(key)];
    if (!node) return;
    selected = key;
    markSelected();
    panel = { kind: 'config', key };
    const def = cat[node.type];
    const form = h('div', { class: 'wf-form' });
    let pushed = false;
    const set = (k, v) => {
      if (!pushed) { undo.push(JSON.stringify(nodesBefore)); redo.length = 0; pushed = true; }
      node.config = { ...(node.config || {}), [k]: v };
      nodesBefore = structuredClone(nodes);
      saveState.textContent = 'Unsaved';
      save();
      draw();
    };
    const insertVar = (input, v) => {
      const s = input.selectionStart ?? input.value.length;
      input.value = input.value.slice(0, s) + v + input.value.slice(input.selectionEnd ?? s);
      input.dispatchEvent(new Event('input'));
      input.focus();
    };
    const drawForm = () => {
      clear(form).append(def.fields.filter((f) => !f.show_if || valueOf(def, node, f.show_if[0]) === f.show_if[1]).map((f) => {
        const v = valueOf(def, node, f.key);
        const id = `wf-f-${node.key}-${f.key}`;
        let input;
        if (f.type === 'select') {
          input = h('select', { id, onchange: (e) => { set(f.key, e.target.value); drawForm(); } }, f.options.map(([val, label]) => h('option', { value: val, selected: String(v) === String(val) }, label)));
        } else if (f.type === 'agent') {
          input = agents.length
            ? h('select', { id, onchange: (e) => set(f.key, Number(e.target.value)) }, h('option', { value: '' }, 'Pick an agent'), agents.map((a) => h('option', { value: a.id, selected: String(v) === String(a.id) }, a.name)))
            : h('p', { class: 'muted small' }, 'No agents yet. ', link('/agents', {}, 'Create one'), ' first.');
        } else if (f.type === 'multi') {
          const sel = new Set(v || []);
          input = h('div', { class: 'wz-chips', id, role: 'group' }, f.options.map(([val, label]) => h('button', {
            type: 'button', class: `wz-chip pf-${val}${sel.has(val) ? ' on' : ''}`, 'aria-pressed': String(sel.has(val)),
            onclick: () => { sel.has(val) ? sel.delete(val) : sel.add(val); set(f.key, [...sel]); drawForm(); },
          }, PLATFORM_ICONS[val] ? icon(PLATFORM_ICONS[val], 14) : null, label)));
        } else if (f.type === 'keywords') {
          input = h('input', { id, type: 'text', value: (v || []).join(', '), placeholder: f.placeholder || '',
            oninput: (e) => set(f.key, e.target.value.split(',').map((x) => x.trim()).filter(Boolean)) });
        } else if (f.type === 'textarea') {
          input = h('textarea', { id, rows: 3, placeholder: f.placeholder || '', oninput: (e) => set(f.key, e.target.value) });
          input.value = v ?? '';
        } else {
          input = h('input', { id, type: f.type === 'number' ? 'number' : f.type === 'time' ? 'time' : 'text', value: v ?? '', placeholder: f.placeholder || '',
            min: f.min, max: f.max, step: f.step, oninput: (e) => set(f.key, f.type === 'number' ? (e.target.value === '' ? '' : Number(e.target.value)) : e.target.value) });
        }
        const missing = (f.required || (f.required_if && valueOf(def, node, f.required_if[0]) === f.required_if[1])) && (v === undefined || v === null || v === '' || (Array.isArray(v) && !v.length));
        return h('div', { class: `field${missing ? ' wf-missing' : ''}` },
          h('label', { for: id }, f.label, f.required ? h('span', { class: 'wf-req', title: 'Required' }, ' *') : null, f.help ? h('span', { class: 'hint' }, ` ${f.help}`) : null),
          input,
          ['textarea', 'text'].includes(f.type) && (def.kind === 'action' || f.key === 'value') ? h('div', { class: 'wf-vars' }, VARS.map((x) => h('button', { type: 'button', class: 'wf-var', onclick: () => insertVar(input, x) }, x))) : null);
      }));
      if (!def.fields.length) form.append(h('p', { class: 'muted small' }, 'Nothing to set up: press Run now in the top bar to start it.'));
    };
    drawForm();
    const result = h('div', { class: 'wf-test-result', hidden: true });
    const i = idx(key);
    const actions = h('div', { class: 'row wf-panel-actions' },
      i > 0 ? h('button', {
        class: 'btn sm', onclick: (e) => busy(e.currentTarget, 'Testing…', async () => {
          const r = await testStep(key);
          result.hidden = false;
          result.className = `wf-test-result ${r.ok ? 'ok' : 'bad'}`;
          result.textContent = r.message;
        }),
      }, icon('sparkle', 14), 'Test step') : h('button', { class: 'btn sm', onclick: () => openAdd(0, true) }, icon('repeat', 14), 'Change trigger'),
      h('span', { class: 'grow' }),
      i > 0 ? h('button', { class: 'btn ghost sm danger-text', onclick: () => remove(key) }, icon('trash', 14), 'Delete') : null);
    showPanel(def.label, def.kind === 'trigger' ? 'Trigger · what starts this workflow' : def.kind === 'logic' ? 'Logic' : 'Action', [
      def.help ? h('p', { class: 'callout small' }, def.help) : null,
      form, actions, result,
    ], toneIcon(def, 18));
  }

  function openRuns(openId = null) {
    panel = { kind: 'runs' };
    const runs = d.runs;
    const body = runs.length ? h('div', { class: 'wf-runs' }, runs.map((r) => {
      const [tone, label] = RUN_PILL[r.status] || ['', r.status];
      const details = h('details', { class: 'wf-run', open: r.id === openId },
        h('summary', {}, h('span', { class: `pill ${tone}` }, label), r.dry_run ? h('span', { class: 'pill new' }, 'Test') : null,
          h('span', { class: 'muted small' }, timeAgo(r.created_at)), h('span', { class: 'wf-run-trig small clamp-1' }, r.log?.[0]?.message || '')),
        h('ol', { class: 'wf-run-log' }, (r.log || []).map((s) => h('li', { class: s.skipped ? 'skipped' : s.ok ? 'ok' : 'bad' },
          h('span', { class: 'wf-run-dot', 'aria-hidden': 'true' }, s.ok ? icon(s.skipped ? 'pause' : 'check', 11) : icon('x', 11)),
          h('div', {}, h('strong', { class: 'small' }, cat[s.type]?.label || s.type), h('p', { class: 'small' }, s.message))))),
        r.status === 'waiting' && r.resume_at ? h('p', { class: 'muted small' }, `Continues ${new Date(r.resume_at).toLocaleString()}`) : null);
      return details;
    })) : h('p', { class: 'muted small' }, 'No runs yet. Press Test to try it with sample data, or switch it on.');
    showPanel('Runs', 'The last 30, newest first', [
      h('button', { class: 'btn ghost sm', onclick: async () => { d = await api('GET', `workflows/${id}`); openRuns(); } }, icon('refresh', 13), 'Refresh'),
      body]);
  }

  // ---- keyboard ----
  function typing(e) { return ['INPUT', 'TEXTAREA', 'SELECT'].includes(e.target.tagName) || e.target.isContentEditable; }
  const onKey = (e) => {
    if (!view.isConnected) return;
    if (document.querySelector('.modal-overlay')) return;
    const mod = e.ctrlKey || e.metaKey;
    if (mod && e.key.toLowerCase() === 'z' && !typing(e)) { e.preventDefault(); e.shiftKey ? restore(redo, undo) : restore(undo, redo); return; }
    if (mod && e.key.toLowerCase() === 'y' && !typing(e)) { e.preventDefault(); restore(redo, undo); return; }
    if (typing(e)) { if (e.key === 'Escape') e.target.blur(); return; }
    if (e.key === '/') { e.preventDefault(); searchInput.focus(); return; }
    if (e.key === 'Escape') { if (openMenu) closeMenu(); else if (panel) closePanel(); return; }
    if (e.key === '+' || e.key === '=') { setZoom(zoom + 0.1); return; }
    if (e.key === '-') { setZoom(zoom - 0.1); return; }
    if (e.key === '0') { setZoom(1); return; }
    const i = selected ? idx(selected) : -1;
    if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
      if (e.altKey && i > 0) { e.preventDefault(); move(selected, e.key === 'ArrowUp' ? -1 : 1); return; }
      e.preventDefault();
      const j = Math.max(0, Math.min(nodes.length - 1, i + (e.key === 'ArrowDown' ? 1 : -1)));
      selected = nodes[j].key;
      container.querySelector(`[data-node-key="${selected}"]`)?.focus();
      return;
    }
    if (i < 0 || !e.target.closest?.('.wf-canvas')) return;
    if (e.key === 'Enter') { e.preventDefault(); openConfig(selected); }
    else if ((e.key === 'Backspace' || e.key === 'Delete') && i > 0) { e.preventDefault(); remove(selected); }
    else if (e.key.toLowerCase() === 'd' && !mod && i > 0) { e.preventDefault(); duplicate(selected); }
  };
  document.addEventListener('keydown', onKey);
  canvas.addEventListener('wheel', (e) => { if (e.ctrlKey) { e.preventDefault(); setZoom(zoom + (e.deltaY < 0 ? 0.1 : -0.1)); } }, { passive: false });

  view.classList.add('wf-view');
  view.append(bar, h('div', { class: 'wf-stage' }, canvas, side));
  draw();
  if (!nodes[0] || !configured(cat[nodes[0].type], nodes[0]) || nodes.length === 1) openConfig(nodes[0].key);
  canvas.focus({ preventScroll: true });

  return () => {
    document.removeEventListener('keydown', onKey);
    document.removeEventListener('click', onDocClick);
    document.body.classList.remove('wf-full');
    if (saveState.textContent === 'Unsaved') save.flush();
  };
}
