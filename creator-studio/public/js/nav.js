// The sidebar: sections and pages, in order. Items she hides (Settings → Make it yours) are
// filtered out when the shell is built; "love" items only show while love notes are on.
// `desc` is the one-liner shown in the header's mega menus.
// `ability` is the Role ability (app/Enums/Role.php) a user needs to see the item; none means
// 'studio' (hers alone). The API enforces the same abilities, so hiding is never the only guard.
export const NAV_ITEMS = [
  // Community members & followers: starter kits, blueprints, AMA questions, perks
  { header: 'Community', ability: 'portal.community' },
  { to: '/community', label: 'Community Hub', icon: 'sparkle', ability: 'portal.community', desc: 'Starter kits, blueprints, AMA and perks' },

  // Clients: their own requests, money and account.
  { header: 'Your projects', ability: 'portal.requests' },
  { to: '/', label: 'Overview', icon: 'home', ability: 'portal.requests', desc: 'What’s happening and what needs you' },
  { to: '/requests', label: 'My requests', icon: 'briefcase', ability: 'portal.requests', desc: 'Every request, proposal and deliverable' },
  { to: '/requests/new', label: 'New request', icon: 'plus', ability: 'portal.requests', desc: 'Ask for something new' },
  { to: '/brand-vault', label: 'Brand Vault', icon: 'sliders', ability: 'portal.requests', desc: 'Brand assets, guidelines and tracking links' },
  { to: '/billing', label: 'Billing', icon: 'credit-card', ability: 'portal.billing', desc: 'Payments, invoices and retainers' },
  { header: 'You', ability: 'portal.requests' },
  { to: '/account', label: 'Account & security', icon: 'lock', ability: 'portal.requests', desc: 'Profile, password and two-factor sign-in' },

  // Her studio.
  { header: 'Today' },
  { to: '/', label: 'Dashboard', icon: 'home', desc: 'Your day, what’s due and the week at a glance' },
  { to: '/needs-you', label: 'Needs you', icon: 'alert', ability: 'studio.private', desc: 'Everything waiting on you, across every role' },
  { to: '/flow', label: 'Dictate', icon: 'mic', desc: 'Talk it out; it becomes a clean note' },
  { to: '/love', label: 'For you', ability: 'studio.private', icon: 'heart', love: true, desc: 'Letters, dates and little moments' },
  { header: 'YouTube' },
  { to: '/breakouts', label: 'Breakouts', icon: 'trend', desc: 'Videos beating their channel’s usual' },
  { to: '/ideas', label: 'Ideas', icon: 'bulb', desc: 'Generate, sort and pick your next video' },
  { to: '/scripts', label: 'Scripts', icon: 'doc', desc: 'Write, review and fact-check' },
  { to: '/goals', label: 'Weekly goals', icon: 'target', desc: 'Your plan, streak and mentor check-ins' },
  { to: '/channel', label: 'My channel', icon: 'users', desc: 'Your videos vs your own median' },
  { to: '/youtube', label: 'Upload & test', icon: 'play', desc: 'Upload from here; let titles compete' },
  { header: 'Social' },
  { to: '/calendar', label: 'Calendar', icon: 'calendar', desc: 'Every post on every network' },
  { to: '/analytics', label: 'Analytics', icon: 'chart', desc: 'Reports, best times and goals' },
  { to: '/deals', label: 'Brand deals', ability: 'studio.private', icon: 'briefcase', desc: 'Sponsorship pipeline, deliverables and rates' },
  { to: '/mediakit', label: 'Media kit', ability: 'studio.private', icon: 'file-text', desc: 'Audience reach, demographics and rate card' },
  { to: '/agents', label: 'Agents', icon: 'bot', desc: 'AI that plans, writes and learns' },
  { to: '/workflows', label: 'Workflows', icon: 'flow', desc: 'When this happens, do that' },
  { to: '/inbox', label: 'Inbox', icon: 'inbox', desc: 'Comments and DMs in one place' },
  { to: '/repurpose', label: 'Repurpose', icon: 'repeat', desc: 'One piece, native posts everywhere' },
  { to: '/bio', label: 'Link in bio', ability: 'studio.private', icon: 'link', desc: 'One page for your bios, clicks counted' },
  { to: '/linkedin', label: 'LinkedIn', icon: 'brand:linkedin', desc: 'Posts and carousels' },
  { to: '/instagram', label: 'Instagram', icon: 'brand:instagram', desc: 'Carousels, Reels and Stories' },
  { to: '/x', label: 'X', icon: 'brand:x', desc: 'Posts and threads' },
  { to: '/tiktok', label: 'TikTok', icon: 'brand:tiktok', desc: 'Videos and photo carousels' },
  { to: '/threads', label: 'Threads', icon: 'brand:threads', desc: 'Posts and threads' },
  { header: 'Create' },
  { to: '/website', label: 'Website', ability: 'studio.private', icon: 'chart', desc: 'Funnel, page sections, booking hours and tracked links' },
  { to: '/landing', label: 'Landing page', ability: 'studio.private', icon: 'globe', desc: 'Public portfolio, services and packages' },
  { to: '/snippets', label: 'Code cards', icon: 'code', desc: 'Visual code snippets and terminal cards' },
  { to: '/tools', label: 'Toolkit', icon: 'sparkle', desc: 'Writing templates for every job' },
  { to: '/chat', label: 'Producer chat', icon: 'chat', desc: 'Think out loud with your producer' },
  { to: '/library', label: 'Library', icon: 'book', desc: 'Everything you saved' },
  { to: '/media', label: 'Media', icon: 'image', desc: 'Every image, video and audio file' },
  { header: 'Studio' },
  { to: '/team', label: 'Team & Production', icon: 'users', ability: 'staff.team', desc: 'Editorial pipeline, staff assignments, and QA checklist' },
  { to: '/clients', label: 'Clients', icon: 'users', ability: 'clients.manage', desc: 'Client accounts, their requests and messages' },
  { to: '/community/manage', label: 'Community', icon: 'sparkle', ability: 'studio.private', desc: 'Posts, answers, approvals, Pro tier and warm leads' },
  { to: '/crm', label: 'Contacts', icon: 'users', ability: 'studio.private', desc: 'Leads, clients and sponsors with follow-ups' },
  { to: '/portal-brand', label: 'Portal branding', icon: 'sliders', ability: 'studio.private', desc: 'White-label what clients and members see' },
  { to: '/account', label: 'Account & security', icon: 'lock', desc: 'Your login, password and two-factor sign-in' },
  { to: '/connections', label: 'Connections', ability: 'studio.private', icon: 'plug', desc: 'Sign in to your networks' },
  { to: '/brand', label: 'Brand kit', ability: 'studio.private', icon: 'sliders', desc: 'Colours, font, logo and voice rules' },
  { to: '/settings', label: 'Settings', ability: 'studio.private', icon: 'gear', desc: 'AI, keys, voice and make it yours' },
];

