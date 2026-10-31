// Landing page "01: About": her words from the dashboard's "About you" card plus facts the studio
// already keeps (audience from Public reach, latest talk from Speaking, build-slot availability
// from Client journey), so this section always agrees with the rest of the page. It renders at once
// with her current copy, then updates from /api/landing/about.
import { h, api } from './core.js';
import { icon } from './icons.js';

const DEFAULTS = {
  headline: 'Engineer first. Storyteller always.',
  bio: ['I spent years writing production code at Google before trading the campus for a carry-on. Now I split my time between shipping AI-powered products for clients and explaining technology to a few hundred thousand people who were told it was not for them.'],
  thesis: 'Tech feels scary because it is explained badly.',
  thesis_detail: 'Whether it is a 30-second reel, a conference keynote, or an MVP built in three weeks, my job is the same: take something intimidating and make it feel obvious.',
  now: 'Building AI-powered apps and explaining them on camera',
  location: '',
  story_url: 'https://linkedin.com/in/delialazarescu',
  milestones: [
    { label: 'Self-taught engineer', detail: 'Learned to code on her own and got hired as a software engineer.' },
    { label: 'Google', detail: 'Wrote production code at Google.' },
    { label: 'Independent founder', detail: 'Left Big Tech to build AI-powered products for clients, from anywhere.' },
    { label: 'Creator & speaker', detail: 'Explains tech to a large community and on stage.' },
  ],
  portrait_url: '/images/549290582_18080889986298812_7772569637968987998_n.jpg',
  facts: { countries: 69, audience: '472K+', networks: 6, talks: 0, latest_talk: null, availability: null },
};

/** "Engineer first. Storyteller always." → the second sentence's first word in the accent serif. */
function headline(text) {
  const m = String(text).match(/^(.*?[.!?]\s+)(\S+)(.*)$/);
  return m ? [m[1], h('em', {}, m[2]), m[3]] : [text];
}

const jump = (id) => (e) => {
  const el = document.getElementById(id);
  if (!el) return;
  e.preventDefault();
  el.scrollIntoView({ behavior: 'smooth' });
};

function factTiles(f) {
  const talk = f.latest_talk;
  const tiles = [
    f.countries ? { icon: 'globe', num: String(f.countries), label: 'countries', sub: 'worked and shipped from', href: null } : null,
    f.audience ? { icon: 'users', num: f.audience, label: 'people', sub: `follow along across ${f.networks} networks`, href: 'reach' } : null,
    f.talks ? { icon: 'mic', num: String(f.talks), label: f.talks === 1 ? 'talk' : 'talks', sub: talk ? `${talk.upcoming ? 'Next' : 'Latest'}: ${talk.event}` : 'on stages worldwide', href: 'speaking' } : null,
  ].filter(Boolean);
  return h('div', { class: 'landing-about-facts' }, tiles.map((t) => {
    const body = [
      h('span', { class: 'landing-about-fact-icon', 'aria-hidden': 'true' }, icon(t.icon, 16)),
      h('span', { class: 'landing-about-fact-num' }, t.num, h('small', {}, ` ${t.label}`)),
      h('span', { class: 'landing-about-fact-sub' }, t.sub),
    ];
    return t.href
      ? h('a', { class: 'landing-about-fact', href: `#${t.href}`, onclick: jump(t.href) }, ...body)
      : h('div', { class: 'landing-about-fact' }, ...body);
  }));
}

function journey(milestones) {
  if (!milestones.length) return null;
  const list = h('ol', { class: 'landing-about-journey', id: 'landing-about-journey', style: { '--steps': String(milestones.length) } },
    milestones.map((m, i) => h('li', { class: i === milestones.length - 1 ? 'is-now' : '' },
      h('span', { class: 'landing-about-step-dot', 'aria-hidden': 'true' }, i === milestones.length - 1 ? icon('sparkle', 12) : String(i + 1)),
      h('strong', {}, m.label),
      m.detail ? h('span', { class: 'landing-about-step-detail' }, m.detail) : null)));
  const hasDetails = milestones.some((m) => m.detail);
  const toggle = hasDetails ? h('button', {
    type: 'button', class: 'landing-about-toggle-btn', 'aria-expanded': 'false', 'aria-controls': 'landing-about-journey',
    onclick: () => {
      const open = list.classList.toggle('show-details');
      toggle.setAttribute('aria-expanded', String(open));
      toggle.replaceChildren(open ? 'Hide the story' : 'Show the story', icon(open ? 'chevron-up' : 'chevron-down', 13));
    },
  }, 'Show the story', icon('chevron-down', 13)) : null;
  return h('div', { class: 'landing-about-journey-wrap' },
    h('div', { class: 'landing-about-journey-head' }, h('span', {}, 'The path so far'), toggle),
    list);
}

function portrait(d) {
  const f = d.facts || {};
  const avail = f.availability;
  return h('div', { class: 'landing-about-showcase' },
    h('figure', { class: 'landing-about-photo-card' },
      h('img', { src: d.portrait_url, alt: 'Delia Lazarescu, ex-Google engineer and founder', class: 'landing-about-photo', loading: 'eager', decoding: 'async' }),
      h('span', { class: 'landing-about-photo-tag' }, icon('code', 12), 'Ex-Google Engineer & Founder'),
      d.now || d.location || avail ? h('figcaption', { class: 'landing-about-now' },
        h('span', { class: 'landing-about-now-label' }, h('i', { class: 'landing-pulse-dot', 'aria-hidden': 'true' }), 'Now'),
        d.now ? h('strong', {}, d.now) : null,
        h('span', { class: 'landing-about-now-meta' },
          d.location ? h('span', {}, icon('pin', 12), d.location) : null,
          avail ? h('span', { class: `is-${avail.status}` }, avail.label) : null)) : null));
}

/** @param {{ onBookCall: (type: string, service?: string) => void }} options */
export function aboutSection({ onBookCall }) {
  const section = h('section', { class: 'landing-section landing-about', id: 'about', 'aria-labelledby': 'landing-about-title' });

  const render = (d) => {
    section.replaceChildren(h('div', { class: 'landing-container' },
      h('div', { class: 'landing-about-grid' },
        h('div', { class: 'landing-about-text' },
          h('p', { class: 'landing-section-eyebrow' }, '01: About'),
          h('h2', { id: 'landing-about-title' }, ...headline(d.headline)),
          ...(d.bio || []).map((p) => h('p', { class: 'landing-about-bio' }, p)),
          d.thesis ? h('blockquote', { class: 'landing-about-thesis' },
            h('p', {}, h('strong', {}, d.thesis)),
            d.thesis_detail ? h('p', { class: 'muted' }, d.thesis_detail) : null) : null,
          factTiles(d.facts || {}),
          journey(d.milestones || []),
          h('div', { class: 'landing-about-actions' },
            h('button', { type: 'button', class: 'btn primary', onclick: () => onBookCall('consulting', 'About: Intro call') }, icon('calendar', 14), 'Book an intro call'),
            d.story_url ? h('a', { class: 'btn ghost', href: d.story_url, target: '_blank', rel: 'noopener' }, 'Read my story', icon('external', 13)) : null)),
        portrait(d))));
  };

  render(DEFAULTS);
  api('GET', 'landing/about').then((d) => render({ ...DEFAULTS, ...d, facts: { ...DEFAULTS.facts, ...(d.facts || {}) } })).catch(() => {});
  return section;
}
