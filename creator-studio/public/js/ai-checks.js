// The AI engines where the work happens, not only on the Agents page: the script editor's
// "AI checks" tab, the post composer's reach check, an evergreen reboot for proven content,
// and the weekly production sprint. They run as the studio agent (her brand and voice, nothing
// credited to an agent). Scores from the model are labelled as estimates; her own numbers are
// shown next to them where she has enough.
import { h, api, busy, toast, clear, modal, field, copyText, pill } from './core.js';
import { icon } from './icons.js';
import { complianceDialog } from './deal-tools.js';

export const STUDIO_AGENT = { id: 'studio', name: 'Studio', platforms: ['youtube', 'linkedin', 'x', 'instagram', 'tiktok', 'threads'] };
const loadAgents = () => import('./pages/agents.js');

const ytId = (url) => String(url || '').match(/(?:youtu\.be\/|v=|\/shorts\/|\/live\/)([\w-]{11})/)?.[1] || '';

function checkCard(title, text, button) {
  return h('div', { class: 'card ai-check' }, h('div', {}, h('h3', {}, title), h('p', { class: 'muted small' }, text)), button);
}

/** Script editor → AI checks. `before` saves pending edits; `selection` returns selected text. */
export function aiChecksPane(script, { before = async () => {}, selection = () => '' } = {}) {
  const pane = h('div');
  const run = (fn) => async () => { await before(); fn(); };
  function draw() {
    const deals = script.deals || [];
    clear(pane).append(
      h('div', { class: 'card', style: { marginBottom: '14px' } },
        h('h2', {}, 'AI checks'),
        h('p', { class: 'muted small' }, 'Second opinions from the AI engines, run on this script with your brand and voice. Their scores are estimates, not measurements; title variants are also compared with your own videos once you have 8+ tracked.')),
      h('div', { class: 'ai-checks-grid' },
        checkCard('Retention audit', 'Where viewers are likely to drop off, and the pacing resets to add.',
          h('button', { class: 'btn primary sm', onclick: run(async () => (await loadAgents()).retentionAuditModal(STUDIO_AGENT, null, { title: script.title, body: script.body })) }, icon('play', 14), 'Audit retention')),
        checkCard('Title variants', 'Four title directions with thumbnail ideas, checked against your past videos.',
          h('button', { class: 'btn sm', onclick: run(async () => (await loadAgents()).banditTitlesModal(STUDIO_AGENT, null, { topic: script.title, videoId: ytId(script.youtube_url) })) }, icon('chart', 14), 'Write titles')),
        checkCard('Hook matrix', 'Six opening lines in different styles for this topic.',
          h('button', { class: 'btn sm', onclick: run(async () => (await loadAgents()).hookMatrixModal(STUDIO_AGENT, null, { topic: script.title })) }, icon('sparkle', 14), 'Write hooks')),
        checkCard('Claim shield', 'A quick AI read of the technical claims (your selection, or the opening). For live sources, use the Facts tab.',
          h('button', { class: 'btn sm', onclick: run(async () => (await loadAgents()).factCheckModal(STUDIO_AGENT, null, { text: selection() || String(script.body || '').slice(0, 4000) })) }, icon('check', 14), 'Check claims')),
        deals.length ? checkCard(`Sponsor check: ${deals.map((d) => d.brand_name).join(', ')}`, 'Talking points, ad disclosure, do-nots, code and link, against the deal.',
          h('div', { class: 'row' }, deals.map((d) => h('button', { class: 'btn sm', onclick: run(() => complianceDialog(d, { content: script.body, title: script.title })) }, icon('briefcase', 14), d.brand_name)))) : null,
        script.status === 'published' ? checkCard('Evergreen reboot', 'Three fresh angles on this video for social, ready to schedule.',
          h('button', { class: 'btn sm', onclick: run(() => evergreenDialog({ script_id: script.id, topic: script.title })) }, icon('repeat', 14), 'Find angles')) : null));
  }
  draw();
  return { pane, draw };
}

