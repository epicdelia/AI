// Creator Studio entry point: auth gate, app shell and routing.
import { h, api, clear, link, navigate, setUnauthorizedHandler, toast, loading } from './core.js';
import { icon } from './icons.js';
import { installPalette, openPalette } from './palette.js';
import { startTour } from './tour.js';
import { themeSwitch } from './theme.js';
import { setLove, dailyHello, heartButton } from './love.js';
import { pageSkeleton } from './skeleton.js';
import { startReminders, stopReminders } from './queue.js';
import { mountCopilot, unmountCopilot, resetCopilotContext, pageRendered, toggleCopilot, COPILOT_MARK } from './copilot.js';
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
import { renderConnections } from './pages/connections.js';
import { renderAgents, renderAgent } from './pages/agents.js';
import { renderInbox } from './pages/inbox.js';
import { renderWorkflows, renderWorkflow } from './pages/workflows.js';
import { notificationsButton, stopNotifications } from './notifications.js';
import { registerServiceWorker, installButton, installShortcutsHelp } from './pwa.js';
import { applyPrefs, prefs } from './prefs.js';
import { renderLove, checkLove } from './pages/love.js';
import { renderYouTubeStudio } from './pages/ytstudio.js';
import { renderMediaLibrary } from './pages/mediapage.js';
import { renderBrand } from './pages/brand.js';
import { renderBio } from './pages/bio.js';
import { loveOn } from './love.js';
import { NAV_ITEMS, MEGA } from './nav.js';

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
  { path: /^\/connections$/, render: renderConnections, nav: '/connections' },
  { path: /^\/agents$/, render: renderAgents, nav: '/agents' },
  { path: /^\/agents\/(\d+)$/, render: renderAgent, nav: '/agents' },
  { path: /^\/inbox$/, render: renderInbox, nav: '/inbox' },
  { path: /^\/love$/, render: renderLove, nav: '/love' },
  { path: /^\/youtube$/, render: renderYouTubeStudio, nav: '/youtube' },
  { path: /^\/media$/, render: renderMediaLibrary, nav: '/media' },
  { path: /^\/brand$/, render: renderBrand, nav: '/brand' },
  { path: /^\/bio$/, render: renderBio, nav: '/bio' },
  { path: /^\/workflows$/, render: renderWorkflows, nav: '/workflows' },
  { path: /^\/workflows\/(\d+)$/, render: renderWorkflow, nav: '/workflows' },
  { path: /^\/repurpose$/, render: renderRepurpose, nav: '/repurpose' },
  { path: /^\/(linkedin|instagram|x|tiktok|threads)$/, render: renderPlatform, nav: null }, // nav = the platform
  { path: /^\/posts\/(\d+)$/, render: renderPost, nav: '' },                // set by the page
];

const NAV = NAV_ITEMS;

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
  const was = document.body.classList.contains('nav-open');
  document.body.classList.toggle('nav-open', open);
  state.shell?.drawerToggle.setAttribute('aria-expanded', String(open));
  // Drawer mode (phones and tablets): the page behind can't be tabbed into while it's open,
  // focus goes into the drawer, and back to the menu button when it closes.
  const main = document.querySelector('.main-col');
  if (main) main.inert = open && window.matchMedia('(max-width: 1199px)').matches;
  if (open && !was) setTimeout(() => document.querySelector('.sidebar .nav-item.active, .sidebar .nav-item')?.focus(), 60);
  if (!open && was && window.matchMedia('(max-width: 1199px)').matches) state.shell?.drawerToggle.focus();
}
document.addEventListener('keydown', (e) => {
  if (e.key === 'Escape' && document.body.classList.contains('nav-open')) openDrawer(false);
});

