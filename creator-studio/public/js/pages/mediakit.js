// Media Kit: public-ready creator press deck, audience reach, demographics and rate card.
import {
  h, api, toast, pageHead, clear, compact, link,
} from '../core.js';
import { icon } from '../icons.js';

export async function renderMediaKit(view) {
  const data = await api('GET', 'deals/media-kit');
  const c = data.creator;
  const a = data.audience;

  const headerCard = h('div', { class: 'card mediakit-hero' },
    h('div', { class: 'mediakit-hero-inner' },
      h('div', { class: 'mediakit-avatar' }, '🦄'),
      h('div', {},
        h('div', { class: 'row' },
          h('h1', { style: { margin: 0 } }, c.name),
          h('span', { class: 'pf-chip pf-youtube' }, icon('check', 13), c.handle)),
        h('p', { class: 'mediakit-tagline' }, c.tagline),
        h('div', { class: 'row muted small' },
          h('span', {}, icon('anchor', 13), ' ', c.location),
          h('span', {}, ' · '),
          h('a', { href: `mailto:${c.email}` }, icon('mail', 13), ' ', c.email)))));

  const totalReachCard = h('div', { class: 'card stat-box tone-accent' },
    h('div', { class: 'num' }, compact(a.total_reach)),
    h('div', { class: 'lbl' }, 'Total cross-platform tech community'));

  const platformsGrid = h('div', { class: 'grid grid-3', style: { margin: '18px 0' } },
    a.platforms.map((p) => h('div', { class: `card mediakit-platform pf-${p.platform}` },
      h('div', { class: 'card-header' },
        h('strong', {}, p.label),
        h('span', { class: 'deal-rate' }, compact(p.followers))),
      h('a', { class: 'small muted', href: p.url, target: '_blank', rel: 'noopener' }, p.handle, ' ↗'),
      h('p', { class: 'small', style: { marginTop: '8px' } }, p.highlight))));

  const demoRow = h('div', { class: 'grid grid-2', style: { margin: '18px 0' } },
    h('div', { class: 'card' },
      h('h3', {}, 'Audience Roles & Decision Makers'),
      h('p', { class: 'muted small' }, 'Highly concentrated in engineering, AI research, and technical leadership.'),
      h('ul', { class: 'mini-list', style: { marginTop: '10px' } },
        a.demographics.professions.map((prof) => h('li', {},
          h('span', {}, prof.label),
          h('strong', {}, `${prof.pct}%`))))),
    h('div', { class: 'card' },
      h('h3', {}, 'Geographic Distribution'),
      h('p', { class: 'muted small' }, a.demographics.age_bracket),
      h('ul', { class: 'mini-list', style: { marginTop: '10px' } },
        a.demographics.geography.map((geo) => h('li', {},
          h('span', {}, geo.country),
          h('strong', {}, `${geo.pct}%`))))));

  const ratesTable = h('div', { class: 'card', style: { margin: '18px 0' } },
    h('div', { class: 'card-header' },
      h('h3', {}, 'Standard Partnership Deliverables'),
      h('span', { class: 'muted small' }, 'Custom omnichannel bundles available')),
    h('table', { class: 'data-table' },
      h('thead', {}, h('tr', {}, h('th', {}, 'Deliverable'), h('th', {}, 'Starting Rate'), h('th', {}, 'Scope & Details'))),
      h('tbody', {}, Object.values(data.rates).map((r) => h('tr', {},
        h('td', {}, h('strong', {}, r.label)),
        h('td', {}, h('strong', { class: 'deal-rate' }, `$${r.rate.toLocaleString()}`)),
        h('td', { class: 'muted small' }, r.deliverables))))));

  const guidelinesCard = h('div', { class: 'card', style: { margin: '18px 0' } },
    h('h3', {}, 'Partnership Ethics & Standards'),
    h('ul', { class: 'checklist', style: { marginTop: '10px' } },
      data.guidelines.map((g) => h('li', { class: 'done' }, h('span', { class: 'check' }, icon('check', 14)), h('span', {}, g)))));

  view.append(
    pageHead('Media Kit', 'Official creator press kit, verified metrics, audience demographics and partnership options.',
      link('/deals', { class: 'btn' }, icon('briefcase', 15), 'Deals pipeline'),
      h('button', {
        class: 'btn primary',
        onclick: () => {
          navigator.clipboard.writeText(window.location.href);
          toast('Media Kit link copied to clipboard.');
        },
      }, icon('copy', 15), 'Share media kit'),
      h('button', { class: 'btn', onclick: () => window.print() }, icon('arrow', 15), 'Print / PDF')),
    headerCard,
    totalReachCard,
    platformsGrid,
    demoRow,
    ratesTable,
    guidelinesCard,
  );
}