/** Three reboot angles for a proven script or post; the winning one can be scheduled. */
export function evergreenDialog({ script_id = null, post_id = null, topic = '', platform = 'linkedin' } = {}) {
  return modal('Evergreen reboot', (close) => {
    const net = h('select', { 'aria-label': 'Network' }, ['linkedin', 'x', 'threads', 'instagram', 'tiktok', 'youtube'].map((p) => h('option', { value: p, selected: p === platform }, p)));
    const out = h('div', { style: { marginTop: '14px' } });
    const go = h('button', {
      class: 'btn primary',
      onclick: (e) => busy(e.currentTarget, 'Finding fresh angles…', async () => {
        const r = (await api('POST', 'agents/studio/recycle-evergreen', { script_id: script_id || undefined, post_id: post_id || undefined, topic: topic || undefined, target_platform: net.value })).result;
        const win = r.winning_reboot || {};
        clear(out).append(
          r.historical_performance_driver ? h('p', { class: 'small' }, h('strong', {}, 'Why it worked: '), r.historical_performance_driver) : null,
          h('ul', { class: 'mini-list' }, (r.reboot_angles || []).map((a) => h('li', {}, h('span', {}, h('strong', {}, a.title), ` · ${a.archetype}`, a.hook ? h('div', { class: 'muted small' }, a.hook) : null), h('span', { class: 'muted small nowrap' }, a.suggested_platform)))),
          win.body ? h('div', { class: 'card', style: { marginTop: '10px' } },
            h('div', { class: 'row', style: { justifyContent: 'space-between' } }, h('strong', {}, win.title || 'Draft'), pill('', win.platform)),
            h('pre', { style: { whiteSpace: 'pre-wrap', fontFamily: 'inherit' } }, win.body),
            h('div', { class: 'row', style: { justifyContent: 'flex-end', gap: '8px' } },
              h('button', { class: 'btn sm', onclick: () => copyText(win.body) }, icon('copy', 13), 'Copy'),
              h('button', {
                class: 'btn sm primary',
                onclick: (ev) => busy(ev.currentTarget, 'Scheduling…', async () => {
                  const s = await api('POST', 'agents/studio/schedule-evergreen-reboot', { reboot: { platform: win.platform || net.value, body: win.body, title: win.title, parts: win.parts || [] } });
                  toast(`${s.message} It waits for your approval.`);
                  close(true);
                }),
              }, icon('calendar', 13), 'Schedule (waits for approval)'))) : null);
      }),
    }, icon('repeat', 14), 'Find angles');
    return h('div', {},
      h('p', { class: 'muted small' }, 'Keeps what worked, changes the angle for now. The draft goes to your calendar in your next good slot and waits for your approval.'),
      h('div', { class: 'row', style: { gap: '8px' } }, field('Network', net), go),
      out);
  }, { wide: true });
}

