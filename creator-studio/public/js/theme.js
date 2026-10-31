// Light / dark / system theme. The choice is stored per browser; index.html applies it before
// the first paint (no white flash), and "system" follows the OS live.
import { h } from './core.js';
import { icon as svgIcon } from './icons.js';

const KEY = 'studio-theme';
const media = window.matchMedia?.('(prefers-color-scheme: dark)');

export function themePref() {
  try { return localStorage.getItem(KEY) || 'system'; } catch { return 'system'; }
}

export function effectiveTheme(pref = themePref()) {
  return pref === 'system' ? (media?.matches ? 'dark' : 'light') : pref;
}

export function applyTheme(pref = themePref()) {
  const theme = effectiveTheme(pref);
  document.documentElement.dataset.theme = theme;
  document.documentElement.dataset.themePref = pref;
  document.querySelector('meta[name="theme-color"]')?.setAttribute('content', theme === 'dark' ? '#17131a' : '#fdfaf8');
  document.querySelectorAll('.sbcp').forEach((el) => { el.dataset.vsAppTheme = theme; }); // Copilot panel
  document.querySelectorAll('.theme-switch button').forEach((b) => b.setAttribute('aria-pressed', String(b.dataset.themeOpt === pref)));
  window.dispatchEvent(new CustomEvent('theme:change', { detail: { theme, pref } }));
}

export function setTheme(pref) {
  try { localStorage.setItem(KEY, pref); } catch { /* private mode: applies for this visit */ }
  applyTheme(pref);
}

media?.addEventListener?.('change', () => { if (themePref() === 'system') applyTheme('system'); });

/** Light / Dark / System segmented switch (sidebar footer and Settings). */
export function themeSwitch() {
  const pref = themePref();
  const opts = [['light', 'sun', 'Light'], ['dark', 'moon', 'Dark'], ['system', 'monitor', 'System']];
  return h('div', { class: 'theme-switch', role: 'group', 'aria-label': 'Theme' },
    opts.map(([v, icon, label]) => h('button', {
      type: 'button', 'data-theme-opt': v, 'aria-pressed': String(pref === v), title: `${label} theme`,
      onclick: () => setTheme(v),
    }, svgIcon(icon, 14), h('span', { class: 'theme-switch-label' }, label))));
}

/** Keyboard shortcut: Ctrl+Shift+L flips light/dark. */
document.addEventListener('keydown', (e) => {
  if ((e.ctrlKey || e.metaKey) && e.shiftKey && e.key.toLowerCase() === 'l') {
    e.preventDefault();
    setTheme(effectiveTheme() === 'dark' ? 'light' : 'dark');
  }
});

applyTheme();
