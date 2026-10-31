// Dashboard: this week's commitment, scoreboard, setup checklist and the latest pipeline items.
import { h, api, link, pill, toast, fmtDay, daysUntil, compact, timeAgo, emptyState } from '../core.js';
import { icon } from '../icons.js';
import { weekDots } from './goals.js';
import { loveCard } from '../love.js';
import { socialStrip } from './social.js';
import { homeComposer } from '../composer.js';
import { dueStrip } from '../queue.js';
import { briefCard } from '../growth.js';
import { arrangeDashboard, customiseDashboard, prefs } from '../prefs.js';
import { loveExtras } from './love.js';
import { websiteCard, reachCard } from '../website-feed.js';
import { speakingCard } from '../speaking.js';
import { aboutCard } from '../about-editor.js';
import { pricingCard } from '../pricing-editor.js';
import { partnersCard } from '../partners-editor.js';
import { marqueeCard } from '../marquee-editor.js';
import { siteHeroCard } from '../hero-editor.js';

const SETUP_LINKS = {
  channel_id: '/settings',
  youtube_api_key: '/settings',
  llm_api_key: '/settings',
  channels: '/breakouts',
  goals: '/goals',
};

function greeting() {
  const hr = new Date().getHours();
  if (hr < 5) return 'Up late';
  if (hr < 12) return 'Good morning';
  if (hr < 18) return 'Good afternoon';
  return 'Good evening';
}

function heroCard(d) {
  const w = d.current_week;
  const total = d.weeks.length;
  let line;
  let sub;
  if (!w) {
    line = 'No weekly plan yet.';
    sub = 'Set a 12-week plan and the mentor keeps score for you.';
  } else {
    const left = daysUntil(w.due_date);
    const when = left > 1 ? `${left} days left` : left === 1 ? 'due tomorrow' : left === 0 ? 'due today' : `${-left} days past due`;
    line = `Week ${w.number} of ${total} · ${when}`;
    sub = w.status === 'shipped'
      ? `Shipped: “${w.video_title || 'this week’s video'}”. Nice.`
      : w.video_title
        ? `This week’s video: “${w.video_title}”.`
        : 'This week’s video isn’t picked yet. That’s the first job.';
  }

  return h('div', { class: 'card hero-card' },
    h('div', { class: 'hero-inner' },
      h('div', {},
        h('p', { class: 'hero-eyebrow' }, `${greeting()}, ${(window.__studioName || d.creator_name || '').split(' ')[0]}`),
        h('h1', {}, line),
        prefs().motto ? h('p', { class: 'hero-motto' }, `“${prefs().motto}”`) : null,
        h('p', { class: 'hero-sub' }, sub),
        h('div', { class: 'row', style: { marginTop: '14px' } },
          link('/flow', { class: 'btn on-hero' }, icon('mic'), 'Dictate an idea'),
          link('/ideas', { class: 'btn on-hero ghost-light' }, icon('sparkle'), 'Get fresh ideas'),
          link('/goals', { class: 'btn on-hero ghost-light' }, icon('target'), w ? 'Scoreboard' : 'Plan my weeks'))),
      w ? h('div', { class: 'hero-week' },
        h('div', { class: 'hero-week-num' }, w.number),
        h('div', { class: 'hero-week-lbl' }, `due ${fmtDay(w.due_date, { weekday: 'short', month: 'short', day: 'numeric' })}`),
        pill(w.status)) : null));
}

function statBox(num, label, tone) {
  return h('div', { class: `card stat-box ${tone || ''}` }, h('div', { class: 'num' }, num), h('div', { class: 'lbl' }, label));
}

