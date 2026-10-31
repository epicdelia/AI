// "For you": sealed "open when…" letters, special dates with countdowns, days together and a
// photo of the two of them. Plus the moments that pop up on their own: a letter unlocking, a
// follower milestone, an anniversary or birthday. The writer's desk at the bottom is for him.
import { h, api, busy, toast, pageHead, clear, field, modal, confirmDialog, link, apiUpload, emptyState } from '../core.js';
import { icon } from '../icons.js';
import { hearts, loveSignature } from '../love.js';

const KIND_ICON = { anniversary: 'heart', birthday: 'gift', other: 'calendar' };
const kindIcon = (k, size = 16) => icon(KIND_ICON[k] || 'calendar', size);
const NETS = [['instagram', 'Instagram'], ['tiktok', 'TikTok'], ['youtube', 'YouTube'], ['linkedin', 'LinkedIn'], ['x', 'X'], ['threads', 'Threads']];
const fmt = (iso) => new Date(`${iso}T12:00:00`).toLocaleDateString([], { day: 'numeric', month: 'long' });

/** Run once after sign-in: celebrate milestones, announce unlocked letters, mark special days. */
export async function checkLove() {
  // Wait for the morning hello (or the tour) to be closed first: one moment at a time.
  for (let i = 0; i < 240 && (document.querySelector('.modal-overlay') || document.querySelector('.tour-overlay, .tour-pop')); i++) {
    await new Promise((r) => setTimeout(r, 500));
  }
  let s;
  try { s = await api('GET', 'love/status'); } catch { return; }
  if (!s.enabled) return;
  const today = s.calendar?.today?.[0];
  const queue = [];
  if (today) {
    queue.push(() => modal(today.kind === 'birthday' ? 'Happy birthday!' : today.kind === 'anniversary' ? `Happy anniversary` : today.label, (close) => h('div', { class: 'love-card love-moment' },
      h('div', { class: 'love-big' }, kindIcon(today.kind, 34)),
      h('p', { class: 'love-text' }, today.kind === 'anniversary' && today.years ? `${today.years} year${today.years === 1 ? '' : 's'} of us. Best decision I ever made.`
        : today.kind === 'birthday' ? 'Today is all about you. No scripts, no posting schedule, just cake.' : `Today: ${today.label}`),
      h('p', { class: 'love-sign' }, loveSignature()),
      h('div', { class: 'row', style: { justifyContent: 'center' } }, h('button', { class: 'btn primary', onclick: () => { close(); hearts(30); } }, icon('heart-fill', 15), 'Love you')))));
  }
  for (const m of s.milestones || []) {
    queue.push(() => modal(`${m.count.toLocaleString()} on ${m.label}!`, (close) => h('div', { class: 'love-card love-moment' },
      h('div', { class: 'love-big' }, icon('trophy', 34)),
      h('p', { class: 'love-text' }, `${m.count.toLocaleString()} people follow you on ${m.label}. Every one of them is lucky. I was first in line.`),
      h('p', { class: 'love-sign' }, loveSignature()),
      h('div', { class: 'row', style: { justifyContent: 'center' } }, h('button', { class: 'btn primary', onclick: () => { close(); hearts(30); } }, 'Celebrate')))));
  }
  for (const l of s.unlocked || []) {
    queue.push(() => modal('A letter just unlocked', (close) => h('div', { class: 'love-card love-moment' },
      h('div', { class: 'love-big envelope-pop' }, icon('mail', 34)),
      h('p', { class: 'love-text' }, `“${l.title}”`),
      h('div', { class: 'row', style: { justifyContent: 'center' } },
        h('button', { class: 'btn', onclick: () => close() }, 'Later'),
        link('/love', { class: 'btn primary', onclick: () => close() }, 'Open it now')))));
  }
  for (const show of queue) { hearts(14); await show(); } // one at a time
}

/** Countdown/together line for the dashboard's love card. */
export async function loveExtras() {
  const box = h('div', { class: 'love-extras' });
  api('GET', 'love/status').then((s) => {
    if (!s.enabled) return;
    const c = s.calendar || {};
    const chips = [];
    if (c.together_days != null) chips.push(h('span', { class: 'love-chip' }, icon('heart', 13), h('strong', {}, c.together_days.toLocaleString()), ' days together'));
    const next = (c.upcoming || []).find((u) => u.days > 0);
    if (next) chips.push(h('span', { class: 'love-chip' }, kindIcon(next.kind, 13), ' ', `${next.label} in `, h('strong', {}, next.days), next.days === 1 ? ' day' : ' days'));
    if (s.waiting) chips.push(link('/love', { class: 'love-chip glow' }, icon('mail', 13), ` ${s.waiting} letter${s.waiting === 1 ? '' : 's'} for you`));
    if (s.photo) box.prepend(h('img', { class: 'love-photo-sm', src: '/api/love/photo', alt: 'Us', loading: 'lazy' }));
    box.append(h('div', { class: 'love-chips' }, chips));
  }).catch(() => {});
  return box;
}

