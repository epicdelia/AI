// Creator Studio entry point: auth gate, app shell and routing.
// One dashboard for everyone, shaped by role (app/Enums/Role.php): she gets the studio, a client
// gets their own projects. Pages load only when opened, so a client never downloads studio code.
import { h, api, clear, link, navigate, setUnauthorizedHandler, toast, loading } from './core.js';
import { icon, brandMark } from './icons.js';
import { installPalette, openPalette } from './palette.js';
import { startTour } from './tour.js';
import { themeSwitch } from './theme.js';
import { setLove, dailyHello, heartButton, loveOn } from './love.js';
import { pageSkeleton } from './skeleton.js';
import { startReminders, stopReminders } from './queue.js';
import { mountCopilot, unmountCopilot, resetCopilotContext, pageRendered, toggleCopilot, COPILOT_MARK } from './copilot.js';
import { renderAuth, renderVerifyEmail, renderTwoFactorSetup } from './pages/auth.js';
import { notificationsButton, stopNotifications } from './notifications.js';
import { registerServiceWorker, installButton, installShortcutsHelp } from './pwa.js';
import { applyPrefs, prefs } from './prefs.js';
import { navFor, can } from './nav.js';

/** A page module, fetched the first time its page opens. */
const lazy = (load, name) => async (...args) => (await load())[name](...args);
const page = {
  dashboard: () => import('./pages/dashboard.js'),
  breakouts: () => import('./pages/breakouts.js'),
  audience: () => import('./pages/audience.js'),
  ideas: () => import('./pages/ideas.js'),
  scripts: () => import('./pages/scripts.js'),
  goals: () => import('./pages/goals.js'),
  flow: () => import('./pages/flow.js'),
  settings: () => import('./pages/settings.js'),
  tools: () => import('./pages/tools.js'),
  library: () => import('./pages/library.js'),
  chat: () => import('./pages/chat.js'),
  social: () => import('./pages/social.js'),
  calendar: () => import('./pages/calendar.js'),
  analytics: () => import('./pages/analytics.js'),
  connections: () => import('./pages/connections.js'),
  agents: () => import('./pages/agents.js'),
  inbox: () => import('./pages/inbox.js'),
  workflows: () => import('./pages/workflows.js'),
  love: () => import('./pages/love.js'),
  ytstudio: () => import('./pages/ytstudio.js'),
  mediapage: () => import('./pages/mediapage.js'),
  brand: () => import('./pages/brand.js'),
  bio: () => import('./pages/bio.js'),
  deals: () => import('./pages/deals.js'),
  mediakit: () => import('./pages/mediakit.js'),
  snippets: () => import('./pages/snippets.js'),
  landing: () => import('./pages/landing.js'),
  website: () => import('./pages/website.js'),
  review: () => import('./landing-proof.js'),
  dealRoom: () => import('./deal-room.js'),
  client: () => import('./pages/client.js'),
  account: () => import('./pages/account.js'),
  clients: () => import('./pages/clients.js'),
};
const renderBrandKit = lazy(page.mediakit, 'renderBrandKit');
const renderReview = lazy(page.review, 'renderReview');
const renderDealRoom = lazy(page.dealRoom, 'renderDealRoom');
const renderLanding = lazy(page.landing, 'renderLanding');

