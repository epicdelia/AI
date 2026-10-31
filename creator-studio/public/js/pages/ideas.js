// Ideas: AI-generated briefs from breakout signals, plus her own. Pick, drop, edit, or turn into a script.
import { h, api, busy, toast, pageHead, emptyState, clear, pill, modal, confirmDialog, field, formData, navigate, timeAgo, loading } from '../core.js';
import { icon } from '../icons.js';

const TABS = [
  { key: '', label: 'All' },
  { key: 'new', label: 'New' },
  { key: 'picked', label: 'Picked' },
  { key: 'scripted', label: 'Scripted' },
  { key: 'dropped', label: 'Dropped' },
];

const EDIT_FIELDS = [
  ['title', 'Working title', 'input', 'under 60 characters works best'],
  ['thumbnail_text', 'Thumbnail text', 'input', '3 to 5 words'],
  ['premise', 'Premise', 'textarea', 'one sentence'],
  ['angle', 'My angle', 'textarea', 'what you have that others don’t'],
  ['format', 'Format', 'input', 'tutorial, experiment, reaction+take, story, explainer'],
  ['effort', 'Effort', 'input', 'low, med or high'],
  ['shelf_life', 'Shelf life', 'input', 'news or evergreen'],
  ['risk', 'Risk', 'textarea', 'what makes it flop'],
  ['source_url', 'Source URL', 'url'],
  ['notes', 'My notes', 'textarea'],
];

function ideaForm(idea, close, onSave) {
  const form = h('form', {
    onsubmit: async (e) => {
      e.preventDefault();
      const values = formData(form);
      for (const k of Object.keys(values)) if (values[k] === '') values[k] = null;
      const btn = form.querySelector('button[type=submit]');
      await busy(btn, 'Saving…', async () => {
        await onSave(values);
        close(true);
      });
    },
  },
  h('div', { class: 'grid grid-2 tight' }, EDIT_FIELDS.map(([name, label, kind, hint]) => {
    const value = idea?.[name] ?? '';
    const input = kind === 'textarea'
      ? h('textarea', { name, rows: name === 'notes' ? 4 : 2 }, value)
      : h('input', { type: kind === 'url' ? 'url' : 'text', name, value, required: name === 'title' });
    const f = field(label, input, hint);
    if (kind === 'textarea') f.classList.add('span-2');
    return f;
  })),
  h('div', { class: 'row', style: { justifyContent: 'flex-end' } },
    h('button', { type: 'button', class: 'btn', onclick: () => close(false) }, 'Cancel'),
    h('button', { type: 'submit', class: 'btn primary' }, 'Save idea')));
  return form;
}

