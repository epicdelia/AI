// Dashboard "About you": what the landing page's About section says (headline, bio, thesis, the
// "Now" line, countries, milestones, portrait) and the live facts it shows next to them. Saving
// updates the website straight away.
import { h, api, apiUpload, link, modal, toast, field } from './core.js';
import { icon } from './icons.js';

export function aboutCard(a, onChange) {
  if (!a) return null;
  const f = a.facts || {};
  return h('div', { class: 'card about-card' },
    h('div', { class: 'card-header' },
      h('div', {},
        h('h2', {}, 'About you'),
        h('p', { class: 'muted small', style: { margin: '2px 0 0' } }, 'The About section of your website')),
      h('div', { class: 'row' },
        link('/landing', { class: 'btn ghost sm' }, icon('eye', 14), 'Preview'),
        h('button', { class: 'btn sm', onclick: () => editAbout().then((changed) => changed && onChange?.()) }, icon('pen', 14), 'Edit'))),
    h('div', { class: 'about-card-body' },
      h('img', { src: a.portrait_url, alt: '', class: 'about-card-photo' }),
      h('div', { class: 'about-card-main' },
        h('strong', {}, a.headline),
        a.now ? h('p', { class: 'about-card-now' }, h('span', { class: 'about-card-now-label' }, 'Now'), a.now, a.location ? h('span', { class: 'muted' }, ` · ${a.location}`) : null)
          : h('p', { class: 'muted small' }, 'Add a “Now” line so visitors see what you’re working on.'),
        h('div', { class: 'about-card-facts' },
          h('span', {}, h('b', {}, f.countries), ' countries'),
          h('span', {}, h('b', {}, f.audience), ' audience'),
          h('span', {}, h('b', {}, f.talks), f.talks === 1 ? ' talk' : ' talks'),
          f.availability ? h('span', { class: `is-${f.availability.status}` }, f.availability.label) : null))),
    h('p', { class: 'muted small', style: { margin: '10px 0 0' } }, 'Audience, talks and availability update from Public reach, Speaking and Client journey.'));
}

/** Edit the About section. Resolves true when something was saved. */
export function editAbout() {
  return modal('About you', (close) => {
    let changed = false;
    const body = h('div', {}, h('p', { class: 'muted' }, 'Loading…'));

    api('GET', 'about').then(({ about }) => {
      const input = (value, props = {}) => h('input', { class: 'input', value: value ?? '', ...props });
      const area = (value, rows = 3, max = 900) => h('textarea', { class: 'input', rows, maxlength: max }, value ?? '');
      const fields = {
        headline: input(about.headline, { maxlength: 120, required: true }),
        bio1: area(about.bio[0], 4),
        bio2: area(about.bio[1], 3),
        thesis: input(about.thesis, { maxlength: 160 }),
        thesis_detail: area(about.thesis_detail, 2, 400),
        now: input(about.now, { maxlength: 120, placeholder: 'What you’re working on right now' }),
        location: input(about.location, { maxlength: 80, placeholder: 'Lisbon, Portugal' }),
        countries: input(about.countries, { type: 'number', min: 0, max: 250, required: true }),
        story_url: input(about.story_url, { type: 'url', maxlength: 300, placeholder: 'https://' }),
      };
      const steps = Array.from({ length: 5 }, (_, i) => ({
        label: input(about.milestones[i]?.label, { maxlength: 60, placeholder: i === 0 ? 'Self-taught engineer' : '' }),
        detail: input(about.milestones[i]?.detail, { maxlength: 200, placeholder: 'One line' }),
      }));
      const photo = h('img', { src: about.portrait_url, alt: '', class: 'about-edit-photo' });
      const file = h('input', { type: 'file', accept: 'image/jpeg,image/png,image/webp' });
      const resetBtn = h('button', { type: 'button', class: 'btn ghost sm', hidden: !about.portrait }, 'Use the original photo');

      file.addEventListener('change', async () => {
        if (!file.files[0]) return;
        const fd = new FormData();
        fd.append('image', file.files[0]);
        try {
          const res = await apiUpload('about/portrait', fd);
          photo.src = res.about.portrait_url;
          resetBtn.hidden = false;
          changed = true;
          toast('Portrait updated.');
        } catch (err) { toast(err.message, 'err'); }
      });
      resetBtn.addEventListener('click', async () => {
        try {
          const res = await api('DELETE', 'about/portrait');
          photo.src = res.about.portrait_url;
          resetBtn.hidden = true;
          changed = true;
        } catch (err) { toast(err.message, 'err'); }
      });

      const form = h('form', {
        class: 'about-edit',
        onsubmit: async (e) => {
          e.preventDefault();
          const payload = {
            headline: fields.headline.value.trim(),
            bio: [fields.bio1.value, fields.bio2.value],
            thesis: fields.thesis.value,
            thesis_detail: fields.thesis_detail.value,
            now: fields.now.value,
            location: fields.location.value,
            countries: Number(fields.countries.value || 0),
            story_url: fields.story_url.value.trim() || null,
            milestones: steps.map((s) => ({ label: s.label.value, detail: s.detail.value })),
          };
          try {
            await api('PUT', 'about', payload);
            toast('Your About section is updated.');
            close(true);
          } catch (err) { toast(err.message, 'err'); }
        },
      },
        h('div', { class: 'about-edit-top' },
          h('div', { class: 'about-edit-photo-col' }, photo, field('Portrait', file, 'JPEG, PNG or WebP, up to 8 MB'), resetBtn),
          h('div', { class: 'about-edit-fields' },
            field('Headline', fields.headline, 'The second sentence’s first word gets the accent, e.g. “Engineer first. Storyteller always.”'),
            h('div', { class: 'grid grid-2' }, field('Now', fields.now, 'Shown on your photo'), field('Location', fields.location, 'Optional')),
            h('div', { class: 'grid grid-2' }, field('Countries', fields.countries, 'One number, used everywhere'), field('Story link', fields.story_url, '“Read my story”')))),
        field('Bio', fields.bio1),
        field('Second paragraph', fields.bio2, 'Optional'),
        field('Thesis', fields.thesis, 'Your one-line belief, shown as a quote'),
        field('Thesis detail', fields.thesis_detail),
        h('fieldset', { class: 'about-edit-steps' },
          h('legend', {}, 'The path so far (up to 5; empty rows are skipped)'),
          steps.map((s, i) => h('div', { class: 'about-edit-step' }, h('span', { class: 'muted small' }, String(i + 1)), s.label, s.detail))),
        h('div', { class: 'row', style: { justifyContent: 'flex-end', marginTop: '12px' } },
          h('button', { type: 'button', class: 'btn ghost', onclick: () => close(changed) }, 'Cancel'),
          h('button', { type: 'submit', class: 'btn primary' }, 'Save')));
      body.replaceChildren(form);
    }).catch((err) => body.replaceChildren(h('p', { class: 'error' }, err.message)));

    return body;
  }, { wide: true });
}
