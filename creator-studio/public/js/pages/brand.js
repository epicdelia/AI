// Brand kit: colours, font and logo for carousels and the link-in-bio page, plus the words she
// never uses and her usual call to action (every AI writer and the post score follow them).
import { h, api, busy, toast, pageHead, field, apiUpload } from '../core.js';
import { icon } from '../icons.js';

export async function renderBrand(view) {
  let { brand: b, fonts } = await api('GET', 'brand');
  const c1 = h('input', { type: 'color', value: b.color });
  const c2 = h('input', { type: 'color', value: b.color_2 });
  const font = h('select', {}, Object.entries(fonts).map(([v, l]) => h('option', { value: v, selected: v === b.font }, l)));
  const banned = h('textarea', { rows: 3, placeholder: 'One per line or comma separated, e.g.\nsynergy\nhustle culture\nguru' });
  banned.value = b.banned_words || '';
  const cta = h('input', { type: 'text', value: b.cta || '', maxlength: 300, placeholder: 'e.g. Subscribe for a new AI tool every week' });
  const tags = h('input', { type: 'text', value: b.hashtags || '', maxlength: 500, placeholder: '#womenintech #aitools' });

  const logoImg = h('img', { class: 'brand-logo', alt: 'Your logo', hidden: !b.logo, src: b.logo ? `/api/brand/logo?${Date.now()}` : '' });
  const logoInput = h('input', { type: 'file', accept: 'image/png,image/jpeg,image/webp,image/svg+xml', hidden: true, onchange: async () => {
    const f = logoInput.files[0];
    if (!f) return;
    const fd = new FormData();
    fd.append('logo', f);
    try { await apiUpload('brand/logo', fd); logoImg.src = `/api/brand/logo?${Date.now()}`; logoImg.hidden = false; toast('Logo saved.'); } catch (e) { toast(e.message, 'err'); }
  } });

  // Live preview: a carousel cover in her colours and font.
  const preview = h('div', { class: 'brand-preview', 'aria-label': 'Preview' },
    h('div', { class: 'brand-slide' }, h('span', { class: 'brand-slide-kicker' }, 'Slide 1 / 6'), h('strong', {}, '5 AI tools I use every week'), h('span', { class: 'brand-slide-foot' }, 'Swipe →')));
  const paint = () => {
    preview.style.setProperty('--b1', c1.value);
    preview.style.setProperty('--b2', c2.value);
    preview.style.setProperty('--bf', font.value === 'system' ? 'system-ui, sans-serif' : `"${font.value}", system-ui, sans-serif`);
    if (font.value !== 'system' && !document.querySelector(`link[data-font="${font.value}"]`)) {
      document.head.append(h('link', { rel: 'stylesheet', 'data-font': font.value, href: `https://fonts.googleapis.com/css2?family=${encodeURIComponent(font.value)}:wght@600;800&display=swap` }));
    }
  };
  [c1, c2, font].forEach((el) => el.addEventListener('input', paint));
  paint();

  const save = h('button', { class: 'btn primary', onclick: () => busy(save, 'Saving…', async () => {
    b = (await api('PUT', 'brand', { color: c1.value, color_2: c2.value, font: font.value, banned_words: banned.value, cta: cta.value, hashtags: tags.value })).brand;
    toast('Brand kit saved. Carousels, AI writing and the post score use it from now on.');
  }) }, icon('check', 15), 'Save brand kit');

  view.append(
    pageHead('Brand kit', 'Your look and your rules, used everywhere the studio makes something for you.', save),
    h('div', { class: 'grid grid-2' },
      h('div', { class: 'card' },
        h('h2', {}, 'Look'),
        h('div', { class: 'grid grid-2 tight' }, field('Main colour', c1), field('Second colour', c2)),
        field('Font', font, 'carousels and your link-in-bio page'),
        h('div', { class: 'field' }, h('label', {}, 'Logo'), h('div', { class: 'row' }, logoImg, logoInput,
          h('button', { class: 'btn sm', type: 'button', onclick: () => logoInput.click() }, icon('image', 14), b.logo ? 'Change' : 'Upload'),
          h('button', { class: 'btn ghost sm', type: 'button', onclick: async () => { await api('DELETE', 'brand/logo'); logoImg.hidden = true; toast('Logo removed.'); } }, 'Remove'))),
        preview),
      h('div', { class: 'card' },
        h('h2', {}, 'Voice rules'),
        field('Words you never use', banned, 'the AI avoids them; the post score flags them'),
        field('Your usual call to action', cta, 'the AI adds it when it fits'),
        field('Signature hashtags', tags, 'for reference when writing captions'))));
}