export async function renderIdeas(view) {
  const state = { status: '', page: 1, ideas: [], lastPage: 1, counts: {}, fresh: 0 };
  const listBox = h('div');
  const tabsBox = h('div', { class: 'tabs', role: 'tablist' });
  const summaryBox = h('div');
  const freshText = h('span');

  async function load(reset = true) {
    if (reset) { state.page = 1; state.ideas = []; clear(listBox).append(loading()); }
    const q = new URLSearchParams({ page: state.page });
    if (state.status) q.set('status', state.status);
    const res = await api('GET', `ideas?${q}`);
    state.ideas = reset ? res.ideas.data : state.ideas.concat(res.ideas.data);
    state.lastPage = res.ideas.last_page;
    state.counts = res.counts || {};
    state.fresh = res.fresh_signals;
    drawTabs();
    drawList();
    freshText.textContent = state.fresh
      ? `${state.fresh} fresh breakout signal${state.fresh === 1 ? '' : 's'} ready to use.`
      : 'No fresh breakout signals: ideas will come from your niche (evergreen). Scan breakouts for timely ones.';
  }

  function drawTabs() {
    const total = Object.values(state.counts).reduce((a, b) => a + Number(b), 0);
    clear(tabsBox).append(TABS.map((t) => h('button', {
      class: `tab${state.status === t.key ? ' active' : ''}`,
      role: 'tab',
      'aria-selected': String(state.status === t.key),
      onclick: () => { state.status = t.key; load().catch((e) => toast(e.message, 'err')); },
    }, t.label, h('span', { class: 'count' }, t.key ? (state.counts[t.key] || 0) : total))));
  }

  async function update(idea, values, message) {
    const res = await api('PATCH', `ideas/${idea.id}`, values);
    Object.assign(idea, res.idea);
    if (message) toast(message);
    // Status changes move the card between tabs, so refresh counts and the filtered list.
    if ('status' in values) await load(); else drawList();
  }

  async function startScript(idea, blank, btn) {
    await busy(btn, blank ? 'Creating…' : 'Drafting script with AI… (up to a minute)', async () => {
      const res = await api('POST', 'scripts', { idea_id: idea.id, blank });
      toast(res.message);
      navigate(`/scripts/${res.script.id}`);
    });
  }

  function ideaCard(idea) {
    const meta = [idea.format, idea.effort && `effort: ${idea.effort}`, idea.shelf_life].filter(Boolean);
    const draftBtn = h('button', { class: 'btn primary sm', onclick: () => startScript(idea, false, draftBtn) }, icon('sparkle', 15), 'Draft script');
    const blankBtn = h('button', { class: 'btn sm', onclick: () => startScript(idea, true, blankBtn) }, icon('pen', 15), 'Write it myself');

    const actions = [];
    if (idea.status === 'new' || idea.status === 'dropped') {
      actions.push(h('button', { class: 'btn sm pick', onclick: () => update(idea, { status: 'picked' }, 'Picked. Nice choice.').catch((e) => toast(e.message, 'err')) }, icon('heart', 15), 'Pick'));
    }
    if (idea.status === 'new' || idea.status === 'picked') {
      actions.push(draftBtn, blankBtn);
    }
    if (idea.status === 'new' || idea.status === 'picked') {
      actions.push(h('button', { class: 'btn ghost sm', onclick: () => update(idea, { status: 'dropped' }).catch((e) => toast(e.message, 'err')) }, 'Drop'));
    }
    if (idea.status === 'dropped') {
      actions.push(h('button', { class: 'btn ghost sm', onclick: () => update(idea, { status: 'new' }).catch((e) => toast(e.message, 'err')) }, 'Restore'));
    }
    if (idea.status === 'scripted') {
      actions.push(h('button', { class: 'btn sm', onclick: () => navigate('/scripts') }, icon('doc', 15), 'Open scripts'));
    }

    return h('article', { class: `card idea-card status-${idea.status}` },
      h('div', { class: 'idea-top' },
        h('div', {},
          h('h3', {}, idea.title),
          idea.thumbnail_text ? h('div', { class: 'thumb-text' }, idea.thumbnail_text) : null),
        pill(idea.status)),
      idea.premise ? h('p', { class: 'premise' }, idea.premise) : null,
      h('dl', { class: 'idea-facts' },
        idea.angle ? [h('dt', {}, 'Angle'), h('dd', {}, idea.angle)] : null,
        idea.why_now ? [h('dt', {}, 'Why now'), h('dd', {}, idea.why_now)] : null,
        idea.risk ? [h('dt', {}, 'Risk'), h('dd', {}, idea.risk)] : null,
        idea.notes ? [h('dt', {}, 'Notes'), h('dd', { class: 'pre' }, idea.notes)] : null),
      h('div', { class: 'idea-meta' },
        meta.map((m) => h('span', { class: 'tag' }, m)),
        idea.source_url ? h('a', { href: idea.source_url, target: '_blank', rel: 'noopener', class: 'tag link' }, icon('link', 13), 'source') : null,
        h('span', { class: 'muted' }, timeAgo(idea.created_at))),
      h('div', { class: 'idea-actions' },
        h('div', { class: 'row' }, actions),
        h('div', { class: 'row' },
          h('button', {
            class: 'btn ghost sm', title: 'Edit',
            onclick: () => modal('Edit idea', (close) => ideaForm(idea, close, (v) => update(idea, v, 'Idea saved.')), { wide: true }),
          }, icon('pen', 15)),
          h('button', {
            class: 'btn ghost sm danger', title: 'Delete',
            onclick: async () => {
              if (!(await confirmDialog(`Delete “${idea.title}”?`))) return;
              try { await api('DELETE', `ideas/${idea.id}`); await load(); } catch (e) { toast(e.message, 'err'); }
            },
          }, icon('trash', 15)))));
  }

  function drawList() {
    clear(listBox);
    if (!state.ideas.length) {
      listBox.append(h('div', { class: 'card' }, emptyState('💡',
        state.status ? `No ${state.status} ideas` : 'No ideas yet',
        'Generate a batch from breakout signals, dictate one, or add your own.')));
      return;
    }
    listBox.append(h('div', { class: 'idea-grid' }, state.ideas.map(ideaCard)));
    if (state.page < state.lastPage) {
      const more = h('button', {
        class: 'btn', onclick: () => busy(more, 'Loading…', async () => { state.page++; await load(false); }),
      }, 'Load more');
      listBox.append(h('div', { class: 'row', style: { justifyContent: 'center', marginTop: '18px' } }, more));
    }
  }

  const extra = h('textarea', { rows: 2, placeholder: 'Optional direction: “something about budgeting for students”, “I just got a new camera”, “more experiments”…' });
  const genBtn = h('button', {
    class: 'btn primary',
    onclick: () => busy(genBtn, 'Thinking up ideas… (up to a minute)', async () => {
      const res = await api('POST', 'ideas/generate', { extra: extra.value });
      extra.value = '';
      clear(summaryBox);
      if (res.summary) summaryBox.append(h('div', { class: 'callout' }, h('strong', {}, 'Today’s signal: '), res.summary));
      toast(res.message);
      state.status = 'new';
      await load();
    }),
  }, icon('sparkle'), 'Generate ideas');

  const addBtn = h('button', {
    class: 'btn',
    onclick: () => modal('Add your own idea', (close) => {
      const form = h('form', {
        onsubmit: async (e) => {
          e.preventDefault();
          const v = formData(form);
          if (!v.source_url) delete v.source_url;
          await busy(form.querySelector('[type=submit]'), 'Saving…', async () => {
            const res = await api('POST', 'ideas', v);
            toast(res.message);
            close(true);
            state.status = 'picked';
            await load();
          });
        },
      },
      field('Working title', h('input', { type: 'text', name: 'title', required: true, maxlength: 200 })),
      field('Premise', h('textarea', { name: 'premise', rows: 2 }), 'one sentence'),
      field('Notes', h('textarea', { name: 'notes', rows: 4 })),
      field('Source URL', h('input', { type: 'url', name: 'source_url' }), 'optional'),
      h('div', { class: 'row', style: { justifyContent: 'flex-end' } },
        h('button', { type: 'button', class: 'btn', onclick: () => close(false) }, 'Cancel'),
        h('button', { type: 'submit', class: 'btn primary' }, 'Add to picked')));
      return form;
    }),
  }, icon('plus'), 'Add my own');

  view.append(
    pageHead('Ideas', 'Five ideas you can greenlight in three minutes: what’s working for others right now, filtered through your angle.', addBtn),
    h('div', { class: 'card generate-card' },
      h('div', { class: 'row-between wrap' }, h('h2', {}, 'Generate a batch'), h('span', { class: 'muted' }, freshText)),
      extra,
      h('div', { class: 'row', style: { marginTop: '10px' } }, genBtn)),
    summaryBox,
    tabsBox,
    listBox);

  await load();
}