function setupCard(checks) {
  const todo = checks.filter((c) => !c.done);
  if (!todo.length) return null;
  const done = checks.length - todo.length;
  return h('div', { class: 'card setup-card' },
    h('div', { class: 'card-header' },
      h('h2', {}, 'Finish setting up'),
      h('span', { class: 'muted' }, `${done} of ${checks.length} done`)),
    h('div', { class: 'progress' }, h('div', { class: 'progress-bar', style: { width: `${(done / checks.length) * 100}%` } })),
    h('ul', { class: 'checklist' }, checks.map((c) => h('li', { class: c.done ? 'done' : '' },
      h('span', { class: 'check' }, c.done ? icon('check', 14) : ''),
      c.done ? h('span', {}, c.label) : link(SETUP_LINKS[c.key] || '/settings', {}, c.label)))));
}

/** The one thing to do next for this week's video (NextStep on the server). */
function nextStepCard(step) {
  if (!step) return null;
  return h('div', { class: 'card next-step' },
    h('div', { class: 'next-step-body' },
      h('p', { class: 'next-step-eyebrow' }, 'Next step'),
      h('h2', {}, step.title),
      step.detail ? h('p', { class: 'muted' }, step.detail) : null),
    link(step.link, { class: 'btn primary' }, step.action, icon('arrow', 15)));
}

/** Intro-call requests and checkouts from the public landing page, waiting in Deals. */
function inboundCard(inb) {
  if (!inb || (!inb.new_inquiries && !inb.awaiting_payment && !inb.paid_this_month)) return null;
  const usd = (n) => `$${Math.round(Number(n) || 0).toLocaleString()}`;
  return h('div', { class: 'card' },
    h('div', { class: 'card-header' },
      h('h2', {}, 'From your website'),
      link('/deals', { class: 'btn ghost sm' }, 'Open deals', icon('arrow', 14))),
    h('div', { class: 'grid grid-3' },
      statBox(inb.new_inquiries, inb.new_inquiries === 1 ? 'new inquiry' : 'new inquiries', 'tone-accent'),
      statBox(usd(inb.awaiting_payment), 'agreed, not paid yet', 'tone-warn'),
      statBox(usd(inb.paid_this_month), 'paid this month', 'tone-good')),
    inb.by_service?.length ? h('div', { class: 'row', style: { flexWrap: 'wrap', gap: '6px', marginTop: '12px' }, 'aria-label': 'Open inquiries by service' },
      h('span', { class: 'muted small' }, 'Asked about:'),
      inb.by_service.map((s) => link('/deals', { class: 'pill' }, `${s.service} · ${s.count}`))) : null,
    inb.latest.length ? h('ul', { class: 'item-list', style: { marginTop: '12px' } }, inb.latest.map((d) => h('li', {},
      link('/deals', { class: 'item-main' },
        h('strong', {}, d.brand_name),
        h('div', { class: 'muted clamp-1' }, `${d.deliverables?.[0]?.service || d.deliverables?.[0]?.format || 'Inquiry'} · ${d.contact_email} · ${timeAgo(d.created_at)}`)),
      h('span', { class: 'score' }, usd(d.rate))))) : null);
}

/**
 * The landing page's "How it works" phases as her pipeline: deals per phase, and the build
 * capacity that decides the "build slot open / waitlist" pill visitors see.
 */