// `ability`: the Role ability needed (none = 'studio'; 'any' = everyone signed in). The API checks
// the same abilities; this only decides what the dashboard offers.
const ROUTES = [
  // "/" is each role's home: her dashboard, or a client's overview.
  { path: /^\/$/, ability: 'any', render: (view) => (can(state.user, 'studio') ? lazy(page.dashboard, 'renderDashboard') : lazy(page.client, 'renderClientHome'))(view), nav: '/' },
  { path: /^\/requests$/, ability: 'portal.requests', render: lazy(page.client, 'renderRequests'), nav: '/requests' },
  { path: /^\/requests\/new$/, ability: 'portal.requests', render: lazy(page.client, 'renderNewRequest'), nav: '/requests/new' },
  { path: /^\/requests\/(\d+)$/, ability: 'portal.requests', render: lazy(page.client, 'renderRequest'), nav: '/requests' },
  { path: /^\/brand-vault$/, ability: 'portal.requests', render: lazy(page.client, 'renderBrandVault'), nav: '/brand-vault' },
  { path: /^\/billing$/, ability: 'portal.billing', render: lazy(page.client, 'renderBilling'), nav: '/billing' },
  { path: /^\/account$/, ability: 'account', render: lazy(page.account, 'renderAccount'), nav: '/account' },
  { path: /^\/clients$/, ability: 'clients.manage', render: lazy(page.clients, 'renderClients'), nav: '/clients' },
  { path: /^\/clients\/(\d+)$/, ability: 'clients.manage', render: lazy(page.clients, 'renderClient'), nav: '/clients' },
  { path: /^\/review\/([A-Za-z0-9]{20,64})$/, ability: 'any', render: renderReview, nav: '/website' },
  { path: /^\/deal\/([A-Za-z0-9]{20,64})$/, ability: 'any', render: renderDealRoom, nav: '/deals' },
  { path: /^\/kit\/([A-Za-z0-9]{20,64})$/, ability: 'any', render: renderBrandKit, nav: '/mediakit' },

  // The studio (Role::STUDIO).
  { path: /^\/breakouts$/, render: lazy(page.breakouts, 'renderBreakouts'), nav: '/breakouts' },
  { path: /^\/channel$/, render: lazy(page.audience, 'renderAudience'), nav: '/channel' },
  { path: /^\/ideas$/, render: lazy(page.ideas, 'renderIdeas'), nav: '/ideas' },
  { path: /^\/scripts$/, render: lazy(page.scripts, 'renderScripts'), nav: '/scripts' },
  { path: /^\/scripts\/(\d+)$/, render: lazy(page.scripts, 'renderScript'), nav: '/scripts' },
  { path: /^\/goals$/, render: lazy(page.goals, 'renderGoals'), nav: '/goals' },
  { path: /^\/flow$/, render: lazy(page.flow, 'renderFlow'), nav: '/flow' },
  { path: /^\/settings$/, render: lazy(page.settings, 'renderSettings'), nav: '/settings' },
  { path: /^\/tools$/, render: lazy(page.tools, 'renderTools'), nav: '/tools' },
  { path: /^\/tools\/([\w-]+)$/, render: lazy(page.tools, 'renderTools'), nav: '/tools' },
  { path: /^\/library$/, render: lazy(page.library, 'renderLibrary'), nav: '/library' },
  { path: /^\/chat$/, render: lazy(page.chat, 'renderChat'), nav: '/chat' },
  { path: /^\/chat\/(\d+)$/, render: lazy(page.chat, 'renderChat'), nav: '/chat' },
  { path: /^\/calendar$/, render: lazy(page.calendar, 'renderCalendar'), nav: '/calendar' },
  { path: /^\/analytics$/, render: lazy(page.analytics, 'renderAnalytics'), nav: '/analytics' },
  { path: /^\/deals$/, render: lazy(page.deals, 'renderDeals'), nav: '/deals' },
  { path: /^\/mediakit$/, render: lazy(page.mediakit, 'renderMediaKit'), nav: '/mediakit' },
  { path: /^\/snippets$/, render: lazy(page.snippets, 'renderSnippets'), nav: '/snippets' },
  { path: /^\/website$/, render: lazy(page.website, 'renderWebsite'), nav: '/website' },
  { path: /^\/landing$/, render: (view) => renderLanding(view, { user: state.user, onAuthClick: () => navigate('/') }), nav: '/landing' },
  { path: /^\/connections$/, render: lazy(page.connections, 'renderConnections'), nav: '/connections' },
  { path: /^\/agents$/, render: lazy(page.agents, 'renderAgents'), nav: '/agents' },
  { path: /^\/agents\/(\d+)$/, render: lazy(page.agents, 'renderAgent'), nav: '/agents' },
  { path: /^\/inbox$/, render: lazy(page.inbox, 'renderInbox'), nav: '/inbox' },
  { path: /^\/love$/, render: lazy(page.love, 'renderLove'), nav: '/love' },
  { path: /^\/youtube$/, render: lazy(page.ytstudio, 'renderYouTubeStudio'), nav: '/youtube' },
  { path: /^\/media$/, render: lazy(page.mediapage, 'renderMediaLibrary'), nav: '/media' },
  { path: /^\/brand$/, render: lazy(page.brand, 'renderBrand'), nav: '/brand' },
  { path: /^\/bio$/, render: lazy(page.bio, 'renderBio'), nav: '/bio' },
  { path: /^\/workflows$/, render: lazy(page.workflows, 'renderWorkflows'), nav: '/workflows' },
  { path: /^\/workflows\/(\d+)$/, render: lazy(page.workflows, 'renderWorkflow'), nav: '/workflows' },
  { path: /^\/repurpose$/, render: lazy(page.social, 'renderRepurpose'), nav: '/repurpose' },
  { path: /^\/(linkedin|instagram|x|tiktok|threads)$/, render: lazy(page.social, 'renderPlatform'), nav: null }, // nav = the platform
  { path: /^\/posts\/(\d+)$/, render: lazy(page.social, 'renderPost'), nav: '' },                // set by the page
];

