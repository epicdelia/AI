// "Make it yours": accent colour, text size, density, what the studio calls her, which sidebar
// items show, and the dashboard's cards and order. Saved in Settings (so every device matches)
// and cached in this browser so the colours are right before the first paint.
import { h, api, busy, toast, field, modal, apiUpload } from './core.js';
import { icon } from './icons.js';

const CACHE = 'studio-prefs';
let current = { accent: 'rose', text_size: 'default', density: 'comfortable', nav_hidden: [], dashboard_layout: null, nickname: '' };

export const ACCENTS = [
  ['rose', 'Rose', '#c93a70'], ['violet', 'Lavender', '#7b5cd6'], ['coral', 'Coral', '#c2452d'],
  ['ocean', 'Ocean', '#1f6fb0'], ['forest', 'Forest', '#237a55'], ['plum', 'Plum', '#9b3d8f'],
];

export function prefs() { return current; }

/** Apply to the page (html data attributes) and remember for the next first paint. */
export function applyPrefs(p) {
  if (!p) return;
  current = { ...current, ...p };
  const el = document.documentElement;
  el.dataset.accent = current.accent || 'rose';
  el.dataset.text = current.text_size || 'default';
  el.dataset.density = current.density || 'comfortable';
  el.dataset.nav = current.nav_mode || 'top';
  try { localStorage.setItem(CACHE, JSON.stringify({ accent: el.dataset.accent, text: el.dataset.text, density: el.dataset.density, nav: el.dataset.nav })); } catch { /* fine */ }
}

async function save(patch) {
  const body = {};
  for (const [k, v] of Object.entries(patch)) body[k] = Array.isArray(v) || (v && typeof v === 'object') ? JSON.stringify(v) : v;
  await api('PUT', 'settings', body);
  applyPrefs(patch);
}