function journeyCard(j) {
  if (!j) return null;
  const capNum = h('strong', { class: 'journey-cap-num', 'aria-live': 'polite' }, j.capacity);
  const availLine = h('p', { class: 'muted small', style: { margin: 0 } });
  const drawAvail = (x) => {
    availLine.textContent = x.capacity === 0
      ? 'Availability is hidden on your website.'
      : `Website shows: “${x.label}” (${x.active} in progress of ${x.capacity}).`;
  };
  drawAvail(j);
  let busy = false;
  const setCap = async (delta) => {
    const next = Math.max(0, Math.min(10, Number(capNum.textContent) + delta));
    if (busy || next === Number(capNum.textContent)) return;
    busy = true;
    try {
      const x = await api('PUT', 'landing/capacity', { capacity: next });
      capNum.textContent = x.capacity;
      drawAvail(x);
    } catch (err) {
      toast(err.message || 'Could not save.', 'err');
    } finally {
      busy = false;
    }
  };
  return h('div', { class: 'card journey-card' },
    h('div', { class: 'card-header' },
      h('div', {},
        h('h2', {}, 'Client journey'),
        h('p', { class: 'muted small', style: { margin: '2px 0 0' } }, 'The four phases from your website, filled from Deals')),
      link('/deals', { class: 'btn ghost sm' }, 'Open deals', icon('arrow', 14))),
    h('ol', { class: 'journey-phases' }, j.phases.map((p, i) => h('li', { class: p.count ? 'has' : '' },
      link('/deals', { title: p.stages.join(', ') },
        h('span', { class: 'journey-num' }, String(i + 1).padStart(2, '0')),
        h('strong', {}, p.count),
        h('span', { class: 'muted small' }, p.label))))),
    h('div', { class: 'journey-foot' },
      h('div', {},
        h('p', { style: { margin: 0, fontWeight: 600 } }, `${j.shipped_90d} shipped in 90 days`),
        availLine),
      h('div', { class: 'journey-cap', role: 'group', 'aria-label': 'Builds you take on at once' },
        h('span', { class: 'muted small' }, 'Build slots'),
        h('button', { class: 'btn ghost sm', 'aria-label': 'One fewer', onclick: () => setCap(-1) }, '−'),
        capNum,
        h('button', { class: 'btn ghost sm', 'aria-label': 'One more', onclick: () => setCap(1) }, '+'))));
}

/** Studio quick launchpad to jump straight into key creator workflows. */
function launchpadCard() {
  return h('div', { class: 'card launchpad-card' },
    h('div', { class: 'card-header' },
      h('h2', {}, 'Studio launchpad'),
      h('span', { class: 'muted small' }, '1-click quick actions')),
    h('div', { class: 'row', style: { flexWrap: 'wrap', gap: '8px', marginTop: '4px' } },
      link('/flow', { class: 'btn sm primary' }, icon('mic', 14), 'Dictate note'),
      link('/scripts', { class: 'btn sm' }, icon('pen', 14), 'New script'),
      link('/repurpose', { class: 'btn sm' }, icon('sparkle', 14), 'Social post'),
      link('/snippets', { class: 'btn sm' }, icon('code', 14), 'Tech code card'),
      link('/deals', { class: 'btn sm' }, icon('briefcase', 14), 'New brand deal'),
      link('/ideas', { class: 'btn sm ghost' }, icon('bulb', 14), 'Breakout ideas')));
}

/** Active sponsorship deliverables card. */
function deliverablesCard(delivs, summary) {
  if (!delivs?.length && !summary?.active_count) return null;
  const usd = (n) => `$${Math.round(Number(n) || 0).toLocaleString()}`;
  return h('div', { class: 'card' },
    h('div', { class: 'card-header' },
      h('div', {},
        h('h2', {}, 'Active sponsorship deliverables'),
        h('p', { class: 'muted small', style: { margin: '2px 0 0' } },
          `${delivs?.length || 0} deliverable${delivs?.length === 1 ? '' : 's'} · ${usd(summary?.confirmed_revenue)} committed`)),
      link('/deals', { class: 'btn ghost sm' }, 'Open deals CRM', icon('arrow', 14))),
    delivs?.length ? h('ul', { class: 'item-list', style: { marginTop: '8px' } }, delivs.map((d) => h('li', {},
      h('div', { class: 'item-main' },
        h('div', { class: 'row', style: { gap: '8px', alignItems: 'center' } },
          h('strong', {}, d.brand_name),
          d.due_label ? pill(d.due_label, d.days_until !== null && d.days_until <= 2 ? 'bad' : d.days_until <= 5 ? 'warn' : 'ok') : null),
        h('div', { class: 'muted clamp-1 small' },
          `${d.platform} · ${d.format} · ${usd(d.rate)}` + (d.script_title ? ` · Script: “${d.script_title}”` : ''))),
      d.script_id
        ? link(`/scripts/${d.script_id}`, { class: 'btn sm primary' }, 'Open script', icon('arrow', 13))
        : link('/deals', { class: 'btn sm ghost' }, 'View in deals', icon('arrow', 13))))) : h('p', { class: 'muted small' }, 'No active deliverables pending.'));
}

