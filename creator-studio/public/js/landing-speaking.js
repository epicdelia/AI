// Landing page "On Stage": her talks from the dashboard (Speaking card → Manage talks). The featured
// talk leads with its stage photo, upcoming talks count down, the rest follow newest first, and the
// stats only count what's in her list. "Book Delia to speak" opens the booking form as a speaking request.
import { h, api } from './core.js';
import { icon } from './icons.js';

const SHOWN = 4;

const fmtDate = (d) => new Date(`${d}T00:00:00`).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
const daysUntil = (d) => Math.round((new Date(`${d}T00:00:00`) - new Date(new Date().toDateString())) / 86400000);
const whenLabel = (d) => {
  const n = daysUntil(d);
  return n <= 0 ? 'Today' : n === 1 ? 'Tomorrow' : `In ${n} days`;
};
const meta = (t) => [t.date ? fmtDate(t.date) : null, t.city].filter(Boolean).join(' · ');

function featuredCard(t) {
  const body = [
    h('div', { class: 'landing-stage-feature-media' },
      t.image_url ? h('img', { src: t.image_url, alt: `Delia on stage at ${t.event}`, loading: 'lazy', decoding: 'async' }) : h('span', { class: 'landing-stage-feature-ph', 'aria-hidden': 'true' }, icon('mic', 40)),
      h('span', { class: 'landing-stage-kind is-overlay' }, icon('mic', 12), t.kind_label),
      t.upcoming ? h('span', { class: 'landing-stage-soon is-overlay' }, whenLabel(t.date)) : null),
    h('div', { class: 'landing-stage-feature-body' },
      h('p', { class: 'landing-stage-event' }, t.event),
      t.title ? h('h3', { class: 'landing-stage-title' }, `“${t.title}”`) : null,
      t.description ? h('p', { class: 'muted landing-stage-desc' }, t.description) : null,
      h('div', { class: 'landing-stage-meta' },
        t.date ? h('span', {}, icon('calendar', 13), fmtDate(t.date), t.time ? ` · ${t.time}` : '') : null,
        t.venue || t.city ? h('span', {}, icon('compass', 13), [t.venue, t.city].filter(Boolean).join(', ')) : null),
      t.url ? h('span', { class: 'landing-stage-link' }, t.upcoming ? 'Event details' : 'See the announcement', icon('external', 12)) : null),
  ];
  return t.url
    ? h('a', { class: 'landing-stage-feature', href: t.url, target: '_blank', rel: 'noopener' }, ...body)
    : h('article', { class: 'landing-stage-feature' }, ...body);
}

function talkRow(t, i) {
  const inner = [
    t.upcoming
      ? h('span', { class: 'landing-stage-date', 'aria-hidden': 'true' },
        h('b', {}, new Date(`${t.date}T00:00:00`).getDate()),
        new Date(`${t.date}T00:00:00`).toLocaleDateString('en-US', { month: 'short' }))
      : h('span', { class: 'landing-stage-num', 'aria-hidden': 'true' }, String(i + 1).padStart(2, '0')),
    h('div', { class: 'landing-stage-row-main' },
      h('div', { class: 'landing-stage-row-top' },
        h('h3', {}, t.event),
        t.upcoming ? h('span', { class: 'landing-stage-soon' }, whenLabel(t.date)) : null),
      t.title ? h('p', { class: 'landing-stage-row-title' }, `“${t.title}”`) : null,
      t.description ? h('p', { class: 'muted small landing-stage-row-desc' }, t.description) : null,
      meta(t) ? h('p', { class: 'landing-stage-row-meta' }, meta(t)) : null),
    h('span', { class: `landing-stage-kind kind-${t.kind}` }, t.kind_label),
    t.url ? h('span', { class: 'landing-stage-go', 'aria-hidden': 'true' }, icon('external', 13)) : null,
  ];
  const cls = `landing-stage-row${t.upcoming ? ' is-upcoming' : ''}${i >= SHOWN ? ' is-more' : ''}`;
  return t.url
    ? h('li', { class: cls }, h('a', { href: t.url, target: '_blank', rel: 'noopener', class: 'landing-stage-row-link' }, ...inner))
    : h('li', { class: cls }, h('div', { class: 'landing-stage-row-link' }, ...inner));
}

