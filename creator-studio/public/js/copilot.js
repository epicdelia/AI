// Copilot: an agentic assistant panel available on every page.
// Markup follows the "Develop with AI" panel spec (sbcp-* / bw-tc-* classes, data-cp actions,
// data-testid hooks). Floating (draggable) or docked (resizable), maximize, Agent/Ask modes,
// model picker across every provider with a key, slash commands, file attachments, voice
// input, stop, history, projects and feedback. Talks to /api/copilot/* and streams agent steps.
import { h, api, apiStream, apiUpload, toast, clear, markdown, navigate, copyText } from './core.js';
import { noteOfTheDay, loveOn, hearts } from './love.js';

const MAX = 2000;
const ATTACH_TYPES = '.txt,.md,.csv,.json,.html,.htm,.css,.js,text/*';
const MAX_ATTACH = 5;
const MAX_ATTACH_CHARS = 200000;

// ------------------------------------------------------------------ icons (from the spec)
const SVG = {
  mark: '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m12 3-1.9 5.1L5 10l5.1 1.9L12 17l1.9-5.1L19 10l-5.1-1.9z"></path><path d="M19 15l.8 2.2L22 18l-2.2.8L19 21l-.8-2.2L16 18l2.2-.8z"></path></svg>',
  grip: '<svg viewBox="0 0 24 24" width="14" height="14" fill="currentColor" aria-hidden="true"><circle cx="9" cy="6" r="1.5"/><circle cx="15" cy="6" r="1.5"/><circle cx="9" cy="12" r="1.5"/><circle cx="15" cy="12" r="1.5"/><circle cx="9" cy="18" r="1.5"/><circle cx="15" cy="18" r="1.5"/></svg>',
  plus: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"></path><path d="M12 5v14"></path></svg>',
  dock: '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3.75" y="4.75" width="16.5" height="14.5" rx="2.5"></rect><path d="M9.25 4.75v14.5"></path></svg>',
  float: '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3.75" y="4.75" width="16.5" height="14.5" rx="2.5"></rect><rect x="11" y="11" width="7" height="6" rx="1.2"></rect></svg>',
  max: '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 3 21 3 21 9"></polyline><polyline points="9 21 3 21 3 15"></polyline><line x1="21" y1="3" x2="14" y2="10"></line><line x1="3" y1="21" x2="10" y2="14"></line></svg>',
  min: '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="4 14 10 14 10 20"></polyline><polyline points="20 10 14 10 14 4"></polyline><line x1="14" y1="10" x2="21" y2="3"></line><line x1="3" y1="21" x2="10" y2="14"></line></svg>',
  feedback: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2.992 16.342a2 2 0 0 1 .094 1.167l-1.065 3.29a1 1 0 0 0 1.236 1.168l3.413-.998a2 2 0 0 1 1.099.092 10 10 0 1 0-4.777-4.719"></path></svg>',
  close: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"></path><path d="m6 6 12 12"></path></svg>',
  attach: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5.75v12.5M5.75 12h12.5"></path></svg>',
  star: '<svg viewBox="0 0 24 24" width="14" height="14" fill="currentColor" aria-hidden="true"><path d="M12 2l2.4 6.6L21 11l-5.4 4.5L17 22l-5-3.8-5 3.8 1.4-6.5L3 11l6.6-2.4z"></path></svg>',
  caret: '<svg class="bw-tc-caret" viewBox="0 0 24 24" width="12" height="12" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2"><path d="m7 10 5 5 5-5"></path></svg>',
  mic: '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="9" y="3.75" width="6" height="10.5" rx="3"></rect><path d="M5.75 11a6.25 6.25 0 0 0 12.5 0M12 17.25v3"></path></svg>',
  stop: '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="7" y="7" width="10" height="10" rx="2"></rect></svg>',
  send: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 18.25V5.75M6.75 11L12 5.75 17.25 11"></path></svg>',
  history: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4.75 12a7.25 7.25 0 1 0 2.1-5.1M4.75 4.75v3.5h3.5M12 8.25V12l2.5 1.75"></path></svg>',
};
const svg = (name, cls) => h('span', { class: cls || 'sbcp-btn-ic', html: SVG[name] });

// ------------------------------------------------------------------ persisted UI state
const store = {
  get(k, d) { try { const v = localStorage.getItem(`sbcp:${k}`); return v === null ? d : JSON.parse(v); } catch { return d; } },
  set(k, v) { try { localStorage.setItem(`sbcp:${k}`, JSON.stringify(v)); } catch { /* private mode */ } },
};

// ------------------------------------------------------------------ page context
let pageContext = { path: '/' };
/** Pages call this (e.g. the script editor) so the Copilot knows what she's looking at. */
export function setCopilotContext(ctx) {
  pageContext = { path: location.pathname, ...ctx };
  ui?.renderChips();
}
/** Called by the router before each page renders. */
export function resetCopilotContext() {
  pageContext = { path: location.pathname };
  ui?.renderChips();
}
/** Called after a page rendered: use its heading as the context title unless the page set one. */
export function pageRendered(title) {
  if (!pageContext.title && title) pageContext = { ...pageContext, title };
  ui?.renderChips();
}

let ui = null;

export function mountCopilot() {
  if (ui) return ui;
  ui = new CopilotPanel();
  return ui;
}

export function unmountCopilot() {
  ui?.destroy();
  ui = null;
}