const allowed = (r) => r.ability === 'any' || can(state.user, r.ability);
const isStudio = () => can(state.user, 'studio');
const myNav = () => navFor(state.user);

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
  const drawerMode = () => window.matchMedia('(max-width: 1199px)').matches || document.documentElement.dataset.nav !== 'sidebar';
  if (main) main.inert = open && drawerMode();
  if (open && !was) setTimeout(() => document.querySelector('.sidebar .nav-item.active, .sidebar .nav-item')?.focus(), 60);
  if (!open && was && drawerMode()) state.shell?.drawerToggle.focus();
}
document.addEventListener('keydown', (e) => {
  if (e.key === 'Escape' && document.body.classList.contains('nav-open')) openDrawer(false);
  // Ctrl+B / Cmd+B toggles sidebar (or off-canvas drawer in top/mobile nav mode)
  if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'b' && !e.shiftKey && !e.altKey) {
    const isEditing = ['INPUT', 'TEXTAREA'].includes(e.target.tagName) || e.target.isContentEditable;
    if (!isEditing) {
      e.preventDefault();
      const drawerMode = window.matchMedia('(max-width: 1199px)').matches || document.documentElement.dataset.nav !== 'sidebar';
      if (drawerMode) {
        openDrawer(!document.body.classList.contains('nav-open'));
      } else {
        toggleNavCollapsed();
      }
    }
  }
});

function buildShell() {
  const label = (text) => h('span', { class: 'nav-label' }, text);
  const hidden = new Set(prefs().nav_hidden || []);
  const shown = myNav().items.filter((item) => item.header || (!hidden.has(item.to) && (!item.love || loveOn())))
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

  const shell = h('div', { class: `app-shell${isStudio() ? '' : ' is-client'}` },
    h('aside', { class: 'sidebar', id: 'sidebar' },
      h('div', { class: 'brand' },
        link('/', { class: 'brand-link', onclick: () => openDrawer(false), 'aria-label': 'Creator Studio Dashboard' },
          brandMark(20),
          h('div', { class: 'brand-text nav-label' },
            h('span', { class: 'brand-title' }, 'Creator Studio'),
            h('span', { class: 'brand-badge' }, isStudio() ? 'PRO' : 'CLIENT'))),
        h('div', { class: 'brand-actions' },
          isStudio() ? heartButton() : null,
          h('button', { class: 'btn ghost sm drawer-close', 'aria-label': 'Close menu', onclick: () => openDrawer(false) }, icon('x')))),
      h('nav', { class: 'nav', onclick: (e) => { if (e.target.closest('a')) openDrawer(false); } }, navLinks),
      h('div', { class: 'sidebar-footer' }, collapseBtn)),
    h('div', { class: 'nav-backdrop', onclick: () => openDrawer(false) }),
    h('div', { class: 'main-col' },
      appHeader(drawerToggle),
      content));

  applyNavPref();
  return { el: shell, content, syncToggle, drawerToggle };
}

/** Opening the studio on "/" goes to the page she chose (once per visit, so Dashboard still works). */
function openStartPage() {
  const start = prefs().start_page;
  let seen = false;
  try { seen = sessionStorage.getItem('studio-started') === '1'; sessionStorage.setItem('studio-started', '1'); } catch { /* fine */ }
  if (!seen && start && start !== '/' && location.pathname === '/' && myNav().items.some((n) => n.to === start)) history.replaceState({}, '', start);
}

