// Landing page "On Stage": her talks from the dashboard (Speaking card → Manage talks). Talks with a
// stage photo form a slider (featured first), upcoming talks count down, the rest follow newest first, and the
// stats only count what's in her list. "Book Delia to speak" opens the booking form as a speaking request.
import { h, api, ensureLandingCss } from './core.js';
import { icon } from './icons.js';

ensureLandingCss(); // its styles live in landing.css

// One talk beside the photo; the rest behind "Show more" so both columns end level.
const SHOWN = 1;

const fmtDate = (d) => new Date(`${d}T00:00:00`).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
const daysUntil = (d) => Math.round((new Date(`${d}T00:00:00`) - new Date(new Date().toDateString())) / 86400000);
const whenLabel = (d) => {
  const n = daysUntil(d);
  return n <= 0 ? 'Today' : n === 1 ? 'Tomorrow' : `In ${n} days`;
};
const meta = (t) => [t.date ? fmtDate(t.date) : null, t.time, [t.venue, t.city].filter(Boolean).join(', ')].filter(Boolean).join(' · ');

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

/**
 * The stage photos as a slider (every talk with a photo, featured first): swipe or scroll on touch,
 * arrows, dots and ←/→ on keyboard. With one photo it's just the card.
 */
function stageSlider(slides) {
  if (slides.length < 2) return featuredCard(slides[0]);
  const track = h('div', { class: 'landing-stage-track', tabindex: '0', 'aria-live': 'polite' },
    slides.map((t, i) => h('div', { class: 'landing-stage-slide', role: 'group', 'aria-roledescription': 'slide', 'aria-label': `${i + 1} of ${slides.length}: ${t.event}` }, featuredCard(t))));
  const counter = h('span', { class: 'landing-stage-counter' });
  const dots = slides.map((t, i) => h('button', { type: 'button', class: 'landing-stage-dot', 'aria-label': `Show ${t.event}`, onclick: () => go(i) }));
  let index = 0;
  const go = (i) => {
    const n = (i + slides.length) % slides.length;
    track.scrollTo({ left: track.clientWidth * n, behavior: window.matchMedia?.('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth' });
  };
  const sync = () => {
    index = Math.round(track.scrollLeft / Math.max(1, track.clientWidth));
    counter.textContent = `${index + 1} / ${slides.length}`;
    dots.forEach((d, i) => { d.classList.toggle('on', i === index); d.setAttribute('aria-current', String(i === index)); });
  };
  track.addEventListener('scroll', () => requestAnimationFrame(sync), { passive: true });
  track.addEventListener('keydown', (e) => {
    if (e.key === 'ArrowRight' || e.key === 'ArrowLeft') { e.preventDefault(); go(index + (e.key === 'ArrowRight' ? 1 : -1)); }
  });
  sync();
  return h('div', { class: 'landing-stage-slider', role: 'region', 'aria-roledescription': 'carousel', 'aria-label': 'Delia on stage' },
    track,
    h('div', { class: 'landing-stage-controls' },
      h('button', { type: 'button', class: 'landing-stage-nav', 'aria-label': 'Previous talk', onclick: () => go(index - 1) }, icon('chevron-left', 16)),
      h('div', { class: 'landing-stage-dots' }, dots),
      counter,
      h('button', { type: 'button', class: 'landing-stage-nav', 'aria-label': 'Next talk', onclick: () => go(index + 1) }, icon('chevron-right', 16))));
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
      meta(t) ? h('p', { class: 'landing-stage-row-meta' }, icon(t.date ? 'calendar' : 'compass', 12), meta(t)) : null,
      t.url ? h('span', { class: 'landing-stage-link' }, t.upcoming ? 'Event details' : 'Read more', icon('external', 12)) : null),
    h('span', { class: `landing-stage-kind kind-${t.kind}` }, t.kind_label),
  ];
  const cls = `landing-stage-row${t.upcoming ? ' is-upcoming' : ''}${i >= SHOWN ? ' is-more' : ''}`;
  return t.url
    ? h('li', { class: cls }, h('a', { href: t.url, target: '_blank', rel: 'noopener', class: 'landing-stage-row-link' }, ...inner))
    : h('li', { class: cls }, h('div', { class: 'landing-stage-row-link' }, ...inner));
}

