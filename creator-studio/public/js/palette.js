// Ctrl+K / ⌘K search across pages, settings, scripts, ideas, Library, chats and tools
// with Omnibar 2.0 Action Commands (> new script, > new post, > add deal, > toggle dark, etc.).
import { h, api, clear, debounce, navigate, toast } from './core.js';
import { icon } from './icons.js';
import { setTheme, effectiveTheme } from './theme.js';

let open = false;

const ACTIONS = [
  { label: 'New script draft', context: 'Create a script with AI linting', icon: 'doc', action: () => navigate('/scripts') },
  { label: 'New social post', context: 'Schedule post for LinkedIn, X, IG, TikTok', icon: 'calendar', action: () => navigate('/calendar') },
  { label: 'Add brand deal', context: 'Sponsorship pipeline & deliverables', icon: 'briefcase', action: () => navigate('/deals') },
  { label: 'Code cards & terminal graphics', context: 'Ray.so visual code snippet studio', icon: 'code', action: () => navigate('/snippets') },
  { label: 'Media kit press deck', context: 'Audience reach, demographics & rate card', icon: 'file-text', action: () => navigate('/mediakit') },
  { label: 'Dictate an idea', context: 'Voice recording with live streaming transcript', icon: 'mic', action: () => navigate('/flow') },
  { label: 'Toggle dark / light mode', context: 'Switch studio appearance (Ctrl+Shift+L)', icon: 'moon', action: () => {
    const next = effectiveTheme() === 'dark' ? 'light' : 'dark';
    setTheme(next);
    toast(`Theme changed to ${next}.`);
  } },
  { label: 'Toggle sidebar', context: 'Expand or collapse sidebar (Ctrl+B)', icon: 'menu', action: () => {
    document.dispatchEvent(new KeyboardEvent('keydown', { key: 'b', ctrlKey: true }));
  } },
  { label: 'Producer chat', context: 'Think out loud with your producer', icon: 'chat', action: () => navigate('/chat') },
  { label: 'YouTube breakouts', context: 'Competitor videos beating their channel median', icon: 'trend', action: () => navigate('/breakouts') },
];

export function openPalette() {
  if (open) return;
  open = true;
  let items = [];
  let active = 0;

  const input = h('input', { type: 'search', class: 'palette-input', placeholder: 'Type to search or ">" for actions…', 'aria-label': 'Search or action command' });
  const list = h('div', { class: 'palette-results', role: 'listbox' });
  const overlay = h('div', { class: 'modal-overlay palette-overlay' }, h('div', { class: 'palette', role: 'dialog', 'aria-label': 'Search' },
    h('div', { class: 'palette-bar' }, icon('search', 18), input), list));

  const close = () => {
    open = false;
    overlay.remove();
    document.removeEventListener('keydown', onKey, true);
  };
  const go = (item) => {
    close();
    if (typeof item.action === 'function') {
      item.action();
    } else if (item.url) {
      navigate(item.url);
    }
  };

  const highlight = () => {
    list.querySelectorAll('.palette-item').forEach((el, i) => el.classList.toggle('active', i === active));
    list.querySelector('.palette-item.active')?.scrollIntoView({ block: 'nearest' });
  };

  function renderList(groups) {
    clear(list);
    items = groups.flatMap((g) => g.items);
    active = 0;
    if (!items.length) {
      list.append(h('p', { class: 'muted small palette-hint' }, 'No matching actions or results.'));
      return;
    }
    let i = 0;
    for (const g of groups) {
      if (!g.items.length) continue;
      list.append(h('div', { class: 'palette-group' }, g.title));
      for (const item of g.items) {
        const idx = i++;
        list.append(h('button', {
          class: 'palette-item', role: 'option', type: 'button',
          onmousemove: () => { if (active !== idx) { active = idx; highlight(); } },
          onclick: () => go(item),
        }, icon(item.icon || 'arrow', 16), h('span', { class: 'palette-label' }, item.label), h('span', { class: 'muted small' }, item.context || '')));
      }
    }
    highlight();
  }

  // Initial display: Quick actions
  renderList([{ title: 'Quick Actions', items: ACTIONS }]);

  const run = debounce(async () => {
    const raw = input.value.trim();
    if (!raw) {
      renderList([{ title: 'Quick Actions', items: ACTIONS }]);
      return;
    }

    const isCommand = raw.startsWith('>');
    const cleanQ = (isCommand ? raw.slice(1) : raw).trim().toLowerCase();

    // Match actions
    const matchedActions = ACTIONS.filter((a) =>
      !cleanQ || a.label.toLowerCase().includes(cleanQ) || (a.context && a.context.toLowerCase().includes(cleanQ))
    );

    if (isCommand) {
      renderList([{ title: 'Action Commands', items: matchedActions }]);
      return;
    }

    if (cleanQ.length < 2) {
      renderList([{ title: matchedActions.length ? 'Quick Actions' : 'Search', items: matchedActions }]);
      return;
    }

    try {
      const r = await api('GET', `search?q=${encodeURIComponent(cleanQ)}`);
      if (input.value.trim() !== raw) return; // a newer query is on its way
      const groups = [];
      if (matchedActions.length) {
        groups.push({ title: 'Actions', items: matchedActions });
      }
      for (const g of r.groups || []) {
        if (g.items && g.items.length) groups.push(g);
      }
      renderList(groups);
    } catch (e) {
      if (matchedActions.length) {
        renderList([{ title: 'Actions', items: matchedActions }]);
      } else {
        clear(list).append(h('p', { class: 'error-text palette-hint' }, e.message));
      }
    }
  }, 160);

  const onKey = (e) => {
    if (e.key === 'Escape') {
      e.preventDefault(); close();
    } else if (e.key === 'ArrowDown' && items.length) {
      e.preventDefault(); active = (active + 1) % items.length; highlight();
    } else if (e.key === 'ArrowUp' && items.length) {
      e.preventDefault(); active = (active - 1 + items.length) % items.length; highlight();
    } else if (e.key === 'Enter' && items[active]) {
      e.preventDefault(); go(items[active]);
    }
  };

  input.addEventListener('input', run);
  overlay.addEventListener('mousedown', (e) => { if (e.target === overlay) close(); });
  document.addEventListener('keydown', onKey, true);
  document.body.append(overlay);
  input.focus();
}

/** Global shortcut: Ctrl+K / ⌘K, or "/" when not typing. */
export function installPalette() {
  document.addEventListener('keydown', (e) => {
    if (!document.querySelector('.app-shell')) return; // logged out
    const typing = ['INPUT', 'TEXTAREA', 'SELECT'].includes(e.target.tagName) || e.target.isContentEditable;
    // A page with its own search (the workflow canvas) keeps "/"; Ctrl+K still opens this.
    const ownSlash = document.querySelector('[data-slash-search]');
    if ((e.key.toLowerCase() === 'k' && (e.ctrlKey || e.metaKey)) || (e.key === '/' && !typing && !ownSlash && !document.querySelector('.modal-overlay'))) {
      e.preventDefault();
      openPalette();
    }
  });
}
