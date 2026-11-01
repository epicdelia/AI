// Landing page social proof and owned audience: "What clients say" (testimonials she approved,
// hidden until there's at least one) and the free-download opt-in (only when she's turned it on
// in Website → Email list). Also the private review form a client opens from her link.
import { h, api, toast, botTrap, ensureLandingCss } from './core.js';
import { icon, brandMark } from './icons.js';
import { attribution } from './landing-tracking.js';

ensureLandingCss(); // its styles live in landing.css

const stars = (n) => (n ? h('span', { class: 'landing-quote-stars', 'aria-label': `${n} out of 5` }, '★'.repeat(n), h('span', { class: 'off' }, '★'.repeat(5 - n))) : null);

export function testimonialsSection() {
  const section = h('section', { class: 'landing-section', id: 'testimonials', 'aria-labelledby': 'landing-quotes-heading', hidden: true });
  api('GET', 'landing/testimonials').then(({ testimonials }) => {
    if (!testimonials?.length) return;
    section.append(h('div', { class: 'landing-container' },
      h('div', { class: 'landing-section-head' },
        h('p', { class: 'landing-section-eyebrow' }, 'Clients'),
        h('h2', { id: 'landing-quotes-heading' }, 'What clients ', h('em', {}, 'say')),
        h('p', { class: 'muted' }, 'In their own words, shared with their permission.')),
      h('div', { class: 'landing-quotes' }, testimonials.map((t) => h('figure', { class: 'landing-card landing-quote' },
        stars(t.rating),
        h('blockquote', {}, `“${t.quote}”`),
        h('figcaption', {}, h('strong', {}, t.name), [t.role, t.company].filter(Boolean).length ? h('span', { class: 'muted' }, ` · ${[t.role, t.company].filter(Boolean).join(', ')}`) : null))))));
    section.hidden = false;
  }).catch(() => {});
  return section;
}

export function leadMagnetSection() {
  const section = h('section', { class: 'landing-section tinted', id: 'download', 'aria-labelledby': 'landing-magnet-heading', hidden: true });
  api('GET', 'landing/lead-magnet').then((m) => {
    if (!m.enabled) return;
    const email = h('input', { type: 'email', required: true, placeholder: 'you@company.com', 'aria-label': 'Your email', autocomplete: 'email' });
    const name = h('input', { type: 'text', placeholder: 'First name (optional)', 'aria-label': 'First name', autocomplete: 'given-name' });
    const trap = h('input', { type: 'text', name: 'website', tabindex: '-1', autocomplete: 'off', class: 'hp-field', 'aria-hidden': 'true' });
    const btn = h('button', { type: 'submit', class: 'btn primary' }, icon('download', 15), ` ${m.button}`);
    const box = h('div', { class: 'landing-magnet-body' });
    const form = h('form', {
      class: 'landing-magnet-form',
      onsubmit: async (e) => {
        e.preventDefault();
        btn.disabled = true;
        try {
          const r = await api('POST', 'subscribe', { email: email.value.trim(), name: name.value.trim() || null, website: trap.value || null, attribution: attribution() });
          box.replaceChildren(h('div', { class: 'landing-magnet-done', role: 'status' },
            h('p', {}, icon('check', 16), ` ${r.message}`),
            r.delivery_url ? h('a', { class: 'btn primary', href: r.delivery_url, target: '_blank', rel: 'noopener' }, icon('external', 15), ` ${r.delivery_label || 'Open it'}`) : null));
        } catch (err) {
          btn.disabled = false;
          toast(err.message || 'That didn’t work. Please try again.', 'err');
        }
      },
    }, name, email, trap, btn);
    box.append(form, h('p', { class: 'muted small' }, 'No spam. One click to unsubscribe from any email.'));
    section.append(h('div', { class: 'landing-container landing-magnet' },
      h('div', {}, h('p', { class: 'landing-section-eyebrow' }, 'Free'), h('h2', { id: 'landing-magnet-heading' }, m.title), m.description ? h('p', { class: 'muted' }, m.description) : null),
      box));
    section.hidden = false;
  }).catch(() => {});
  return section;
}

/** /review/<token>: a client leaves a testimonial. Works signed out. */
export async function renderReview(view, token) {
  const d = await api('GET', `review/${token}`);
  const name = h('input', { type: 'text', required: true, value: d.name || '', autocomplete: 'name' });
  const reviewTrap = botTrap();
  const role = h('input', { type: 'text', value: d.role || '', placeholder: 'e.g. CTO' });
  const company = h('input', { type: 'text', value: d.company || '' });
  const quote = h('textarea', { rows: 5, required: true, minlength: 10, placeholder: `What was it like working with ${d.creator.split(' ')[0]}? What changed for you?` }, d.quote || '');
  let rating = 5;
  const starBtns = [1, 2, 3, 4, 5].map((n) => h('button', { type: 'button', class: 'star-btn on', 'aria-label': `${n} star${n === 1 ? '' : 's'}`, onclick: () => { rating = n; starBtns.forEach((b, i) => b.classList.toggle('on', i < n)); } }, '★'));
  const permission = h('input', { type: 'checkbox', checked: true });
  const field = (label, input) => h('label', { class: 'field' }, h('span', {}, label), input);
  const btn = h('button', { type: 'submit', class: 'btn primary' }, 'Send');
  const form = h('form', {
    class: 'card review-form',
    onsubmit: async (e) => {
      e.preventDefault();
      btn.disabled = true;
      try {
        const r = await api('POST', `review/${token}`, { website: reviewTrap.value || undefined, name: name.value.trim(), role: role.value.trim() || null, company: company.value.trim() || null, quote: quote.value.trim(), rating, permission: permission.checked });
        form.replaceChildren(h('div', { class: 'review-done' }, icon('heart', 28), h('h2', {}, r.message)));
      } catch (err) {
        btn.disabled = false;
        toast(err.message, 'err');
      }
    },
  },
  h('div', { class: 'row', style: { gap: '10px', marginBottom: '12px' } }, brandMark(28), h('div', {}, h('h1', { style: { margin: 0, fontSize: '1.3rem' } }, `A few words for ${d.creator}`), h('p', { class: 'muted small', style: { margin: 0 } }, 'Two minutes. Thank you!'))),
  d.submitted ? h('p', { class: 'muted small' }, 'You already sent this: you can update it below.') : null,
  field('Your review', quote),
  h('div', { class: 'review-stars', role: 'group', 'aria-label': 'Rating' }, starBtns),
  h('div', { class: 'grid grid-3' }, field('Name', name), field('Role', role), field('Company', company)), reviewTrap,
  h('label', { class: 'row small', style: { gap: '8px', margin: '10px 0' } }, permission, `${d.creator.split(' ')[0]} may show this on her website`),
  h('div', { class: 'row', style: { justifyContent: 'flex-end' } }, btn));
  view.append(h('div', { class: 'review-page' }, form));
}
