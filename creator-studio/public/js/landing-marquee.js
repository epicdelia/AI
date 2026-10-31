// Landing page marquee, just above the footer: her phrases (from the dashboard's "Marquee" card) each
// jumping to its section, mixed with live facts from her studio (audience, open build slots, talks,
// latest video, countries). It pauses on hover or focus, screen readers get the list once (the
// copy that makes the loop seamless is hidden), and with reduced motion it becomes a static row.
import { h, api } from './core.js';
import { icon } from './icons.js';

const FALLBACK = ['Vibecoded Apps', 'Content Creation', 'Speaking & Keynotes', 'AI Integration', 'Brand Partnerships', 'Technical Advisory']
  .map((label, i) => ({ label, target: ['services', 'youtube', 'speaking', 'services', 'pricing', 'pricing'][i], kind: 'phrase' }));

function chip(item, clone) {
  const props = {
    href: `#${item.target}`,
    class: `landing-marquee-item is-${item.kind}${item.status ? ` is-${item.status}` : ''}`,
    tabindex: clone ? '-1' : null,
    onclick: (e) => {
      const el = document.getElementById(item.target);
      if (el) { e.preventDefault(); el.scrollIntoView({ behavior: 'smooth' }); }
    },
  };
  return h('li', {},
    h('a', props,
      item.kind === 'live' ? h('span', { class: 'landing-marquee-live', 'aria-hidden': 'true' }, icon(item.icon || 'sparkle', 13)) : null,
      h('span', {}, item.label)),
    h('span', { class: 'landing-marquee-dot', 'aria-hidden': 'true' }, '✦'));
}

export function marqueeSection() {
  const track = h('div', { class: 'landing-marquee-track' });
  const band = h('section', { class: 'landing-marquee', 'aria-label': 'What Delia does, and what is new' }, track);

  const render = ({ items, duration }) => {
    const list = items.length ? items : FALLBACK;
    // Repeat short lists so one copy is wider than the screen, then add the hidden copy for the loop.
    const reps = Math.max(1, Math.ceil(10 / list.length));
    const run = Array.from({ length: reps }, () => list).flat();
    track.replaceChildren(
      h('ul', { class: 'landing-marquee-group' }, run.map((it, i) => chip(it, i >= list.length))),
      h('ul', { class: 'landing-marquee-group', 'aria-hidden': 'true' }, run.map((it) => chip(it, true))));
    track.style.setProperty('--marquee-duration', `${Math.round((duration || 40) * reps)}s`);
  };

  render({ items: FALLBACK, duration: 40 });
  api('GET', 'landing/marquee').then(render).catch(() => {});
  return band;
}