/** The top bar on every page: search (opens Ctrl K), notifications, Copilot and her menu. */
function appHeader(drawerToggle) {
  const copilotBtn = h('button', {
    class: 'hdr-copilot', type: 'button', 'aria-pressed': 'false', title: 'Copilot (Ctrl+I)',
    onclick: () => toggleCopilot(),
  }, h('span', { class: 'hdr-copilot-mark', html: COPILOT_MARK }), h('span', { class: 'hdr-label' }, 'Copilot'));
  window.addEventListener('copilot:state', (e) => copilotBtn.setAttribute('aria-pressed', String(!!e.detail.open)));

  const name = (window.__studioName || state.user?.name || '').trim();
  const avatar = h('button', { class: 'hdr-avatar', type: 'button', 'aria-haspopup': 'true', 'aria-expanded': 'false', 'aria-label': 'Your menu', title: name });
  const drawAvatar = () => avatar.replaceChildren(prefs().avatar ? h('img', { src: `/api/me/avatar?${Date.now()}`, alt: '' }) : (name[0] || 'S').toUpperCase());
  drawAvatar();
  window.addEventListener('prefs:avatar', drawAvatar);
  let menu = null;
  const closeMenu = () => { menu?.remove(); menu = null; avatar.setAttribute('aria-expanded', 'false'); };
  avatar.addEventListener('click', (e) => {
    e.stopPropagation();
    if (menu) { closeMenu(); return; }
    menu = h('div', { class: 'hdr-menu', role: 'menu', onclick: (ev) => ev.stopPropagation() },
      h('div', { class: 'hdr-menu-head' }, h('strong', {}, name || 'You'), h('span', { class: 'muted small' }, state.user?.email || '')),
      themeSwitch(),
      link('/account', { class: 'hdr-menu-item', role: 'menuitem', onclick: closeMenu }, icon('lock', 15), 'Account & security'),
      isStudio() ? [
        link('/love', { class: 'hdr-menu-item', role: 'menuitem', onclick: closeMenu }, icon('heart', 15), 'For you'),
        link('/settings', { class: 'hdr-menu-item', role: 'menuitem', onclick: closeMenu }, icon('gear', 15), 'Settings'),
        h('button', { class: 'hdr-menu-item', role: 'menuitem', type: 'button', onclick: () => { closeMenu(); document.dispatchEvent(new KeyboardEvent('keydown', { key: '?' })); } }, icon('key', 15), 'Keyboard shortcuts'),
        h('button', { class: 'hdr-menu-item', role: 'menuitem', type: 'button', onclick: () => { closeMenu(); startTour(); } }, icon('play', 15), 'Take the tour'),
      ] : null,
      (() => { const b = installButton((t) => t); b.className = 'hdr-menu-item'; b.setAttribute('role', 'menuitem'); return b; })(),
      h('button', { class: 'hdr-menu-item danger', role: 'menuitem', type: 'button', onclick: () => { closeMenu(); logout(); } }, icon('logout', 15), 'Log out'));
    avatar.parentElement.append(menu);
    avatar.setAttribute('aria-expanded', 'true');
    menu.querySelector('a, button.hdr-menu-item')?.focus();
  });
  document.addEventListener('click', closeMenu);
  document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && menu) { closeMenu(); avatar.focus(); } });

  return h('header', { class: 'app-header' },
    h('div', { class: 'hdr-phone' }, drawerToggle, link('/', { class: 'mobile-brand' }, brandMark(18))),
    isStudio() ? h('button', { class: 'hdr-search', type: 'button', onclick: openPalette, 'aria-label': 'Search (Ctrl+K)' },
      icon('search', 16), h('span', { class: 'hdr-search-text' }, 'Search scripts, posts, ideas…'), h('kbd', {}, 'Ctrl K')) : null,
    megaMenus(),
    h('div', { class: 'hdr-actions' },
      isStudio() ? notificationsButton() : null,
      isStudio() ? copilotBtn : null,
      h('div', { class: 'hdr-avatar-wrap' }, avatar)));
}

/**
 * Mega menus: a tab per section in the header; hover (with a short intent delay), click or
 * keyboard (Enter / ↓) opens a full-width panel with the section's pages grouped into columns,
 * each with a one-line description, live counts, and quick actions.
 */