function statsStrip(stats, next) {
  const items = [
    stats.talks ? [stats.talks, stats.talks === 1 ? 'talk' : 'talks'] : null,
    stats.keynotes ? [stats.keynotes, stats.keynotes === 1 ? 'keynote' : 'keynotes'] : null,
    stats.cities ? [stats.cities, stats.cities === 1 ? 'city' : 'cities'] : null,
  ].filter(Boolean);
  return h('div', { class: 'landing-stage-stats' },
    items.map(([n, label]) => h('span', { class: 'landing-stage-stat' }, h('b', {}, n), label)),
    next ? h('span', { class: 'landing-stage-stat is-next' }, icon('calendar', 13), 'Next: ', h('b', {}, next.event), ` · ${whenLabel(next.date).toLowerCase()}`) : null);
}

/** @param {{ onBookCall: (type: string, service?: string) => void }} options */
export function speakingSection({ onBookCall }) {
  const content = h('div', { class: 'landing-stage', 'aria-busy': 'true' },
    h('div', { class: 'landing-stage-feature is-skeleton', 'aria-hidden': 'true' }),
    h('ul', { class: 'landing-stage-list' }, Array.from({ length: 3 }, () => h('li', { class: 'landing-stage-row is-skeleton', 'aria-hidden': 'true' }))));
  const statsSlot = h('div');
  const topics = h('ul', { class: 'landing-stage-topics', 'aria-label': 'Talk topics' });
  const book = () => onBookCall('speaking', 'Speaking & Workshops');

  api('GET', 'landing/talks').then((d) => {
    const talks = d.talks || [];
    if (!talks.length) throw new Error('none');
    const featured = d.featured || talks[0];
    const rest = talks.filter((t) => t.id !== featured.id);
    statsSlot.replaceChildren(statsStrip(d.stats || {}, (d.upcoming || [])[0]));

    const list = h('ol', { class: 'landing-stage-list', id: 'landing-stage-list' }, rest.map(talkRow));
    const hidden = rest.length - SHOWN;
    const more = hidden > 0 ? h('button', {
      type: 'button', class: 'landing-service-all-btn', 'aria-expanded': 'false', 'aria-controls': 'landing-stage-list',
      onclick: () => {
        const open = list.classList.toggle('show-all');
        more.setAttribute('aria-expanded', String(open));
        more.replaceChildren(open ? 'Show fewer talks' : `Show all ${rest.length + 1} talks`, icon('chevron-down', 14));
      },
    }, `Show all ${rest.length + 1} talks`, icon('chevron-down', 14)) : null;

    // The call to action sits under the list, beside the photo, so the two columns balance.
    content.replaceChildren(featuredCard(featured), h('div', { class: 'landing-stage-side' }, list, more ? h('div', { class: 'landing-stage-more' }, more) : null, cta));
    content.removeAttribute('aria-busy');

    topics.replaceChildren(...(d.topics || []).map((t) => h('li', {}, t)));
  }).catch(() => {
    content.removeAttribute('aria-busy');
    content.replaceChildren(h('p', { class: 'muted' }, 'Keynotes, panels and hands-on workshops on AI, building in public and remote engineering.'));
  });


  const cta = h('div', { class: 'landing-stage-cta' },
    h('div', {},
      h('strong', {}, 'Bring Delia to your stage'),
      topics),
    h('button', { type: 'button', class: 'landing-service-btn is-strong', onclick: book }, icon('mic', 13), 'Book Delia to speak', icon('arrow', 12)));

  return h('section', { class: 'landing-section tinted', id: 'speaking', 'aria-labelledby': 'landing-stage-heading' },
    h('div', { class: 'landing-container' },
      h('div', { class: 'landing-section-head' },
        h('p', { class: 'landing-section-eyebrow' }, '06: On Stage'),
        h('h2', { id: 'landing-stage-heading' }, 'Recently ', h('em', {}, 'keynoting')),
        h('p', { class: 'muted' }, 'Keynotes, panels and hands-on workshops that make AI and modern engineering feel obvious.'),
        statsSlot),
      content,
      cta));
}