function buildShell() {
  const label = (text) => h('span', { class: 'nav-label' }, text);
  const hidden = new Set(prefs().nav_hidden || []);
  const shown = NAV.filter((item) => item.header || (!hidden.has(item.to) && (!item.love || loveOn())))
    .filter((item, i, all) => !item.header || (all[i + 1] && !all[i + 1].header));
  const navLinks = shown.map((item) => item.header
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
      h('nav', { class: 'nav', onclick: (e) => { if (e.target.closest('a')) openDrawer(false); } }, navLinks),
      h('div', { class: 'sidebar-footer' }, collapseBtn)),
    h('div', { class: 'nav-backdrop', onclick: () => openDrawer(false) }),
    h('div', { class: 'main-col' },
      appHeader(drawerToggle),
      content));

  applyNavPref();
  return { el: shell, content, syncToggle, drawerToggle };
}

/** The top bar on every page: search (opens Ctrl K), notifications, Copilot and her menu. */
function appHeader(drawerToggle) {
  const copilotBtn = h('button', {
    class: 'hdr-copilot', type: 'button', 'aria-pressed': 'false', title: 'Copilot (Ctrl+I)',
    onclick: () => toggleCopilot(),
  }, h('span', { class: 'hdr-copilot-mark', html: COPILOT_MARK }), h('span', { class: 'hdr-label' }, 'Copilot'));
  window.addEventListener('copilot:state', (e) => copilotBtn.setAttribute('aria-pressed', String(!!e.detail.open)));

  const name = (window.__studioName || state.user?.name || '').trim();
  const avatar = h('button', { class: 'hdr-avatar', type: 'button', 'aria-haspopup': 'true', 'aria-expanded': 'false', 'aria-label': 'Your menu', title: name },
    (name[0] || '♥').toUpperCase());
  let menu = null;
  const closeMenu = () => { menu?.remove(); menu = null; avatar.setAttribute('aria-expanded', 'false'); };
  avatar.addEventListener('click', (e) => {
    e.stopPropagation();
    if (menu) { closeMenu(); return; }
    menu = h('div', { class: 'hdr-menu', role: 'menu', onclick: (ev) => ev.stopPropagation() },
      h('div', { class: 'hdr-menu-head' }, h('strong', {}, name || 'You'), h('span', { class: 'muted small' }, state.user?.email || '')),
      themeSwitch(),
      link('/love', { class: 'hdr-menu-item', role: 'menuitem', onclick: closeMenu }, icon('heart', 15), 'For you'),
      link('/settings', { class: 'hdr-menu-item', role: 'menuitem', onclick: closeMenu }, icon('gear', 15), 'Settings'),
      h('button', { class: 'hdr-menu-item', role: 'menuitem', type: 'button', onclick: () => { closeMenu(); document.dispatchEvent(new KeyboardEvent('keydown', { key: '?' })); } }, icon('key', 15), 'Keyboard shortcuts'),
      h('button', { class: 'hdr-menu-item', role: 'menuitem', type: 'button', onclick: () => { closeMenu(); startTour(); } }, icon('play', 15), 'Take the tour'),
      (() => { const b = installButton((t) => t); b.className = 'hdr-menu-item'; b.setAttribute('role', 'menuitem'); return b; })(),
      h('button', { class: 'hdr-menu-item danger', role: 'menuitem', type: 'button', onclick: () => { closeMenu(); logout(); } }, icon('logout', 15), 'Log out'));
    avatar.parentElement.append(menu);
    avatar.setAttribute('aria-expanded', 'true');
    menu.querySelector('a, button.hdr-menu-item')?.focus();
  });
  document.addEventListener('click', closeMenu);
  document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && menu) { closeMenu(); avatar.focus(); } });

  return h('header', { class: 'app-header' },
    h('div', { class: 'hdr-phone' }, drawerToggle, link('/', { class: 'mobile-brand' }, h('span', { class: 'logo' }, '🎬'), h('span', { class: 'hdr-label' }, 'Creator Studio'))),
    h('button', { class: 'hdr-search', type: 'button', onclick: openPalette, 'aria-label': 'Search (Ctrl+K)' },
      icon('search', 16), h('span', { class: 'hdr-search-text' }, 'Search scripts, posts, ideas…'), h('kbd', {}, 'Ctrl K')),
    megaMenus(),
    h('div', { class: 'hdr-actions' },
      notificationsButton(),
      copilotBtn,
      h('div', { class: 'hdr-avatar-wrap' }, avatar)));
}