/** Autonomous agent approvals card on Dashboard. */
function agentApprovalsCard(approvals, onApproved) {
  if (!approvals || approvals.total === 0) return null;
  const items = approvals.items || [];
  return h('div', { class: 'card' },
    h('div', { class: 'card-header' },
      h('div', {},
        h('h2', {}, 'AI agent drafts to review'),
        h('p', { class: 'muted small', style: { margin: '2px 0 0' } }, `${approvals.total} post${approvals.total === 1 ? '' : 's'} waiting for your sign-off`)),
      link('/agents', { class: 'btn ghost sm' }, 'View all agents', icon('arrow', 14))),
    h('ul', { class: 'item-list', style: { marginTop: '8px' } }, items.map((p) => h('li', {},
      link(`/posts/${p.id}`, { class: 'item-main' },
        h('strong', {}, p.agent_name),
        h('div', { class: 'muted clamp-1 small' }, `${p.platform} · ${p.format || 'post'} · “${p.title}”`)),
      h('div', { class: 'row', style: { gap: '6px' } },
        h('button', {
          class: 'btn sm primary',
          onclick: async (e) => {
            e.stopPropagation();
            try {
              await api('POST', `agents/posts/${p.id}/approve`, {});
              toast('Post approved and scheduled.');
              if (onApproved) onApproved();
            } catch (err) {
              toast(err.message || 'Could not approve.', 'err');
            }
          }
        }, icon('check', 13), 'Approve'),
        link(`/posts/${p.id}`, { class: 'btn sm ghost' }, 'Edit'))))));
}

function listCard(title, to, items, renderItem, empty) {
  return h('div', { class: 'card' },
    h('div', { class: 'card-header' }, h('h2', {}, title), link(to, { class: 'btn ghost sm' }, 'See all', icon('arrow', 14))),
    items.length ? h('ul', { class: 'item-list' }, items.map(renderItem)) : h('p', { class: 'muted' }, empty));
}

