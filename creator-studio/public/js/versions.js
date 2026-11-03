// Version history for a script: every copy kept before autosave, AI or Copilot overwrote it.
import { h, api, busy, toast, modal, clear, timeAgo, confirmDialog, mdBlock } from './core.js';
import { icon } from './icons.js';
import { skLines } from './skeleton.js';

const SOURCE = { auto: 'Autosaved', manual: 'Saved by you', restore: 'Before a restore' };

function hunkView(hunks) {
  if (!hunks.some((x) => x.type === 'change')) return h('p', { class: 'muted' }, 'Same text as now.');
  return h('div', { class: 'vh-diff' }, hunks.map((x) => (x.type === 'same'
    ? h('div', { class: 'vh-same muted small' }, `${x.before.length} unchanged paragraph${x.before.length === 1 ? '' : 's'}`)
    : h('div', { class: 'vh-change' },
      x.before.length ? h('div', { class: 'vh-old' }, h('span', { class: 'vh-tag' }, 'In this version'), x.before.join('\n\n')) : null,
      x.after.length ? h('div', { class: 'vh-new' }, h('span', { class: 'vh-tag' }, 'Now'), x.after.join('\n\n')) : null))));
}

/**
 * Open the history. `beforeOpen` saves pending edits first; `onRestored(res)` gets the
 * restored script. Resolves when the dialog closes.
 */
export async function versionsDialog(script, { beforeOpen, onRestored } = {}) {
  await beforeOpen?.();
  let data = await api('GET', `scripts/${script.id}/versions`);
  let selected = null;
  let mode = 'diff';

  return modal('Version history', (close) => {
    const list = h('ul', { class: 'vh-list' });
    const pane = h('div', { class: 'vh-pane' });
    const label = h('input', { type: 'text', maxlength: 120, placeholder: 'Name this version (optional), e.g. “Before filming”' });
    const saveBtn = h('button', {
      class: 'btn sm primary',
      onclick: () => busy(saveBtn, 'Saving…', async () => {
        await beforeOpen?.();
        const r = await api('POST', `scripts/${script.id}/versions`, { label: label.value.trim() || null });
        label.value = '';
        data = await api('GET', `scripts/${script.id}/versions`);
        toast(r.message);
        drawList();
        select(r.version.id);
      }),
    }, icon('check', 15), 'Save current text');

    function drawList() {
      clear(list);
      if (!data.versions.length) {
        list.append(h('li', { class: 'muted small vh-empty' }, 'No versions yet. One is kept automatically every 10 minutes while you write, and before any big change.'));
        return;
      }
      for (const v of data.versions) {
        list.append(h('li', {},
          h('button', { class: `vh-item${selected?.id === v.id ? ' active' : ''}`, onclick: () => select(v.id) },
            h('strong', {}, v.label || SOURCE[v.source] || v.source),
            h('span', { class: 'muted small' }, `${timeAgo(v.created_at)} · ${new Date(v.created_at).toLocaleString([], { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' })} · ${Number(v.words).toLocaleString()} words`))));
      }
    }

    async function select(id) {
      clear(pane).append(skLines(10, 'this version'));
      selected = { id };
      drawList();
      try {
        const res = await api('GET', `scripts/${script.id}/versions/${id}`);
        if (selected?.id !== id) return; // she already clicked another one
        selected = { id, ...res };
      } catch (err) { clear(pane).append(h('p', { class: 'error-text' }, err.message)); return; }
      drawPane();
    }

    function drawPane() {
      if (!selected?.version) { clear(pane).append(h('p', { class: 'muted' }, 'Pick a version to compare it with your current text.')); return; }
      const v = selected.version;
      const restoreBtn = h('button', {
        class: 'btn sm primary',
        onclick: () => busy(restoreBtn, 'Restoring…', async () => {
          const r = await api('POST', `scripts/${script.id}/versions/${v.id}/restore`);
          toast(r.message);
          onRestored?.(r);
          close(true);
        }),
      }, icon('history', 15), 'Restore this version');
      const tabs = h('div', { class: 'row' },
        h('button', { class: `btn ghost sm${mode === 'diff' ? ' active' : ''}`, onclick: () => { mode = 'diff'; drawPane(); } }, 'Changes since'),
        h('button', { class: `btn ghost sm${mode === 'full' ? ' active' : ''}`, onclick: () => { mode = 'full'; drawPane(); } }, 'Full text'));
      clear(pane).append(
        h('div', { class: 'card-header' }, tabs,
          h('div', { class: 'row' },
            h('button', {
              class: 'btn ghost sm danger', title: 'Delete this version',
              onclick: async () => {
                if (!(await confirmDialog('Delete this saved version?'))) return;
                try {
                  await api('DELETE', `scripts/${script.id}/versions/${v.id}`);
                  data.versions = data.versions.filter((x) => x.id !== v.id);
                  selected = null;
                  drawList();
                  drawPane();
                } catch (err) { toast(err.message, 'err'); }
              },
            }, icon('trash', 15)),
            restoreBtn)),
        mode === 'diff' ? hunkView(selected.hunks) : h('div', { class: 'vh-full' }, mdBlock(v.body)));
    }

    drawList();
    drawPane();
    if (data.versions[0]) select(data.versions[0].id);

    return h('div', {},
      h('p', { class: 'muted small' }, 'Kept every 10 minutes while you write, before any big rewrite (AI, Copilot, a large paste or delete), and whenever you save one by hand. Restoring keeps your current text here too, so nothing is lost.'),
      h('div', { class: 'row', style: { marginBottom: '12px' } }, h('div', { style: { flex: '1', minWidth: '200px' } }, label), saveBtn),
      h('div', { class: 'vh-layout' }, list, pane));
  }, { wide: true });
}
