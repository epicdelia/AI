// Dashboard "Marquee": the moving band above the website footer. Her phrases (each jumps to a
// section of the page), whether live facts from the studio join them, and how fast it moves.
import { h, api, link, toast } from './core.js';
import { icon } from './icons.js';

export function marqueeCard(d, onChange) {
  if (!d) return null;
  let items = (d.items || []).map((i) => ({ ...i }));
  const saveBtn = h('button', { class: 'btn primary sm', disabled: true, onclick: save }, 'Save');
  const dirty = () => { saveBtn.disabled = false; };
  const showLive = h('input', { type: 'checkbox', checked: d.show_live, onchange: dirty, 'aria-label': 'Mix in live facts' });
  const speed = h('select', { class: 'input marquee-speed', onchange: dirty, 'aria-label': 'Speed' },
    (d.speeds || ['slow', 'normal', 'fast']).map((s) => h('option', { value: s, selected: s === d.speed }, s[0].toUpperCase() + s.slice(1))));
  const list = h('ul', { class: 'marquee-items' });

  function draw() {
    list.replaceChildren(...items.map((it, i) => h('li', {},
      h('input', { class: 'input', value: it.label, maxlength: 40, placeholder: 'Phrase', 'aria-label': `Phrase ${i + 1}`, oninput: (e) => { it.label = e.target.value; dirty(); } }),
      h('select', { class: 'input', 'aria-label': `Phrase ${i + 1} jumps to`, onchange: (e) => { it.target = e.target.value; dirty(); } },
        Object.entries(d.targets || {}).map(([k, label]) => h('option', { value: k, selected: k === it.target }, `→ ${label}`))),
      h('button', { type: 'button', class: 'btn ghost sm', disabled: i === 0, 'aria-label': 'Move up', onclick: () => { [items[i - 1], items[i]] = [items[i], items[i - 1]]; dirty(); draw(); } }, icon('chevron-up', 13)),
      h('button', { type: 'button', class: 'btn ghost sm', 'aria-label': 'Remove', onclick: () => { items.splice(i, 1); dirty(); draw(); } }, icon('x', 13)))),
    h('li', {}, h('button', { type: 'button', class: 'btn ghost sm', disabled: items.length >= 12, onclick: () => { items.push({ label: '', target: 'services' }); dirty(); draw(); list.querySelector('li:nth-last-child(2) input')?.focus(); } }, icon('plus', 13), 'Add a phrase')));
  }

  async function save() {
    saveBtn.disabled = true;
    try {
      await api('PUT', 'marquee', { items: items.filter((i) => i.label.trim()), show_live: showLive.checked, speed: speed.value });
      toast('Your marquee is updated.');
      onChange?.();
    } catch (err) {
      saveBtn.disabled = false;
      toast(err.message || 'Could not save.', 'err');
    }
  }

  draw();
  return h('div', { class: 'card marquee-card' },
    h('div', { class: 'card-header' },
      h('div', {},
        h('h2', {}, 'Marquee'),
        h('p', { class: 'muted small', style: { margin: '2px 0 0' } }, 'The moving band above your website footer')),
      h('div', { class: 'row' }, link('/landing', { class: 'btn ghost sm' }, icon('eye', 14), 'Preview'), saveBtn)),
    h('div', { class: 'marquee-grid' },
      h('div', {}, h('p', { class: 'partners-head' }, 'Phrases'), list),
      h('div', {},
        h('div', { class: 'partners-head row', style: { justifyContent: 'space-between' } },
          h('span', {}, 'Live facts'),
          h('label', { class: 'switch-mini' }, showLive, h('span', { 'aria-hidden': 'true' }))),
        h('ul', { class: 'marquee-live' }, (d.live || []).map((f) => h('li', {}, icon(f.icon || 'sparkle', 12), f.label))),
        h('p', { class: 'muted small' }, 'These update on their own from Public reach, Client journey, Speaking, your videos and About you.'),
        h('label', { class: 'marquee-speed-row' }, h('span', { class: 'muted small' }, 'Speed'), speed))));
}