// ---------------------------------------------------------------- page

export async function renderLove(view) {
  let d = await api('GET', 'love');

  const head = h('div');
  const jarBox = h('div');
  const lettersBox = h('div');
  const desk = h('div');

  function drawHead() {
    const c = d.calendar;
    clear(head).append(h('div', { class: 'card love-hero' },
      d.photo ? h('img', { class: 'love-photo', src: `/api/love/photo?${Date.now()}`, alt: 'The two of us' }) : h('div', { class: 'love-photo empty', 'aria-hidden': 'true' }, icon('heart', 44)),
      h('div', { class: 'love-hero-main' },
        h('h2', {}, c.together_days != null ? `${c.together_days.toLocaleString()} days together` : 'Just for you'),
        c.today?.length ? h('p', { class: 'love-today' }, `Today: ${c.today.map((t) => t.label).join(', ')}`) : null,
        c.upcoming?.length ? h('ul', { class: 'love-countdowns' }, c.upcoming.filter((u) => u.days > 0).map((u) => h('li', {},
          h('span', { class: 'love-cd-ico' }, kindIcon(u.kind)), h('span', {}, u.label, u.years ? ` (${u.years})` : ''), h('strong', {}, `${u.days} day${u.days === 1 ? '' : 's'}`), h('em', { class: 'muted small' }, fmt(u.date))))) : null,
        h('p', { class: 'love-sign' }, loveSignature()))));
  }

  function drawLetters() {
    const letters = d.letters;
    const fresh = letters.filter((l) => l.open && !l.opened_at);
    const sealed = letters.filter((l) => !l.open);
    const read = letters.filter((l) => l.opened_at);
    const env = (l) => h('button', {
      type: 'button', class: `envelope${l.open ? (l.opened_at ? ' read' : ' ready') : ' sealed'}`,
      'aria-label': `${l.title}. ${l.open ? (l.opened_at ? 'Read again' : 'Open it') : l.hint}`,
      onclick: async () => {
        if (!l.open) { toast(`Not yet ${l.hint}.`); return; }
        const r = await api('POST', `love/letters/${l.id}/open`).catch((e) => { toast(e.message, 'err'); return null; });
        if (!r) return;
        hearts(16);
        await modal(r.letter.title, () => h('div', { class: 'letter' }, h('div', { class: 'letter-paper' }, r.letter.body.split(/\n{2,}/).map((p) => h('p', {}, p)), h('p', { class: 'love-sign' }, loveSignature()))), { wide: true });
        d = await api('GET', 'love');
        drawLetters();
      },
    },
    h('span', { class: 'envelope-flap', 'aria-hidden': 'true' }),
    h('span', { class: 'envelope-seal', 'aria-hidden': 'true' }, icon(l.open ? (l.opened_at ? 'heart-fill' : 'mail') : 'lock', 26)),
    h('strong', {}, l.title),
    h('span', { class: 'small' }, l.open ? (l.opened_at ? 'Read it again' : 'Ready to open') : l.hint));
    clear(lettersBox).append(
      letters.length ? [
        fresh.length ? [h('h2', { class: 'section-title' }, 'Waiting for you'), h('div', { class: 'envelopes' }, fresh.map(env))] : null,
        sealed.length ? [h('h2', { class: 'section-title' }, 'Sealed until the moment'), h('div', { class: 'envelopes' }, sealed.map(env))] : null,
        read.length ? [h('h2', { class: 'section-title' }, 'Letters you’ve read'), h('div', { class: 'envelopes' }, read.map(env))] : null,
      ] : emptyState(icon('mail', 30), 'No letters yet', 'Letters written for you show up here: some open any time, some on a date, some when you hit a milestone.'));
  }

  // ---- writer's desk (for him) ----
  function drawDesk() {
    clear(desk).append(h('details', { class: 'card writers-desk' },
      h('summary', {}, h('strong', {}, icon('pen', 15), ' Writer’s desk'), h('span', { class: 'muted small' }, ' letters, dates and the photo (no peeking at the letters, Delia)')),
      h('div', { class: 'desk-grid' },
        h('div', {},
          h('div', { class: 'card-header' }, h('h3', {}, 'Letters'), h('button', { class: 'btn primary sm', onclick: () => editLetter() }, icon('plus', 14), 'Write a letter')),
          d.letters.length ? h('ul', { class: 'item-list' }, d.letters.map((l) => h('li', {},
            h('div', { class: 'item-main' }, h('strong', {}, l.title), h('div', { class: 'muted small' }, l.hint, l.opened_at ? ' · read' : '')),
            h('span', { class: 'row' },
              h('button', { class: 'btn ghost sm', 'aria-label': 'Edit', onclick: () => editLetter(l.id) }, icon('pen', 14)),
              h('button', { class: 'btn ghost sm', 'aria-label': 'Delete', onclick: async () => { if (await confirmDialog(`Delete “${l.title}”?`)) { await api('DELETE', `love/letters/${l.id}`); reload(); } } }, icon('trash', 14)))))) : h('p', { class: 'muted small' }, 'Write the first one: “Open when you’re nervous before filming”, “Open on our anniversary”, “Open at 10K followers”…')),
        h('div', {}, datesForm(), photoForm()))));
  }

  function datesForm() {
    const since = h('input', { type: 'date', value: d.together_since || '' });
    const rows = (d.dates.length ? d.dates : [{ label: 'Our anniversary', date: '', kind: 'anniversary', yearly: true }, { label: 'Her birthday', date: '', kind: 'birthday', yearly: true }]).map((x) => ({ ...x }));
    const list = h('div', { class: 'dates-edit' });
    const draw = () => list.replaceChildren(...rows.map((r, i) => h('div', { class: 'date-row' },
      h('select', { 'aria-label': 'Kind', onchange: (e) => { r.kind = e.target.value; } }, [['anniversary', 'Anniversary'], ['birthday', 'Birthday'], ['other', 'Other']].map(([v, l]) => h('option', { value: v, selected: r.kind === v }, l))),
      h('input', { type: 'text', value: r.label, placeholder: 'What', 'aria-label': 'What', oninput: (e) => { r.label = e.target.value; } }),
      h('input', { type: 'date', value: r.date, 'aria-label': 'Date', oninput: (e) => { r.date = e.target.value; } }),
      h('button', { type: 'button', class: 'btn ghost sm', 'aria-label': 'Remove', onclick: () => { rows.splice(i, 1); draw(); } }, icon('x', 13)))));
    draw();
    const saveBtn = h('button', {
      class: 'btn sm primary', onclick: () => busy(saveBtn, 'Saving…', async () => {
        await api('PUT', 'love/dates', { together_since: since.value || null, dates: rows.filter((r) => r.label.trim() && r.date).map((r) => ({ ...r, yearly: r.kind !== 'other' || !!r.yearly })) });
        toast('Dates saved');
        reload();
      }),
    }, 'Save dates');
    return h('div', { class: 'desk-block' },
      h('h3', {}, 'Special dates'),
      field('Together since', since, 'for the “days together” counter'),
      list,
      h('div', { class: 'row' }, h('button', { type: 'button', class: 'btn ghost sm', onclick: () => { rows.push({ label: '', date: '', kind: 'other', yearly: true }); draw(); } }, icon('plus', 13), 'Add a date'), saveBtn));
  }

  function photoForm() {
    const input = h('input', { type: 'file', accept: 'image/jpeg,image/png,image/webp', hidden: true, onchange: async () => {
      const f = input.files[0];
      if (!f) return;
      const fd = new FormData();
      fd.append('photo', f);
      try { await apiUpload('love/photo', fd); toast('Photo saved'); reload(); } catch (e) { toast(e.message, 'err'); }
    } });
    return h('div', { class: 'desk-block' },
      h('h3', {}, 'Our photo'),
      h('p', { class: 'muted small' }, 'Shown on this page and on her dashboard. Stays private on this computer (never on the sign-in page).'),
      h('div', { class: 'row' }, input,
        h('button', { type: 'button', class: 'btn sm', onclick: () => input.click() }, icon('image', 14), d.photo ? 'Change photo' : 'Add a photo'),
        d.photo ? h('button', { type: 'button', class: 'btn ghost sm', onclick: async () => { await api('DELETE', 'love/photo'); reload(); } }, 'Remove') : null));
  }

  async function editLetter(id = null) {
    const l = id ? (await api('GET', `love/letters/${id}`)).letter : { title: '', body: '', unlock: 'anytime', unlock_value: '' };
    const title = h('input', { type: 'text', value: l.title, maxlength: 120, placeholder: 'Open when…' });
    const body = h('textarea', { rows: 9, placeholder: 'Write it like you’d say it.' });
    body.value = l.body || '';
    const unlock = h('select', {}, [['anytime', 'Any time she needs it'], ['date', 'On a date'], ['followers', 'At a follower milestone'], ['first_post', 'When she publishes her first post from the studio'], ['streak', 'After a shipping streak (weeks)']]
      .map(([v, lab]) => h('option', { value: v, selected: l.unlock === v }, lab)));
    const [pv, nv] = String(l.unlock_value || '').includes(':') ? l.unlock_value.split(':') : ['instagram', l.unlock === 'followers' ? '' : ''];
    const date = h('input', { type: 'date', value: l.unlock === 'date' ? l.unlock_value : '' });
    const net = h('select', {}, NETS.map(([v, lab]) => h('option', { value: v, selected: pv === v }, lab)));
    const count = h('input', { type: 'number', min: 1, value: l.unlock === 'followers' ? nv : '', placeholder: '10000' });
    const weeks = h('input', { type: 'number', min: 1, value: l.unlock === 'streak' ? l.unlock_value : '', placeholder: '4' });
    const extra = h('div');
    const sync = () => extra.replaceChildren(
      unlock.value === 'date' ? field('Opens on', date) : null,
      unlock.value === 'followers' ? h('div', { class: 'grid grid-2 tight' }, field('Network', net), field('Followers', count)) : null,
      unlock.value === 'streak' ? field('Weeks in a row', weeks) : null);
    unlock.onchange = sync;
    sync();
    const ok = await modal(id ? 'Edit letter' : 'Write a letter', (close) => h('div', { class: 'ib-form' },
      field('Title she sees on the envelope', title),
      field('Opens', unlock), extra,
      field('The letter', body, 'sealed until it opens; stored encrypted'),
      h('div', { class: 'row', style: { justifyContent: 'flex-end', marginTop: '12px' } },
        h('button', { class: 'btn', onclick: () => close(false) }, 'Cancel'),
        h('button', {
          class: 'btn primary', onclick: (e) => busy(e.currentTarget, 'Sealing…', async () => {
            const value = { date: date.value, followers: `${net.value}:${count.value}`, streak: weeks.value }[unlock.value] ?? null;
            await api(id ? 'PUT' : 'POST', id ? `love/letters/${id}` : 'love/letters', { title: title.value.trim(), body: body.value, unlock: unlock.value, unlock_value: value });
            close(true);
          }),
        }, id ? 'Save' : 'Seal it'))), { wide: true });
    if (ok) { toast(id ? 'Saved.' : 'Sealed'); reload(); }
  }

  async function reload() {
    d = await api('GET', 'love');
    drawHead(); drawLetters(); drawDesk();
  }

  async function drawJar() {
    const j = await api('GET', 'love/jar').catch(() => null);
    if (!j) return;
    const t = j.today;
    const box = (who, name) => {
      const ta = h('textarea', { rows: 2, placeholder: `${name}’s answer…`, 'aria-label': `${name}’s answer` });
      ta.value = t[`${who}_answer`] || '';
      const save = h('button', { class: 'btn sm', type: 'button', onclick: () => busy(save, 'Saving…', async () => {
        await api('PUT', `love/jar/${t.id}`, { who, answer: ta.value });
        hearts(8);
        toast('Into the jar');
      }) }, 'Save');
      return h('div', { class: 'jar-answer' }, h('strong', { class: 'small' }, name), ta, save);
    };
    clear(jarBox).append(h('div', { class: 'card jar' },
      h('div', { class: 'card-header' }, h('h2', { class: 'with-icon' }, icon('jar', 20), 'Memory jar'), h('span', { class: 'muted small' }, 'A question a day, for both of you')),
      h('p', { class: 'jar-question' }, t.question),
      h('div', { class: 'jar-answers' }, box('her', j.her), box('his', j.him)),
      j.past.length ? h('details', { class: 'jar-past' }, h('summary', { class: 'small' }, `${j.past.length} memor${j.past.length === 1 ? 'y' : 'ies'} in the jar`),
        h('ul', {}, j.past.map((p) => h('li', {},
          h('span', { class: 'muted small' }, new Date(`${p.asked_on}T12:00:00`).toLocaleDateString([], { day: 'numeric', month: 'short', year: 'numeric' })),
          h('strong', {}, p.question),
          p.her_answer ? h('p', {}, h('em', {}, `${j.her}: `), p.her_answer) : null,
          p.his_answer ? h('p', {}, h('em', {}, `${j.him}: `), p.his_answer) : null)))) : null));
  }

  view.append(pageHead('For you', 'Letters, dates and little moments. Some letters open any time; others wait for a date or a milestone.'), head, jarBox, lettersBox, desk);
  drawHead(); drawLetters(); drawDesk(); drawJar();
}