class CopilotPanel {
  constructor() {
    this.state = {
      open: store.get('open', false),
      position: store.get('position', 'floating'),
      dockWidth: store.get('dockWidth', 480),
      rect: store.get('rect', null),
      max: false,
      mode: store.get('mode', 'agent'),
      model: store.get('model', null),
      project: store.get('project', null),
      threadId: store.get('thread', null),
    };
    this.meta = null;
    this.attachments = [];
    this.dropContext = false;
    this.abort = null;
    this.busy = false;
    this.cmdIndex = 0;
    this.build();
    this.applyLayout();
    this.loadMeta();
    if (this.state.threadId) this.openThread(this.state.threadId, true); else this.renderEmpty();
    this.onKey = (e) => {
      if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'i') { e.preventDefault(); this.toggle(); }
    };
    document.addEventListener('keydown', this.onKey);
    this.onResize = () => this.applyLayout();
    window.addEventListener('resize', this.onResize);
  }

  destroy() {
    this.abort?.abort();
    document.removeEventListener('keydown', this.onKey);
    window.removeEventListener('resize', this.onResize);
    this.el.remove();
    this.launcher.remove();
    document.body.classList.remove('sbcp-docked-body');
  }

  // ------------------------------------------------------------ DOM
  build() {
    const icon = (cp, label, name, extra = {}) => h('button', { type: 'button', class: 'sbcp-icon', 'data-cp': cp, 'aria-label': label, title: label, ...extra }, svg(name));

    this.positionBtn = icon('position', 'Dock chat', 'dock');
    this.maxBtn = icon('maximize', 'Maximize', 'max');
    this.head = h('header', { class: 'sbcp-head' },
      h('button', { type: 'button', class: 'sbcp-drag', 'data-cp': 'drag', 'data-testid': 'develop-drag-handle', 'aria-label': 'Drag Copilot to reposition', title: 'Drag to reposition', html: `<i class="eicon-grip-vertical" aria-hidden="true">${SVG.grip}</i>` }),
      h('span', { class: 'sbcp-mark', 'aria-hidden': 'true', html: SVG.mark }),
      h('h2', { class: 'sbcp-title' }, 'Copilot'),
      h('span', { class: 'sbcp-chip' }, 'Beta'),
      h('span', { class: 'sbcp-end' },
        icon('new', 'New chat', 'plus'),
        this.positionBtn,
        this.maxBtn,
        icon('feedback', 'Send feedback', 'feedback'),
        icon('close', 'Close', 'close')));

    this.log = h('div', { class: 'sbcp-log', id: 'sbcp-log', 'data-testid': 'chat-transcript', role: 'log', 'aria-live': 'polite' });
    this.chips = h('div', { class: 'sbcp-chips', id: 'sbcp-chips' });
    this.input = h('textarea', { class: 'sbcp-input', 'data-testid': 'chat-input-textarea', rows: 1, maxlength: MAX, placeholder: 'Ask AI to build, rewrite or explain…', 'aria-label': 'Message the Copilot' });
    this.cmds = h('ul', { class: 'sbcp-cmds', role: 'listbox', hidden: true });
    this.file = h('input', { type: 'file', class: 'sbcp-file', accept: ATTACH_TYPES, multiple: true, hidden: true });
    this.modelLabel = h('span', { class: 'bw-tc-label' }, 'Default model');
    this.modelBtn = h('button', { type: 'button', class: 'bw-btn bw-btn-ghost bw-tc-provider', 'data-bw-tc-act': 'provider-menu', 'aria-haspopup': 'menu', 'aria-expanded': 'false', title: 'Which model answers' },
      h('i', { class: 'bw-tc-mark', 'aria-hidden': 'true', html: SVG.star }), this.modelLabel, h('span', { html: SVG.caret, class: 'bw-tc-caret-wrap' }));
    this.modelMenu = h('div', { class: 'bw-tc-menu', role: 'menu', hidden: true });
    this.modeBtns = ['agent', 'ask'].map((m) => h('button', {
      type: 'button', 'data-cp-mode': m, 'aria-pressed': String(this.state.mode === m),
      title: m === 'agent' ? 'Agent: can propose changes to the page' : 'Ask: answers only, never changes the page',
    }, m === 'agent' ? 'Agent' : 'Ask'));
    this.left = h('span', { class: 'sbcp-left', id: 'sbcp-left' });
    this.voiceBtn = h('button', { type: 'button', class: 'sbcp-icon sbcp-voice', 'data-cp': 'voice', 'aria-pressed': 'false', 'aria-label': 'Start voice recording', title: 'Start voice recording', html: SVG.mic });
    this.stopBtn = h('button', { type: 'button', class: 'sbcp-stop', 'data-cp': 'stop', 'aria-label': 'Stop response', title: 'Stop response', hidden: true, html: SVG.stop });
    this.sendBtn = h('button', { type: 'submit', class: 'sbcp-send', 'data-testid': 'chat-send-button', disabled: true, 'aria-label': 'Send', title: 'Send (Enter)', html: SVG.send });
    this.projectsBtn = h('button', { type: 'button', 'data-cp': 'projects', 'data-testid': 'develop-chat-row-projects-trigger' }, 'Projects');
    this.footPosBtn = h('button', { type: 'button', class: 'sbcp-foot-icon', 'data-cp': 'position', 'data-testid': 'develop-chat-row-position-toggle', 'aria-label': 'Dock chat', title: 'Dock chat', html: SVG.dock });

    this.form = h('form', { class: 'sbcp-form', novalidate: true },
      this.chips,
      h('div', { class: 'sbcp-box', 'data-testid': 'chat-input-card' },
        this.input,
        this.cmds,
        h('div', { class: 'sbcp-bar' },
          h('div', { class: 'sbcp-bar-l' },
            h('button', { type: 'button', class: 'sbcp-icon sbcp-attach', 'data-cp': 'attach', 'data-testid': 'chat-attachment-menu-trigger', 'aria-label': 'Attach files', title: 'Attach files', html: SVG.attach }),
            this.file,
            h('div', { class: 'sbcp-model', id: 'sbcp-model', 'data-testid': 'chat-model-picker' }, h('div', { class: 'bw-tc-anchor' }, this.modelBtn, this.modelMenu)),
            h('div', { class: 'sbcp-mode', role: 'group', 'aria-label': 'Mode' }, this.modeBtns)),
          h('div', { class: 'sbcp-bar-r' }, this.left, this.voiceBtn, this.stopBtn, this.sendBtn))),
      h('p', { class: 'sbcp-note' }, 'The Copilot can be wrong. Check what it writes before you publish.'),
      h('div', { class: 'sbcp-foot', 'data-testid': 'develop-chat-row' },
        this.projectsBtn,
        h('span', { class: 'sbcp-foot-end' },
          h('button', { type: 'button', class: 'sbcp-foot-icon', 'data-cp': 'history', 'data-testid': 'develop-chat-row-history-button', 'aria-label': 'History', title: 'History', html: SVG.history }),
          this.footPosBtn)));

    this.resizer = h('span', { class: 'sbcp-resize', 'data-testid': 'develop-resize-handle', role: 'separator', tabindex: '0', 'aria-label': 'Resize Develop panel' });
    this.overlay = h('div', { class: 'sbcp-overlay', hidden: true });

    this.el = h('aside', { class: 'sbcp', 'aria-label': 'Develop with AI', 'data-testid': 'develop-panel', 'data-position': this.state.position, 'data-vs-app-theme': document.documentElement.dataset.theme || 'light' },
      this.head, this.log, this.overlay, this.form, this.resizer);
    this.launcher = h('button', { type: 'button', class: 'sbcp-launch', 'aria-label': 'Open Copilot (Ctrl+I)', title: 'Copilot (Ctrl+I)', html: SVG.mark });

    document.body.append(this.el, this.launcher);
    this.wire();
  }

  wire() {
    this.launcher.addEventListener('click', () => this.toggle(true));
    this.el.addEventListener('click', (e) => {
      const btn = e.target.closest('[data-cp]');
      if (!btn || !this.el.contains(btn)) return;
      const act = btn.dataset.cp;
      if (act === 'new') this.newChat();
      else if (act === 'position') this.togglePosition();
      else if (act === 'maximize') this.toggleMax();
      else if (act === 'feedback') this.showFeedback();
      else if (act === 'close') this.toggle(false);
      else if (act === 'attach') this.file.click();
      else if (act === 'voice') this.toggleVoice();
      else if (act === 'stop') this.stop();
      else if (act === 'history') this.showHistory();
      else if (act === 'projects') this.showProjects();
    });
    for (const b of this.modeBtns) b.addEventListener('click', () => this.setMode(b.dataset.cpMode));
    this.modelBtn.addEventListener('click', (e) => { e.stopPropagation(); this.toggleModelMenu(); });
    document.addEventListener('click', (e) => { if (!this.modelMenu.hidden && !this.modelMenu.contains(e.target)) this.toggleModelMenu(false); });

    this.form.addEventListener('submit', (e) => { e.preventDefault(); this.send(); });
    this.input.addEventListener('input', () => { this.autosize(); this.updateCounter(); this.updateCmds(); });
    this.input.addEventListener('focus', () => this.renderChips());
    this.input.addEventListener('keydown', (e) => this.onInputKey(e));
    this.file.addEventListener('change', () => this.addFiles([...this.file.files]));
    this.el.addEventListener('dragover', (e) => { if (e.dataTransfer?.types?.includes('Files')) { e.preventDefault(); this.el.classList.add('is-drop'); } });
    this.el.addEventListener('dragleave', () => this.el.classList.remove('is-drop'));
    this.el.addEventListener('drop', (e) => { if (e.dataTransfer?.files?.length) { e.preventDefault(); this.el.classList.remove('is-drop'); this.addFiles([...e.dataTransfer.files]); } });

    this.wireDrag();
    this.wireResize();
  }

  // ------------------------------------------------------------ layout: open / dock / float / max
  toggle(open = !this.state.open) {
    this.state.open = open;
    store.set('open', open);
    this.applyLayout();
    if (open) { this.renderChips(); setTimeout(() => this.input.focus(), 50); }
  }

  togglePosition() {
    this.state.position = this.state.position === 'docked' ? 'floating' : 'docked';
    this.state.max = false;
    store.set('position', this.state.position);
    this.applyLayout();
  }

  toggleMax() {
    this.state.max = !this.state.max;
    this.applyLayout();
  }

  applyLayout() {
    const { open, position, dockWidth, max } = this.state;
    const el = this.el;
    el.hidden = !open;
    this.launcher.hidden = open;
    el.dataset.position = position;
    el.classList.toggle('is-floating', position === 'floating' && !max);
    el.classList.toggle('is-docked', position === 'docked' && !max);
    el.classList.toggle('is-max', max);
    el.dataset.dockWidth = String(dockWidth);
    el.style.setProperty('--sbcp-dock-width', `${dockWidth}px`);
    document.body.classList.toggle('sbcp-docked-body', open && position === 'docked' && !max && window.innerWidth > 900);
    document.body.style.setProperty('--sbcp-dock-width', `${dockWidth}px`);

    const docked = position === 'docked';
    for (const b of [this.positionBtn, this.footPosBtn]) {
      b.setAttribute('aria-label', docked ? 'Float chat' : 'Dock chat');
      b.title = docked ? 'Float chat' : 'Dock chat';
      b.innerHTML = '';
      b.append(b === this.positionBtn ? svg(docked ? 'float' : 'dock') : h('span', { html: SVG[docked ? 'float' : 'dock'] }));
    }
    this.maxBtn.setAttribute('aria-label', max ? 'Restore' : 'Maximize');
    this.maxBtn.title = max ? 'Restore' : 'Maximize';
    this.maxBtn.replaceChildren(svg(max ? 'min' : 'max'));

    if (position === 'floating' && !max) {
      const vw = window.innerWidth;
      const vh = window.innerHeight;
      const w = Math.min(this.state.rect?.w || 420, vw - 16);
      const hgt = Math.min(this.state.rect?.h || Math.min(640, vh - 96), vh - 16);
      const x = Math.max(8, Math.min(this.state.rect?.x ?? vw - w - 24, vw - w - 8));
      const y = Math.max(8, Math.min(this.state.rect?.y ?? vh - hgt - 24, vh - hgt - 8));
      Object.assign(el.style, { left: `${x}px`, top: `${y}px`, width: `${w}px`, height: `${hgt}px`, right: '', bottom: '' });
    } else {
      Object.assign(el.style, { left: '', top: '', width: '', height: '', right: '', bottom: '' });
    }
  }

  wireDrag() {
    const handle = this.head.querySelector('.sbcp-drag');
    let start = null;
    const move = (e) => {
      if (!start) return;
      const r = { ...this.state.rect, x: start.x + e.clientX - start.mx, y: start.y + e.clientY - start.my };
      this.state.rect = r;
      this.applyLayout();
    };
    const up = () => {
      if (!start) return;
      start = null;
      this.el.classList.remove('is-dragging');
      store.set('rect', this.state.rect);
      window.removeEventListener('pointermove', move);
      window.removeEventListener('pointerup', up);
    };
    handle.addEventListener('pointerdown', (e) => {
      if (this.state.position !== 'floating' || this.state.max) { this.togglePosition(); return; } // dragging a docked panel floats it
      const r = this.el.getBoundingClientRect();
      start = { x: r.left, y: r.top, mx: e.clientX, my: e.clientY };
      this.state.rect = { x: r.left, y: r.top, w: r.width, h: r.height };
      this.el.classList.add('is-dragging');
      window.addEventListener('pointermove', move);
      window.addEventListener('pointerup', up);
      e.preventDefault();
    });
    handle.addEventListener('keydown', (e) => {
      if (this.state.position !== 'floating') return;
      const step = e.shiftKey ? 40 : 10;
      const d = { ArrowLeft: [-step, 0], ArrowRight: [step, 0], ArrowUp: [0, -step], ArrowDown: [0, step] }[e.key];
      if (!d) return;
      e.preventDefault();
      const r = this.el.getBoundingClientRect();
      this.state.rect = { x: r.left + d[0], y: r.top + d[1], w: r.width, h: r.height };
      store.set('rect', this.state.rect);
      this.applyLayout();
    });
  }

  wireResize() {
    let start = null;
    const move = (e) => {
      if (!start) return;
      if (this.state.position === 'docked') {
        this.state.dockWidth = Math.max(360, Math.min(Math.min(820, window.innerWidth - 240), start.w - (e.clientX - start.mx)));
      } else {
        this.state.rect = { ...this.state.rect, w: Math.max(340, start.w + e.clientX - start.mx), h: Math.max(380, start.h + e.clientY - start.my) };
      }
      this.applyLayout();
    };
    const up = () => {
      if (!start) return;
      start = null;
      store.set('dockWidth', this.state.dockWidth);
      store.set('rect', this.state.rect);
      window.removeEventListener('pointermove', move);
      window.removeEventListener('pointerup', up);
      this.el.classList.remove('is-resizing');
    };
    this.resizer.addEventListener('pointerdown', (e) => {
      if (this.state.max) return;
      const r = this.el.getBoundingClientRect();
      start = { w: this.state.position === 'docked' ? this.state.dockWidth : r.width, h: r.height, mx: e.clientX, my: e.clientY };
      if (this.state.position === 'floating') this.state.rect = { x: r.left, y: r.top, w: r.width, h: r.height };
      this.el.classList.add('is-resizing');
      window.addEventListener('pointermove', move);
      window.addEventListener('pointerup', up);
      e.preventDefault();
    });
    this.resizer.addEventListener('keydown', (e) => {
      if (this.state.position !== 'docked') return;
      const d = { ArrowLeft: 20, ArrowRight: -20 }[e.key];
      if (!d) return;
      e.preventDefault();
      this.state.dockWidth = Math.max(360, Math.min(820, this.state.dockWidth + d));
      store.set('dockWidth', this.state.dockWidth);
      this.applyLayout();
    });
  }

  // ------------------------------------------------------------ meta: models, commands, projects
  async loadMeta() {
    try {
      this.meta = await api('GET', 'copilot/meta');
    } catch { return; }
    this.renderModelLabel();
    this.renderProjectsLabel();
  }

  modelInfo(value) {
    for (const g of this.meta?.groups || []) {
      const m = g.models.find((x) => x.value === value);
      if (m) return { ...m, provider: g.label };
    }
    return null;
  }

  renderModelLabel() {
    const v = this.state.model;
    const info = v ? this.modelInfo(v) : null;
    this.modelLabel.textContent = info ? info.label : v ? v.split('|').pop() : (this.meta?.default_label || 'Default model');
    this.modelBtn.title = info ? `${info.provider}: ${info.description}` : 'Which model answers';
  }

  toggleModelMenu(open = this.modelMenu.hidden) {
    this.modelMenu.hidden = !open;
    this.modelBtn.setAttribute('aria-expanded', String(open));
    if (!open) return;
    const pick = (value) => {
      this.state.model = value;
      store.set('model', value);
      this.renderModelLabel();
      this.toggleModelMenu(false);
      if (this.state.threadId) api('PATCH', `copilot/threads/${this.state.threadId}`, { model: value }).catch(() => {});
    };
    const search = h('input', { type: 'search', class: 'bw-tc-search', placeholder: 'Search models…', 'aria-label': 'Search models' });
    const list = h('div', { class: 'bw-tc-list' });
    const draw = () => {
      const q = search.value.trim().toLowerCase();
      clear(list).append(
        h('button', { type: 'button', role: 'menuitemradio', class: `bw-tc-item${!this.state.model ? ' is-on' : ''}`, onclick: () => pick(null) },
          h('span', {}, h('strong', {}, 'Default'), h('small', {}, ` ${this.meta?.default_label || ''} (Settings)`))),
        (this.meta?.groups || []).map((g) => {
          const models = g.models.filter((m) => !q || `${m.label} ${m.id} ${g.label}`.toLowerCase().includes(q));
          if (!models.length) return null;
          return [h('div', { class: 'bw-tc-group' }, g.label, g.current ? h('span', { class: 'bw-tc-tag' }, 'Settings') : null),
            models.slice(0, q ? 60 : 25).map((m) => h('button', {
              type: 'button', role: 'menuitemradio', 'aria-checked': String(this.state.model === m.value),
              class: `bw-tc-item${this.state.model === m.value ? ' is-on' : ''}`, title: m.description, onclick: () => pick(m.value),
            }, h('span', {}, m.label), h('span', { class: 'bw-tc-badges' }, m.reasoning ? h('span', { class: 'bw-tc-tag' }, 'thinks') : null, m.vision ? h('span', { class: 'bw-tc-tag' }, 'sees') : null)))];
        }),
        h('form', {
          class: 'bw-tc-custom',
          onsubmit: (e) => {
            e.preventDefault();
            const v = e.target.elements.custom.value.trim();
            if (v) pick(v.includes('|') ? v : `${this.meta?.engine?.id}|${v}`);
          },
        }, h('input', { name: 'custom', type: 'text', placeholder: 'Other model id (e.g. gpt-5.5 or openrouter|x/y)' }), h('button', { type: 'submit', class: 'bw-btn' }, 'Use')));
      if (!(this.meta?.groups || []).length) list.append(h('p', { class: 'bw-tc-empty' }, 'Add an AI key in Settings to pick models.'));
    };
    search.addEventListener('input', draw);
    clear(this.modelMenu).append(search, list);
    draw();
    setTimeout(() => search.focus(), 0);
  }

  setMode(mode) {
    this.state.mode = mode;
    store.set('mode', mode);
    for (const b of this.modeBtns) b.setAttribute('aria-pressed', String(b.dataset.cpMode === mode));
    if (this.state.threadId) api('PATCH', `copilot/threads/${this.state.threadId}`, { mode }).catch(() => {});
  }

  renderProjectsLabel() {
    this.projectsBtn.textContent = this.state.project ? `Projects · ${this.state.project}` : 'Projects';
  }

  // ------------------------------------------------------------ composer
  autosize() {
    this.input.style.height = 'auto';
    this.input.style.height = `${Math.min(Math.max(44, this.input.scrollHeight), 220)}px`;
  }

  updateCounter() {
    const n = this.input.value.length;
    this.left.textContent = n > MAX * 0.75 ? `${(MAX - n).toLocaleString()} left` : '';
    this.left.classList.toggle('is-low', MAX - n < 100);
    this.sendBtn.disabled = this.busy || !this.input.value.trim();
  }

  selection() {
    try { return (pageContext.getSelection?.() || window.getSelection?.().toString() || '').trim(); } catch { return ''; }
  }

  renderChips() {
    if (!this.chips) return;
    const chips = [];
    const sel = this.selection();
    if (!this.dropContext && (pageContext.title || pageContext.path !== '/')) {
      chips.push(h('span', { class: 'sbcp-chip-ctx', title: 'The Copilot can see this page' },
        '📄 ', pageContext.title || pageContext.path,
        h('button', { type: 'button', class: 'sbcp-chip-x', 'aria-label': 'Don’t include this page', onclick: () => { this.dropContext = true; this.renderChips(); } }, '×')));
    }
    if (sel) chips.push(h('span', { class: 'sbcp-chip-ctx is-sel', title: sel.slice(0, 400) }, `✂️ Selection · ${sel.split(/\s+/).length} words`));
    this.attachments.forEach((f, i) => chips.push(h('span', { class: 'sbcp-chip-ctx is-file', title: `${f.text.length.toLocaleString()} characters` }, '📎 ', f.name,
      h('button', { type: 'button', class: 'sbcp-chip-x', 'aria-label': `Remove ${f.name}`, onclick: () => { this.attachments.splice(i, 1); this.renderChips(); } }, '×'))));
    clear(this.chips).append(chips);
    this.chips.hidden = !chips.length;
  }

  async addFiles(files) {
    for (const f of files) {
      if (this.attachments.length >= MAX_ATTACH) { toast(`Up to ${MAX_ATTACH} files.`, 'err'); break; }
      if (f.size > 2 * 1024 * 1024) { toast(`${f.name} is too big (max 2 MB of text).`, 'err'); continue; }
      const ok = /\.(txt|md|csv|json|html?|css|js)$/i.test(f.name) || (f.type || '').startsWith('text/');
      if (!ok) { toast(`${f.name}: attach text files (txt, md, csv, json, html, css, js). For PDFs use Library → Upload.`, 'err'); continue; }
      const text = (await f.text()).slice(0, MAX_ATTACH_CHARS);
      this.attachments.push({ name: f.name, text });
    }
    this.file.value = '';
    this.renderChips();
  }

  updateCmds() {
    const v = this.input.value;
    const cmds = this.meta?.commands || [];
    if (!v.startsWith('/') || v.includes(' ') || !cmds.length) { this.cmds.hidden = true; return; }
    const matches = cmds.filter((c) => c.cmd.startsWith(v.toLowerCase()));
    if (!matches.length) { this.cmds.hidden = true; return; }
    this.cmdIndex = Math.min(this.cmdIndex, matches.length - 1);
    clear(this.cmds).append(matches.map((c, i) => h('li', {
      role: 'option', 'aria-selected': String(i === this.cmdIndex), class: i === this.cmdIndex ? 'is-on' : '',
      onmousedown: (e) => { e.preventDefault(); this.useCmd(c); },
    }, h('strong', {}, c.cmd), h('span', {}, c.hint))));
    this.cmds.hidden = false;
    this.cmdMatches = matches;
  }

  useCmd(c) {
    this.input.value = c.prompt;
    this.cmds.hidden = true;
    this.cmdIndex = 0;
    this.autosize();
    this.updateCounter();
    this.input.focus();
  }

  onInputKey(e) {
    if (!this.cmds.hidden && this.cmdMatches?.length) {
      if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
        e.preventDefault();
        this.cmdIndex = (this.cmdIndex + (e.key === 'ArrowDown' ? 1 : -1) + this.cmdMatches.length) % this.cmdMatches.length;
        this.updateCmds();
        return;
      }
      if (e.key === 'Enter' || e.key === 'Tab') { e.preventDefault(); this.useCmd(this.cmdMatches[this.cmdIndex]); return; }
      if (e.key === 'Escape') { this.cmds.hidden = true; return; }
    }
    if (e.key === 'Enter' && !e.shiftKey && !e.isComposing) { e.preventDefault(); this.send(); }
    if (e.key === 'Escape' && this.busy) this.stop();
  }

  // ------------------------------------------------------------ voice input
  async toggleVoice() {
    if (this.rec) { this.rec.stop(); return; }
    const SR = window.SpeechRecognition || window.webkitSpeechRecognition;
    let info = null;
    try { info = await api('GET', 'transcribe'); } catch { /* offline */ }
    if (info?.engine && navigator.mediaDevices?.getUserMedia && window.MediaRecorder) return this.recordServer();
    if (SR) return this.recordBrowser(SR);
    toast('Voice input needs an AssemblyAI or OpenAI key in Settings (or a browser with speech recognition).', 'err');
  }

  setVoiceUi(on) {
    this.voiceBtn.setAttribute('aria-pressed', String(on));
    this.voiceBtn.classList.toggle('is-rec', on);
    this.voiceBtn.setAttribute('aria-label', on ? 'Stop voice recording' : 'Start voice recording');
    this.voiceBtn.title = on ? 'Stop voice recording' : 'Start voice recording';
  }

  async recordServer() {
    let stream;
    try { stream = await navigator.mediaDevices.getUserMedia({ audio: true }); } catch { toast('Allow microphone access to dictate.', 'err'); return; }
    const chunks = [];
    const rec = new MediaRecorder(stream);
    this.rec = rec;
    rec.ondataavailable = (e) => { if (e.data.size) chunks.push(e.data); };
    rec.onstop = async () => {
      stream.getTracks().forEach((t) => t.stop());
      this.rec = null;
      this.setVoiceUi(false);
      const blob = new Blob(chunks, { type: rec.mimeType || 'audio/webm' });
      if (blob.size < 800) return;
      this.left.textContent = 'Transcribing…';
      try {
        const fd = new FormData();
        fd.append('file', blob, `voice.${(rec.mimeType || 'audio/webm').includes('mp4') ? 'm4a' : 'webm'}`);
        const r = await apiUpload('transcribe', fd);
        this.insertText(r.transcript);
      } catch (e) { toast(e.message, 'err'); } finally { this.updateCounter(); }
    };
    rec.start();
    this.setVoiceUi(true);
  }

  recordBrowser(SR) {
    const rec = new SR();
    rec.lang = document.documentElement.lang || 'en-US';
    rec.interimResults = false;
    rec.onresult = (e) => this.insertText([...e.results].map((r) => r[0].transcript).join(' '));
    rec.onend = () => { this.rec = null; this.setVoiceUi(false); };
    rec.onerror = () => { this.rec = null; this.setVoiceUi(false); };
    this.rec = rec;
    rec.start();
    this.setVoiceUi(true);
  }

  insertText(text) {
    const t = String(text || '').trim();
    if (!t) return;
    this.input.value = (this.input.value ? `${this.input.value.trimEnd()} ` : '') + t;
    this.input.value = this.input.value.slice(0, MAX);
    this.autosize();
    this.updateCounter();
    this.input.focus();
  }

  // ------------------------------------------------------------ threads
  newChat() {
    this.stop();
    this.state.threadId = null;
    store.set('thread', null);
    this.attachments = [];
    this.dropContext = false;
    this.renderChips();
    this.renderEmpty();
    this.input.focus();
  }

  async ensureThread() {
    if (this.state.threadId) return this.state.threadId;
    const r = await api('POST', 'copilot/threads', { mode: this.state.mode, model: this.state.model, project: this.state.project });
    this.state.threadId = r.thread.id;
    store.set('thread', r.thread.id);
    return r.thread.id;
  }

  async openThread(id, quiet = false) {
    try {
      const r = await api('GET', `copilot/threads/${id}`);
      this.state.threadId = id;
      store.set('thread', id);
      this.setMode(r.thread.mode);
      if (r.thread.model) { this.state.model = r.thread.model; store.set('model', r.thread.model); this.renderModelLabel(); }
      clear(this.log);
      const actions = new Map(r.actions.map((a) => [a.id, a]));
      for (const m of r.messages) this.appendStored(m);
      // Action cards in order after their turn (simplest faithful placement: after the transcript).
      for (const a of actions.values()) this.log.append(this.actionCard(a));
      if (!r.messages.length) this.renderEmpty();
      this.scroll(true);
    } catch (e) {
      if (!quiet) toast(e.message, 'err');
      this.state.threadId = null;
      store.set('thread', null);
      this.renderEmpty();
    }
  }

  appendStored(m) {
    if (m.role === 'user') this.log.append(this.userBubble(m.content, m.attachments, m.context));
    else if (m.role === 'steps') {
      const box = this.stepsBox();
      m.steps.forEach((s) => box.add({ label: s.label, status: 'done' }));
      if (m.content) box.note(m.content);
      this.log.append(box.el);
    } else this.log.append(this.assistantBubble(m.content, m.id));
  }

  renderEmpty() {
    const name = (window.__studioName || '').split(' ')[0];
    const starters = ['Read my current script and tell me the weakest minute', 'Give me 5 title ideas for this week’s video', 'What are my breakouts telling me?', 'Turn the selection into a punchier cold open'];
    clear(this.log).append(h('div', { class: 'sbcp-empty' },
      h('div', { class: 'sbcp-empty-mark', html: SVG.mark }),
      h('h3', {}, name ? `Hi ${name}${loveOn() ? ' 💕' : ''}` : 'Hi there'),
      h('p', {}, 'I can read your scripts, ideas and breakouts, search your past work, and (in Agent mode) propose edits you approve.'),
      loveOn() ? h('p', { class: 'sbcp-love' }, noteOfTheDay()) : null,
      h('div', { class: 'sbcp-starters' }, starters.map((s) => h('button', { type: 'button', onclick: () => { this.input.value = s; this.autosize(); this.updateCounter(); this.send(); } }, s))),
      h('p', { class: 'sbcp-hint' }, 'Type / for commands · Ctrl+I to open or close')));
  }

  // ------------------------------------------------------------ send / stream
  async send() {
    const content = this.input.value.trim();
    if (!content || this.busy) return;
    this.cmds.hidden = true;
    if (this.log.querySelector('.sbcp-empty')) clear(this.log);

    const context = this.dropContext ? { path: location.pathname } : {
      path: pageContext.path || location.pathname,
      title: pageContext.title || null,
      script_id: pageContext.script_id || null,
      selection: this.selection() || null,
    };
    const attachments = this.attachments.map((f) => ({ name: f.name, text: f.text }));
    this.log.append(this.userBubble(content, attachments.map((a) => ({ name: a.name })), context));
    this.input.value = '';
    this.attachments = [];
    this.autosize();
    this.renderChips();
    this.setBusy(true);

    const steps = this.stepsBox();
    this.log.append(steps.el);
    steps.thinking();
    const answer = this.assistantBubble('', null, true);
    let text = '';
    this.scroll(true);

    this.abort = new AbortController();
    try {
      const id = await this.ensureThread();
      const r = await apiStream(`copilot/threads/${id}/messages`, { content, mode: this.state.mode, model: this.state.model, context, attachments }, {
        signal: this.abort.signal,
        onStatus: (s) => steps.thinking(s.step === 'working' ? 'Working…' : 'Thinking…'),
        onEvent: (event, data) => {
          if (event === 'tool') steps.add(data);
          else if (event === 'note') steps.note(data);
          else if (event === 'action') { this.log.insertBefore(this.actionCard(data), answer.el); this.scroll(); }
          else if (event === 'client' && data.name === 'open_page' && typeof data.args?.path === 'string' && data.args.path.startsWith('/')) navigate(data.args.path);
        },
        onDelta: (t) => {
          if (!text) { steps.done(); this.log.append(answer.el); }
          text += t;
          answer.set(text);
          this.scroll();
        },
      });
      steps.done();
      if (!text) { this.log.append(answer.el); answer.set(r.message?.content || ''); }
      answer.finish(r.message?.id);
      if (!steps.hasSteps()) steps.el.remove();
    } catch (e) {
      steps.done();
      if (!steps.hasSteps()) steps.el.remove();
      if (e.name === 'AbortError') {
        if (text) { answer.set(`${text}\n\n_(stopped)_`); answer.finish(null); } else answer.el.remove();
      } else {
        answer.el.remove();
        this.log.append(h('div', { class: 'sbcp-msg is-error', role: 'alert' }, e.message || String(e)));
      }
    } finally {
      this.abort = null;
      this.setBusy(false);
      this.scroll();
      this.input.focus();
    }
  }

  stop() {
    this.abort?.abort();
  }

  setBusy(on) {
    this.busy = on;
    this.stopBtn.hidden = !on;
    this.sendBtn.hidden = on;
    this.el.classList.toggle('is-busy', on);
    this.updateCounter();
  }

  scroll(force = false) {
    const nearBottom = this.log.scrollHeight - this.log.scrollTop - this.log.clientHeight < 120;
    if (force || nearBottom) requestAnimationFrame(() => { this.log.scrollTop = this.log.scrollHeight; });
  }

  // ------------------------------------------------------------ transcript pieces
  userBubble(text, attachments = [], context = null) {
    return h('div', { class: 'sbcp-msg is-user' },
      h('div', { class: 'sbcp-bubble' }, text),
      (attachments?.length || context?.selection) ? h('div', { class: 'sbcp-meta' },
        context?.selection ? h('span', {}, `✂️ ${String(context.selection).split(/\s+/).length} words selected`) : null,
        (attachments || []).map((a) => h('span', {}, `📎 ${a.name}`))) : null);
  }

  /** An answer bubble: set(text) re-renders Markdown while streaming, finish(id) adds its buttons. */
  assistantBubble(text, id, pending = false) {
    const body = h('div', { class: 'sbcp-md markdown-body' });
    const tools = h('div', { class: 'sbcp-msg-tools', hidden: true });
    const el = h('div', { class: 'sbcp-msg is-ai' }, body, tools);
    let current = '';
    const bubble = {
      el,
      set: (t) => {
        current = t;
        body.innerHTML = markdown(t) + (pending ? '<span class="caret"></span>' : '');
      },
      finish: (messageId) => {
        pending = false;
        body.querySelector('.caret')?.remove();
        tools.hidden = false;
        clear(tools).append(
          h('button', { type: 'button', title: 'Copy', onclick: () => copyText(current) }, 'Copy'),
          pageContext.script_id ? h('button', { type: 'button', title: 'Insert into the script at the cursor', onclick: () => window.dispatchEvent(new CustomEvent('copilot:insert', { detail: { text: current } })) }, 'Insert') : null,
          messageId ? h('button', { type: 'button', class: 'sbcp-rate', title: 'Good answer', 'aria-label': 'Good answer', onclick: (e) => this.rate(messageId, 'up', e.currentTarget) }, '👍') : null,
          messageId ? h('button', { type: 'button', class: 'sbcp-rate', title: 'Bad answer', 'aria-label': 'Bad answer', onclick: (e) => this.rate(messageId, 'down', e.currentTarget) }, '👎') : null);
      },
    };
    if (text) { bubble.set(text); if (!pending) bubble.finish(id); }
    return bubble;
  }

  stepsBox() {
    const list = h('ul', { class: 'sbcp-steps-list' });
    const head = h('button', { type: 'button', class: 'sbcp-steps-head', 'aria-expanded': 'true' }, h('span', { class: 'sbcp-steps-title' }, 'Thinking…'));
    const el = h('div', { class: 'sbcp-steps' }, head, list);
    const items = new Map();
    let count = 0;
    head.addEventListener('click', () => { const open = list.hidden; list.hidden = !open; head.setAttribute('aria-expanded', String(open)); });
    const title = head.firstChild;
    return {
      el,
      thinking(t = 'Thinking…') { el.classList.add('is-live'); if (!count) title.textContent = t; },
      add(s) {
        let li = items.get(s.id);
        if (!li) {
          li = h('li', { class: 'sbcp-step' });
          if (s.id) items.set(s.id, li);
          list.append(li);
          count++;
        }
        li.className = `sbcp-step is-${s.status || 'done'}`;
        li.replaceChildren(h('span', { class: 'sbcp-step-dot', 'aria-hidden': 'true' }), h('span', {}, s.label),
          s.status === 'proposed' ? h('span', { class: 'sbcp-step-tag' }, 'needs your OK') : null,
          s.status === 'error' ? h('span', { class: 'sbcp-step-tag is-bad' }, 'didn’t work') : null);
        title.textContent = `${count} step${count === 1 ? '' : 's'}`;
      },
      note(t) { list.append(h('li', { class: 'sbcp-step is-note' }, String(t))); },
      done() { el.classList.remove('is-live'); if (count) { title.textContent = `${count} step${count === 1 ? '' : 's'}`; list.hidden = true; head.setAttribute('aria-expanded', 'false'); } },
      hasSteps: () => count > 0,
    };
  }

  actionCard(a) {
    const card = h('div', { class: `sbcp-action is-${a.status}`, 'data-action': a.id });
    const draw = () => {
      const p = a.result?.preview || {};
      const diff = (p.before || p.after) ? h('div', { class: 'sbcp-diff' },
        p.before ? h('div', { class: 'sbcp-diff-old' }, h('span', { class: 'sbcp-diff-tag' }, 'Before'), h('div', { class: 'sbcp-diff-text' }, p.before)) : null,
        p.after ? h('div', { class: 'sbcp-diff-new' }, h('span', { class: 'sbcp-diff-tag' }, p.operation === 'append' ? 'Added' : 'After'), h('div', { class: 'sbcp-diff-text' }, p.after)) : null) : null;
      const meta = [p.reason, p.words_before !== undefined ? `${p.words_before} → ${p.words_after} words` : null, p.premise].filter(Boolean).join(' · ');
      const status = { pending: null, applied: '✓ Applied', dismissed: 'Dismissed', undone: '↶ Undone', failed: `✕ ${a.result?.error || 'Failed'}` }[a.status];
      const buttons = a.status === 'pending' ? [
        h('button', { type: 'button', class: 'sbcp-apply', onclick: (e) => this.applyAction(a, e.currentTarget, draw) }, 'Apply'),
        h('button', { type: 'button', class: 'sbcp-dismiss', onclick: async () => { const r = await api('POST', `copilot/actions/${a.id}/dismiss`).catch((e) => toast(e.message, 'err')); if (r) { Object.assign(a, r.action); draw(); } } }, 'Dismiss'),
      ] : a.status === 'applied' ? [
        a.result?.link ? h('button', { type: 'button', class: 'sbcp-dismiss', onclick: () => navigate(a.result.link) }, 'Open') : null,
        a.result?.undo ? h('button', { type: 'button', class: 'sbcp-dismiss', onclick: (e) => this.undoAction(a, e.currentTarget, draw) }, 'Undo') : null,
      ] : [];
      card.className = `sbcp-action is-${a.status}`;
      card.replaceChildren(
        h('div', { class: 'sbcp-action-head' }, h('span', { class: 'sbcp-action-kind' }, 'Proposed change'), status ? h('span', { class: 'sbcp-action-status' }, status) : null),
        h('div', { class: 'sbcp-action-sum' }, a.summary),
        meta ? h('div', { class: 'sbcp-action-meta' }, meta) : null,
        diff,
        buttons.some(Boolean) ? h('div', { class: 'sbcp-action-btns' }, buttons) : null);
    };
    draw();
    return card;
  }

  async applyAction(a, btn, redraw) {
    btn.disabled = true;
    window.dispatchEvent(new CustomEvent('copilot:before-apply', { detail: a })); // e.g. the editor saves first
    await new Promise((r) => setTimeout(r, 150));
    try {
      const r = await api('POST', `copilot/actions/${a.id}/apply`);
      Object.assign(a, r.action);
      redraw();
      toast(r.message);
      if (loveOn()) hearts(6);
      window.dispatchEvent(new CustomEvent('copilot:applied', { detail: { action: r.action, script: r.script, link: r.link } }));
      this.refreshPageFor(r.link);
    } catch (e) {
      if (e.data?.action) Object.assign(a, e.data.action);
      redraw();
      toast(e.message, 'err');
    }
  }

  async undoAction(a, btn, redraw) {
    btn.disabled = true;
    try {
      const r = await api('POST', `copilot/actions/${a.id}/undo`);
      Object.assign(a, r.action);
      redraw();
      toast(r.message);
      window.dispatchEvent(new CustomEvent('copilot:applied', { detail: { action: r.action, script: r.script, undone: true } }));
      this.refreshPageFor(a.result?.link);
    } catch (e) { btn.disabled = false; toast(e.message, 'err'); }
  }

  /** Re-render list pages that show what just changed (the script editor updates itself). */
  refreshPageFor(link) {
    if (!link) return;
    const base = link.split('?')[0];
    const here = location.pathname;
    if (here === base && !/^\/scripts\/\d+$/.test(here)) navigate(here);
  }

  async rate(messageId, rating, btn) {
    try {
      await api('POST', 'copilot/feedback', { thread_id: this.state.threadId, message_id: messageId, rating });
      btn.parentElement.querySelectorAll('.sbcp-rate').forEach((b) => { b.disabled = true; });
      btn.classList.add('is-on');
    } catch (e) { toast(e.message, 'err'); }
  }

  // ------------------------------------------------------------ overlays: history, projects, feedback
  openOverlay(title, body) {
    clear(this.overlay).append(
      h('div', { class: 'sbcp-ov-head' }, h('strong', {}, title),
        h('button', { type: 'button', class: 'sbcp-icon', 'aria-label': 'Close', title: 'Close', onclick: () => this.closeOverlay() }, svg('close'))),
      h('div', { class: 'sbcp-ov-body' }, body));
    this.overlay.hidden = false;
  }

  closeOverlay() {
    this.overlay.hidden = true;
    clear(this.overlay);
  }

  async showHistory() {
    const box = h('div', {}, h('p', { class: 'sbcp-muted' }, 'Loading…'));
    this.openOverlay(this.state.project ? `History · ${this.state.project}` : 'History', box);
    try {
      const q = this.state.project ? `?project=${encodeURIComponent(this.state.project)}` : '';
      const r = await api('GET', `copilot/threads${q}`);
      if (!r.threads.length) { clear(box).append(h('p', { class: 'sbcp-muted' }, 'No chats yet.')); return; }
      clear(box).append(h('ul', { class: 'sbcp-history' }, r.threads.map((t) => h('li', { class: t.id === this.state.threadId ? 'is-on' : '' },
        h('button', { type: 'button', class: 'sbcp-history-open', onclick: () => { this.closeOverlay(); this.openThread(t.id); } },
          h('span', { class: 'sbcp-history-title' }, t.title),
          h('span', { class: 'sbcp-muted' }, [t.project, new Date(t.updated_at).toLocaleDateString(), t.mode === 'ask' ? 'Ask' : null].filter(Boolean).join(' · '))),
        h('button', {
          type: 'button', class: 'sbcp-icon', 'aria-label': `Delete ${t.title}`, title: 'Delete',
          onclick: async (e) => {
            e.stopPropagation();
            if (!window.confirm(`Delete “${t.title}”?`)) return;
            await api('DELETE', `copilot/threads/${t.id}`).catch((er) => toast(er.message, 'err'));
            if (t.id === this.state.threadId) this.newChat();
            this.showHistory();
          },
        }, svg('close'))))));
    } catch (e) { clear(box).append(h('p', { class: 'error-text' }, e.message)); }
  }

  async showProjects() {
    const projects = (await api('GET', 'copilot/meta').catch(() => this.meta))?.projects || [];
    const choose = async (p) => {
      this.state.project = p;
      store.set('project', p);
      this.renderProjectsLabel();
      this.closeOverlay();
      if (this.state.threadId) await api('PATCH', `copilot/threads/${this.state.threadId}`, { project: p }).catch(() => {});
      toast(p ? `Chats now go to “${p}”.` : 'Showing all chats.');
    };
    const name = h('input', { type: 'text', maxlength: 80, placeholder: 'New project, e.g. “Budget series”' });
    this.openOverlay('Projects', h('div', {},
      h('p', { class: 'sbcp-muted' }, 'Group chats by video or series. New chats (and this one) go into the project you pick; History shows only that project.'),
      h('ul', { class: 'sbcp-history' },
        h('li', { class: !this.state.project ? 'is-on' : '' }, h('button', { type: 'button', class: 'sbcp-history-open', onclick: () => choose(null) }, h('span', { class: 'sbcp-history-title' }, 'All chats'))),
        projects.map((p) => h('li', { class: p === this.state.project ? 'is-on' : '' }, h('button', { type: 'button', class: 'sbcp-history-open', onclick: () => choose(p) }, h('span', { class: 'sbcp-history-title' }, `📁 ${p}`))))),
      h('form', { class: 'sbcp-ov-form', onsubmit: (e) => { e.preventDefault(); if (name.value.trim()) choose(name.value.trim()); } }, name, h('button', { type: 'submit', class: 'sbcp-apply' }, 'Create'))));
    name.focus();
  }

  showFeedback() {
    const text = h('textarea', { rows: 4, maxlength: 3000, placeholder: 'What worked, what didn’t, what you wish it could do…' });
    const rating = { v: null };
    const thumbs = ['up', 'down'].map((r) => h('button', { type: 'button', class: 'sbcp-thumb', 'aria-pressed': 'false', onclick: (e) => { rating.v = r; thumbs.forEach((b) => b.setAttribute('aria-pressed', String(b === e.currentTarget))); } }, r === 'up' ? '👍' : '👎'));
    this.openOverlay('Send feedback', h('form', {
      class: 'sbcp-ov-form is-col',
      onsubmit: async (e) => {
        e.preventDefault();
        try {
          const r = await api('POST', 'copilot/feedback', { thread_id: this.state.threadId, rating: rating.v, text: text.value || null });
          toast(r.message);
          this.closeOverlay();
        } catch (er) { toast(er.message, 'err'); }
      },
    }, h('div', { class: 'row' }, thumbs), text, h('button', { type: 'submit', class: 'sbcp-apply' }, 'Send')));
    text.focus();
  }
}
