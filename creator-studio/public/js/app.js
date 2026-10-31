// Creator Studio entry point: auth gate, app shell and routing.
import { h, api, clear, link, navigate, setUnauthorizedHandler, toast, loading } from './core.js';
import { icon } from './icons.js';
import { installPalette, openPalette } from './palette.js';
import { startTour } from './tour.js';
import { themeSwitch } from './theme.js';
import { setLove, dailyHello, heartButton } from './love.js';
import { pageSkeleton } from './skeleton.js';
import { startReminders, stopReminders } from './queue.js';
import { mountCopilot, unmountCopilot, resetCopilotContext, pageRendered } from './copilot.js';
import { renderAuth } from './pages/auth.js';
import { renderDashboard } from './pages/dashboard.js';
import { renderBreakouts } from './pages/breakouts.js';
import { renderAudience } from './pages/audience.js';
import { renderIdeas } from './pages/ideas.js';
import { renderScripts, renderScript } from './pages/scripts.js';
import { renderGoals } from './pages/goals.js';
import { renderFlow } from './pages/flow.js';
import { renderSettings } from './pages/settings.js';
import { renderTools } from './pages/tools.js';
import { renderLibrary } from './pages/library.js';
import { renderChat } from './pages/chat.js';
import { renderRepurpose, renderPlatform, renderPost } from './pages/social.js';
import { renderCalendar } from './pages/calendar.js';
import { renderAnalytics } from './pages/analytics.js';

const ROUTES = [
  { path: /^\/$/, render: renderDashboard, nav: '/' },
  { path: /^\/breakouts$/, render: renderBreakouts, nav: '/breakouts' },
  { path: /^\/channel$/, render: renderAudience, nav: '/channel' },
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
  { path: /^\/calendar$/, render: renderCalendar, nav: '/calendar' },
  { path: /^\/analytics$/, render: renderAnalytics, nav: '/analytics' },
  { path: /^\/repurpose$/, render: renderRepurpose, nav: '/repurpose' },
  { path: /^\/(linkedin|instagram|x|tiktok|threads)$/, render: renderPlatform, nav: null }, // nav = the platform
  { path: /^\/posts\/(\d+)$/, render: renderPost, nav: '' },                // set by the page
];

const NAV = [
  { header: 'Today' },
  { to: '/', label: 'Dashboard', icon: 'home' },
  { to: '/flow', label: 'Dictate', icon: 'mic' },
  { header: 'YouTube' },
  { to: '/breakouts', label: 'Breakouts', icon: 'trend' },
  { to: '/ideas', label: 'Ideas', icon: 'bulb' },
  { to: '/scripts', label: 'Scripts', icon: 'doc' },
  { to: '/goals', label: 'Weekly goals', icon: 'target' },
  { to: '/channel', label: 'My channel', icon: 'users' },
  { header: 'Social' },
  { to: '/calendar', label: 'Calendar', icon: 'calendar' },
  { to: '/analytics', label: 'Analytics', icon: 'chart' },
  { to: '/repurpose', label: 'Repurpose', icon: 'repeat' },
  { to: '/linkedin', label: 'LinkedIn', icon: 'brand:linkedin' },
  { to: '/instagram', label: 'Instagram', icon: 'brand:instagram' },
  { to: '/x', label: 'X', icon: 'brand:x' },
  { to: '/tiktok', label: 'TikTok', icon: 'brand:tiktok' },
  { to: '/threads', label: 'Threads', icon: 'brand:threads' },
  { header: 'Create' },
  { to: '/tools', label: 'Toolkit', icon: 'sparkle' },
  { to: '/chat', label: 'Producer chat', icon: 'chat' },
  { to: '/library', label: 'Library', icon: 'book' },
  { header: 'Studio' },
  { to: '/settings', label: 'Settings', icon: 'gear' },
];

const root = document.getElementById('app');
const state = { user: null, cleanup: null, shell: null };