/** Settings card. `nav` is the sidebar list ({to, label, header}) so items can be hidden. */
export function personaliseCard(nav) {
  const swatches = h('div', { class: 'accent-swatches', role: 'radiogroup', 'aria-label': 'Accent colour' }, ACCENTS.map(([key, label, hex]) => h('button', {
    type: 'button', role: 'radio', class: 'accent-swatch', 'aria-checked': String(current.accent === key), title: label, 'aria-label': label,
    style: `--sw: ${hex}`,
    onclick: async (e) => {
      swatches.querySelectorAll('.accent-swatch').forEach((b) => b.setAttribute('aria-checked', String(b === e.currentTarget)));
      await save({ accent: key }).catch((err) => toast(err.message, 'err'));
    },
  }, h('span', { 'aria-hidden': 'true' }), h('em', {}, label))));

  const seg = (key, options, label) => h('div', { class: 'segmented', role: 'group', 'aria-label': label }, options.map(([v, l]) => h('button', {
    type: 'button', class: `seg${(current[key] || options[0][0]) === v ? ' active' : ''}`,
    onclick: async (e) => {
      e.currentTarget.parentElement.querySelectorAll('.seg').forEach((b) => b.classList.toggle('active', b === e.currentTarget));
      await save({ [key]: v }).catch((err) => toast(err.message, 'err'));
      if (key === 'nav_mode') window.dispatchEvent(new CustomEvent('prefs:nav'));
    },
  }, l)));

  const nick = h('input', { type: 'text', value: current.nickname || '', maxlength: 40, placeholder: 'e.g. Delia, Dee, babe' });
  const nickSave = h('button', { type: 'button', class: 'btn sm', onclick: () => busy(nickSave, 'Saving…', async () => { await save({ nickname: nick.value.trim() }); toast('Saved. The studio calls you that from now on'); }) }, 'Save');

  // Profile photo (header avatar), motto, and the page the studio opens on.
  const photo = h('div', { class: 'profile-photo', 'aria-hidden': 'true' });
  const drawPhoto = () => photo.replaceChildren(current.avatar ? h('img', { class: 'profile-photo', src: `/api/me/avatar?${Date.now()}`, alt: '' }) : (current.nickname || 'S')[0].toUpperCase());
  drawPhoto();
  const photoInput = h('input', { type: 'file', accept: 'image/jpeg,image/png,image/webp', hidden: true, onchange: async () => {
    const f = photoInput.files[0];
    if (!f) return;
    const fd = new FormData();
    fd.append('photo', f);
    try { await apiUpload('me/avatar', fd); current.avatar = true; drawPhoto(); window.dispatchEvent(new CustomEvent('prefs:avatar')); toast('Looking good'); } catch (e) { toast(e.message, 'err'); }
  } });
  const motto = h('input', { type: 'text', value: current.motto || '', maxlength: 120, placeholder: 'e.g. Make the tech world a little kinder' });
  const mottoSave = h('button', { type: 'button', class: 'btn sm', onclick: () => busy(mottoSave, 'Saving…', async () => { await save({ motto: motto.value.trim() }); toast('Saved.'); }) }, 'Save');
  const start = h('select', { onchange: async (e) => { await save({ start_page: e.target.value }).catch((err) => toast(err.message, 'err')); toast('The studio opens there from now on.'); } },
    nav.filter((n) => n.to && !n.love).map((n) => h('option', { value: n.to, selected: (current.start_page || '/') === n.to }, n.label)));

  const hidden = new Set(current.nav_hidden || []);
  const navItems = nav.filter((n) => n.to && !['/', '/settings'].includes(n.to));
  const navBox = h('div', { class: 'nav-pick' }, navItems.map((n) => {
    const cb = h('input', { type: 'checkbox', checked: !hidden.has(n.to), onchange: async (e) => {
      e.target.checked ? hidden.delete(n.to) : hidden.add(n.to);
      await save({ nav_hidden: [...hidden] }).catch((err) => toast(err.message, 'err'));
      window.dispatchEvent(new CustomEvent('prefs:nav'));
    } });
    return h('label', { class: 'nav-pick-item' }, cb, icon(n.icon, 15), n.label);
  }));

  return h('section', { class: 'card personalise' },
    h('h2', {}, 'Make it yours'),
    h('p', { class: 'muted small' }, 'Colours, size and layout. Saved for every device you use.'),
    h('div', { class: 'grid grid-2 tight' },
      h('div', { class: 'field span-2' }, h('label', {}, 'Your photo', h('span', { class: 'hint' }, ' shown in the header')),
        h('div', { class: 'profile-photo-row' }, photo, photoInput,
          h('button', { type: 'button', class: 'btn sm', onclick: () => photoInput.click() }, icon('image', 14), 'Choose a photo'),
          h('button', { type: 'button', class: 'btn ghost sm', onclick: async () => { await api('DELETE', 'me/avatar'); current.avatar = false; drawPhoto(); window.dispatchEvent(new CustomEvent('prefs:avatar')); } }, 'Remove'))),
      h('div', { class: 'field span-2' }, h('label', {}, 'Your motto', h('span', { class: 'hint' }, ' a line under your dashboard greeting')), h('div', { class: 'row' }, motto, mottoSave)),
      h('div', { class: 'field' }, h('label', {}, 'Open the studio on'), start),
      h('div', { class: 'field span-2' }, h('label', {}, 'Accent colour'), swatches),
      h('div', { class: 'field' }, h('label', {}, 'Text size'), seg('text_size', [['default', 'Default'], ['large', 'Larger']], 'Text size')),
      h('div', { class: 'field' }, h('label', {}, 'Density'), seg('density', [['comfortable', 'Comfortable'], ['compact', 'Compact']], 'Density')),
      h('div', { class: 'field span-2' }, h('label', {}, 'Navigation on a computer', h('span', { class: 'hint' }, ' top menus with big dropdowns, or the sidebar kept open')), seg('nav_mode', [['top', 'Top menus'], ['sidebar', 'Sidebar']], 'Navigation')),
      h('div', { class: 'field span-2' }, h('label', {}, 'What should the studio call you?', h('span', { class: 'hint' }, ' greetings and love notes use it')),
        h('div', { class: 'row' }, nick, nickSave)),
      h('div', { class: 'field span-2' }, h('label', {}, 'Sidebar', h('span', { class: 'hint' }, ' untick what you don’t use; it’s still reachable with Ctrl K')), navBox)));
}