/** @param {{ onBookCall: (type: string, service?: string) => void }} options */
export function speakingSection({ onBookCall }) {
  const content = h('div', { class: 'landing-stage', 'aria-busy': 'true' },
    h('div', { class: 'landing-stage-feature is-skeleton', 'aria-hidden': 'true' }),
    h('ul', { class: 'landing-stage-list' }, Array.from({ length: 3 }, () => h('li', { class: 'landing-stage-row is-skeleton', 'aria-hidden': 'true' }))));
  const topics = h('ul', { class: 'landing-stage-topics', 'aria-label': 'Talk topics' });
  const book = () => onBookCall('speaking', 'Speaking & Workshops');
  const cta = h('div', { class: 'landing-stage-cta' },
    h('div', {},
      h('strong', {}, 'Bring Delia to your stage'),
      topics),
    h('button', { type: 'button', class: 'landing-service-btn is-strong', onclick: book }, icon('mic', 13), 'Book Delia to speak', icon('arrow', 12)));

  api('GET', 'landing/talks').then((d) => {
    const talks = d.talks || [];
    if (!talks.length) throw new Error('none');
    const featured = d.featured || talks[0];
    const rest = talks.filter((t) => t.id !== featured.id);

    const list = h('ol', { class: 'landing-stage-list', id: 'landing-stage-list' }, rest.map(talkRow));
    // Collapsed, the first talk fills the column and names the rest, which "Show more" reveals.
    const later = rest.slice(SHOWN);
    if (later.length) {
      list.firstElementChild?.querySelector('.landing-stage-row-main')?.append(
        h('p', { class: 'landing-stage-row-next' }, h('span', {}, 'Also on stage'), later.map((t) => h('b', {}, t.event))));
    }
    const hidden = rest.length - SHOWN;
    const moreLabel = (open) => (open ? 'Show less' : `Show ${hidden} more ${hidden === 1 ? 'talk' : 'talks'}`);
    const more = hidden > 0 ? h('button', {
      type: 'button', class: 'landing-service-all-btn', 'aria-expanded': 'false', 'aria-controls': 'landing-stage-list',
      onclick: () => {
        const open = list.classList.toggle('show-all');
        more.setAttribute('aria-expanded', String(open));
        more.replaceChildren(moreLabel(open), icon('chevron-down', 14));
      },
    }, moreLabel(false), icon('chevron-down', 14)) : null;

    // The events, then the call to action, beside the photo.
    // Every talk with a stage photo is a slide, featured first, then newest first.
    const slides = [featured, ...rest.filter((t) => t.image_url).sort((x, y) => String(y.date || '').localeCompare(String(x.date || '')))].filter((t, i, all) => all.indexOf(t) === i && (t.image_url || i === 0));
    content.replaceChildren(stageSlider(slides), h('div', { class: 'landing-stage-side' }, list, more ? h('div', { class: 'landing-stage-more' }, more) : null, cta));
    content.removeAttribute('aria-busy');

    topics.replaceChildren(...(d.topics || []).map((t) => h('li', {}, t)));
  }).catch(() => {
    content.removeAttribute('aria-busy');
    content.replaceChildren(h('p', { class: 'muted' }, 'Keynotes, panels and hands-on workshops on AI, building in public and remote engineering.'));
  });

  return h('section', { class: 'landing-section tinted', id: 'speaking', 'aria-labelledby': 'landing-stage-heading' },
    h('div', { class: 'landing-container' },
      h('div', { class: 'landing-section-head' },
        h('p', { class: 'landing-section-eyebrow' }, '06: On Stage'),
        h('h2', { id: 'landing-stage-heading' }, 'Recently ', h('em', {}, 'keynoting')),
        h('p', { class: 'muted' }, 'Keynotes, panels and hands-on workshops that make AI and modern engineering feel obvious.')),
      content,
      cta));
}