function megaMenus() {
  const { items, mega } = myNav();
  const byTo = Object.fromEntries(items.filter((i) => i.to).map((i) => [i.to, i]));
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
        api('GET', 'agents/notifications').then((r) => { counts = { pending: r.pending, inbox: r.inbox }; if (open === sec) fill(sec); }).catch(() => { });
      }
    }
    if (focusFirst) links()[0]?.focus();
  }

  for (const sec of mega) {
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

/** /login, /auth or ?login=1 ask for the sign-in screen instead of the public landing page. */
const isLoginPath = () => location.pathname === '/login' || location.pathname === '/auth' || location.search.includes('login=1');

async function route() {
  // Signed out: links on the landing page (e.g. "Studio Suite Sign In") still need to work.
  if (!state.user) { state.guestNav?.(); return; }
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
    if (m && allowed(r)) { match = r; params = m.slice(1); break; }
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
    window.scrollTo({ top: 0, behavior: 'instant' });
    content.scrollTop = 0;
  }, 120);
  const settle = () => {
    clearTimeout(skTimer);
    content.inert = false;
    window.scrollTo({ top: 0, behavior: 'instant' });
    content.scrollTop = 0;
  };

  if (!match) {
    settle();
    clear(content).append(h('div', { class: 'empty' },
      h('div', { class: 'empty-icon' }, icon('compass', 30)), h('h3', {}, 'Page not found'),
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

    const onLoginSuccess = async () => {
      state.guestCleanup?.();
      state.guestNav = state.guestCleanup = null;
      // Signed in from /login: land on the dashboard (or her start page), not "Page not found".
      if (isLoginPath()) history.replaceState({}, '', '/');
      // Fetch the role, love-notes config and tour state first, so the sidebar is built once with them.
      const st = await api('GET', 'auth/status').catch(() => ({}));
      if (!st.user) { boot(); return; }
      await enter(st);
    };

    const showAuth = () => {
      state.guestCleanup?.();
      state.guestCleanup = null;
      state.guestView = 'auth';
      renderAuth(root, status.initialized, status.welcome, onLoginSuccess, showLanding);
    };

    const showLanding = async () => {
      state.guestCleanup?.();
      state.guestCleanup = null;
      state.guestView = 'landing';
      const view = h('div');
      const cleanup = await renderLanding(view, { onAuthClick: showAuth, user: null });
      if (state.guestView !== 'landing') { cleanup?.(); return; } // they moved on while it loaded
      state.guestCleanup = cleanup;
      clear(root).append(view);
    };

    // Explicit login (path or query) shows sign-in; otherwise the public Tech Unicorn landing page.
    // Back/forward and in-page links switch between the two without re-rendering on #anchors.
    // A brand's media kit link (/kit/<token>) opens signed out, as its own page.
    // A client's review link (/review/<token>) works the same way.
    const showPublic = (render, token, gone) => {
      state.guestCleanup?.();
      state.guestCleanup = null;
      const view = h('div', { class: 'view public-kit' });
      clear(root).append(h('main', { class: 'content-area' }, view));
      render(view, token).catch(() => clear(view).append(h('div', { class: 'empty' }, h('h3', {}, gone), link('/', { class: 'btn primary' }, 'Visit the website'))));
    };
    const showKit = (token) => showPublic(renderBrandKit, token, 'This media kit link isn’t active.');
    state.guestNav = () => {
      const kit = location.pathname.match(/^\/kit\/([A-Za-z0-9]{20,64})$/);
      if (kit) { state.guestView = 'kit'; showKit(kit[1]); return; }
      const review = location.pathname.match(/^\/review\/([A-Za-z0-9]{20,64})$/);
      if (review) { state.guestView = 'review'; showPublic(renderReview, review[1], 'This review link isn’t active.'); return; }
      const room = location.pathname.match(/^\/deal\/([A-Za-z0-9]{20,64})$/);
      if (room) { state.guestView = 'deal'; showPublic(renderDealRoom, room[1], 'This proposal link isn’t active.'); return; }
      const want = isLoginPath() ? 'auth' : 'landing';
      if (want !== state.guestView) (want === 'auth' ? showAuth : showLanding)();
    };
    state.guestView = null;
    state.guestNav();
    return;
  }
  await enter(status);
}

/**
 * Signed in: a client confirms their email first, a role that needs two-factor (admins) turns it
 * on first, then the dashboard for their role. Her studio extras (Copilot, reminders, love notes,
 * the tour) are hers alone.
 */
async function enter(status) {
  state.user = status.user;
  state.shell = null;
  const user = status.user;
  if (!user.email_verified) {
    renderVerifyEmail(root, user, logout);
    return;
  }
  if (user.two_factor?.required && !user.two_factor?.enabled) {
    renderTwoFactorSetup(root, user, { onDone: boot, onLogout: logout });
    return;
  }
  if (!isStudio()) {
    window.__studioName = user.name || '';
    await route();
    return;
  }
  setLove(status.love);
  applyPrefs(status.prefs);
  openStartPage();
  window.__studioName = status.love?.name || user.name || '';
  mountCopilot();
  startReminders();
  await route();
  // A brand-new studio gets the tour; a returning login only if it was never finished.
  if (status.tour_done === false) startTour({ onDone: dailyHello });
  else dailyHello();
  page.love().then((m) => m.checkLove()).catch(() => {});
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