// ---- Sidebar: an icon rail by default on wide screens (expand to see labels; remembered),
// and a slide-in drawer on phones. ----
const NAV_KEY = 'studio-nav';
function navCollapsed() {
  try { return (localStorage.getItem(NAV_KEY) || 'collapsed') === 'collapsed'; } catch { return true; }
}
function applyNavPref() {
  document.body.classList.toggle('nav-collapsed', navCollapsed());
}
function toggleNavCollapsed() {
  const next = !navCollapsed();
  try { localStorage.setItem(NAV_KEY, next ? 'collapsed' : 'expanded'); } catch { /* not remembered */ }
  applyNavPref();
  state.shell?.syncToggle();
}
function openDrawer(open) {
  document.body.classList.toggle('nav-open', open);
  state.shell?.drawerToggle.setAttribute('aria-expanded', String(open));
}
document.addEventListener('keydown', (e) => {
  if (e.key === 'Escape' && document.body.classList.contains('nav-open')) openDrawer(false);
});

function buildShell() {
  const label = (text) => h('span', { class: 'nav-label' }, text);
  const navLinks = NAV.map((item) => item.header
    ? h('div', { class: 'nav-header' }, label(item.header))
    : link(item.to, { class: 'nav-item', dataset: { to: item.to }, title: item.label, 'aria-label': item.label }, icon(item.icon), label(item.label)));

  const content = h('main', { class: 'content-area', id: 'content', tabindex: '-1' });
  const collapseBtn = h('button', { class: 'nav-collapse', onclick: toggleNavCollapsed });
  const syncToggle = () => {
    const c = navCollapsed();
    collapseBtn.replaceChildren(icon(c ? 'chevron-right' : 'chevron-left', 16), label(c ? 'Expand' : 'Collapse'));
    collapseBtn.title = c ? 'Expand the sidebar' : 'Collapse the sidebar';
    collapseBtn.setAttribute('aria-label', collapseBtn.title);
    collapseBtn.setAttribute('aria-expanded', String(!c));
  };
  syncToggle();
  const drawerToggle = h('button', { class: 'btn ghost sm', 'aria-label': 'Open menu', 'aria-expanded': 'false', 'aria-controls': 'sidebar', onclick: () => openDrawer(true) }, icon('menu'));

  const shell = h('div', { class: 'app-shell' },
    h('aside', { class: 'sidebar', id: 'sidebar' },
      h('div', { class: 'brand' },
        h('div', { class: 'logo' }, '🎬'), label('Creator Studio'), heartButton(),
        h('button', { class: 'btn ghost sm drawer-close', 'aria-label': 'Close menu', onclick: () => openDrawer(false) }, icon('x'))),
      h('button', { class: 'search-launch', onclick: openPalette, title: 'Search (Ctrl+K)', 'aria-label': 'Search' }, icon('search', 16), label('Search'), h('kbd', { class: 'nav-label' }, 'Ctrl K')),
      h('nav', { class: 'nav', onclick: (e) => { if (e.target.closest('a')) openDrawer(false); } }, navLinks),
      h('div', { class: 'sidebar-footer' },
        h('div', { class: 'nav-label' }, h('div', { class: 'user-name' }, state.user?.name || ''), h('div', { class: 'muted' }, state.user?.email || '')),
        h('div', { class: 'nav-label' }, themeSwitch()),
        h('button', { class: 'btn ghost sm', title: 'Take the tour', onclick: () => { openDrawer(false); startTour(); } }, icon('play', 14), label('Take the tour')),
        h('button', { class: 'btn sm', title: 'Log out', onclick: logout }, icon('logout'), label('Log out')),
        collapseBtn)),
    h('div', { class: 'nav-backdrop', onclick: () => openDrawer(false) }),
    h('div', { class: 'main-col' },
      h('header', { class: 'mobile-bar' },
        drawerToggle,
        link('/', { class: 'mobile-brand' }, h('span', { class: 'logo' }, '🎬'), 'Creator Studio'),
        h('button', { class: 'btn ghost sm', 'aria-label': 'Search', onclick: openPalette }, icon('search'))),
      content));

  applyNavPref();
  return { el: shell, content, syncToggle, drawerToggle };
}