/** Post composer: reach problems on this network, a safer version, and her own post score. */
export function algorithmCheckDialog({ platform, content, title = '', onUse }) {
  return modal('Reach check', (close) => {
    const out = h('div', {}, h('p', { class: 'muted' }, 'Checking…'));
    api('POST', 'agents/studio/shield-algorithm', { platform, content, title: title || undefined }).then((r) => {
      const res = r.result;
      const ps = res.post_score;
      const aps = res.armored_post_score;
      const scoreLine = (s) => (s ? `${s.score}/99 · ${s.label} (${s.basis === 'your data' ? `your ${s.history} posts` : 'rules of thumb'})` : '–');
      clear(out).append(
        h('div', { class: 'grid grid-2' },
          h('div', { class: 'card stat-box' }, h('div', { class: 'num' }, `${res.algorithm_reach_score}`), h('div', { class: 'lbl' }, `reach risk score · ${res.score_source === 'rules' ? 'rule of thumb' : 'AI estimate'}`)),
          h('div', { class: 'card stat-box tone-accent' }, h('div', { class: 'num' }, ps ? ps.score : '–'), h('div', { class: 'lbl' }, `your post score now · ${ps?.basis || 'n/a'}`))),
        (res.penalties_detected || []).length ? h('ul', { class: 'checklist', style: { marginTop: '10px' } }, res.penalties_detected.map((p) => h('li', {},
          h('span', { class: 'check' }, '!'), h('span', {}, h('strong', {}, p.penalty_type.replace(/_/g, ' ')), ` (${p.severity}): ${p.explanation}`, p.fix_applied ? h('div', { class: 'muted small' }, `Fix: ${p.fix_applied}`) : null))))
          : h('p', { class: 'small', style: { marginTop: '10px' } }, icon('check', 13), ' No reach problems found.'),
        res.armored_content && res.armored_content.trim() !== content.trim() ? h('div', { class: 'card', style: { marginTop: '10px' } },
          h('div', { class: 'row', style: { justifyContent: 'space-between' } }, h('strong', {}, 'Safer version'), h('span', { class: 'muted small' }, `post score: ${scoreLine(aps)}`)),
          h('pre', { style: { whiteSpace: 'pre-wrap', fontFamily: 'inherit' } }, res.armored_content),
          res.first_comment_prompt ? h('p', { class: 'small' }, h('strong', {}, 'First comment: '), res.first_comment_prompt) : null,
          h('div', { class: 'row', style: { justifyContent: 'flex-end', gap: '8px' } },
            h('button', { class: 'btn sm', onclick: () => copyText(res.armored_content) }, icon('copy', 13), 'Copy'),
            onUse ? h('button', { class: 'btn sm primary', onclick: () => { onUse(res.armored_content, res.first_comment_prompt); close(true); } }, icon('check', 13), 'Use this version') : null)) : null,
        res.optimization_summary ? h('p', { class: 'muted small', style: { marginTop: '8px' } }, res.optimization_summary) : null);
    }).catch((e) => clear(out).append(h('p', { class: 'error-text' }, e.message)));
    return out;
  }, { wide: true });
}

/** Weekly goals: a production plan across brand deals, scripts in progress and social slots. */
export function sprintCard() {
  const hours = h('input', { type: 'number', min: 1, max: 80, value: 15, style: { width: '90px' }, 'aria-label': 'Hours you have this week' });
  const focus = h('input', { type: 'text', placeholder: 'Optional focus, e.g. finish the sponsor video', 'aria-label': 'Focus' });
  const out = h('div', { style: { marginTop: '12px' } });
  const go = h('button', {
    class: 'btn primary sm',
    onclick: (e) => busy(e.currentTarget, 'Planning your week…', async () => {
      const r = (await api('POST', 'agents/studio/plan-sprint', { hours_available: Number(hours.value) || 15, focus_topic: focus.value.trim() || undefined })).result;
      const c = r.target_commitments || {};
      clear(out).append(
        h('p', { class: 'small' }, `${r.sprint_dates || 'This week'}: ${c.youtube_videos ?? 0} video(s), ${c.social_posts ?? 0} social post(s), ${c.sponsor_deliverables ?? 0} sponsor deliverable(s).`),
        (r.sponsor_deadline_alerts || []).length ? h('ul', { class: 'mini-list' }, r.sponsor_deadline_alerts.map((a) => h('li', {}, h('span', {}, icon('briefcase', 13), ` ${a.brand}: ${a.deliverable}`), pill(a.urgency === 'high' ? 'bad' : 'WARN', a.status)))) : null,
        (r.batching_strategy || []).length ? h('div', { class: 'ai-checks-grid', style: { marginTop: '10px' } }, r.batching_strategy.map((b) => h('div', { class: 'card' },
          h('div', { class: 'row', style: { justifyContent: 'space-between' } }, h('strong', {}, b.session_name), h('span', { class: 'muted small' }, `${b.day} · ${b.duration_hours} h`)),
          h('ul', { class: 'mini-list' }, (b.tasks_combined || []).map((t) => h('li', {}, t)))))) : null,
        r.operations_advice ? h('p', { class: 'muted small' }, r.operations_advice) : null);
    }),
  }, icon('calendar', 14), 'Plan my week');
  return h('div', { class: 'card' },
    h('div', { class: 'card-header' }, h('h2', {}, 'Production sprint'), h('div', { class: 'row', style: { gap: '6px' } }, hours, h('span', { class: 'muted small' }, 'hours'), go)),
    h('p', { class: 'muted small' }, 'Batches your filming, writing and sponsor work for the week from your deals, scripts in progress and social targets.'),
    focus,
    out);
}