/**
 * The header's mega menus: one per section, links grouped into columns, plus quick actions.
 * `badge` keys read live counts (see app.js megaCounts).
 */
export const MEGA = [
  { id: 'today', label: 'Today', groups: [{ title: 'Today', items: ['/', '/needs-you', '/flow', '/love'] }],
    actions: [{ label: 'Dictate an idea', to: '/flow', icon: 'mic' }, { label: 'Open today’s note', to: '/love', icon: 'heart', love: true }] },
  { id: 'youtube', label: 'YouTube', groups: [
    { title: 'Research', items: ['/breakouts', '/channel'] },
    { title: 'Make', items: ['/ideas', '/scripts', '/goals', '/youtube'] }],
    actions: [{ label: 'Get fresh ideas', to: '/ideas', icon: 'sparkle' }, { label: 'New script', to: '/scripts', icon: 'doc' }] },
  { id: 'social', label: 'Social', groups: [
    { title: 'Plan & publish', items: ['/calendar', '/agents', '/workflows', '/repurpose'] },
    { title: 'Grow & monetize', items: ['/analytics', '/deals', '/mediakit', '/inbox', '/bio'], badges: { '/agents': 'pending', '/inbox': 'inbox' } },
    { title: 'Networks', items: ['/linkedin', '/instagram', '/x', '/tiktok', '/threads'], compact: true }],
    actions: [{ label: 'Plan the week', to: '/agents', icon: 'bot' }, { label: 'Open the calendar', to: '/calendar', icon: 'calendar' }, { label: 'Brand deals', to: '/deals', icon: 'briefcase' }] },
  { id: 'create', label: 'Create', groups: [{ title: 'Create', items: ['/website', '/landing', '/snippets', '/tools', '/chat', '/library', '/media'] }],
    actions: [{ label: 'Website funnel', to: '/website', icon: 'chart' }, { label: 'Preview landing page', to: '/landing', icon: 'globe' }, { label: 'New code card', to: '/snippets', icon: 'code' }] },
  { id: 'studio', label: 'Studio', groups: [{ title: 'Team', items: ['/team', '/clients'] }, { title: 'Studio', items: ['/connections', '/brand', '/settings', '/account'] }],
    actions: [{ label: 'Team pipeline', to: '/team', icon: 'users' }, { label: 'Your clients', to: '/clients', icon: 'users' }] },
  { id: 'projects', label: 'Your projects', ability: 'portal.requests', groups: [{ title: 'Projects', items: ['/', '/requests', '/requests/new', '/billing'] }, { title: 'You', items: ['/account'] }],
    actions: [{ label: 'Ask for something new', to: '/requests/new', icon: 'plus' }, { label: 'Billing', to: '/billing', icon: 'credit-card' }] },
  { id: 'community', label: 'Community', ability: 'portal.community', groups: [{ title: 'Community Hub', items: ['/community'] }, { title: 'Account', items: ['/account'] }],
    actions: [{ label: 'Starter kits & perks', to: '/community', icon: 'sparkle' }] },
];

/** True when the signed-in user's role grants the ability (see app/Enums/Role.php). */
export function can(user, ability) {
  return !!user?.abilities?.includes(ability || 'studio');
}

/** The sidebar items and header menus this user's role may see. */
export function navFor(user) {
  const seen = new Set();
  const items = NAV_ITEMS.filter((i) => {
    if (!can(user, i.ability)) return false;
    if (i.header) return true;
    if (seen.has(i.to)) return false;
    seen.add(i.to);
    return true;
  });
  return { items, mega: MEGA.filter((m) => can(user, m.ability)) };
}
