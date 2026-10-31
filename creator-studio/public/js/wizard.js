// Script wizard (ported from MagicAI's article wizard): pick a title, pick and edit a chapter
// plan, choose a length; the draft then streams into the editor following that plan.
import { h, api, busy, toast, modal, clear, navigate } from './core.js';
import { icon } from './icons.js';

/** opts: { idea } (an idea object) or nothing for a free topic. */
export function scriptWizard({ idea = null } = {}) {
  return modal(idea ? `Plan “${idea.title}”` : 'Plan a new video', (close) => {
    const st = { topic: '', title: '', minutes: 10, outlines: [], chapters: [] };
    const body = h('div', { class: 'wizard' });
    const steps = h('ol', { class: 'wizard-steps' }, ['Title', 'Chapters', 'Write'].map((s) => h('li', {}, s)));
    const mark = (n) => [...steps.children].forEach((li, i) => li.classList.toggle('on', i <= n));
    const base = () => ({ idea_id: idea?.id || null, topic: st.topic || null });

    // ---- step 1: titles ----
    function stepTitle() {
      mark(0);
      const topic = h('textarea', { rows: 3, placeholder: 'What is the video about? What happens, and why are you the one to make it?' }, st.topic);
      const list = h('div', { class: 'choice-list' });
      const custom = h('input', { type: 'text', maxlength: 120, placeholder: 'Or type your own title', value: st.title });
      const gen = h('button', {
        class: 'btn primary', type: 'button',
        onclick: () => busy(gen, 'Thinking up titles…', async () => {
          st.topic = topic.value.trim();
          const r = await api('POST', 'wizard/titles', { ...base(), count: 6 });
          clear(list).append(r.titles.map((t) => h('label', { class: 'choice' },
            h('input', { type: 'radio', name: 'title', value: t.title, onchange: () => { custom.value = t.title; } }),
            h('div', {}, h('strong', {}, t.title), h('div', { class: 'muted small' }, [t.style, t.why].filter(Boolean).join(' · '))))));
        }),
      }, icon('sparkle', 15), 'Suggest titles');
      const next = h('button', {
        class: 'btn primary', type: 'button',
        onclick: () => {
          st.topic = topic.value.trim();
          st.title = custom.value.trim();
          if (!st.title) { toast('Pick or type a title first.', 'err'); return; }
          stepChapters();
        },
      }, 'Next: chapters', icon('arrow', 15));
      clear(body).append(
        idea ? h('p', { class: 'muted' }, idea.premise || '') : h('div', { class: 'field' }, h('label', {}, 'The video'), topic),
        idea ? h('details', {}, h('summary', {}, 'Add your own notes'), topic) : null,
        h('div', { class: 'row', style: { margin: '6px 0 12px' } }, gen),
        list,
        h('div', { class: 'field', style: { marginTop: '12px' } }, h('label', {}, 'Title'), custom),
        h('div', { class: 'row wizard-nav' }, next));
    }

    // ---- step 2: chapter plans ----
    function stepChapters() {
      mark(1);
      const minutes = h('select', {}, [6, 8, 10, 12, 15, 20].map((m) => h('option', { value: m, selected: m === st.minutes }, `${m} min`)));
      const plans = h('div', { class: 'choice-list' });
      const editor = h('div');

      const drawEditor = () => {
        clear(editor);
        if (!st.chapters.length) return;
        editor.append(h('h3', {}, 'Your chapters'), h('p', { class: 'muted small' }, 'Edit, reorder or remove before writing.'),
          h('ol', { class: 'chapter-edit' }, st.chapters.map((c, i) => h('li', {},
            h('input', { type: 'text', value: c.title, maxlength: 120, oninput: (e) => { c.title = e.target.value; } }),
            h('input', { type: 'text', value: c.beat, maxlength: 400, placeholder: 'What happens', oninput: (e) => { c.beat = e.target.value; } }),
            h('div', { class: 'row' },
              h('button', { type: 'button', class: 'btn ghost sm', disabled: i === 0, 'aria-label': 'Move up', onclick: () => { [st.chapters[i - 1], st.chapters[i]] = [st.chapters[i], st.chapters[i - 1]]; drawEditor(); } }, '↑'),
              h('button', { type: 'button', class: 'btn ghost sm', 'aria-label': 'Remove', onclick: () => { st.chapters.splice(i, 1); drawEditor(); } }, icon('x', 13)))))),
          h('button', { type: 'button', class: 'btn ghost sm', onclick: () => { st.chapters.push({ title: 'New chapter', beat: '' }); drawEditor(); } }, icon('plus', 13), 'Add chapter'));
      };

      const gen = h('button', {
        class: 'btn primary', type: 'button',
        onclick: () => busy(gen, 'Planning chapters…', async () => {
          st.minutes = Number(minutes.value);
          const r = await api('POST', 'wizard/outlines', { ...base(), title: st.title, minutes: st.minutes, count: 3 });
          st.outlines = r.outlines;
          clear(plans).append(r.outlines.map((o, i) => h('label', { class: 'choice' },
            h('input', { type: 'radio', name: 'plan', onchange: () => { st.chapters = o.chapters.map((c) => ({ ...c })); drawEditor(); } }),
            h('div', {}, h('strong', {}, o.name || `Plan ${i + 1}`),
              h('ol', { class: 'muted small plan-preview' }, o.chapters.map((c) => h('li', {}, c.title)))))));
        }),
      }, icon('sparkle', 15), st.outlines.length ? 'New plans' : 'Suggest chapter plans');

      const next = h('button', {
        class: 'btn primary', type: 'button',
        onclick: () => {
          st.minutes = Number(minutes.value);
          st.chapters = st.chapters.filter((c) => c.title.trim());
          if (st.chapters.length < 2) { toast('Pick a plan (or add at least two chapters).', 'err'); return; }
          stepWrite();
        },
      }, 'Next', icon('arrow', 15));

      clear(body).append(
        h('p', {}, h('strong', {}, st.title)),
        h('div', { class: 'row', style: { marginBottom: '12px' } }, h('label', { class: 'inline-check' }, 'Length ', minutes), gen),
        plans, editor,
        h('div', { class: 'row wizard-nav' },
          h('button', { type: 'button', class: 'btn', onclick: stepTitle }, icon('back', 15), 'Back'), next));
      drawEditor();
    }

    // ---- step 3: confirm & write ----
    function stepWrite() {
      mark(2);
      const go = h('button', {
        class: 'btn primary', type: 'button',
        onclick: () => busy(go, 'Saving…', async () => {
          const r = await api('POST', 'wizard/create', { ...base(), title: st.title, minutes: st.minutes, chapters: st.chapters });
          close(true);
          navigate(`/scripts/${r.script.id}?draft=1`);
        }),
      }, icon('pen', 15), 'Write the script');
      clear(body).append(
        h('h3', {}, st.title),
        h('p', { class: 'muted' }, `About ${st.minutes} minutes (~${(st.minutes * 150).toLocaleString()} words), ${st.chapters.length} chapters:`),
        h('ol', {}, st.chapters.map((c) => h('li', {}, h('strong', {}, c.title), c.beat ? ` · ${c.beat}` : ''))),
        h('p', { class: 'muted small' }, 'The draft streams into the editor and follows this plan. The plan stays attached to the script.'),
        h('div', { class: 'row wizard-nav' }, h('button', { type: 'button', class: 'btn', onclick: stepChapters }, icon('back', 15), 'Back'), go));
    }

    stepTitle();
    return h('div', {}, steps, body);
  }, { wide: true });
}
