// Creator Studio entry point: auth gate, app shell and routing.
import { h, api, clear, link, navigate, setUnauthorizedHandler, toast, loading } from './core.js';
import { icon } from './icons.js';
import { installPalette, openPalette } from './palette.js';
import { startTour } from './tour.js';
import { renderAuth } from './pages/auth.js';
import { renderDashboard } from './pages/dashboard.js';
import { renderBreakouts } from './pages/breakouts.js';
import { renderIdeas } from './pages/ideas.js';
import { renderScripts, renderScript } from './pages/scripts.js';
import { renderGoals } from './pages/goals.js';
import { renderFlow } from './pages/flow.js';
import { renderSettings } from './pages/settings.js';
import { renderTools } from './pages/tools.js';
import { renderLibrary } from './pages/library.js';
import { renderChat } from './pages/chat.js';

const ROUTES = [
  { path: /^\/$/, render: renderDashboard, nav: '/' },
  { path: /^\/breakouts$/, render: renderBreakouts, nav: '/breakouts' },
  { path: /^\/ideas$/, render: renderIdeas, nav: '/ideas' },
  { path: /^\/scripts$/, render: renderScripts, nav: '/scripts' },
  { path: /^\/scripts\/(\d+)$/, render: renderScript, nav: '/scripts' },
  { path: /^\/goals$/, render: renderGoals, nav: '/goals' },
  { path: /^\/flow$/, render: renderFlow, nav: '/flow' },
  { path: /^\/settings$/, render: renderSettings, nav: '/settings' },
  { path: /^\/tools$/, render: renderTools, nav: '/tools' },
  { path: /^\/tools\/([\w-]+)$/, render: renderTools, nav: '/tools' },
  { path: /^\/library$/, render: renderLibrary, nav: '/library' },
  { path: /^\/chat$/, render: renderChat, nav: '/chat' },
  { path: /^\/chat\/(\d+)$/, render: renderChat, nav: '/chat' },
];

const NAV = [
  { header: 'Today' },
  { to: '/', label: 'Dashboard', icon: 'home' },
  { to: '/flow', label: 'Dictate', icon: 'mic' },
  { header: 'Pipeline' },
  { to: '/breakouts', label: 'Breakouts', icon: 'trend' },
  { to: '/ideas', label: 'Ideas', icon: 'bulb' },
  { to: '/scripts', label: 'Scripts', icon: 'doc' },
  { to: '/goals', label: 'Weekly goals', icon: 'target' },
  { header: 'Create' },
  { to: '/tools', label: 'Toolkit', icon: 'sparkle' },
  { to: '/chat', label: 'Producer chat', icon: 'chat' },
  { to: '/library', label: 'Library', icon: 'book' },
  { header: 'Studio' },
  { to: '/settings', label: 'Settings', icon: 'gear' },
];

const root = document.getElementById('app');
const state = { user: null, cleanup: null, shell: null };

function buildShell() {
  const navLinks = NAV.map((item) => item.header
    ? h('div', { class: 'nav-header' }, item.header)
    : link(item.to, { class: 'nav-item', dataset: { to: item.to } }, icon(item.icon), item.label));

  const content = h('main', { class: 'content-area', id: 'content', tabindex: '-1' });
  const menuBtn = h('button', {
    class: 'btn ghost sm menu-toggle',
    'aria-label': 'Menu',
    onclick: () => document.body.classList.toggle('nav-open'),
  }, icon('menu'));

  const shell = h('div', { class: 'app-shell' },
    h('aside', { class: 'sidebar' },
      h('div', { class: 'brand' }, h('div', { class: 'logo' }, '🎬'), h('span', {}, 'Creator Studio'), menuBtn),
      h('button', { class: 'search-launch', onclick: openPalette, title: 'Search (Ctrl+K)' }, icon('search', 16), h('span', {}, 'Search'), h('kbd', {}, 'Ctrl K')),
      h('nav', { class: 'nav', onclick: () => document.body.classList.remove('nav-open') }, navLinks),
      h('div', { class: 'sidebar-footer' },
        h('div', {}, h('div', { class: 'user-name' }, state.user?.name || ''), h('div', { class: 'muted' }, state.user?.email || '')),
        h('button', { class: 'btn ghost sm', onclick: () => startTour() }, icon('play', 14), 'Take the tour'),
        h('button', { class: 'btn sm', onclick: logout }, icon('logout'), 'Log out'))),
    content);

  return { el: shell, content };
}

async function logout() {
  try { await api('POST', 'auth/logout'); } catch { /* already logged out */ }
  state.user = null;
  state.shell = null;
  navigate('/', { replace: true });
  boot();
}

async function route() {
  if (!state.user) return;
  if (!state.shell) {
    state.shell = buildShell();
    clear(root).append(state.shell.el);
  }
  if (typeof state.cleanup === 'function') {
    try { state.cleanup(); } catch (e) { console.error(e); }
  }
  state.cleanup = null;

  const path = location.pathname.replace(/\/+$/, '') || '/';
  let match = null;
  let params = [];
  for (const r of ROUTES) {
    const m = path.match(r.path);
    if (m) { match = r; params = m.slice(1); break; }
  }

  for (const a of root.querySelectorAll('.nav-item')) {
    a.classList.toggle('active', !!match && a.dataset.to === match.nav);
  }

  const content = state.shell.content;
  clear(content).append(loading());
  window.scrollTo(0, 0);

  if (!match) {
    clear(content).append(h('div', { class: 'empty' },
      h('div', { class: 'empty-icon' }, '🧭'), h('h3', {}, 'Page not found'),
      link('/', { class: 'btn primary' }, 'Back to dashboard')));
    return;
  }

  try {
    const view = h('div', { class: 'view' });
    const cleanup = await match.render(view, ...params);
    // Ignore a render that finished after the user already moved to another page.
    if ((location.pathname.replace(/\/+$/, '') || '/') !== path) {
      if (typeof cleanup === 'function') cleanup();
      return;
    }
    state.cleanup = cleanup;
    clear(content).append(view);
    document.title = `${view.querySelector('h1')?.textContent || 'Creator Studio'} · Creator Studio`;
  } catch (err) {
    console.error(err);
    if (err.status === 401) return;
    clear(content).append(h('div', { class: 'card error-card' },
      h('h2', {}, 'Something went wrong'),
      h('p', {}, err.message || String(err)),
      h('button', { class: 'btn', onclick: route }, 'Try again')));
  }
}

async function boot() {
  clear(root).append(h('div', { class: 'auth-wrapper' }, loading('Opening your studio…')));
  let status;
  try {
    status = await api('GET', 'auth/status');
  } catch (err) {
    clear(root).append(h('div', { class: 'auth-wrapper' }, h('div', { class: 'auth-card' },
      h('h2', {}, "Can't start Creator Studio"), h('p', {}, err.message),
      h('button', { class: 'btn primary', onclick: boot }, 'Retry'))));
    return;
  }

  if (!status.authenticated) {
    state.user = null;
    state.shell = null;
    renderAuth(root, status.initialized, async (user) => {
      state.user = user;
      await route();
      // A brand-new studio gets the tour; a returning login only if it was never finished.
      const st = await api('GET', 'auth/status').catch(() => ({}));
      if (st.tour_done === false) startTour();
    });
    return;
  }
  state.user = status.user;
  await route();
  if (status.tour_done === false) startTour();
}

setUnauthorizedHandler(() => {
  if (!state.user) return;
  state.user = null;
  state.shell = null;
  toast('Please log in again.', 'err');
  boot();
});

window.addEventListener('popstate', route);
installPalette();
boot();