/**
 * Mega menus: a tab per section in the header; hover (with a short intent delay), click or
 * keyboard (Enter / ↓) opens a full-width panel with the section's pages grouped into columns,
 * each with a one-line description, live counts, and quick actions.
 */
function megaMenus() {
  const byTo = Object.fromEntries(NAV_ITEMS.filter((i) => i.to).map((i) => [i.to, i]));
  const hiddenNav = new Set(prefs().nav_hidden || []);
  const visible = (to) => byTo[to] && !hiddenNav.has(to) && (!byTo[to].love || loveOn());
  const panel = h('div', { class: 'mega', id: 'mega-panel', hidden: true, role: 'region' });
  let open = null;
  let timer = null;
  let counts = null;
  const tabs = [];

  const close = (focusTab = false) => {
    clearTimeout(timer);
    if (!open) return;
    const t = tabs.find((x) => x.sec === open);
    t?.btn.setAttribute('aria-expanded', 'false');
    panel.classList.remove('open');
    panel.hidden = true;
    if (focusTab) t?.btn.focus();
    open = null;
  };
  const links = () => [...panel.querySelectorAll('a')];

  function fill(sec) {
    const badges = Object.assign({}, ...sec.groups.map((g) => g.badges || {}));
    const item = (to, compact) => {
      const it = byTo[to];
      const n = counts && badges[to] ? counts[badges[to]] : 0;
      return link(to, { class: `mega-link${compact ? ' compact' : ''}${location.pathname === to ? ' current' : ''}`, onclick: () => close() },
        h('span', { class: 'mega-ico' }, icon(it.icon, compact ? 16 : 18)),
        h('span', { class: 'mega-text' }, h('strong', {}, it.label, n ? h('span', { class: 'mega-badge' }, n) : null), compact ? null : h('span', { class: 'muted small' }, it.desc)));
    };
    const groups = sec.groups.map((g) => ({ ...g, items: g.items.filter(visible) })).filter((g) => g.items.length);
    panel.replaceChildren(h('div', { class: 'mega-inner' },
      h('div', { class: `mega-cols cols-${groups.length}` }, groups.map((g) => h('div', { class: 'mega-col' },
        h('p', { class: 'mega-title' }, g.title),
        h('div', { class: g.compact ? 'mega-chips' : 'mega-list' }, g.items.map((to) => item(to, g.compact)))))),
      h('aside', { class: 'mega-side' },
        h('p', { class: 'mega-title' }, 'Quick actions'),
        sec.actions.filter((a) => !a.love || loveOn()).map((a) => link(a.to, { class: 'mega-action', onclick: () => close() }, icon(a.icon, 15), a.label, icon('arrow', 13))),
        sec.id === 'social' && counts ? h('div', { class: 'mega-stats' },
          h('span', {}, h('strong', {}, counts.pending), ' to approve'), h('span', {}, h('strong', {}, counts.inbox), ' to answer')) : null)));
  }

  function show(sec, focusFirst = false) {
    clearTimeout(timer);
    if (open !== sec) {
      tabs.forEach((t) => t.btn.setAttribute('aria-expanded', String(t.sec === sec)));
      open = sec;
      fill(sec);
      panel.hidden = false;
      requestAnimationFrame(() => panel.classList.add('open'));
      if (sec.id === 'social' && !counts) {
        api('GET', 'agents/notifications').then((r) => { counts = { pending: r.pending, inbox: r.inbox }; if (open === sec) fill(sec); }).catch(() => {});
      }
    }
    if (focusFirst) links()[0]?.focus();
  }

  for (const sec of MEGA) {
    if (!sec.groups.some((g) => g.items.some(visible))) continue;
    const btn = h('button', { class: 'mega-tab', type: 'button', 'aria-expanded': 'false', 'aria-controls': 'mega-panel' }, sec.label, icon('chevron-down', 14));
    btn.addEventListener('mouseenter', () => { clearTimeout(timer); timer = setTimeout(() => show(sec), open ? 0 : 110); });
    btn.addEventListener('mouseleave', () => { clearTimeout(timer); timer = setTimeout(() => close(), 220); });
    btn.addEventListener('click', (e) => { e.stopPropagation(); open === sec ? close() : show(sec); });
    btn.addEventListener('keydown', (e) => {
      if (e.key === 'ArrowDown' || e.key === 'Enter' || e.key === ' ') { e.preventDefault(); show(sec, true); }
      if (e.key === 'Escape') close();
      const i = tabs.findIndex((t) => t.btn === btn);
      if (e.key === 'ArrowRight') tabs[(i + 1) % tabs.length].btn.focus();
      if (e.key === 'ArrowLeft') tabs[(i - 1 + tabs.length) % tabs.length].btn.focus();
    });
    tabs.push({ sec, btn });
  }
  panel.addEventListener('mouseenter', () => clearTimeout(timer));
  panel.addEventListener('mouseleave', () => { timer = setTimeout(() => close(), 220); });
  panel.addEventListener('click', (e) => e.stopPropagation());
  panel.addEventListener('keydown', (e) => {
    const all = links();
    const i = all.indexOf(document.activeElement);
    if (e.key === 'Escape') { e.preventDefault(); close(true); }
    if (e.key === 'ArrowDown' || e.key === 'ArrowRight') { e.preventDefault(); all[(i + 1) % all.length]?.focus(); }
    if (e.key === 'ArrowUp' || e.key === 'ArrowLeft') { e.preventDefault(); all[(i - 1 + all.length) % all.length]?.focus(); }
  });
  document.addEventListener('click', () => close());
  window.addEventListener('popstate', () => close());

  const current = () => {
    const path = location.pathname;
    tabs.forEach((t) => t.btn.classList.toggle('current', t.sec.groups.some((g) => g.items.some((to) => to === path || (to !== '/' && path.startsWith(`${to}/`))))));
  };
  window.addEventListener('popstate', current);
  current();

  return h('div', { class: 'mega-wrap' }, h('nav', { class: 'mega-tabs', 'aria-label': 'Sections' }, tabs.map((t) => t.btn)), panel);
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
  stopNotifications();
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
    document.title = heading ? `${heading} · Creator Studio` : (view.querySelector('.wf-name')?.value ? `${view.querySelector('.wf-name').value} · Creator Studio` : 'Creator Studio');
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
    renderAuth(root, status.initialized, status.welcome, async (user) => {
      state.user = user;
      // Fetch the love-notes config and tour state first, so the sidebar is built once with them.
      const st = await api('GET', 'auth/status').catch(() => ({}));
      setLove(st.love);
      applyPrefs(st.prefs);
      window.__studioName = st.love?.name || user?.name || '';
      state.shell = null;
      mountCopilot();
      startReminders();
      await route();
      // A brand-new studio gets the tour; a returning login only if it was never finished.
      if (st.tour_done === false) startTour({ onDone: dailyHello });
      else dailyHello();
      checkLove();
    });
    return;
  }
  state.user = status.user;
  setLove(status.love);
  applyPrefs(status.prefs);
  window.__studioName = status.love?.name || status.user?.name || '';
  mountCopilot();
  startReminders();
  await route();
  if (status.tour_done === false) startTour({ onDone: dailyHello });
  else dailyHello();
  checkLove();
}

// Sidebar items hidden or shown again in Settings → Make it yours: rebuild the shell.
window.addEventListener('prefs:nav', () => { state.shell = null; route(); });

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
installShortcutsHelp();
registerServiceWorker();
boot();