/** Highlight a sidebar item (pages without a fixed item, like a post, call this themselves). */
function setActiveNav(to) {
  for (const a of root.querySelectorAll('.nav-item')) {
    const on = !!to && a.dataset.to === to;
    a.classList.toggle('active', on);
    if (on) a.setAttribute('aria-current', 'page'); else a.removeAttribute('aria-current');
  }
}

async function logout() {
  try { await api('POST', 'auth/logout'); } catch { /* already logged out */ }
  stopReminders();
  unmountCopilot();
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

  openDrawer(false);
  setActiveNav(match?.nav === null ? `/${params[0]}` : match?.nav);

  const content = state.shell.content;
  resetCopilotContext();
  // Keep the old page for a beat (no flash on fast loads), then show a skeleton shaped like the
  // new one. The old page is already cleaned up, so it's inert meanwhile.
  const nav = (state.nav = (state.nav || 0) + 1);
  content.inert = true;
  const skTimer = setTimeout(() => {
    if (state.nav !== nav) return;
    clear(content).append(pageSkeleton(path));
    window.scrollTo(0, 0);
  }, 120);
  const settle = () => { clearTimeout(skTimer); content.inert = false; window.scrollTo(0, 0); };

  if (!match) {
    settle();
    clear(content).append(h('div', { class: 'empty' },
      h('div', { class: 'empty-icon' }, '🧭'), h('h3', {}, 'Page not found'),
      link('/', { class: 'btn primary' }, 'Back to dashboard')));
    return;
  }

  try {
    const view = h('div', { class: 'view' });
    const cleanup = await match.render(view, ...params);
    // Ignore a render that finished after the user already moved to another page.
    if ((location.pathname.replace(/\/+$/, '') || '/') !== path || state.nav !== nav) {
      if (typeof cleanup === 'function') cleanup();
      return;
    }
    settle();
    state.cleanup = cleanup;
    clear(content).append(view);
    const heading = view.querySelector('h1')?.textContent || '';
    document.title = `${heading || 'Creator Studio'} · Creator Studio`;
    pageRendered(heading);
  } catch (err) {
    console.error(err);
    if (state.nav !== nav) return;
    settle();
    if (err.status === 401) return;
    clear(content).append(h('div', { class: 'card error-card' },
      h('h2', {}, 'Something went wrong'),
      h('p', {}, err.message || String(err)),
      h('button', { class: 'btn', onclick: route }, 'Try again')));
  }
}

async function boot() {
  clear(root).append(h('div', { class: 'auth-wrapper' }, loading('Opening your studio… 💕')));
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
      // Fetch the love-notes config and tour state first, so the sidebar is built once with them.
      const st = await api('GET', 'auth/status').catch(() => ({}));
      setLove(st.love);
      window.__studioName = st.love?.name || user?.name || '';
      state.shell = null;
      mountCopilot();
      startReminders();
      await route();
      // A brand-new studio gets the tour; a returning login only if it was never finished.
      if (st.tour_done === false) startTour({ onDone: dailyHello });
      else dailyHello();
    });
    return;
  }
  state.user = status.user;
  setLove(status.love);
  window.__studioName = status.love?.name || status.user?.name || '';
  mountCopilot();
  startReminders();
  await route();
  if (status.tour_done === false) startTour({ onDone: dailyHello });
  else dailyHello();
}

setUnauthorizedHandler(() => {
  if (!state.user) return;
  unmountCopilot();
  state.user = null;
  state.shell = null;
  toast('Please log in again.', 'err');
  boot();
});

window.addEventListener('popstate', route);
installPalette();
boot();