export async function renderDashboard(view) {
  const d = await api('GET', 'dashboard');
  const s = d.stats;

  const love = loveCard();
  if (love) loveExtras().then((x) => love.append(x));
  // Every block has an id so she can hide and reorder them (Customise).
  const blocks = [
    { id: 'launchpad', label: 'Studio launchpad', node: launchpadCard() },
    { id: 'hero', label: 'Week & greeting', node: heroCard(d) },
    { id: 'next', label: 'Next step', node: nextStepCard(d.next_step) },
    { id: 'agent-approvals', label: 'AI agent approvals', node: agentApprovalsCard(d.agent_approvals, () => redraw()) },
    { id: 'deliverables', label: 'Active brand deliverables', node: deliverablesCard(d.active_deliverables, d.deals_summary) },
    { id: 'brief', label: 'Your day', node: briefCard() },
    { id: 'due', label: 'Due now', node: dueStrip() },
    { id: 'composer', label: 'Composer', node: homeComposer() },
    { id: 'inbound', label: 'From your website', node: inboundCard(d.inbound) },
    { id: 'journey', label: 'Client journey', node: journeyCard(d.journey) },
    { id: 'hero-site', label: 'Website hero', node: siteHeroCard(d.hero, () => redraw()) },
    { id: 'marquee', label: 'Marquee', node: marqueeCard(d.marquee, () => redraw()) },
    { id: 'partners', label: 'Brand partners & stages', node: partnersCard(d.partners, () => redraw()) },
    { id: 'pricing', label: 'Packages & pricing', node: pricingCard(d.pricing, () => redraw()) },
    { id: 'about', label: 'About you', node: aboutCard(d.about, () => redraw()) },
    { id: 'speaking', label: 'Speaking', node: speakingCard(d.speaking, () => redraw()) },
    { id: 'reach', label: 'Public reach', node: reachCard(d.reach, () => redraw()) },
    { id: 'website', label: 'Your website feed', node: websiteCard(d.website, () => redraw()) },
    { id: 'social', label: 'Social this week', node: socialStrip(d.social) },
    { id: 'love', label: 'Today’s note', node: love },
    { id: 'setup', label: 'Setup checklist', node: setupCard(d.setup_checks) },
    { id: 'stats', label: 'YouTube stats', node: h('div', { class: 'grid grid-4' },
      statBox(s.shipped, s.missed ? `shipped · ${s.missed} missed` : 'videos shipped', 'tone-good'),
      statBox(s.streak, s.streak === 1 ? 'week streak' : 'week streak', 'tone-accent'),
      statBox(s.new_ideas, `new ideas · ${s.picked_ideas} picked`, 'tone-violet'),
      statBox(s.breakouts, 'breakouts found', 'tone-warn')) },
    { id: 'scoreboard', label: 'Scoreboard', node: d.weeks.length ? h('div', { class: 'card' },
      h('div', { class: 'card-header' }, h('h2', {}, 'Scoreboard'), link('/goals', { class: 'btn ghost sm' }, 'Open', icon('arrow', 14))),
      weekDots(d.weeks, d.current_week?.id)) : null },
  ];
  const redraw = () => { view.replaceChildren(); renderDashboard(view); };
  view.append(arrangeDashboard(blocks).map((b) => b.node),
    h('div', { class: 'dash-customise' }, h('button', { class: 'btn ghost sm', onclick: () => customiseDashboard(blocks, redraw) }, icon('sliders', 14), 'Customise dashboard')));

  view.append(h('div', { class: 'grid grid-2', style: { marginTop: '18px' } },
    listCard('Picked ideas', '/ideas', d.picked_ideas, (i) => h('li', {},
      h('div', { class: 'item-main' }, h('strong', {}, i.title), i.premise ? h('div', { class: 'muted clamp-1' }, i.premise) : null),
      pill(i.status)), 'Nothing picked yet. Generate ideas and pick one you love.'),

    listCard('Recent scripts', '/scripts', d.recent_scripts, (sc) => h('li', {},
      link(`/scripts/${sc.id}`, { class: 'item-main' },
        h('div', { class: 'row', style: { gap: '6px', alignItems: 'center' } },
          h('strong', {}, sc.title),
          sc.deals?.length ? h('span', { class: 'pill tone-accent small' }, sc.deals[0].brand_name) : null),
        h('div', { class: 'muted' }, `edited ${timeAgo(sc.updated_at)}`)),
      pill(sc.status)), 'No scripts yet.'),

    listCard('Top breakouts', '/breakouts', d.recent_outliers, (o) => h('li', {},
      h('a', { href: o.url, target: '_blank', rel: 'noopener', class: 'item-main' },
        h('strong', { class: 'clamp-1' }, o.title),
        h('div', { class: 'muted' }, `${o.channel_title || 'Unknown'} · ${compact(o.views)} views`)),
      h('span', { class: 'score' }, `${Number(o.outlier_score).toFixed(1)}×`)), 'Add competitor channels and run a scan.'),

    listCard('Dictated notes', '/flow', d.recent_notes, (n) => h('li', {},
      h('div', { class: 'item-main' },
        h('div', { class: 'clamp-2' }, n.polished.replace(/[#*_>`]/g, '').slice(0, 220)),
        h('div', { class: 'muted' }, timeAgo(n.created_at)))), 'Hold the mic button and ramble. It becomes a clean note.'),
  ));

  if (!d.weeks.length && !d.picked_ideas.length && !d.recent_scripts.length) {
    view.append(h('div', { class: 'card', style: { marginTop: '18px' } }, emptyState(icon('sprout', 30), 'A fresh studio',
      'Start in Settings: add your channel and an AI key. Then add a few competitor channels to watch.',
      link('/settings', { class: 'btn primary' }, 'Open settings'))));
  }
}
