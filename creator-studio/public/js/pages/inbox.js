// Inbox: comments and DMs from her connected accounts in one place, AI reply drafts in her
// voice, and automations ("if someone comments GUIDE, reply and DM them the link").
import { h, api, busy, toast, pageHead, clear, field, timeAgo, modal, confirmDialog, link, emptyState } from '../core.js';
import { icon } from '../icons.js';
import { PLATFORM_ICONS } from './social.js';
import { refreshNotifications } from '../notifications.js';

const LABEL = { instagram: 'Instagram', threads: 'Threads', youtube: 'YouTube', tiktok: 'TikTok', linkedin: 'LinkedIn', x: 'X' };
const INTENT = { question: ['new', 'Question'], lead: ['ok', 'Lead'], praise: ['picked', 'Praise'], spam: ['bad', 'Spam'], other: ['', 'Comment'] };

export async function renderInbox(view) {
  const state = { status: 'open', platform: '', kind: '', tab: 'inbox' };
  let d = await api('GET', 'engage');
  const body = h('div');
  const tabs = h('div', { class: 'tabs', role: 'tablist' });

  async function load() {
    const q = new URLSearchParams(Object.entries({ status: state.status, platform: state.platform, kind: state.kind }).filter(([, v]) => v));
    d = await api('GET', `engage?${q}`);
    draw();
  }

  function draw() {
    clear(tabs).append([['inbox', 'Inbox', d.counts.open], ['rules', 'Automations', d.rules.length]].map(([k, label, n]) => h('button', {
      class: `tab${state.tab === k ? ' active' : ''}`, role: 'tab', 'aria-selected': String(state.tab === k), onclick: () => { state.tab = k; draw(); },
    }, label, n ? h('span', { class: 'count' }, n) : null)));
    clear(body).append(state.tab === 'inbox' ? inbox() : rules());
  }

  function supported() {
    return d.networks.filter((n) => n.supports.length);
  }

  function inbox() {
    const live = supported().filter((n) => n.connected);
    const select = (key, options, label) => {
      const s = h('select', { class: 'inline-select', 'aria-label': label, onchange: (e) => { state[key] = e.target.value; load(); } },
        options.map(([v, l]) => h('option', { value: v, selected: state[key] === v }, l)));
      return s;
    };
    const poll = h('button', {
      class: 'btn sm', disabled: !live.length, onclick: () => busy(poll, 'Checking…', async () => {
        const r = await api('POST', 'engage/poll');
        const errs = Object.entries(r.errors || {}).map(([p, m]) => `${LABEL[p]}: ${m}`);
        toast(`${r.new} new${r.sent ? `, ${r.sent} answered by your automations` : ''}.${errs.length ? ` ${errs.join(' ')}` : ''}`, errs.length ? 'err' : 'ok', 8000);
        await load();
        refreshNotifications();
      }),
    }, icon('refresh', 14), 'Check now');
    const draftAll = h('button', {
      class: 'btn ghost sm', onclick: () => busy(draftAll, 'Drafting…', async () => {
        const r = await api('POST', 'engage/draft-all');
        toast(r.drafted ? `Drafted ${r.drafted} repl${r.drafted === 1 ? 'y' : 'ies'}. Check and send.` : 'Everything open already has a draft.');
        await load();
      }),
    }, icon('sparkle', 14), 'Draft replies');

    return [
      !live.length ? h('div', { class: 'card callout' },
        h('strong', {}, 'Connect Instagram, Threads or YouTube to see comments here.'),
        h('p', { class: 'muted small' }, 'Instagram brings comments and DMs; Threads replies; YouTube comments. TikTok, LinkedIn and X don’t offer comment or DM access to individual creators.'),
        link('/connections', { class: 'btn primary sm' }, icon('plug', 14), 'Connections')) : null,
      h('div', { class: 'row-between wrap ib-toolbar' },
        h('div', { class: 'segmented' }, [['open', `To answer (${d.counts.open})`], ['replied', `Answered (${d.counts.replied})`], ['ignored', `Ignored & spam (${d.counts.ignored})`]]
          .map(([k, l]) => h('button', { class: `seg${state.status === k ? ' active' : ''}`, onclick: () => { state.status = k; load(); } }, l))),
        h('div', { class: 'row' },
          select('platform', [['', 'All networks'], ...supported().map((n) => [n.platform, LABEL[n.platform]])], 'Network'),
          select('kind', [['', 'Comments & DMs'], ['comment', 'Comments'], ['dm', 'DMs']], 'Type'),
          state.status === 'open' && d.items.length ? draftAll : null, poll)),
      live.length ? h('p', { class: 'muted small ib-polled' }, 'Checked automatically every 10 minutes. Last: ',
        live.map((n) => `${LABEL[n.platform]} ${d.last_poll[n.platform] ? timeAgo(d.last_poll[n.platform]) : 'not yet'}`).join(' · ')) : null,
      d.items.length ? h('div', { class: 'ib-list' }, d.items.map(item)) : emptyState(icon('inbox', 28),
        state.status === 'open' ? 'All caught up' : 'Nothing here', state.status === 'open' ? 'New comments and DMs appear here, with a reply drafted when an automation matches.' : null),
    ];
  }

  function item(it) {
    const [tone, tag] = INTENT[it.intent] || INTENT.other;
    const reply = h('textarea', { rows: 2, placeholder: it.kind === 'dm' ? 'Write a DM reply…' : 'Write a public reply…' });
    reply.value = it.reply_draft || '';
    const dm = it.platform === 'instagram' && it.kind === 'comment' ? h('textarea', { rows: 2, placeholder: 'Also send a private DM (optional, Instagram allows one per comment within 7 days)' }) : null;
    if (dm && it.rule?.dm_text) dm.value = it.rule.dm_text.replace('{name}', it.author ? `@${it.author}` : '');
    if (dm) dm.hidden = !dm.value;
    const addDm = dm && dm.hidden ? h('button', { class: 'btn ghost sm', onclick: (e) => { dm.hidden = false; dm.focus(); e.currentTarget.remove(); } }, icon('chat', 14), 'Add a DM') : null;
    const send = h('button', {
      class: 'btn primary sm', onclick: () => busy(send, 'Sending…', async () => {
        await api('POST', `engage/items/${it.id}/reply`, { text: reply.value, dm: dm?.value || null });
        toast('Sent.');
        await load();
        refreshNotifications();
      }),
    }, icon('check', 14), 'Send');
    const draft = h('button', {
      class: 'btn ghost sm', onclick: () => busy(draft, 'Writing…', async () => {
        const r = await api('POST', `engage/items/${it.id}/draft`);
        reply.value = r.item.reply_draft || '';
        reply.focus();
      }),
    }, icon('sparkle', 14), reply.value ? 'Redraft' : 'Draft with AI');
    const ignore = h('button', { class: 'btn ghost sm', onclick: () => busy(ignore, '…', async () => { await api('POST', `engage/items/${it.id}/ignore`); await load(); }) }, it.status === 'ignored' ? 'Restore' : 'Ignore');
    const done = it.status === 'replied';
    return h('div', { class: `card ib-item pf-${it.platform}${done ? ' done' : ''}` },
      h('div', { class: 'ib-top' },
        h('span', { class: 'ib-net' }, icon(PLATFORM_ICONS[it.platform], 16)),
        h('strong', {}, it.author ? `@${it.author}` : 'Someone'),
        h('span', { class: 'muted small' }, `${it.kind === 'dm' ? 'DM' : 'comment'} · ${timeAgo(it.received_at)}`),
        h('span', { class: `pill ${tone}` }, tag),
        it.rule ? h('span', { class: 'pill picked', title: 'Matched an automation' }, icon('bot', 12), it.rule.name) : null),
      it.post ? h('p', { class: 'muted small ib-on' }, 'On: ', it.post.url ? h('a', { href: it.post.url, target: '_blank', rel: 'noopener' }, (it.post.title || it.post.body || '').slice(0, 80)) : (it.post.title || it.post.body || '').slice(0, 80)) : null,
      h('p', { class: 'ib-text' }, it.text),
      done ? h('div', { class: 'ib-reply' }, h('span', { class: 'muted small' }, `${it.replied_by === 'rule' ? 'Answered by an automation' : it.replied_by === 'ai' ? 'You sent the AI draft' : 'You replied'} · ${timeAgo(it.replied_at)}`), h('p', {}, it.reply)) : [
        it.error ? h('p', { class: 'error-text small' }, it.error) : null,
        reply, dm,
        h('div', { class: 'row ib-actions' }, ignore, addDm, h('span', { class: 'grow' }), draft, send),
      ]);
  }

  function rules() {
    const nets = supported();
    return [
      h('div', { class: 'row-between wrap', style: { marginBottom: '14px' } },
        h('p', { class: 'muted small', style: { margin: 0 } }, 'Automations run on every new comment or DM, top to bottom; the first that matches handles it. Drafts wait in your Inbox unless “Send automatically” is on.'),
        h('button', { class: 'btn primary sm', onclick: () => ruleDialog() }, icon('plus', 14), 'New automation')),
      d.rules.length ? h('div', { class: 'ib-rules' }, d.rules.map((r) => h('div', { class: `card ib-rule${r.active ? '' : ' off'}` },
        h('div', { class: 'row-between' },
          h('div', {}, h('h2', {}, r.name),
            h('p', { class: 'muted small' }, d.triggers[r.trigger], r.trigger === 'keyword' ? `: ${r.keywords.join(', ')}` : '', ' · ',
              r.platforms.map((p) => LABEL[p]).join(', '), ' · ', r.reply_mode === 'ai' ? 'AI reply' : r.reply_mode === 'template' ? 'fixed reply' : 'no public reply',
              r.dm_text ? ' + DM' : '', ` · fired ${r.fired}×`)),
          h('div', { class: 'row' },
            h('span', { class: `pill ${r.auto_send ? 'ok' : 'new'}` }, r.auto_send ? 'Sends automatically' : 'Drafts for you'),
            h('label', { class: 'switch', title: r.active ? 'On' : 'Off' }, h('input', { type: 'checkbox', checked: r.active, onchange: async (e) => { await api('PATCH', `engage/rules/${r.id}`, { active: e.target.checked }); load(); } }), h('span', { class: 'slider' })),
            h('button', { class: 'btn ghost sm', onclick: () => ruleDialog(r) }, icon('pen', 14)),
            h('button', { class: 'btn ghost sm', 'aria-label': 'Delete', onclick: async () => { if (await confirmDialog(`Delete “${r.name}”?`)) { await api('DELETE', `engage/rules/${r.id}`); load(); } } }, icon('trash', 14))))))) :
        h('div', { class: 'card' }, h('h2', {}, 'Ideas to start with'), h('div', { class: 'ib-presets' }, [
          ['Comment “GUIDE”, get the link by DM', { name: 'Guide by DM', platforms: ['instagram'], trigger: 'keyword', keywords: ['guide'], reply_mode: 'template', reply_template: 'Sent it to your DMs {name} 💌', dm_text: 'Here’s the guide: (your link)', auto_send: false }],
          ['Answer questions in my voice', { name: 'Answer questions', platforms: nets.map((n) => n.platform), trigger: 'question', reply_mode: 'ai', instructions: 'Answer briefly. My gear list and links are in my bio.', auto_send: false }],
          ['Reply to every DM', { name: 'DM replies', platforms: ['instagram'], trigger: 'any_dm', reply_mode: 'ai', instructions: 'Friendly and short. For collabs or business, ask them to email me.', auto_send: false }],
        ].map(([label, preset]) => h('button', { class: 'ib-preset', onclick: () => ruleDialog(null, preset) }, icon('bot', 16), label, icon('arrow', 14))))),
    ];
  }

  async function ruleDialog(rule = null, preset = {}) {
    const r = { platforms: ['instagram'], trigger: 'keyword', keywords: [], reply_mode: 'ai', auto_send: false, max_per_hour: 20, ...preset, ...(rule || {}) };
    const nets = supported();
    const name = h('input', { type: 'text', value: r.name || '', maxlength: 80 });
    const pf = new Set(r.platforms);
    const pfBox = h('div', { class: 'wz-chips' });
    const drawPf = () => clear(pfBox).append(nets.map((n) => h('button', {
      type: 'button', class: `wz-chip pf-${n.platform}${pf.has(n.platform) ? ' on' : ''}`,
      onclick: () => { pf.has(n.platform) ? pf.delete(n.platform) : pf.add(n.platform); drawPf(); },
    }, icon(PLATFORM_ICONS[n.platform], 14), LABEL[n.platform], n.supports.includes('dm') ? h('span', { class: 'wz-tag' }, '+ DMs') : null)));
    drawPf();
    const trigger = h('select', {}, Object.entries(d.triggers).map(([k, l]) => h('option', { value: k, selected: r.trigger === k }, l)));
    const keywords = h('input', { type: 'text', value: (r.keywords || []).join(', '), placeholder: 'guide, link, preset' });
    const kwField = field('Keywords', keywords, 'comma-separated; whole words, any case');
    const mode = h('select', {}, [['ai', 'AI writes it in my voice'], ['template', 'A fixed reply'], ['none', 'No public reply (DM only)']].map(([v, l]) => h('option', { value: v, selected: r.reply_mode === v }, l)));
    const template = h('textarea', { rows: 2, placeholder: 'Sent it to your DMs {name}!' }); template.value = r.reply_template || '';
    const instructions = h('textarea', { rows: 2, placeholder: 'e.g. My gear list is at delia.link/gear. Never promise collabs.' }); instructions.value = r.instructions || '';
    const dmText = h('textarea', { rows: 2, placeholder: 'Here’s the link: …  (Instagram comments only)' }); dmText.value = r.dm_text || '';
    const auto = h('input', { type: 'checkbox', checked: !!r.auto_send });
    const perHour = h('input', { type: 'number', min: 1, max: 200, value: r.max_per_hour || 20, class: 'wz-num' });
    const tplField = field('Reply', template, '{name} = their @handle');
    const insField = field('Guidance for the AI', instructions, 'facts, links, what to avoid');
    const sync = () => { kwField.hidden = trigger.value !== 'keyword'; tplField.hidden = mode.value !== 'template'; insField.hidden = mode.value !== 'ai'; };
    trigger.onchange = sync; mode.onchange = sync; sync();

    const saved = await modal(rule ? 'Edit automation' : 'New automation', (close) => h('div', { class: 'ib-form' },
      field('Name', name),
      h('div', { class: 'field' }, h('label', {}, 'On'), pfBox),
      field('When', trigger), kwField,
      field('Then reply with', mode), tplField, insField,
      field('And send a DM (Instagram comments)', dmText, 'optional; one private reply per comment, within 7 days'),
      h('label', { class: 'wz-switch' }, h('span', {}, h('strong', {}, 'Send automatically'), h('span', { class: 'muted small' }, ' (off: replies wait in your Inbox for a tap)')), h('span', { class: 'switch' }, auto, h('span', { class: 'slider' }))),
      h('label', { class: 'wz-switch' }, h('span', {}, 'At most per hour'), perHour),
      h('div', { class: 'row', style: { justifyContent: 'flex-end', marginTop: '14px' } },
        h('button', { class: 'btn', onclick: () => close(false) }, 'Cancel'),
        h('button', {
          class: 'btn primary', onclick: (e) => busy(e.currentTarget, 'Saving…', async () => {
            const payload = {
              name: name.value.trim(), platforms: [...pf], trigger: trigger.value, keywords: keywords.value.split(',').map((k) => k.trim()).filter(Boolean),
              reply_mode: mode.value, reply_template: template.value || null, instructions: instructions.value || null, dm_text: dmText.value || null,
              auto_send: auto.checked, max_per_hour: Number(perHour.value || 20),
            };
            await api(rule ? 'PATCH' : 'POST', rule ? `engage/rules/${rule.id}` : 'engage/rules', payload);
            close(true);
          }),
        }, 'Save'))));
    if (saved) { toast('Automation saved.'); state.tab = 'rules'; load(); }
  }

  view.append(pageHead('Inbox', 'Comments and DMs from your connected accounts, with replies drafted in your voice. The more you edit and send, the more the drafts sound like you.'), tabs, body);
  draw();
}
