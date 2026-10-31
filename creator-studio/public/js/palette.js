// Ctrl+K / ⌘K search across pages, settings, scripts, ideas, Library, chats and tools
// (backed by /api/search, ported from MagicAI's GlobalSearchService).
import { h, api, clear, debounce, navigate } from './core.js';
import { icon } from './icons.js';

let open = false;

export function openPalette() {
  if (open) return;
  open = true;
  let items = [];
  let active = 0;

  const input = h('input', { type: 'search', class: 'palette-input', placeholder: 'Search scripts, ideas, Library, chats, tools…', 'aria-label': 'Search' });
  const list = h('div', { class: 'palette-results', role: 'listbox' }, h('p', { class: 'muted small palette-hint' }, 'Type to search. ↑↓ to move, Enter to open, Esc to close.'));
  const overlay = h('div', { class: 'modal-overlay palette-overlay' }, h('div', { class: 'palette', role: 'dialog', 'aria-label': 'Search' },
    h('div', { class: 'palette-bar' }, icon('search', 18), input), list));

  const close = () => {
    open = false;
    overlay.remove();
    document.removeEventListener('keydown', onKey, true);
  };
  const go = (item) => { close(); navigate(item.url); };

  const highlight = () => {
    list.querySelectorAll('.palette-item').forEach((el, i) => el.classList.toggle('active', i === active));
    list.querySelector('.palette-item.active')?.scrollIntoView({ block: 'nearest' });
  };

  const run = debounce(async () => {
    const q = input.value.trim();
    if (q.length < 2) { clear(list).append(h('p', { class: 'muted small palette-hint' }, 'Type at least 2 characters.')); items = []; return; }
    try {
      const r = await api('GET', `search?q=${encodeURIComponent(q)}`);
      if (input.value.trim() !== q) return; // a newer query is on its way
      items = r.groups.flatMap((g) => g.items);
      active = 0;
      clear(list);
      if (!items.length) { list.append(h('p', { class: 'muted small palette-hint' }, `Nothing found for “${q}”.`)); return; }
      let i = 0;
      for (const g of r.groups) {
        list.append(h('div', { class: 'palette-group' }, g.title));
        for (const item of g.items) {
          const idx = i++;
          list.append(h('button', {
            class: 'palette-item', role: 'option', type: 'button',
            onmousemove: () => { if (active !== idx) { active = idx; highlight(); } },
            onclick: () => go(item),
          }, icon(item.icon || 'arrow', 16), h('span', { class: 'palette-label' }, item.label), h('span', { class: 'muted small' }, item.context)));
        }
      }
      highlight();
    } catch (e) {
      clear(list).append(h('p', { class: 'error-text palette-hint' }, e.message));
    }
  }, 180);

  const onKey = (e) => {
    if (e.key === 'Escape') { e.preventDefault(); close(); } else if (e.key === 'ArrowDown' && items.length) {
      e.preventDefault(); active = (active + 1) % items.length; highlight();
    } else if (e.key === 'ArrowUp' && items.length) {
      e.preventDefault(); active = (active - 1 + items.length) % items.length; highlight();
    } else if (e.key === 'Enter' && items[active]) { e.preventDefault(); go(items[active]); }
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
