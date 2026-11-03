// Installable app (PWA) and the keyboard-shortcuts overlay ("?").
import { h, modal } from './core.js';
import { icon } from './icons.js';

let deferred = null;
const installButtons = new Set();

export function registerServiceWorker() {
  if (!('serviceWorker' in navigator)) return;
  // Service workers need https (localhost counts as secure).
  if (location.protocol !== 'https:' && !['localhost', '127.0.0.1'].includes(location.hostname)) return;
  window.addEventListener('load', () => navigator.serviceWorker.register('/sw.js').catch(() => {}));
}

window.addEventListener('beforeinstallprompt', (e) => {
  e.preventDefault();
  deferred = e;
  installButtons.forEach((b) => { b.hidden = false; });
});
window.addEventListener('appinstalled', () => {
  deferred = null;
  installButtons.forEach((b) => { b.hidden = true; });
});

/** "Install app" button; only visible when the browser offers installing. */
export function installButton(label) {
  const b = h('button', {
    class: 'btn ghost sm', hidden: !deferred, title: 'Install Creator Studio as an app (own window, dock/home-screen icon)',
    onclick: async () => {
      if (!deferred) return;
      deferred.prompt();
      await deferred.userChoice.catch(() => null);
      deferred = null;
      b.hidden = true;
    },
  }, icon('download', 14), label('Install app'));
  installButtons.add(b);
  return b;
}

const SHORTCUTS = [
  ['Everywhere', [
    ['Ctrl K', 'Search everything'],
    ['/', 'Search (or the page’s own search)'],
    ['?', 'This list'],
    ['Ctrl I', 'Open or close Copilot'],
    ['Ctrl Shift L', 'Light / dark'],
    ['Esc', 'Close a dialog, panel or menu'],
  ]],
  ['Workflow canvas', [
    ['↑ ↓', 'Select the previous / next step'],
    ['Enter', 'Edit the selected step'],
    ['D', 'Duplicate'],
    ['Alt ↑ / Alt ↓', 'Move the step up / down'],
    ['Delete', 'Delete the step'],
    ['Ctrl Z / Ctrl Shift Z', 'Undo / redo'],
    ['+ / − / 0', 'Zoom in / out / reset'],
  ]],
];

export function installShortcutsHelp() {
  document.addEventListener('keydown', (e) => {
    if (e.key !== '?' || e.ctrlKey || e.metaKey || e.altKey) return;
    const t = e.target;
    if (['INPUT', 'TEXTAREA', 'SELECT'].includes(t.tagName) || t.isContentEditable || document.querySelector('.modal-overlay')) return;
    if (!document.querySelector('.app-shell')) return;
    e.preventDefault();
    modal('Keyboard shortcuts', () => h('div', { class: 'kbd-help' }, SHORTCUTS.map(([group, rows]) => h('section', {},
      h('h3', {}, group),
      h('dl', {}, rows.map(([keys, what]) => [h('dt', {}, keys.split(' / ').map((k, i) => [i ? ' / ' : null, ...k.split(' ').map((x) => h('kbd', {}, x))])), h('dd', {}, what)]))))));
  });
}