// ---------------------------------------------------------------- dashboard layout

/**
 * Order and hide the dashboard's blocks. blocks: [{id, label, node, defaultHidden?}] in default
 * order. defaultHidden blocks (e.g. the website editors, which live on /website) start off but
 * can be ticked back on in Customise.
 */
export function arrangeDashboard(blocks) {
  const layout = current.dashboard_layout;
  if (!Array.isArray(layout)) return blocks.filter((b) => b.node && !b.defaultHidden);
  const byId = Object.fromEntries(blocks.map((b) => [b.id, b]));
  const ordered = layout.map((l) => byId[l.id] && { ...byId[l.id], hidden: l.hidden }).filter(Boolean);
  for (const b of blocks) if (!layout.some((l) => l.id === b.id)) ordered.push({ ...b, hidden: !!b.defaultHidden }); // new blocks show up
  return ordered.filter((b) => b.node && !b.hidden);
}

export async function customiseDashboard(blocks, onDone) {
  const layout = Array.isArray(current.dashboard_layout) ? current.dashboard_layout : [];
  let rows = layout.filter((l) => blocks.some((b) => b.id === l.id)).map((l) => ({ id: l.id, hidden: !!l.hidden }));
  for (const b of blocks) if (!rows.some((r) => r.id === b.id)) rows.push({ id: b.id, hidden: !!b.defaultHidden });
  const label = (id) => blocks.find((b) => b.id === id)?.label || id;
  const saved = await modal('Customise your dashboard', (close) => {
    const list = h('ol', { class: 'dash-arrange' });
    const draw = () => list.replaceChildren(...rows.map((r, i) => h('li', { class: r.hidden ? 'off' : '' },
      h('label', {}, h('input', { type: 'checkbox', checked: !r.hidden, onchange: (e) => { r.hidden = !e.target.checked; draw(); } }), label(r.id)),
      h('span', { class: 'row' },
        h('button', { type: 'button', class: 'btn ghost sm', 'aria-label': `Move ${label(r.id)} up`, disabled: i === 0, onclick: () => { [rows[i - 1], rows[i]] = [rows[i], rows[i - 1]]; draw(); list.children[i - 1]?.querySelector('button')?.focus(); } }, '↑'),
        h('button', { type: 'button', class: 'btn ghost sm', 'aria-label': `Move ${label(r.id)} down`, disabled: i === rows.length - 1, onclick: () => { [rows[i + 1], rows[i]] = [rows[i], rows[i + 1]]; draw(); list.children[i + 1]?.querySelectorAll('button')[1]?.focus(); } }, '↓')))));
    draw();
    return h('div', {},
      h('p', { class: 'muted small' }, 'Tick what you want to see, in the order you want it.'),
      list,
      h('div', { class: 'row', style: { justifyContent: 'space-between', marginTop: '14px' } },
        h('button', { class: 'btn ghost', onclick: async () => { await save({ dashboard_layout: '' }); current.dashboard_layout = null; close(true); } }, 'Reset to default'),
        h('span', { class: 'row' },
          h('button', { class: 'btn', onclick: () => close(false) }, 'Cancel'),
          h('button', { class: 'btn primary', onclick: async () => { await save({ dashboard_layout: rows }); close(true); } }, 'Save'))));
  });
  if (saved) { toast('Dashboard updated.'); onDone(); }
}
