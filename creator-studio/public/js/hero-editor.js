// Dashboard "Hero": the top of her website. Headline (with *accent words*, {countries} and
// {audience} filled from her real numbers), intro, button label, reassurance line, the status pill
// and which four stats show. A preview shows what visitors will read.
import { h, api, link, toast, field } from './core.js';
import { icon } from './icons.js';

const emphasize = (text) => String(text).split(/\*([^*]+)\*/).map((part, i) => (i % 2 ? h('em', {}, part) : part));

export function siteHeroCard(d, onChange) {
  if (!d) return null;
  const saveBtn = h('button', { class: 'btn primary sm', disabled: true, onclick: save }, 'Save');
  const dirty = () => { saveBtn.disabled = false; };
  const input = (value, props = {}) => h('input', { class: 'input', value: value ?? '', oninput: dirty, ...props });
  const fields = {
    headline: input(d.headline, { maxlength: 160 }),
    lede: h('textarea', { class: 'input', rows: 3, maxlength: 400, oninput: dirty }, d.lede || ''),
    cta: input(d.cta, { maxlength: 40 }),
    reassurance: input(d.reassurance, { maxlength: 160 }),
  };
  const showStatus = h('input', { type: 'checkbox', checked: d.show_status, onchange: dirty, 'aria-label': 'Show the status pill' });
  const statSelects = [0, 1, 2, 3].map((i) => h('select', { class: 'input', onchange: dirty, 'aria-label': `Stat ${i + 1}` },
    (d.catalog || []).map((c) => h('option', { value: c.key, selected: d.stats[i] === c.key, disabled: !c.available }, `${c.value} · ${c.label}${c.live ? ' (live)' : ''}`))));

  async function save() {
    const stats = statSelects.map((s) => s.value);
    if (new Set(stats).size !== 4) { toast('Pick four different stats.', 'err'); return; }
    saveBtn.disabled = true;
    try {
      await api('PUT', 'hero', {
        headline: fields.headline.value.trim(), lede: fields.lede.value.trim(), cta: fields.cta.value.trim(),
        reassurance: fields.reassurance.value.trim(), show_status: showStatus.checked, stats,
      });
      toast('Your hero is updated.');
      onChange?.();
    } catch (err) {
      saveBtn.disabled = false;
      toast(err.message || 'Could not save.', 'err');
    }
  }

  const p = d.preview || {};
  return h('div', { class: 'card hero-edit-card' },
    h('div', { class: 'card-header' },
      h('div', {},
        h('h2', {}, 'Hero'),
        h('p', { class: 'muted small', style: { margin: '2px 0 0' } }, 'The first thing visitors see on your website')),
      h('div', { class: 'row' }, link('/landing', { class: 'btn ghost sm' }, icon('eye', 14), 'Preview'), saveBtn)),
    h('div', { class: 'hero-edit-preview' },
      p.status ? h('span', { class: `hero-edit-status is-${p.status.state}` }, p.status.text) : null,
      h('strong', {}, ...emphasize(p.headline || '')),
      h('div', { class: 'hero-edit-stats' }, (p.stats || []).map((s) => h('span', {}, h('b', {}, `${s.value}${s.unit || ''}`), ` ${s.label}`)))),
    h('div', { class: 'hero-edit-grid' },
      field('Headline', fields.headline, 'Wrap words in *stars* for the accent. {countries} and {audience} fill in from your numbers.'),
      field('Intro', fields.lede),
      h('div', { class: 'grid grid-2' }, field('Button', fields.cta), field('Line under the buttons', fields.reassurance, 'Optional; separate points with ·')),
      h('div', { class: 'hero-edit-stat-pick' },
        h('p', { class: 'partners-head' }, 'Four stats'),
        h('div', { class: 'grid grid-2' }, statSelects)),
      h('label', { class: 'row', style: { gap: '10px', alignItems: 'center' } },
        h('span', { class: 'switch-mini' }, showStatus, h('span', { 'aria-hidden': 'true' })),
        h('span', { class: 'small' }, 'Status pill (from your build slots and the location in About you)'))));
}
