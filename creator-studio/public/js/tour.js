// First-run tour, after MagicAI's Introduction onboarding (one step per feature, then "done").
// Spotlights each sidebar item with a short explanation; remembered server-side once finished.
import { h, api, navigate } from './core.js';
import { icon } from './icons.js';

const STEPS = [
  { title: 'Welcome to your studio', text: 'Someone who loves you very much built this just for you. A quick tour of what’s where: it takes a minute, and you can skip any time.' },
  { to: '/', title: 'Dashboard', text: 'This week’s video, your streak, and what needs doing next. Start every session here.' },
  { to: '/flow', title: 'Dictate', text: 'Hold Space and ramble an idea out loud. You get a clean note you can turn into a video idea.' },
  { to: '/breakouts', title: 'Breakouts', text: 'Add channels in your niche (and news feeds). The scan finds videos doing far better than usual: proof a topic is hot.' },
  { to: '/ideas', title: 'Ideas', text: 'Fresh video ideas from those signals, filtered through your angle. Pick one, then “Plan it” or “Draft script”.' },
  { to: '/scripts', title: 'Scripts', text: 'Write with a live AI-tell checker, then review, fact-check, listen back, package titles and thumbnails, and export.' },
  { to: '/channel', title: 'My channel', text: 'How each of your videos did against your own usual, and what viewers ask for in the comments. One click turns a question into an idea.' },
  { to: '/calendar', title: 'Calendar', text: 'Your week on LinkedIn, Instagram, X and YouTube in one place. Drag drafts onto a day; targets per platform keep you consistent.' },
  { to: '/analytics', title: 'Analytics', text: 'What works on each platform from your own numbers: best formats, best times to post, top posts, and proven posts worth a fresh take. Import your analytics exports to fill it in fast.' },
  { to: '/repurpose', title: 'Repurpose', text: 'Turn a script, transcript or note into a LinkedIn post, an X thread, an Instagram carousel or Reel, and a Short, each written for its own feed.' },
  { to: '/linkedin', title: 'LinkedIn, Instagram, X, TikTok & Threads', text: 'Each platform has its own page: drafts, scheduled and posted, a live preview with the “see more” cut-off, and the numbers you log after posting.' },
  { to: '/goals', title: 'Weekly goals', text: 'One video a week. The mentor checks your real uploads and keeps the score honest.' },
  { to: '/tools', title: 'Toolkit', text: 'Titles, descriptions, captions, sponsor emails and more, in your voice. Save your own templates too.' },
  { to: '/chat', title: 'Producer chat', text: 'A collaborator who knows your channel and past work. Tick “Web” for live search.' },
  { to: '/library', title: 'Library', text: 'Everything you saved, plus documents you upload (sponsor briefs, research). Ctrl+K searches all of it.' },
  { to: '/settings', title: 'Settings', text: 'Add an AI key first, then your channel and voice. Keys are stored encrypted. That’s it: go make something!' },
];

let active = false;

export function startTour({ onDone } = {}) {
  if (active) return;
  active = true;
  let i = 0;
  const spot = h('div', { class: 'tour-spot' });
  const card = h('div', { class: 'tour-card', role: 'dialog', 'aria-live': 'polite' });
  const layer = h('div', { class: 'tour-layer' }, spot, card);

  const finish = async () => {
    active = false;
    layer.remove();
    window.removeEventListener('resize', place);
    document.removeEventListener('keydown', onKey);
    try { await api('POST', 'tour/done'); } catch { /* the tour just shows again next time */ }
    onDone?.();
  };

  function place() {
    const step = STEPS[i];
    const target = step.to ? document.querySelector(`.nav-item[data-to="${step.to}"]`) : null;
    if (target && target.offsetParent !== null && target.getBoundingClientRect().right > 0) {
      const r = target.getBoundingClientRect();
      Object.assign(spot.style, { display: 'block', top: `${r.top - 4}px`, left: `${r.left - 4}px`, width: `${r.width + 8}px`, height: `${r.height + 8}px` });
      const top = Math.min(window.innerHeight - 220, Math.max(16, r.top - 20));
      Object.assign(card.style, { top: `${top}px`, left: `${Math.min(window.innerWidth - 360, r.right + 20)}px`, transform: 'none' });
    } else {
      spot.style.display = 'none'; // welcome step, or the sidebar is collapsed on a phone
      Object.assign(card.style, { top: '50%', left: '50%', transform: 'translate(-50%, -50%)' });
    }
  }

  function draw() {
    const step = STEPS[i];
    if (step.to && location.pathname !== step.to) navigate(step.to);
    card.replaceChildren(
      h('div', { class: 'tour-count muted small' }, `${i + 1} of ${STEPS.length}`),
      h('h3', {}, step.title),
      h('p', {}, step.text),
      h('div', { class: 'row-between' },
        h('button', { class: 'btn ghost sm', type: 'button', onclick: finish }, 'Skip tour'),
        h('div', { class: 'row' },
          i > 0 ? h('button', { class: 'btn sm', type: 'button', onclick: () => { i--; draw(); } }, icon('back', 14), 'Back') : null,
          h('button', { class: 'btn primary sm', type: 'button', onclick: () => { if (i < STEPS.length - 1) { i++; draw(); } else finish(); } },
            i < STEPS.length - 1 ? 'Next' : 'Done', i < STEPS.length - 1 ? icon('arrow', 14) : null))));
    requestAnimationFrame(place);
    card.querySelector('.btn.primary')?.focus();
  }

  const onKey = (e) => {
    if (e.key === 'Escape') finish();
    else if (e.key === 'ArrowRight') card.querySelector('.btn.primary')?.click();
    else if (e.key === 'ArrowLeft' && i > 0) { i--; draw(); }
  };

  window.addEventListener('resize', place);
  document.addEventListener('keydown', onKey);
  document.body.append(layer);
  draw();
}
