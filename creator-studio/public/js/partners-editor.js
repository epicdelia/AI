// Dashboard "Brand partners & stages": the strip under the landing page hero. Brands are hers to
// list (nothing comes from Deals without her adding it); stages come from her talks, and she can
// switch single stages or all of them off. Saving updates the website straight away.
import { h, api, link, toast } from './core.js';
import { icon } from './icons.js';

export function partnersCard(d, onChange) {
  if (!d) return null;
  let brands = (d.brands || []).map((b) => ({ ...b }));
  const hidden = new Set(d.hidden_stages || []);
  const saveBtn = h('button', { class: 'btn primary sm', disabled: true, onclick: save }, 'Save');
  const dirty = () => { saveBtn.disabled = false; };
  const showStages = h('input', { type: 'checkbox', checked: d.show_stages, onchange: () => { dirty(); drawStages(); }, 'aria-label': 'Show your talks as stages' });
  const brandList = h('ul', { class: 'partners-brands' });
  const stageList = h('div', { class: 'partners-stages' });

  function drawBrands() {
    brandList.replaceChildren(...brands.map((b, i) => h('li', {},
      h('input', { class: 'input', value: b.name, maxlength: 40, placeholder: 'Brand name', 'aria-label': `Brand ${i + 1} name`, oninput: (e) => { b.name = e.target.value; dirty(); } }),
      h('input', { class: 'input', type: 'url', value: b.url || '', maxlength: 300, placeholder: 'https:// (optional)', 'aria-label': `Brand ${i + 1} link`, oninput: (e) => { b.url = e.target.value; dirty(); } }),
      h('button', { type: 'button', class: 'btn ghost sm', 'aria-label': `Move ${b.name || 'brand'} up`, disabled: i === 0, onclick: () => { [brands[i - 1], brands[i]] = [brands[i], brands[i - 1]]; dirty(); drawBrands(); } }, icon('chevron-up', 13)),
      h('button', { type: 'button', class: 'btn ghost sm', 'aria-label': `Remove ${b.name || 'brand'}`, onclick: () => { brands.splice(i, 1); dirty(); drawBrands(); } }, icon('x', 13)))),
    h('li', {}, h('button', { type: 'button', class: 'btn ghost sm', disabled: brands.length >= 24, onclick: () => { brands.push({ name: '', url: '' }); dirty(); drawBrands(); brandList.querySelector('li:nth-last-child(2) input')?.focus(); } }, icon('plus', 13), 'Add a brand')));
  }

  function drawStages() {
    stageList.classList.toggle('is-off', !showStages.checked);
    stageList.replaceChildren(...(d.stages || []).map((s) => h('label', { class: 'partners-stage' },
      h('input', { type: 'checkbox', checked: !hidden.has(s.name), disabled: !showStages.checked, onchange: (e) => { if (e.target.checked) hidden.delete(s.name); else hidden.add(s.name); dirty(); } }),
      icon('mic', 12), s.name)),
      (d.stages || []).length ? null : h('p', { class: 'muted small' }, 'Add talks in Speaking and they appear here.'));
  }

  async function save() {
    saveBtn.disabled = true;
    try {
      await api('PUT', 'partners', { brands: brands.filter((b) => b.name.trim()), show_stages: showStages.checked, hidden_stages: [...hidden] });
      toast('Your partner strip is updated.');
      onChange?.();
    } catch (err) {
      saveBtn.disabled = false;
      toast(err.message || 'Could not save.', 'err');
    }
  }

  drawBrands();
  drawStages();
  return h('div', { class: 'card partners-card' },
    h('div', { class: 'card-header' },
      h('div', {},
        h('h2', {}, 'Brand partners & stages'),
        h('p', { class: 'muted small', style: { margin: '2px 0 0' } }, `${(d.items || []).length} names in the strip under your hero`)),
      h('div', { class: 'row' }, link('/landing', { class: 'btn ghost sm' }, icon('eye', 14), 'Preview'), saveBtn)),
    h('div', { class: 'partners-grid' },
      h('div', {}, h('p', { class: 'partners-head' }, icon('briefcase', 13), 'Brands'), brandList),
      h('div', {},
        h('div', { class: 'partners-head row', style: { justifyContent: 'space-between' } },
          h('span', {}, icon('mic', 13), ' Stages from your talks'),
          h('label', { class: 'switch-mini', title: 'Show stages' }, showStages, h('span', { 'aria-hidden': 'true' }))),
        stageList)));
}
