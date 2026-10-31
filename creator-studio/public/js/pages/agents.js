// Social media agents: a step-by-step setup (accounts → brand → audience → post types →
// fine-tuning → autonomy), a list of her agents, and each agent's page with its approval queue,
// schedule, results, and what it has learned from her edits and its numbers.
import { h, api, busy, toast, pageHead, clear, field, timeAgo, compact, modal, confirmDialog, link, navigate, emptyState } from '../core.js';
import { icon } from '../icons.js';
import { PLATFORM_ICONS } from './social.js';
import { refreshNotifications } from '../notifications.js';
import { scoreBadge } from '../growth.js';

const when = (iso) => iso ? new Date(iso).toLocaleString([], { weekday: 'short', day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' }) : 'not scheduled';
const netChip = (p, label) => h('span', { class: `pf-chip pf-${p}` }, icon(PLATFORM_ICONS[p], 13), label);

// ---------------------------------------------------------------- list

export async function renderAgents(view) {
  const d = await api('GET', 'agents');
  const meta = d.meta;
  const labelOf = (p) => meta.networks.find((n) => n.platform === p)?.label || p;

  view.append(pageHead('Agents', 'Agents plan, write and schedule posts for your networks, publish where they’re allowed, and get better from your edits and your numbers.',
    h('button', { class: 'btn primary', onclick: () => wizard(meta) }, icon('plus', 15), 'New agent')));

  if (!meta.ai) view.append(h('div', { class: 'card callout warn' }, 'Agents write with your AI engine. Add an AI key in ', link('/settings', {}, 'Settings'), ' first.'));

  if (!d.agents.length) {
    view.append(h('div', { class: 'card ag-hero' },
      h('div', { class: 'ag-hero-art', 'aria-hidden': 'true' }, icon('bot', 54)),
      h('h2', {}, h('span', { class: 'muted' }, 'Hey, I’m your AI social media agent. From planning to publishing, I '), 'create, schedule and improve your posts using your own results.'),
      h('button', { class: 'btn primary ag-cta', onclick: () => wizard(meta) }, 'Let’s get started', icon('arrow', 15)),
      h('p', { class: 'muted small' }, 'Every draft is editable, and nothing is published without your approval unless you say so.')));
    return;
  }

  view.append(h('div', { class: 'ag-grid' }, d.agents.map((a) => link(`/agents/${a.id}`, { class: `card ag-card${a.active ? '' : ' paused'}` },
    h('div', { class: 'ag-card-top' },
      h('span', { class: 'ag-avatar' }, icon('bot', 22)),
      h('div', { class: 'ag-card-name' }, h('h2', {}, a.name),
        h('span', { class: 'muted small' }, a.active ? (a.last_run_at ? `Planned ${timeAgo(a.last_run_at)}` : 'Starting…') : 'Paused')),
      h('span', { class: `pill ${a.approval === 'auto' ? 'ok' : 'new'}` }, a.approval === 'auto' ? 'Autonomous' : 'Reviews with you')),
    h('div', { class: 'row ag-nets' }, a.platforms.map((p) => netChip(p, labelOf(p)))),
    h('div', { class: 'ag-counts' },
      h('div', { class: a.counts.pending ? 'hot' : '' }, h('strong', {}, a.counts.pending), h('span', {}, 'to approve')),
      h('div', {}, h('strong', {}, a.counts.scheduled), h('span', {}, 'scheduled')),
      h('div', {}, h('strong', {}, a.counts.published), h('span', {}, 'published')),
      h('div', {}, h('strong', {}, a.lessons.length), h('span', {}, 'lessons')))))));
}

// ---------------------------------------------------------------- wizard

function wizard(meta, existing = null) {
  const s = existing ? {
    platforms: new Set(existing.platforms), site_url: existing.site_url || '', brand: existing.brand || '', audienceAuto: !existing.audience,
    audience: existing.audience || '', picked: new Set(), audiences: [], post_types: new Set(existing.post_types),
    hashtags: existing.options?.hashtags ?? true, hashtag_count: existing.options?.hashtag_count ?? 3, emoji: existing.options?.emoji ?? false,
    cta: existing.options?.cta || '', tone: existing.options?.tone || '', posts_per_week: existing.posts_per_week, approval: existing.approval, name: existing.name,
  } : {
    platforms: new Set(meta.networks.filter((n) => n.connected && n.can_publish).map((n) => n.platform)), site_url: '', brand: '', audienceAuto: true, audience: '',
    picked: new Set(), audiences: [], post_types: new Set(['informative', 'tips_and_tricks']), hashtags: true, hashtag_count: 3, emoji: false,
    cta: '', tone: '', posts_per_week: 3, approval: 'review', name: '',
  };
  let step = 0;

  return modal(existing ? `Edit ${existing.name}` : 'New agent', (close) => {
    const body = h('div', { class: 'wz-body' });
    const dots = h('div', { class: 'wz-dots', 'aria-hidden': 'true' });
    const err = h('p', { class: 'error-text small wz-err', role: 'alert' });
    const back = h('button', { class: 'btn ghost sm', type: 'button', onclick: () => go(step - 1) }, icon('back', 14), 'Back');

    function go(i) {
      err.textContent = '';
      step = Math.max(0, Math.min(STEPS.length - 1, i));
      clear(dots).append(STEPS.map((_, j) => h('span', { class: j === step ? 'on' : (j < step ? 'done' : '') })));
      back.hidden = step === 0;
      clear(body).append(h('div', { class: 'wz-step' }, STEPS[step]()));
      body.querySelector('input, textarea, button.wz-primary')?.focus();
    }
    const next = (check) => h('button', {
      class: 'btn primary block wz-primary', type: 'button',
      onclick: () => { const problem = check?.(); if (problem) { err.textContent = problem; return; } go(step + 1); },
    }, 'Continue', icon('arrow', 15));
    const title = (sub, main) => h('h2', { class: 'wz-title' }, h('span', {}, sub), main);
    const toggle = (label, key, onchange) => {
      const input = h('input', { type: 'checkbox', checked: !!s[key], onchange: (e) => { s[key] = e.target.checked; onchange?.(); } });
      return h('label', { class: 'wz-switch' }, h('span', {}, label), h('span', { class: 'switch' }, input, h('span', { class: 'slider' })));
    };

    function welcome() {
      return [
        h('div', { class: 'ag-hero-art', 'aria-hidden': 'true' }, icon('bot', 54)),
        h('h2', { class: 'wz-hello' }, h('span', { class: 'muted' }, 'Hey, I’m your AI social media agent. From planning to publishing, I help you '), 'create, schedule and improve posts with your own performance insights.'),
        h('button', { class: 'btn primary block wz-primary', type: 'button', onclick: () => go(1) }, 'Let’s get started', icon('arrow', 15)),
        h('p', { class: 'muted small center' }, 'Drafts are fully editable.'),
      ];
    }

    function accounts() {
      const pick = h('div', { class: 'wz-chips' });
      const draw = () => clear(pick).append(meta.networks.map((n) => h('button', {
        type: 'button', class: `wz-chip pf-${n.platform}${s.platforms.has(n.platform) ? ' on' : ''}`, 'aria-pressed': String(s.platforms.has(n.platform)),
        onclick: () => { s.platforms.has(n.platform) ? s.platforms.delete(n.platform) : s.platforms.add(n.platform); draw(); },
      }, icon(PLATFORM_ICONS[n.platform], 16), h('span', {}, n.handle ? `${n.label} · @${String(n.handle).replace(/^@/, '')}` : n.label),
      h('span', { class: `wz-tag ${n.publishes === 'auto' ? 'ok' : ''}`, title: n.publishes === 'auto' ? 'The agent can publish here' : (n.can_publish ? 'Connect it so the agent can publish' : 'You post these yourself when they’re due') }, n.publishes === 'auto' ? 'publishes' : 'reminds you'))));
      draw();
      return [
        title('Where should I post?', 'Pick your networks'), pick,
        h('p', { class: 'muted small' }, 'LinkedIn, Threads and X: the agent publishes once they’re connected. Instagram, TikTok and YouTube: it writes the post (slides, captions) and reminds you when it’s time to post it.'),
        next(() => s.platforms.size ? null : 'Pick at least one network.'),
        link('/connections', { class: 'btn ghost block', onclick: () => close() }, icon('plus', 14), 'Link a social account'),
      ];
    }

    function brand() {
      const url = h('input', { type: 'url', value: s.site_url, placeholder: 'Website URL (optional)', oninput: (e) => { s.site_url = e.target.value; } });
      const desc = h('textarea', { rows: 7, placeholder: 'Explain your brand: who you are, what you make, topics, what to promote (newsletter, videos, products)…', oninput: (e) => { s.brand = e.target.value; } });
      desc.value = s.brand;
      const scanned = h('p', { class: 'small wz-ok', hidden: true });
      const scan = h('button', {
        class: 'btn sm', type: 'button', onclick: () => busy(scan, 'Reading…', async () => {
          if (!url.value.trim()) { toast('Add the website address first.', 'err'); return; }
          const r = await api('POST', 'agents/scrape', { url: url.value.trim() });
          s.brand = desc.value = r.summary;
          scanned.hidden = false;
          scanned.replaceChildren(icon('check', 14), ` Read ${r.pages} page${r.pages === 1 ? '' : 's'}. Check and edit the summary below.`);
        }),
      }, icon('search', 14), 'Read my site');
      return [
        title('Add a URL or explain your brand', 'Tell me about you'),
        h('div', { class: 'wz-url' }, url, scan), scanned,
        h('div', { class: 'wz-or' }, h('span', {}, 'and / or describe it')),
        desc,
        next(() => s.brand.trim().length >= 20 ? null : 'Describe the brand in a sentence or two (or read the website).'),
      ];
    }

    function audience() {
      const box = h('div', { class: 'wz-box', hidden: s.audienceAuto });
      const chips = h('div', { class: 'wz-chips' });
      const free = h('textarea', { rows: 2, placeholder: 'Or describe them yourself, e.g. women in tech who want to work smarter with AI', oninput: (e) => { s.audience = e.target.value; } });
      free.value = s.picked.size ? '' : s.audience;
      const drawChips = () => clear(chips).append(s.audiences.map((a) => h('button', {
        type: 'button', class: `wz-chip${s.picked.has(a.name) ? ' on' : ''}`, title: a.description, 'data-tip': a.description,
        onclick: () => { s.picked.has(a.name) ? s.picked.delete(a.name) : s.picked.add(a.name); drawChips(); },
      }, a.name, s.picked.has(a.name) ? icon('check', 13) : null)));
      const gen = h('button', {
        class: 'btn ghost sm', type: 'button', onclick: () => busy(gen, 'Thinking…', async () => {
          const r = await api('POST', 'agents/audiences', { brand: s.brand });
          s.audiences = r.audiences; s.picked.clear(); drawChips(); gen.lastChild.textContent = 'Suggest others';
        }),
      }, icon('sparkle', 14), s.audiences.length ? 'Suggest others' : 'Suggest audiences');
      drawChips();
      box.append(chips, gen, free);
      return [
        title('Describe your target audience', 'Who are you trying to reach?'),
        toggle('Let the agent decide who engages best', 'audienceAuto', () => { box.hidden = s.audienceAuto; }),
        box,
        next(() => {
          if (s.audienceAuto) { s.audience = ''; return null; }
          const chosen = s.audiences.filter((a) => s.picked.has(a.name)).map((a) => `${a.name}: ${a.description}`);
          s.audience = [...chosen, free.value.trim()].filter(Boolean).join('\n');
          return s.audience ? null : 'Pick or describe an audience, or let the agent decide.';
        }),
      ];
    }

    function types() {
      const chips = h('div', { class: 'wz-chips' });
      const draw = () => clear(chips).append(meta.post_types.map((t) => h('button', {
        type: 'button', class: `wz-chip${s.post_types.has(t.key) ? ' on' : ''}`, title: t.hint, 'data-tip': t.hint, 'aria-pressed': String(s.post_types.has(t.key)),
        onclick: () => { s.post_types.has(t.key) ? s.post_types.delete(t.key) : s.post_types.add(t.key); draw(); },
      }, t.label, s.post_types.has(t.key) ? icon('check', 13) : null)));
      draw();
      return [title('Choose your content focus', 'What kind of posts?'), chips, next(() => s.post_types.size ? null : 'Pick at least one kind of post.')];
    }

    function tune() {
      const count = h('input', { type: 'number', min: 0, max: 10, value: s.hashtag_count, class: 'wz-num', oninput: (e) => { s.hashtag_count = Number(e.target.value); } });
      const countRow = h('label', { class: 'wz-switch', hidden: !s.hashtags }, h('span', {}, 'Hashtags per post'), count);
      const perWeek = h('input', { type: 'number', min: 1, max: 14, value: s.posts_per_week, class: 'wz-num', oninput: (e) => { s.posts_per_week = Number(e.target.value); } });
      return [
        title('Let’s fine-tune the strategy', 'Make it yours'),
        h('div', { class: 'wz-box' },
          h('label', { class: 'wz-switch' }, h('span', {}, 'Posts per week, on each network'), perWeek),
          toggle('Include hashtags', 'hashtags', () => { countRow.hidden = !s.hashtags; }), countRow,
          toggle('Use emoji', 'emoji'),
          field('Point people to (optional)', h('input', { type: 'text', value: s.cta, placeholder: 'e.g. my newsletter at delia.link/news, or my latest video', oninput: (e) => { s.cta = e.target.value; } })),
          field('Tone (optional)', h('input', { type: 'text', value: s.tone, placeholder: 'e.g. warm, a bit nerdy, never salesy', oninput: (e) => { s.tone = e.target.value; } }))),
        next(() => s.posts_per_week >= 1 && s.posts_per_week <= 14 ? null : 'Between 1 and 14 posts a week.'),
      ];
    }

    function autonomy() {
      const name = h('input', { type: 'text', value: s.name || `${[...s.platforms].map((p) => meta.networks.find((n) => n.platform === p)?.label).join(' & ')} agent`, maxlength: 80, oninput: (e) => { s.name = e.target.value; } });
      s.name = name.value;
      const manual = [...s.platforms].filter((p) => meta.networks.find((n) => n.platform === p)?.publishes !== 'auto');
      const opt = (value, head, text) => h('label', { class: `wz-option${s.approval === value ? ' on' : ''}` },
        h('input', { type: 'radio', name: 'approval', value, checked: s.approval === value, onchange: () => { s.approval = value; body.querySelectorAll('.wz-option').forEach((o) => o.classList.toggle('on', o.querySelector('input').checked)); } }),
        h('span', {}, h('strong', {}, head), h('span', { class: 'muted small' }, text)));
      const save = h('button', {
        class: 'btn primary block wz-primary', type: 'button', onclick: () => busy(save, existing ? 'Saving…' : 'Creating…', async () => {
          if (!name.value.trim()) { err.textContent = 'Give the agent a name.'; return; }
          const payload = {
            name: name.value.trim(), platforms: [...s.platforms], site_url: s.site_url || null, brand: s.brand.trim(), audience: s.audienceAuto ? null : s.audience,
            post_types: [...s.post_types], posts_per_week: s.posts_per_week, approval: s.approval,
            options: { hashtags: s.hashtags, hashtag_count: s.hashtag_count, emoji: s.emoji, cta: s.cta || null, tone: s.tone || null },
          };
          if (existing) {
            await api('PATCH', `agents/${existing.id}`, payload);
            toast('Saved.');
            close(true);
            return;
          }
          const r = await api('POST', 'agents', payload);
          close(true);
          navigate(`/agents/${r.agent.id}?first=1`);
        }),
      }, existing ? 'Save changes' : 'Create agent', icon('check', 15));
      return [
        title('Last step', 'Name it and choose how much it does alone'),
        field('Agent name', name),
        opt('review', 'Review every post first (recommended)', 'It plans and writes; you approve, edit or reject each post. Your edits and reasons teach it.'),
        opt('auto', 'Fully autonomous', 'It schedules and publishes by itself on LinkedIn, Threads and X (when connected). You can pause it any time.'),
        manual.length ? h('p', { class: 'muted small' }, `${manual.map((p) => meta.networks.find((n) => n.platform === p)?.label).join(', ')}: it writes and schedules, and the “Due now” reminder tells you when to post.`) : null,
        save,
      ];
    }

    const STEPS = existing ? [accounts, brand, audience, types, tune, autonomy] : [welcome, accounts, brand, audience, types, tune, autonomy];
    go(0);
    return h('div', { class: 'wz' }, dots, body, err, h('div', { class: 'wz-foot' }, back));
  }, { wide: false });
}

// ---------------------------------------------------------------- one agent

async function repurposeModal(agent, onDone) {
  const d = await api('GET', 'scripts').catch(() => ({ scripts: { data: [] } }));
  const scripts = d.scripts?.data || [];
  return modal(`Repurpose Video with ${agent.name}`, (close) => {
    if (!scripts.length) {
      return h('div', {},
        h('p', { class: 'muted' }, 'No scripts available yet. Write or draft a script first.'),
        h('button', { class: 'btn', onclick: () => close() }, 'Close'));
    }
    const list = h('ul', { class: 'item-list', style: { marginTop: '12px', maxHeight: '350px', overflowY: 'auto' } },
      scripts.map((sc) => h('li', {},
        h('div', { class: 'item-main' },
          h('strong', {}, sc.title),
          h('div', { class: 'muted small' }, `${sc.status} · edited ${timeAgo(sc.updated_at)}`)),
        h('button', {
          class: 'btn sm primary',
          onclick: async (e) => {
            const btn = e.target;
            await busy(btn, 'Repurposing…', async () => {
              try {
                const r = await api('POST', `agents/${agent.id}/repurpose-script`, { script_id: sc.id });
                toast(r.message || 'Posts generated and scheduled.');
                close();
                if (onDone) onDone();
              } catch (err) {
                toast(err.message || 'Failed to repurpose script.', 'err');
              }
            });
          }
        }, icon('sparkle', 13), 'Repurpose'))));

    return h('div', {},
      h('p', { class: 'muted small' }, 'Select a video script. The agent will autonomously adapt it into native posts across all active networks, scheduling each in the best available slot.'),
      list);
  }, { wide: true });
}

async function breakoutSpikeModal(agent, onDone) {
  const sigs = await api('GET', 'agents/signals').catch(() => ({ breakouts: [] }));
  const breakouts = sigs.breakouts || [];
  return modal(`Spike Post from Breakout Video`, (close) => {
    if (!breakouts.length) {
      return h('div', {},
        h('p', { class: 'muted' }, 'No recent breakout videos found. Add competitor channels and run a scan in Breakouts.'),
        h('button', { class: 'btn', onclick: () => close() }, 'Close'));
    }
    const list = h('ul', { class: 'item-list', style: { marginTop: '12px', maxHeight: '350px', overflowY: 'auto' } },
      breakouts.map((o) => h('li', {},
        h('div', { class: 'item-main' },
          h('strong', { class: 'clamp-1' }, o.title),
          h('div', { class: 'muted small' }, `${o.channel_title || 'Competitor'} · ${Number(o.outlier_score).toFixed(1)}x median`)),
        h('button', {
          class: 'btn sm primary',
          onclick: async (e) => {
            const btn = e.target;
            await busy(btn, 'Drafting…', async () => {
              try {
                const r = await api('POST', `agents/${agent.id}/spike-breakout`, { outlier_id: o.id });
                toast(r.message || 'Reaction post drafted.');
                close();
                if (onDone) onDone();
              } catch (err) {
                toast(err.message || 'Failed to draft breakout post.', 'err');
              }
            });
          }
        }, icon('trend', 13), 'Write spike post'))));

    return h('div', {},
      h('p', { class: 'muted small' }, 'Pick a breakout video. The agent will autonomously write a hot-take commentary from Delia’s perspective on the trend and schedule it.'),
      list);
  }, { wide: true });
}

async function deliberateModal(agent, onDone) {
  return modal(`Multi-Agent Deliberation Committee`, (close) => {
    const topicInput = h('textarea', {
      rows: 3,
      style: { width: '100%', marginBottom: '12px' },
      placeholder: 'Enter a topic, hypothesis, or engineering takeaway (e.g. "Why we replaced Redis with local SQLite in-memory caches and cut p99 latency by 4x")',
    });

    const netSelect = h('select', { style: { marginBottom: '12px' } },
      agent.platforms.map((p) => h('option', { value: p }, p.toUpperCase())));

    const resultBox = h('div', { style: { marginTop: '16px' } });

    const runBtn = h('button', {
      class: 'btn primary',
      onclick: async () => {
        const topic = topicInput.value.trim();
        if (!topic) return toast('Please enter a topic.', 'err');
        await busy(runBtn, 'Deliberating across 4 agents…', async () => {
          clear(resultBox).append(h('p', { class: 'muted small' }, 'Committee running: Generator → Grounding Auditor → Voice Critic → Optimizer…'));
          try {
            const r = await api('POST', `agents/${agent.id}/deliberate`, {
              topic,
              platform: netSelect.value,
            });

            clear(resultBox).append(
              h('div', { class: 'card', style: { padding: '14px', background: 'var(--card-subtle, rgba(255,255,255,0.02))' } },
                h('div', { class: 'row', style: { justifyContent: 'space-between', alignItems: 'center', marginBottom: '10px' } },
                  h('strong', {}, 'Committee Deliberation Trace'),
                  h('div', { class: 'row', style: { gap: '6px' } },
                    h('span', { class: `pill ${r.grounding_score >= 75 ? 'ok' : 'WARN'} small` }, `Grounding: ${r.grounding_score}/100`),
                    h('span', { class: `pill ${r.voice_score >= 75 ? 'ok' : 'WARN'} small` }, `Voice: ${r.voice_score}/100`))),
                h('div', { style: { display: 'flex', flexDirection: 'column', gap: '8px', marginBottom: '14px' } },
                  (r.trace || []).map((t) => h('div', {
                    style: { padding: '8px 10px', background: 'rgba(0,0,0,0.15)', borderRadius: '6px', fontSize: '13px' },
                  },
                  h('div', { class: 'row', style: { justifyContent: 'space-between' } },
                    h('span', { style: { fontWeight: 600, color: t.passed ? 'var(--ok)' : 'var(--warn)' } }, t.agent),
                    h('span', { class: 'muted small' }, t.role)),
                  h('p', { class: 'small muted', style: { margin: '4px 0 0' } },
                    typeof t.output === 'string' ? t.output : JSON.stringify(t.output))))),
                h('div', { class: 'card', style: { padding: '12px', background: 'var(--bg)' } },
                  h('strong', { class: 'small' }, 'Final Consensus Post Draft:'),
                  h('pre', { style: { whiteSpace: 'pre-wrap', marginTop: '6px', fontSize: '13px', fontFamily: 'inherit' } }, r.post.body),
                  h('div', { class: 'row', style: { justifyContent: 'flex-end', gap: '8px', marginTop: '10px' } },
                    h('button', {
                      class: 'btn sm primary',
                      onclick: async () => {
                        await api('POST', `social`, {
                          platform: r.post.platform,
                          body: r.post.body,
                          parts: r.post.parts,
                          social_agent_id: agent.id,
                        });
                        toast('Post added to drafts queue.');
                        close();
                        if (onDone) onDone();
                      },
                    }, icon('check', 13), 'Save to drafts queue')))));
          } catch (err) {
            clear(resultBox).append(h('p', { class: 'small', style: { color: 'var(--warn)' } }, `Deliberation failed: ${err.message}`));
          }
        });
      },
    }, icon('sparkle', 14), 'Run multi-agent deliberation');

    return h('div', {},
      h('p', { class: 'muted small' }, 'Simulates Big Tech frontier agent committees: 1) Creative Generator creates hook; 2) Grounding Auditor checks claims against source data; 3) Voice Critic removes corporate AI tells and ensures FTC compliance; 4) Consensus Optimizer outputs the verified draft.'),
      h('label', { class: 'small muted' }, 'Topic / Hypothesis:'),
      topicInput,
      h('label', { class: 'small muted' }, 'Target Network:'),
      netSelect,
      h('div', { class: 'row', style: { justifyContent: 'flex-end', gap: '8px' } },
        h('button', { class: 'btn', onclick: () => close() }, 'Cancel'),
        runBtn),
      resultBox);
  }, { wide: true });
}

async function swarmModal(agent, onDone) {
  return modal(`Multi-Agent Swarm Orchestrator (A2A Handoff DAG)`, (close) => {
    const goalInput = h('textarea', {
      rows: 3,
      style: { width: '100%', marginBottom: '12px' },
      placeholder: 'Enter a high-level creator campaign goal (e.g. "Launch an end-to-end multi-platform content campaign on why SQLite handles 100k req/s without Redis")',
    });

    const stepsContainer = h('div', { style: { marginTop: '16px' } });
    let currentMission = null;

    const renderMission = (mission) => {
      currentMission = mission;
      clear(stepsContainer).append(
        h('div', { class: 'card', style: { padding: '14px', background: 'var(--card-subtle, rgba(255,255,255,0.02))' } },
          h('div', { class: 'row', style: { justifyContent: 'space-between', alignItems: 'center', marginBottom: '12px' } },
            h('div', {},
              h('h4', { style: { margin: 0 } }, mission.title),
              h('p', { class: 'small muted', style: { margin: '2px 0 0' } }, `Status: ${mission.status} · Step ${mission.current_step}/${mission.steps.length}`)),
            h('div', { class: 'row', style: { gap: '8px' } },
              mission.status !== 'completed' ? h('button', {
                class: 'btn sm',
                onclick: async (e) => {
                  await busy(e.target, 'Stepping…', async () => {
                    const r = await api('POST', `agents/missions/${mission.id}/step`);
                    renderMission(r.mission);
                  });
                },
              }, icon('play', 13), 'Step next agent') : null,
              mission.status !== 'completed' ? h('button', {
                class: 'btn sm primary',
                onclick: async (e) => {
                  await busy(e.target, 'Running swarm…', async () => {
                    const r = await api('POST', `agents/missions/${mission.id}/run`);
                    renderMission(r.mission);
                    toast('Swarm mission completed.');
                    if (onDone) onDone();
                  });
                },
              }, icon('sparkle', 13), 'Run all steps') : null)),

          h('div', { style: { display: 'flex', flexDirection: 'column', gap: '8px', marginBottom: '14px' } },
            mission.steps.map((st, i) => {
              const isCurrent = i === mission.current_step && mission.status === 'running';
              const isDone = st.status === 'completed';
              return h('div', {
                style: {
                  padding: '10px 12px',
                  borderRadius: '6px',
                  border: isCurrent ? '1px solid var(--accent)' : '1px solid var(--border)',
                  background: isDone ? 'rgba(34, 197, 94, 0.05)' : isCurrent ? 'rgba(59, 130, 246, 0.08)' : 'rgba(0,0,0,0.1)',
                },
              },
              h('div', { class: 'row', style: { justifyContent: 'space-between', alignItems: 'center' } },
                h('div', { class: 'row', style: { gap: '8px', alignItems: 'center' } },
                  icon(isDone ? 'check' : isCurrent ? 'spinner' : 'bot', 14),
                  h('strong', { class: 'small' }, st.agent.replace('_', ' ').toUpperCase()),
                  st.handoff_to ? h('span', { class: 'pill small' }, `Handoff → ${st.handoff_to}`) : null),
                h('span', { class: `pill ${isDone ? 'ok' : isCurrent ? 'new' : ''} small` }, st.status)),
              h('p', { class: 'small', style: { margin: '6px 0 0' } }, st.task),
              st.output ? h('details', { style: { marginTop: '6px' } },
                h('summary', { class: 'small muted', style: { cursor: 'pointer' } }, 'Inspect step output'),
                h('pre', { style: { whiteSpace: 'pre-wrap', fontSize: '11px', background: 'rgba(0,0,0,0.2)', padding: '8px', borderRadius: '4px', marginTop: '4px' } },
                  JSON.stringify(st.output, null, 2))) : null);
            })),

          mission.result ? h('div', { class: 'card', style: { padding: '12px', background: 'var(--bg)' } },
            h('strong', { class: 'small' }, 'Mission Result Artifacts:'),
            h('pre', { style: { whiteSpace: 'pre-wrap', fontSize: '12px', marginTop: '6px' } }, JSON.stringify(mission.result, null, 2))) : null));
    };

    const planBtn = h('button', {
      class: 'btn primary',
      onclick: async () => {
        const goal = goalInput.value.trim();
        if (!goal) return toast('Please enter a campaign goal.', 'err');
        await busy(planBtn, 'Decomposing into worker agents…', async () => {
          try {
            const r = await api('POST', `agents/${agent.id}/swarm/plan`, { goal });
            toast(r.message);
            renderMission(r.mission);
          } catch (err) {
            toast(err.message || 'Failed to plan swarm mission.', 'err');
          }
        });
      },
    }, icon('sparkle', 14), 'Plan Swarm DAG');

    return h('div', {},
      h('p', { class: 'muted small' }, 'The Swarm Orchestrator dynamically decomposes high-level goals into specialized worker agents (Signal Scout, Angle Strategist, Platform Copywriter, Grounding Auditor) with durable state checkpoints and A2A handoffs.'),
      h('label', { class: 'small muted' }, 'Campaign Goal:'),
      goalInput,
      h('div', { class: 'row', style: { justifyContent: 'flex-end', gap: '8px' } },
        h('button', { class: 'btn', onclick: () => close() }, 'Close'),
        planBtn),
      stepsContainer);
  }, { wide: true });
}

async function treeSearchModal(agent, onDone) {
  return modal(`Language Agent Tree Search (LATS / ToT)`, (close) => {
    const topicInput = h('textarea', {
      rows: 2,
      style: { width: '100%', marginBottom: '12px' },
      placeholder: 'Enter a topic or technical architecture problem to explore (e.g. "Distributed transactions vs Event-Driven Sagas")',
    });

    const netSelect = h('select', { style: { marginBottom: '12px' } },
      agent.platforms.map((p) => h('option', { value: p }, p.toUpperCase())));

    const resultBox = h('div', { style: { marginTop: '16px' } });

    const searchBtn = h('button', {
      class: 'btn primary',
      onclick: async () => {
        const topic = topicInput.value.trim();
        if (!topic) return toast('Please enter a topic.', 'err');
        await busy(searchBtn, 'Exploring tree & evaluating nodes…', async () => {
          clear(resultBox).append(h('p', { class: 'muted small' }, 'LATS running: Expanding candidate angles → Generating draft leaves → Evaluating external verifiers → Pruning dead ends…'));
          try {
            const r = await api('POST', `agents/${agent.id}/tree-search`, {
              topic,
              platform: netSelect.value,
              branches_per_level: 3,
            });

            clear(resultBox).append(
              h('div', { class: 'card', style: { padding: '14px', background: 'var(--card-subtle, rgba(255,255,255,0.02))' } },
                h('div', { class: 'row', style: { justifyContent: 'space-between', alignItems: 'center', marginBottom: '10px' } },
                  h('strong', {}, 'Tree Search Trajectory Graph'),
                  h('div', { class: 'row', style: { gap: '6px' } },
                    h('span', { class: 'pill small' }, `Explored: ${r.stats.total_nodes_explored} nodes`),
                    h('span', { class: 'pill WARN small' }, `Pruned: ${r.stats.branches_pruned}`),
                    h('span', { class: 'pill ok small' }, `Highest: ${r.stats.highest_score}/100`))),

                h('div', { style: { display: 'flex', flexDirection: 'column', gap: '8px', marginBottom: '12px' } },
                  (r.tree.nodes || []).filter((n) => n.depth > 0).map((n) => {
                    const isWinning = (r.tree.winning_path || []).includes(n.id);
                    return h('div', {
                      style: {
                        padding: '8px 10px',
                        borderRadius: '6px',
                        border: isWinning ? '1px solid var(--ok)' : n.is_pruned ? '1px solid rgba(239,68,68,0.3)' : '1px solid var(--border)',
                        background: isWinning ? 'rgba(34,197,94,0.06)' : n.is_pruned ? 'rgba(239,68,68,0.04)' : 'rgba(0,0,0,0.1)',
                      },
                    },
                    h('div', { class: 'row', style: { justifyContent: 'space-between', alignItems: 'center' } },
                      h('div', { class: 'row', style: { gap: '6px', alignItems: 'center' } },
                        h('span', { class: `pill small ${n.type === 'angle' ? 'picked' : ''}` }, n.type.toUpperCase()),
                        h('strong', { class: 'small' }, n.label)),
                      h('div', { class: 'row', style: { gap: '6px' } },
                        n.score != null ? h('span', { class: `pill small ${n.score >= 70 ? 'ok' : 'WARN'}` }, `Score: ${n.score}`) : null,
                        n.is_pruned ? h('span', { class: 'pill WARN small' }, 'PRUNED') : null,
                        isWinning ? h('span', { class: 'pill ok small' }, 'WINNING TRAJECTORY') : null)));
                  })),

                h('div', { class: 'card', style: { padding: '12px', background: 'var(--bg)' } },
                  h('strong', { class: 'small' }, 'Winning Trajectory Post Draft:'),
                  h('pre', { style: { whiteSpace: 'pre-wrap', fontSize: '13px', marginTop: '6px', fontFamily: 'inherit' } }, r.post.body),
                  h('div', { class: 'row', style: { justifyContent: 'flex-end', gap: '8px', marginTop: '10px' } },
                    h('button', {
                      class: 'btn sm primary',
                      onclick: async () => {
                        await api('POST', 'social', {
                          platform: r.post.platform,
                          body: r.post.body,
                          parts: r.post.parts,
                          social_agent_id: agent.id,
                        });
                        toast('Winning trajectory saved to drafts queue.');
                        close();
                        if (onDone) onDone();
                      },
                    }, icon('check', 13), 'Save to drafts queue')))));
          } catch (err) {
            clear(resultBox).append(h('p', { class: 'small', style: { color: 'var(--warn)' } }, `Tree search failed: ${err.message}`));
          }
        });
      },
    }, icon('sparkle', 14), 'Execute Tree Search');

    return h('div', {},
      h('p', { class: 'muted small' }, 'DeepMind/OpenAI-style inference-time search: simultaneously explores multiple strategic angles and draft trajectories, uses external verifiers (AiTellsLinter + Rubric) to prune dead ends, and backtracks to select the optimal path.'),
      h('label', { class: 'small muted' }, 'Topic / Architecture:'),
      topicInput,
      h('label', { class: 'small muted' }, 'Target Network:'),
      netSelect,
      h('div', { class: 'row', style: { justifyContent: 'flex-end', gap: '8px' } },
        h('button', { class: 'btn', onclick: () => close() }, 'Cancel'),
        searchBtn),
      resultBox);
  }, { wide: true });
}

async function debateModal(agent, onDone) {
  return modal(`Adversarial Red-Team Debate Arena`, (close) => {
    const topicInput = h('textarea', {
      rows: 2,
      style: { width: '100%', marginBottom: '12px' },
      placeholder: 'Enter a topic to debate, or leave blank to stress-test your agent persona (e.g. "Why microservices are an anti-pattern for 99% of startups")',
    });

    const netSelect = h('select', { style: { marginBottom: '12px' } },
      agent.platforms.map((p) => h('option', { value: p }, p.toUpperCase())));

    const debateBox = h('div', { style: { marginTop: '16px' } });

    const debateBtn = h('button', {
      class: 'btn primary',
      onclick: async () => {
        const topic = topicInput.value.trim() || 'Why simpler architectures win in production';
        await busy(debateBtn, 'Conducting adversarial debate…', async () => {
          clear(debateBox).append(h('p', { class: 'muted small' }, 'Arena running: Creator Advocate → Red-Team Cynic Attack → Rebuttal Defense → Arbiter Armored Post…'));
          try {
            const r = await api('POST', `agents/${agent.id}/debate`, {
              topic,
              platform: netSelect.value,
            });

            clear(debateBox).append(
              h('div', { class: 'card', style: { padding: '14px', background: 'var(--card-subtle, rgba(255,255,255,0.02))' } },
                h('div', { class: 'row', style: { justifyContent: 'space-between', alignItems: 'center', marginBottom: '12px' } },
                  h('strong', {}, 'Adversarial Debate Transcript (4 Rounds)'),
                  h('span', { class: 'pill ok small' }, `Confidence: ${r.confidence_score}/100`)),

                h('div', { style: { display: 'flex', flexDirection: 'column', gap: '8px', marginBottom: '14px' } },
                  (r.transcript || []).map((round) => {
                    const isCynic = round.agent === 'Red-Team Cynic';
                    const isArbiter = round.agent === 'Arbiter Judge';
                    return h('div', {
                      style: {
                        padding: '10px 12px',
                        borderRadius: '6px',
                        border: isCynic ? '1px solid rgba(239,68,68,0.3)' : isArbiter ? '1px solid var(--ok)' : '1px solid var(--border)',
                        background: isCynic ? 'rgba(239,68,68,0.05)' : isArbiter ? 'rgba(34,197,94,0.05)' : 'rgba(0,0,0,0.1)',
                      },
                    },
                    h('div', { class: 'row', style: { justifyContent: 'space-between', alignItems: 'center' } },
                      h('div', { class: 'row', style: { gap: '6px', alignItems: 'center' } },
                        h('strong', { class: 'small', style: { color: isCynic ? 'var(--warn)' : isArbiter ? 'var(--ok)' : 'inherit' } }, `Round ${round.round}: ${round.agent}`),
                        h('span', { class: 'muted small' }, `· ${round.role}`))),
                    h('p', { class: 'small', style: { margin: '6px 0 0', whiteSpace: 'pre-wrap' } }, round.content));
                  })),

                h('div', { class: 'card', style: { padding: '12px', background: 'var(--bg)' } },
                  h('strong', { class: 'small' }, 'Battle-Tested & Armored Draft:'),
                  h('pre', { style: { whiteSpace: 'pre-wrap', fontSize: '13px', marginTop: '6px', fontFamily: 'inherit' } }, r.post.body),
                  h('div', { class: 'row', style: { justifyContent: 'flex-end', gap: '8px', marginTop: '10px' } },
                    h('button', {
                      class: 'btn sm primary',
                      onclick: async () => {
                        await api('POST', 'social', {
                          platform: r.post.platform,
                          body: r.post.body,
                          parts: r.post.parts,
                          social_agent_id: agent.id,
                        });
                        toast('Armored post added to drafts queue.');
                        close();
                        if (onDone) onDone();
                      },
                    }, icon('check', 13), 'Save armored post to drafts queue')))));
          } catch (err) {
            clear(debateBox).append(h('p', { class: 'small', style: { color: 'var(--warn)' } }, `Debate failed: ${err.message}`));
          }
        });
      },
    }, icon('alert', 14), 'Start Red-Team Debate');

    return h('div', {},
      h('p', { class: 'muted small' }, 'Simulates multi-agent adversarial debate (Society of Mind): an aggressive Red-Team Cynic attacks your thesis for hype, ungrounded claims, and missing edge-cases; an Arbiter synthesizes the debate into a bulletproof, battle-hardened post.'),
      h('label', { class: 'small muted' }, 'Topic / Thesis to Stress-Test:'),
      topicInput,
      h('label', { class: 'small muted' }, 'Target Network:'),
      netSelect,
      h('div', { class: 'row', style: { justifyContent: 'flex-end', gap: '8px' } },
        h('button', { class: 'btn', onclick: () => close() }, 'Cancel'),
        debateBtn),
      debateBox);
  }, { wide: true });
}

async function dealDeskModal(agent, onDone) {
  return modal('Autonomous Deal Desk & Inbound Sponsor Negotiator', (close) => {
    const pitchInput = h('textarea', {
      rows: 4,
      style: { width: '100%', marginBottom: '10px' },
      placeholder: 'Paste inbound sponsor email or DM (e.g. "Hi Delia, we love your videos at VectorDB Corp! We want to sponsor your next YouTube video about LLMs and do a LinkedIn post...")',
    });

    const brandInput = h('input', {
      type: 'text',
      placeholder: 'Brand Name (optional, auto-extracted if blank)',
      style: { flex: 1 },
    });

    const productInput = h('input', {
      type: 'text',
      placeholder: 'Product Name (optional)',
      style: { flex: 1 },
    });

    const budgetInput = h('input', {
      type: 'number',
      placeholder: 'Budget Mentioned ($)',
      style: { width: '160px' },
    });

    const resultBox = h('div', { style: { marginTop: '16px' } });

    const analyzeBtn = h('button', {
      class: 'btn primary',
      onclick: async () => {
        const text = pitchInput.value.trim();
        if (!text) return toast('Please paste an inbound sponsor pitch.', 'err');
        await busy(analyzeBtn, 'Analyzing pitch & formulating packages…', async () => {
          clear(resultBox).append(h('p', { class: 'muted small' }, 'Deal Desk Agent running: Conflict check → CPM rate calculation → 3-Tier package synthesis → Executive negotiation email…'));
          try {
            const r = await api('POST', `agents/${agent.id}/sponsor-negotiate`, {
              inbound_text: text,
              brand_name: brandInput.value.trim() || undefined,
              target_product: productInput.value.trim() || undefined,
              initial_budget: budgetInput.value ? Number(budgetInput.value) : undefined,
            });

            const res = r.result;
            const a = res.analysis;
            const tiers = res.tiered_packages;
            const email = res.negotiation_email;
            const stip = res.contract_stipulations || {};

            let selectedTier = 'tier_2_synergy';

            const tierCards = Object.entries(tiers).map(([key, t]) => {
              const isRec = key === 'tier_2_synergy';
              return h('div', {
                class: 'card',
                style: {
                  flex: '1 1 240px',
                  padding: '14px',
                  borderRadius: '8px',
                  border: isRec ? '2px solid var(--accent, #6366f1)' : '1px solid var(--border)',
                  background: isRec ? 'rgba(99,102,241,0.06)' : 'var(--card-subtle, rgba(255,255,255,0.02))',
                  position: 'relative',
                  display: 'flex',
                  flexDirection: 'column',
                  justifyContent: 'space-between',
                },
              },
              h('div', {},
                isRec ? h('span', { class: 'pill ok small', style: { position: 'absolute', top: '10px', right: '10px' } }, 'RECOMMENDED') : null,
                h('h3', { style: { fontSize: '15px', margin: '0 0 6px' } }, t.name),
                h('div', { class: 'row', style: { gap: '8px', alignItems: 'baseline', marginBottom: '8px' } },
                  h('strong', { style: { fontSize: '20px', color: 'var(--text)' } }, `$${t.rate.toLocaleString()}`),
                  h('span', { class: 'muted small' }, `~$${t.cpm} CPM`)),
                h('p', { class: 'small', style: { margin: '0 0 6px', fontWeight: '500' } }, t.deliverables),
                h('p', { class: 'muted small', style: { margin: '0' } }, t.rationale)),
              h('button', {
                class: `btn sm ${isRec ? 'primary' : 'ghost'}`,
                style: { marginTop: '12px' },
                onclick: async () => {
                  try {
                    await api('POST', `agents/${agent.id}/sponsor-save-deal`, {
                      brand_name: a.brand_name,
                      tier_key: key,
                      rate: t.rate,
                      deliverables: t.deliverables,
                      notes: `Selected ${t.name} from Deal Desk Agent.`,
                    });
                    toast(`Saved deal with ${a.brand_name} ($${t.rate.toLocaleString()}) to Pipeline!`);
                    close();
                    if (onDone) onDone();
                  } catch (err) {
                    toast(err.message, 'err');
                  }
                },
              }, icon('check', 13), `Create Deal ($${t.rate.toLocaleString()})`));
            });

            clear(resultBox).append(
              h('div', { class: 'card', style: { padding: '16px', background: 'var(--card-subtle, rgba(255,255,255,0.02))' } },
                // Header Analysis
                h('div', { class: 'row', style: { justifyContent: 'space-between', alignItems: 'center', marginBottom: '14px', flexWrap: 'wrap', gap: '8px' } },
                  h('div', {},
                    h('h3', { style: { margin: '0 0 4px' } }, `${a.brand_name} · ${a.product_name}`),
                    h('p', { class: 'muted small', style: { margin: 0 } }, a.developer_audience_relevance)),
                  h('div', { class: 'row', style: { gap: '6px' } },
                    h('span', { class: `pill ${a.brand_fit_score >= 80 ? 'ok' : 'WARN'} small` }, `Brand Fit: ${a.brand_fit_score}/100`),
                    (a.conflicts_detected || []).length ? h('span', { class: 'pill WARN small' }, 'Exclusivity Conflict Detected') : null)),

                // 3 Tier Cards
                h('h4', { style: { margin: '14px 0 8px', fontSize: '13px', textTransform: 'uppercase', letterSpacing: '0.05em' } }, 'Market-Calibrated Counter-Offer Packages'),
                h('div', { class: 'row', style: { gap: '12px', flexWrap: 'wrap', marginBottom: '16px' } }, tierCards),

                // Negotiation Email
                h('div', { class: 'card', style: { padding: '14px', background: 'var(--bg)', marginBottom: '14px' } },
                  h('div', { class: 'row', style: { justifyContent: 'space-between', alignItems: 'center', marginBottom: '8px' } },
                    h('strong', { class: 'small' }, `Executive Negotiation Reply: ${email.subject}`),
                    h('button', {
                      class: 'btn sm ghost',
                      onclick: () => {
                        navigator.clipboard.writeText(`Subject: ${email.subject}\n\n${email.body}`);
                        toast('Negotiation email copied to clipboard.');
                      },
                    }, icon('copy', 13), 'Copy email')),
                  h('pre', { style: { whiteSpace: 'pre-wrap', fontSize: '12px', fontFamily: 'inherit', margin: 0 } }, email.body)),

                // Contract Stipulations
                h('div', { style: { fontSize: '11px', color: 'var(--muted)', display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(200px, 1fr))', gap: '8px' } },
                  h('div', {}, h('strong', {}, 'Usage Rights: '), stip.usage_rights || '30 days organic'),
                  h('div', {}, h('strong', {}, 'Payment: '), stip.payment_terms || 'Net 30'),
                  h('div', {}, h('strong', {}, 'Revisions: '), stip.revision_limits || '2 rounds max'),
                  h('div', {}, h('strong', {}, 'FTC Compliance: '), stip.ftc_compliance || 'Mandatory #ad disclosure'))));
          } catch (err) {
            clear(resultBox).append(h('p', { class: 'small', style: { color: 'var(--warn)' } }, `Analysis failed: ${err.message}`));
          }
        });
      },
    }, icon('briefcase', 14), 'Analyze & Formulate Counter-Offers');

    return h('div', {},
      h('p', { class: 'muted small' }, 'Direct monetization agent: pastes an inbound sponsor inquiry, checks channel CPM rate benchmarks, screens active deals for category conflicts, drafts a high-converting executive negotiation reply, and builds a 3-tier counter package ready to save into your Deals pipeline.'),
      h('label', { class: 'small muted' }, 'Inbound Pitch / Email Message:'),
      pitchInput,
      h('div', { class: 'row', style: { gap: '8px', marginBottom: '12px' } },
        brandInput, productInput, budgetInput),
      h('div', { class: 'row', style: { justifyContent: 'flex-end', gap: '8px' } },
        h('button', { class: 'btn', onclick: () => close() }, 'Cancel'),
        analyzeBtn),
      resultBox);
  }, { wide: true });
}

async function cascadeModal(agent, onDone) {
  return modal('Omnichannel Transmedia Cascade Engine', (close) => {
    const topicInput = h('textarea', {
      rows: 2,
      style: { width: '100%', marginBottom: '10px' },
      placeholder: 'Enter a video concept, technical topic, or script title (e.g. "Distributed Systems: Append-Only Logs vs Traditional Locks")',
    });

    const notesInput = h('input', {
      type: 'text',
      placeholder: 'Key takeaways or specific talking points (optional)',
      style: { width: '100%', marginBottom: '12px' },
    });

    const cascadeBox = h('div', { style: { marginTop: '16px' } });

    const generateBtn = h('button', {
      class: 'btn primary',
      onclick: async () => {
        const topic = topicInput.value.trim();
        if (!topic) return toast('Please enter a topic or concept.', 'err');
        await busy(generateBtn, 'Generating 5-platform cascade…', async () => {
          clear(cascadeBox).append(h('p', { class: 'muted small' }, 'Synthesizing synchronized cross-platform campaign: LinkedIn Thought Leadership → X Viral Thread → Short-Form Script → Newsletter Deep-Dive → Carousel Slide Deck…'));
          try {
            const r = await api('POST', `agents/${agent.id}/transmedia-cascade`, {
              topic,
              notes: notesInput.value.trim() || undefined,
            });

            const c = r.cascade;

            let activeTab = 'linkedin';
            const tabButtons = h('div', { class: 'row', style: { gap: '6px', marginBottom: '12px', flexWrap: 'wrap' } });
            const tabContent = h('div');

            const renderTab = () => {
              clear(tabButtons).append(
                [
                  ['linkedin', 'LinkedIn Post', c.linkedin?.score],
                  ['x_thread', 'X Thread', c.x_thread?.score],
                  ['short_form', 'Short-Form Video', null],
                  ['newsletter', 'Newsletter Section', null],
                  ['carousel', 'Carousel Deck (7 Slides)', null],
                ].map(([key, label, score]) => h('button', {
                  class: `btn sm ${activeTab === key ? 'primary' : 'ghost'}`,
                  onclick: () => { activeTab = key; renderTab(); },
                }, label, score != null ? h('span', { class: 'pill ok small', style: { marginLeft: '6px' } }, `${score}/100`) : null)));

              clear(tabContent);
              if (activeTab === 'linkedin') {
                tabContent.append(
                  h('div', { class: 'card', style: { padding: '12px', background: 'var(--bg)' } },
                    h('strong', { class: 'small' }, c.linkedin.title),
                    h('pre', { style: { whiteSpace: 'pre-wrap', fontSize: '13px', marginTop: '8px', fontFamily: 'inherit' } }, c.linkedin.body)));
              } else if (activeTab === 'x_thread') {
                tabContent.append(
                  h('div', { class: 'card', style: { padding: '12px', background: 'var(--bg)' } },
                    h('strong', { class: 'small' }, c.x_thread.title),
                    h('div', { style: { display: 'flex', flexDirection: 'column', gap: '8px', marginTop: '10px' } },
                      h('div', { style: { padding: '8px 10px', background: 'rgba(255,255,255,0.03)', borderRadius: '6px', borderLeft: '3px solid var(--accent, #6366f1)' } },
                        h('span', { class: 'pill small' }, 'HOOK TWEET'),
                        h('p', { class: 'small', style: { margin: '4px 0 0' } }, c.x_thread.hook_tweet)),
                      (c.x_thread.thread_tweets || []).map((tw, i) => h('div', { style: { padding: '8px 10px', background: 'rgba(255,255,255,0.02)', borderRadius: '6px' } },
                        h('span', { class: 'muted small' }, `Tweet ${i + 2}`),
                        h('p', { class: 'small', style: { margin: '4px 0 0' } }, tw))))));
              } else if (activeTab === 'short_form') {
                tabContent.append(
                  h('div', { class: 'card', style: { padding: '12px', background: 'var(--bg)' } },
                    h('div', { class: 'row', style: { justifyContent: 'space-between', marginBottom: '8px' } },
                      h('strong', { class: 'small' }, c.short_form.title),
                      h('span', { class: 'pill ok small' }, `~${c.short_form.duration_seconds || 50}s duration`)),
                    h('div', { style: { padding: '8px 10px', background: 'rgba(239,68,68,0.06)', borderRadius: '6px', borderLeft: '3px solid #ef4444', marginBottom: '10px' } },
                      h('span', { class: 'pill WARN small' }, '0-3s VISUAL HOOK'),
                      h('p', { class: 'small', style: { margin: '4px 0 0' } }, c.short_form.hook_3s)),
                    h('pre', { style: { whiteSpace: 'pre-wrap', fontSize: '13px', fontFamily: 'inherit' } }, c.short_form.script_body)));
              } else if (activeTab === 'newsletter') {
                tabContent.append(
                  h('div', { class: 'card', style: { padding: '12px', background: 'var(--bg)' } },
                    h('strong', { class: 'small' }, c.newsletter.title),
                    h('p', { class: 'small muted', style: { margin: '6px 0 10px', fontStyle: 'italic' } }, c.newsletter.executive_summary),
                    h('pre', { style: { whiteSpace: 'pre-wrap', fontSize: '12px', fontFamily: 'inherit' } }, c.newsletter.deep_dive),
                    h('div', { style: { marginTop: '10px', padding: '8px 10px', background: 'rgba(34,197,94,0.06)', borderRadius: '6px' } },
                      h('strong', { class: 'small' }, 'Action Item for Engineers: '),
                      h('span', { class: 'small' }, c.newsletter.action_item))));
              } else if (activeTab === 'carousel') {
                tabContent.append(
                  h('div', { style: { display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(200px, 1fr))', gap: '8px' } },
                    (c.carousel.slides || []).map((s) => h('div', { class: 'card', style: { padding: '10px', background: 'var(--bg)' } },
                      h('span', { class: 'pill small' }, `Slide ${s.slide_number}`),
                      h('h4', { style: { fontSize: '13px', margin: '6px 0 4px' } }, s.headline),
                      h('p', { class: 'small muted', style: { margin: '0 0 6px' } }, s.body),
                      h('span', { style: { fontSize: '10px', color: 'var(--accent, #6366f1)' } }, `Visual: ${s.visual_cue}`)))));
              }
            };

            renderTab();

            const scheduleAllBtn = h('button', {
              class: 'btn primary sm',
              onclick: async () => {
                await busy(scheduleAllBtn, 'Scheduling to calendar…', async () => {
                  try {
                    const schedRes = await api('POST', `agents/${agent.id}/cascade-schedule`, {
                      cascade: c,
                      platforms_to_schedule: ['linkedin', 'x'],
                    });
                    toast(schedRes.message);
                    close();
                    if (onDone) onDone();
                  } catch (err) {
                    toast(err.message, 'err');
                  }
                });
              },
            }, icon('calendar', 13), 'Schedule Social Posts to Calendar');

            clear(cascadeBox).append(
              h('div', { class: 'card', style: { padding: '14px', background: 'var(--card-subtle, rgba(255,255,255,0.02))' } },
                h('div', { class: 'row', style: { justifyContent: 'space-between', alignItems: 'center', marginBottom: '12px' } },
                  h('strong', {}, '5-Platform Transmedia Cascade Assets'),
                  scheduleAllBtn),
                tabButtons,
                tabContent));
          } catch (err) {
            clear(cascadeBox).append(h('p', { class: 'small', style: { color: 'var(--warn)' } }, `Cascade generation failed: ${err.message}`));
          }
        });
      },
    }, icon('flow', 14), 'Generate Transmedia Cascade');

    return h('div', {},
      h('p', { class: 'muted small' }, 'Multiplies a single core subject into a synchronized, multi-format distribution battery: LinkedIn Thought Leadership, X Viral Thread, TikTok/Shorts vertical script, Newsletter deep-dive, and 7-Slide Carousel outline.'),
      h('label', { class: 'small muted' }, 'Topic / Architecture Subject:'),
      topicInput,
      notesInput,
      h('div', { class: 'row', style: { justifyContent: 'flex-end', gap: '8px' } },
        h('button', { class: 'btn', onclick: () => close() }, 'Cancel'),
        generateBtn),
      cascadeBox);
  }, { wide: true });
}

async function hookMatrixModal(agent, onDone) {
  return modal('Viral Hook Matrix & Predictive A/B Optimizer', (close) => {
    const topicInput = h('textarea', {
      rows: 2,
      style: { width: '100%', marginBottom: '10px' },
      placeholder: 'Enter a topic, proposition, or argument (e.g. "Distributed Locks vs Eventual Consistency in Microservices")',
    });

    const netSelect = h('select', { style: { marginBottom: '12px' } },
      agent.platforms.map((p) => h('option', { value: p }, p.toUpperCase())));

    const matrixBox = h('div', { style: { marginTop: '16px' } });

    const runBtn = h('button', {
      class: 'btn primary',
      onclick: async () => {
        const topic = topicInput.value.trim();
        if (!topic) return toast('Please enter a topic.', 'err');
        await busy(runBtn, 'Evaluating hook psychology & predicting virality…', async () => {
          clear(matrixBox).append(h('p', { class: 'muted small' }, 'Matrix running: Brainstorming 6 psychological archetypes → Evaluating channel breakout resonance → Calculating Predictive Virality Scores (PVS)…'));
          try {
            const r = await api('POST', `agents/${agent.id}/hook-matrix`, {
              topic,
              platform: netSelect.value,
            });

            const m = r.matrix || {};
            const win = r.winning_hook || {};

            const archetypeCards = Object.entries(m).map(([key, item]) => {
              const isWinner = win.archetype === key;
              return h('div', {
                class: 'card',
                style: {
                  padding: '12px',
                  borderRadius: '8px',
                  border: isWinner ? '2px solid var(--ok, #22c55e)' : '1px solid var(--border)',
                  background: isWinner ? 'rgba(34,197,94,0.05)' : 'var(--bg)',
                  display: 'flex',
                  flexDirection: 'column',
                  justifyContent: 'space-between',
                },
              },
              h('div', {},
                h('div', { class: 'row', style: { justifyContent: 'space-between', alignItems: 'center', marginBottom: '6px' } },
                  h('span', { class: 'pill small' }, item.name),
                  h('span', { class: `pill ${item.predictive_virality_score >= 90 ? 'ok' : 'WARN'} small` }, `PVS: ${item.predictive_virality_score}/100`)),
                h('p', { style: { fontWeight: '600', fontSize: '13px', margin: '4px 0 6px' } }, item.hook_text),
                h('p', { class: 'muted small', style: { margin: '0 0 6px' } }, item.lead_in),
                h('div', { class: 'row', style: { gap: '6px', fontSize: '11px', color: 'var(--muted)', margin: '6px 0' } },
                  h('span', {}, `Tension: ${item.curiosity_tension}`),
                  h('span', {}, `· Risk: ${item.retention_risk}`),
                  h('span', { style: { color: 'var(--ok)' } }, `· CTR: ${item.estimated_ctr_multiplier}`)),
                h('p', { class: 'muted small', style: { fontSize: '11px', margin: '0' } }, item.rationale)),
              h('button', {
                class: `btn sm ${isWinner ? 'primary' : 'ghost'}`,
                style: { marginTop: '10px' },
                onclick: async () => {
                  await api('POST', 'social', {
                    platform: netSelect.value,
                    body: `${item.hook_text}\n\n${item.lead_in}\n\n[Continue draft here...]`,
                    parts: [],
                    social_agent_id: agent.id,
                  });
                  toast(`Saved "${item.name}" hook to drafts queue.`);
                  close();
                  if (onDone) onDone();
                },
              }, icon('plus', 12), 'Use This Hook'));
            });

            clear(matrixBox).append(
              h('div', { class: 'card', style: { padding: '14px', background: 'var(--card-subtle, rgba(255,255,255,0.02))' } },
                // Winning banner
                win.hook_text ? h('div', { class: 'card', style: { padding: '12px 14px', background: 'rgba(34,197,94,0.08)', border: '1px solid var(--ok)', marginBottom: '14px' } },
                  h('div', { class: 'row', style: { justifyContent: 'space-between', alignItems: 'center' } },
                    h('strong', { style: { color: 'var(--ok)' } }, 'Algorithmic Top Pick (Highest Predicted CTR)'),
                    h('span', { class: 'pill ok small' }, `PVS: ${win.score}/100`)),
                  h('p', { style: { fontSize: '14px', fontWeight: 'bold', margin: '6px 0 4px' } }, `“${win.hook_text}”`),
                  h('p', { class: 'small muted', style: { margin: 0 } }, win.why_it_wins)) : null,

                h('h4', { style: { margin: '0 0 10px', fontSize: '13px' } }, '6 Psychological Hook Archetypes'),
                h('div', { style: { display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(280px, 1fr))', gap: '10px' } }, archetypeCards)));
          } catch (err) {
            clear(matrixBox).append(h('p', { class: 'small', style: { color: 'var(--warn)' } }, `Hook optimization failed: ${err.message}`));
          }
        });
      },
    }, icon('sparkle', 14), 'Generate Hook Matrix');

    return h('div', {},
      h('p', { class: 'muted small' }, 'Explores 6 distinct psychological hook archetypes (Contrarian, Disaster Post-Mortem, Curiosity Gap, Counter-Intuitive Benchmark, Pain Relief, Vulnerable Confession), scores predictive virality (PVS), and identifies the highest CTR angle.'),
      h('label', { class: 'small muted' }, 'Topic / Thesis to Hook:'),
      topicInput,
      h('label', { class: 'small muted' }, 'Target Network:'),
      netSelect,
      h('div', { class: 'row', style: { justifyContent: 'flex-end', gap: '8px' } },
        h('button', { class: 'btn', onclick: () => close() }, 'Cancel'),
        runBtn),
      matrixBox);
  }, { wide: true });
}

async function factCheckModal(agent, onDone) {
  return modal('Technical Grounding & Hallucination Shield', (close) => {
    const draftInput = h('textarea', {
      rows: 5,
      style: { width: '100%', marginBottom: '10px' },
      placeholder: 'Paste post draft, script paragraph, or technical assertions to audit for factual grounding, benchmark integrity, and hallucination risks…',
    });

    const netSelect = h('select', { style: { marginBottom: '12px' } },
      agent.platforms.map((p) => h('option', { value: p }, p.toUpperCase())));

    const auditBox = h('div', { style: { marginTop: '16px' } });

    const auditBtn = h('button', {
      class: 'btn primary',
      onclick: async () => {
        const text = draftInput.value.trim();
        if (!text) return toast('Please enter text to audit.', 'err');
        await busy(auditBtn, 'Auditing technical claims…', async () => {
          clear(auditBox).append(h('p', { class: 'muted small' }, 'Fact-Check Shield running: Extracting claims → Auditing benchmark metrics & API syntax → Evaluating grounding confidence → Generating armored text…'));
          try {
            const r = await api('POST', `agents/${agent.id}/fact-check`, {
              text,
              platform: netSelect.value,
            });

            const claims = r.claims_evaluated || [];
            const score = r.credibility_score;
            const scoreCls = score >= 85 ? 'ok' : score >= 70 ? 'picked' : 'WARN';

            clear(auditBox).append(
              h('div', { class: 'card', style: { padding: '14px', background: 'var(--card-subtle, rgba(255,255,255,0.02))' } },
                h('div', { class: 'row', style: { justifyContent: 'space-between', alignItems: 'center', marginBottom: '12px' } },
                  h('strong', {}, 'Technical Verification Report'),
                  h('span', { class: `pill ${scoreCls} small` }, `Grounding Credibility: ${score}/100`)),

                h('div', { style: { display: 'flex', flexDirection: 'column', gap: '8px', marginBottom: '14px' } },
                  claims.map((c) => {
                    const isWarn = c.status === 'questionable' || c.status === 'deprecated';
                    return h('div', {
                      style: {
                        padding: '10px 12px',
                        borderRadius: '6px',
                        border: isWarn ? '1px solid rgba(239,68,68,0.3)' : '1px solid var(--border)',
                        background: isWarn ? 'rgba(239,68,68,0.04)' : 'rgba(0,0,0,0.1)',
                      },
                    },
                    h('div', { class: 'row', style: { justifyContent: 'space-between', alignItems: 'center' } },
                      h('div', { class: 'row', style: { gap: '6px', alignItems: 'center' } },
                        h('span', { class: `pill small ${c.status === 'verified' ? 'ok' : 'WARN'}` }, c.status.toUpperCase()),
                        h('span', { class: 'pill small' }, c.category)),
                      h('span', { class: 'muted small' }, `Confidence: ${c.confidence}%`)),
                    h('p', { style: { fontWeight: '600', fontSize: '13px', margin: '6px 0 4px' } }, `“${c.claim}”`),
                    h('p', { class: 'small muted', style: { margin: '0 0 4px' } }, c.analysis),
                    c.suggested_correction ? h('p', { class: 'small', style: { color: 'var(--ok)', margin: '4px 0 0' } },
                      h('strong', {}, 'Recommended Correction: '), c.suggested_correction) : null);
                  })),

                h('div', { class: 'card', style: { padding: '12px', background: 'var(--bg)' } },
                  h('strong', { class: 'small' }, 'Armored & Factually-Grounded Draft:'),
                  h('pre', { style: { whiteSpace: 'pre-wrap', fontSize: '13px', marginTop: '6px', fontFamily: 'inherit' } }, r.armored_text),
                  h('div', { class: 'row', style: { justifyContent: 'flex-end', gap: '8px', marginTop: '10px' } },
                    h('button', {
                      class: 'btn sm primary',
                      onclick: async () => {
                        await api('POST', 'social', {
                          platform: netSelect.value,
                          body: r.armored_text,
                          parts: [],
                          social_agent_id: agent.id,
                        });
                        toast('Armored draft saved to drafts queue.');
                        close();
                        if (onDone) onDone();
                      },
                    }, icon('check', 13), 'Save Armored Draft to Queue')))));
          } catch (err) {
            clear(auditBox).append(h('p', { class: 'small', style: { color: 'var(--warn)' } }, `Fact-check audit failed: ${err.message}`));
          }
        });
      },
    }, icon('check', 14), 'Audit Technical Grounding');

    return h('div', {},
      h('p', { class: 'muted small' }, 'Audits posts and scripts for factual accuracy, benchmark integrity, and hallucination risks before publishing to high-authority developer audiences.'),
      h('label', { class: 'small muted' }, 'Content / Draft to Audit:'),
      draftInput,
      h('label', { class: 'small muted' }, 'Target Network:'),
      netSelect,
      h('div', { class: 'row', style: { justifyContent: 'flex-end', gap: '8px' } },
        h('button', { class: 'btn', onclick: () => close() }, 'Cancel'),
        auditBtn),
      auditBox);
  }, { wide: true });
}

async function outlierDeconstructModal(agent, onDone) {
  return modal('Competitor Outlier Reverse-Engineering & Blue Ocean Angle Hunter', (close) => {
    const titleInput = h('textarea', {
      rows: 2,
      style: { width: '100%', marginBottom: '10px' },
      placeholder: 'Enter a competitor breakout video title (e.g. "Why Amazon Prime Video Switched from Serverless to Monolith")',
    });

    const competitorInput = h('input', {
      type: 'text',
      placeholder: 'Competitor / Channel Name (e.g. ByteByteGo)',
      style: { flex: 1 },
    });

    const notesInput = h('input', {
      type: 'text',
      placeholder: 'Why you think it worked or audience comments (optional)',
      style: { flex: 2 },
    });

    const resultBox = h('div', { style: { marginTop: '16px' } });

    const runBtn = h('button', {
      class: 'btn primary',
      onclick: async () => {
        const title = titleInput.value.trim();
        if (!title) return toast('Please enter a competitor breakout title.', 'err');
        await busy(runBtn, 'Deconstructing outlier & finding Blue Ocean angles…', async () => {
          clear(resultBox).append(h('p', { class: 'muted small' }, 'Outlier Agent running: Analyzing viral tension → Identifying audience demand gaps → Formulating 3 Blue Ocean counter-angles…'));
          try {
            const r = await api('POST', `agents/${agent.id}/reverse-engineer-outlier`, {
              title,
              competitor_name: competitorInput.value.trim() || undefined,
              why_it_worked: notesInput.value.trim() || undefined,
            });

            const res = r.result;
            const sum = res.outlier_summary;
            const trig = res.psychological_triggers;
            const angles = res.blue_ocean_angles || [];
            const concept = res.actionable_concept;

            const angleCards = angles.map((ang) => h('div', {
              class: 'card',
              style: {
                padding: '12px',
                borderRadius: '8px',
                border: '1px solid var(--border)',
                background: 'var(--bg)',
                display: 'flex',
                flexDirection: 'column',
                justifyContent: 'space-between',
              },
            },
            h('div', {},
              h('div', { class: 'row', style: { justifyContent: 'space-between', alignItems: 'center', marginBottom: '6px' } },
                h('strong', { style: { fontSize: '13px' } }, ang.angle_name),
                h('span', { class: 'pill ok small' }, `Est. ${ang.predicted_multiplier}`)),
              h('p', { style: { fontWeight: '600', fontSize: '12px', margin: '4px 0 6px' } }, `“${ang.hook_line}”`),
              h('p', { class: 'muted small', style: { margin: '0 0 6px' } }, ang.thesis),
              h('p', { class: 'small', style: { fontSize: '11px', color: 'var(--accent, #6366f1)', margin: 0 } }, `Why it stands out: ${ang.why_it_stands_out}`)),
            h('button', {
              class: 'btn sm ghost',
              style: { marginTop: '10px' },
              onclick: async () => {
                await api('POST', 'social', {
                  platform: agent.platforms[0] || 'linkedin',
                  body: `${ang.hook_line}\n\n${ang.thesis}\n\n[Continue breakdown here...]`,
                  parts: [],
                  social_agent_id: agent.id,
                });
                toast(`Saved "${ang.angle_name}" to drafts queue.`);
                close();
                if (onDone) onDone();
              },
            }, icon('plus', 12), 'Draft This Angle')));

            clear(resultBox).append(
              h('div', { class: 'card', style: { padding: '14px', background: 'var(--card-subtle, rgba(255,255,255,0.02))' } },
                // Outlier Psychological Diagnosis
                h('div', { class: 'row', style: { justifyContent: 'space-between', alignItems: 'center', marginBottom: '12px', flexWrap: 'wrap', gap: '8px' } },
                  h('div', {},
                    h('h3', { style: { margin: '0 0 4px' } }, sum.title),
                    h('p', { class: 'muted small', style: { margin: 0 } }, `By ${sum.competitor} · ${sum.outlier_multiplier} channel median views`)),
                  h('span', { class: 'pill WARN small' }, trig.primary_trigger)),

                h('div', { class: 'card', style: { padding: '10px 12px', background: 'var(--bg)', marginBottom: '14px' } },
                  h('div', { class: 'small' }, h('strong', {}, 'Audience Demand Gap: '), res.audience_demand_gap),
                  h('div', { class: 'small muted', style: { marginTop: '4px' } }, h('strong', {}, 'Unresolved Friction: '), trig.audience_friction)),

                // 3 Blue Ocean Angles
                h('h4', { style: { margin: '14px 0 8px', fontSize: '13px' } }, '3 Uncontested "Blue Ocean" Counter-Angles'),
                h('div', { style: { display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(240px, 1fr))', gap: '10px', marginBottom: '14px' } }, angleCards),

                // Winning Video Concept
                concept ? h('div', { class: 'card', style: { padding: '12px', background: 'rgba(34,197,94,0.06)', border: '1px solid var(--ok)' } },
                  h('div', { class: 'row', style: { justifyContent: 'space-between', alignItems: 'center', marginBottom: '6px' } },
                    h('strong', { style: { color: 'var(--ok)' } }, 'Recommended Next Video Concept'),
                    h('button', {
                      class: 'btn sm primary',
                      onclick: async () => {
                        await api('POST', 'ideas', {
                          title: concept.title,
                          notes: `${concept.elevator_pitch}\n\nTalking points:\n- ${concept.key_talking_points.join('\n- ')}`,
                        });
                        toast(`Saved "${concept.title}" to Idea Bank!`);
                        close();
                        if (onDone) onDone();
                      },
                    }, icon('check', 12), 'Save to Idea Bank')),
                  h('h3', { style: { fontSize: '14px', margin: '4px 0' } }, concept.title),
                  h('p', { class: 'small muted', style: { margin: '0 0 8px' } }, concept.elevator_pitch),
                  h('ul', { style: { fontSize: '12px', margin: 0, paddingLeft: '18px' } },
                    (concept.key_talking_points || []).map((pt) => h('li', {}, pt)))) : null));
          } catch (err) {
            clear(resultBox).append(h('p', { class: 'small', style: { color: 'var(--warn)' } }, `Deconstruction failed: ${err.message}`));
          }
        });
      },
    }, icon('trend', 14), 'Reverse-Engineer Outlier');

    return h('div', {},
      h('p', { class: 'muted small' }, 'Deconstructs 3x-10x breakout competitor videos, diagnoses the underlying psychological curiosity triggers, uncovers what audience questions were left unanswered, and synthesizes 3 unique Blue Ocean angles that stand out in the algorithm.'),
      h('label', { class: 'small muted' }, 'Competitor Breakout Video Title:'),
      titleInput,
      h('div', { class: 'row', style: { gap: '8px', marginBottom: '12px' } },
        competitorInput, notesInput),
      h('div', { class: 'row', style: { justifyContent: 'flex-end', gap: '8px' } },
        h('button', { class: 'btn', onclick: () => close() }, 'Cancel'),
        runBtn),
      resultBox);
  }, { wide: true });
}

async function banditTitlesModal(agent, onDone) {
  return modal('Multi-Armed Bandit YouTube Title & Thumbnail Optimizer', (close) => {
    const topicInput = h('textarea', {
      rows: 2,
      style: { width: '100%', marginBottom: '10px' },
      placeholder: 'Enter a YouTube video topic or current working title (e.g. "Building Modular Monoliths with Append-Only Logs in Go")',
    });

    const videoIdInput = h('input', {
      type: 'text',
      placeholder: 'YouTube Video ID (optional, for direct A/B testing)',
      style: { width: '220px', marginBottom: '12px' },
    });

    const resultBox = h('div', { style: { marginTop: '16px' } });

    const runBtn = h('button', {
      class: 'btn primary',
      onclick: async () => {
        const topic = topicInput.value.trim();
        if (!topic) return toast('Please enter a video topic.', 'err');
        await busy(runBtn, 'Generating algorithmic bandit titles & thumbnail concepts…', async () => {
          clear(resultBox).append(h('p', { class: 'muted small' }, 'Bandit Optimizer running: Exploring 4 algorithmic title structures → Evaluating browse affinity & CTR score → Generating high-contrast thumbnail directions…'));
          try {
            const r = await api('POST', `agents/${agent.id}/bandit-titles`, {
              topic,
              video_id: videoIdInput.value.trim() || undefined,
            });

            const res = r.result;
            const vars = res.variants || [];
            const win = res.bandit_winner || {};

            const variantCards = vars.map((v) => h('div', {
              class: 'card',
              style: {
                padding: '12px',
                borderRadius: '8px',
                border: win.title === v.title ? '2px solid var(--ok, #22c55e)' : '1px solid var(--border)',
                background: win.title === v.title ? 'rgba(34,197,94,0.05)' : 'var(--bg)',
                display: 'flex',
                flexDirection: 'column',
                justifyContent: 'space-between',
              },
            },
            h('div', {},
              h('div', { class: 'row', style: { justifyContent: 'space-between', alignItems: 'center', marginBottom: '6px' } },
                h('span', { class: 'pill small' }, v.archetype),
                h('div', { class: 'row', style: { gap: '4px' } },
                  h('span', { class: 'muted small' }, `${v.char_count} chars`),
                  h('span', { class: `pill ${v.predicted_ctr_score >= 90 ? 'ok' : 'WARN'} small` }, `CTR: ${v.predicted_ctr_score}/100`))),
              h('h3', { style: { fontSize: '14px', margin: '6px 0 8px' } }, v.title),
              v.thumbnail ? h('div', { style: { padding: '8px', background: 'rgba(0,0,0,0.2)', borderRadius: '6px', fontSize: '11px' } },
                h('strong', {}, 'Thumbnail Direction: '),
                h('p', { style: { margin: '2px 0' } }, v.thumbnail.focal_subject),
                h('div', { class: 'row', style: { gap: '6px', marginTop: '4px' } },
                  h('span', { class: 'pill ok small' }, `TEXT: "${v.thumbnail.text_overlay}"`),
                  h('span', { class: 'muted' }, v.thumbnail.color_palette))) : null)));

            clear(resultBox).append(
              h('div', { class: 'card', style: { padding: '14px', background: 'var(--card-subtle, rgba(255,255,255,0.02))' } },
                // Winning title banner
                win.title ? h('div', { class: 'card', style: { padding: '12px 14px', background: 'rgba(34,197,94,0.08)', border: '1px solid var(--ok)', marginBottom: '14px' } },
                  h('div', { class: 'row', style: { justifyContent: 'space-between', alignItems: 'center' } },
                    h('strong', { style: { color: 'var(--ok)' } }, 'Bandit Algorithmic Winner'),
                    h('span', { class: 'pill ok small' }, `Predicted CTR: ${win.score}/100`)),
                  h('h2', { style: { fontSize: '16px', margin: '6px 0 4px' } }, `“${win.title}”`),
                  h('p', { class: 'small muted', style: { margin: 0 } }, win.strategic_rationale)) : null,

                h('h4', { style: { margin: '0 0 10px', fontSize: '13px' } }, '4 Algorithmic Title Variants & Thumbnails'),
                h('div', { style: { display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(260px, 1fr))', gap: '10px', marginBottom: '14px' } }, variantCards),

                // Launch A/B Test action
                videoIdInput.value.trim() ? h('div', { class: 'row', style: { justifyContent: 'flex-end' } },
                  h('button', {
                    class: 'btn primary sm',
                    onclick: async () => {
                      try {
                        const l = await api('POST', 'agents/launch-title-test', {
                          video_id: videoIdInput.value.trim(),
                          titles: vars.map((x) => x.title),
                          hours_per_window: 4,
                          rounds: 3,
                        });
                        toast('Multi-window Title A/B Test initiated on YouTube!');
                        close();
                        if (onDone) onDone();
                      } catch (err) {
                        toast(err.message, 'err');
                      }
                    },
                  }, icon('chart', 13), 'Launch Multi-Window Title Test')) : null));
          } catch (err) {
            clear(resultBox).append(h('p', { class: 'small', style: { color: 'var(--warn)' } }, `Title optimization failed: ${err.message}`));
          }
        });
      },
    }, icon('chart', 14), 'Optimize Titles & Thumbnails');

    return h('div', {},
      h('p', { class: 'muted small' }, 'Generates 4 algorithmic title structures targeting YouTube Browse & Suggested feeds (Provocative Punchy, Quantified Milestone, Skeptical Dilemma, Urgent Warning), predicts CTR scores, pairs with high-contrast thumbnail concepts, and launches multi-window Title A/B tests.'),
      h('label', { class: 'small muted' }, 'Video Topic / Working Title:'),
      topicInput,
      h('label', { class: 'small muted' }, 'YouTube Video ID (Optional, enables direct A/B launch):'),
      videoIdInput,
      h('div', { class: 'row', style: { justifyContent: 'flex-end', gap: '8px' } },
        h('button', { class: 'btn', onclick: () => close() }, 'Cancel'),
        runBtn),
      resultBox);
  }, { wide: true });
}

async function leadTriageModal(agent, onDone) {
  return modal('Community Lead Triage & Revenue Pipeline Agent', (close) => {
    const resultBox = h('div', { style: { marginTop: '8px' } });

    const scanBtn = h('button', {
      class: 'btn primary',
      onclick: async () => {
        await busy(scanBtn, 'Scanning engagement comments and DMs for revenue leads…', async () => {
          clear(resultBox).append(h('p', { class: 'muted small' }, 'Triage Agent running: Screening incoming comments & DMs → Classifying commercial intent → Estimating pipeline deal value…'));
          try {
            const r = await api('POST', `agents/${agent.id}/triage-leads`, {});
            const res = r.result;
            const leads = res.leads || [];

            clear(resultBox).append(
              h('div', { class: 'card', style: { padding: '14px', background: 'var(--card-subtle, rgba(255,255,255,0.02))' } },
                // KPI Banner
                h('div', { class: 'row', style: { gap: '16px', marginBottom: '14px', flexWrap: 'wrap' } },
                  h('div', { class: 'ag-kpi' }, h('strong', {}, res.total_screened), h('span', {}, 'messages screened')),
                  h('div', { class: 'ag-kpi hot' }, h('strong', {}, res.leads_detected), h('span', {}, 'commercial leads')),
                  h('div', { class: 'ag-kpi ok' }, h('strong', {}, `$${res.total_pipeline_value.toLocaleString()}`), h('span', {}, 'pipeline opportunity'))),

                h('h4', { style: { margin: '0 0 10px', fontSize: '13px' } }, 'Triaged Inbound Leads & Recommended Responses'),
                h('div', { style: { display: 'flex', flexDirection: 'column', gap: '10px' } },
                  leads.map((l) => {
                    const isLead = l.intent !== 'general_audience';
                    return h('div', {
                      class: 'card',
                      style: {
                        padding: '12px',
                        borderRadius: '8px',
                        border: isLead ? '1px solid var(--ok, #22c55e)' : '1px solid var(--border)',
                        background: isLead ? 'rgba(34,197,94,0.04)' : 'var(--bg)',
                      },
                    },
                    h('div', { class: 'row', style: { justifyContent: 'space-between', alignItems: 'center', marginBottom: '6px' } },
                      h('div', { class: 'row', style: { gap: '8px', alignItems: 'center' } },
                        h('strong', {}, l.author),
                        netChip(l.platform, l.platform.toUpperCase()),
                        h('span', { class: `pill ${isLead ? 'ok' : ''} small` }, l.intent.toUpperCase())),
                      isLead ? h('strong', { style: { color: 'var(--ok)' } }, `Est. $${l.estimated_value_usd.toLocaleString()}`) : null),
                    h('p', { class: 'small', style: { margin: '4px 0 6px', fontStyle: 'italic' } }, `“${l.text}”`),
                    h('div', { style: { padding: '8px 10px', background: 'rgba(0,0,0,0.2)', borderRadius: '6px', margin: '6px 0' } },
                      h('strong', { class: 'small muted' }, 'Executive Draft Reply: '),
                      h('p', { class: 'small', style: { margin: '2px 0 0', whiteSpace: 'pre-wrap' } }, l.draft_response)),
                    isLead ? h('div', { class: 'row', style: { justifyContent: 'flex-end', gap: '8px', marginTop: '8px' } },
                      h('button', {
                        class: 'btn sm primary',
                        onclick: async () => {
                          try {
                            await api('POST', `agents/${agent.id}/escalate-lead-deal`, {
                              author: l.author,
                              intent: l.intent,
                              estimated_value: l.estimated_value_usd,
                              text: l.text,
                            });
                            toast(`Escalated lead from ${l.author} to Deals pipeline!`);
                            close();
                            if (onDone) onDone();
                          } catch (err) {
                            toast(err.message, 'err');
                          }
                        },
                      }, icon('briefcase', 12), `Escalate to Deals Pipeline ($${l.estimated_value_usd.toLocaleString()})`)) : null);
                  }))));
          } catch (err) {
            clear(resultBox).append(h('p', { class: 'small', style: { color: 'var(--warn)' } }, `Lead triage failed: ${err.message}`));
          }
        });
      },
    }, icon('users', 14), 'Scan & Triage Engagement Leads');

    return h('div', {},
      h('p', { class: 'muted small' }, 'Scans incoming audience comments and DMs across all connected networks, extracts high-ticket commercial inquiries (brand sponsorships, architecture consulting, speaking invites, course buyers), estimates revenue opportunity, and routes leads into your Deals CRM.'),
      h('div', { class: 'row', style: { justifyContent: 'flex-end', gap: '8px' } },
        h('button', { class: 'btn', onclick: () => close() }, 'Close'),
        scanBtn),
      resultBox);
  }, { wide: true });
}

async function retentionAuditModal(agent, onDone) {
  return modal('YouTube Script Retention Curve & Pacing Auditor', (close) => {
    const textInput = h('textarea', {
      rows: 5,
      style: { width: '100%', marginBottom: '10px' },
      placeholder: 'Paste video script, outline, or talking points to simulate retention drop-off and inject 90-second pacing resets…',
    });

    const titleInput = h('input', {
      type: 'text',
      placeholder: 'Video Title (e.g. Building Sagas Without Distributed Deadlocks)',
      style: { flex: 2 },
    });

    const durationInput = h('input', {
      type: 'number',
      value: 10,
      placeholder: 'Target Minutes',
      style: { width: '130px' },
    });

    const resultBox = h('div', { style: { marginTop: '16px' } });

    const auditBtn = h('button', {
      class: 'btn primary',
      onclick: async () => {
        const body = textInput.value.trim();
        if (!body) return toast('Please paste script text or an outline.', 'err');
        await busy(auditBtn, 'Auditing retention curve & pacing…', async () => {
          clear(resultBox).append(h('p', { class: 'muted small' }, 'Retention Auditor running: Simulating drop-off curve across 5 video phases → Detecting boredom hazards → Injecting 90-second visual & verbal resets…'));
          try {
            const r = await api('POST', `agents/${agent.id}/audit-retention`, {
              title: titleInput.value.trim() || undefined,
              body,
              target_duration_minutes: durationInput.value ? Number(durationInput.value) : 10,
            });

            const res = r.result;
            const timeline = res.pacing_timeline || [];
            const hazards = res.hazards_detected || [];

            clear(resultBox).append(
              h('div', { class: 'card', style: { padding: '14px', background: 'var(--card-subtle, rgba(255,255,255,0.02))' } },
                // Header KPIs
                h('div', { class: 'row', style: { justifyContent: 'space-between', alignItems: 'center', marginBottom: '14px', flexWrap: 'wrap', gap: '8px' } },
                  h('div', {},
                    h('h3', { style: { margin: '0 0 4px' } }, res.title),
                    h('p', { class: 'muted small', style: { margin: 0 } }, `${res.pacing_rating} · ${res.target_duration_minutes} min duration`)),
                  h('div', { class: 'row', style: { gap: '6px' } },
                    h('span', { class: 'pill ok small' }, `Retention: ${res.retention_score}/100`),
                    h('span', { class: 'pill ok small' }, `Predicted AVD: ${res.predicted_avd_percent}%`))),

                // Hazards alert
                hazards.length ? h('div', { style: { marginBottom: '14px' } },
                  h('h4', { style: { fontSize: '12px', textTransform: 'uppercase', color: 'var(--warn)', margin: '0 0 6px' } }, 'Drop-Off Hazards Detected'),
                  hazards.map((hz) => h('div', {
                    style: { padding: '8px 10px', background: 'rgba(239,68,68,0.05)', borderLeft: '3px solid #ef4444', borderRadius: '4px', marginBottom: '6px', fontSize: '12px' },
                  },
                  h('strong', {}, `[At ${hz.timestamp_estimate}] ${hz.issue}`),
                  h('p', { style: { margin: '2px 0 0', color: 'var(--ok)' } }, `Fix: ${hz.remedy}`)))) : null,

                // 5-Phase Retention Timeline
                h('h4', { style: { margin: '0 0 10px', fontSize: '13px' } }, '5-Phase Pacing Timeline & Injected Resets'),
                h('div', { style: { display: 'flex', flexDirection: 'column', gap: '8px', marginBottom: '14px' } },
                  timeline.map((ph) => h('div', {
                    class: 'card',
                    style: { padding: '10px 12px', background: 'var(--bg)' },
                  },
                  h('div', { class: 'row', style: { justifyContent: 'space-between', alignItems: 'center', marginBottom: '4px' } },
                    h('div', { class: 'row', style: { gap: '8px', alignItems: 'center' } },
                      h('span', { class: 'pill small' }, ph.timestamp),
                      h('strong', { class: 'small' }, ph.phase)),
                    h('span', { class: 'pill ok small' }, `Est. Retention: ${ph.projected_retention}`)),
                  h('p', { class: 'small muted', style: { margin: '2px 0 6px' } }, ph.focus),
                  h('div', { class: 'row', style: { gap: '6px', flexWrap: 'wrap' } },
                    (ph.retention_resets || []).map((rst) => h('span', { class: 'pill small', style: { background: 'rgba(99,102,241,0.1)', color: 'var(--accent, #6366f1)' } }, rst)))))),

                // Optimized script excerpt
                res.optimized_script_excerpt ? h('div', { class: 'card', style: { padding: '12px', background: 'var(--bg)' } },
                  h('strong', { class: 'small' }, 'Pacing-Optimized Script with Visual Cues:'),
                  h('pre', { style: { whiteSpace: 'pre-wrap', fontSize: '12px', marginTop: '6px', fontFamily: 'inherit' } }, res.optimized_script_excerpt)) : null));
          } catch (err) {
            clear(resultBox).append(h('p', { class: 'small', style: { color: 'var(--warn)' } }, `Retention audit failed: ${err.message}`));
          }
        });
      },
    }, icon('play', 14), 'Audit Retention Pacing');

    return h('div', {},
      h('p', { class: 'muted small' }, 'Simulates YouTube viewer drop-off curves across 5 chronological phases, flags monologue boredom hazards, and injects visual, verbal, and on-screen retention resets every 90 seconds to maximize Average View Duration (AVD %).'),
      h('label', { class: 'small muted' }, 'Script Outline or Draft Text:'),
      textInput,
      h('div', { class: 'row', style: { gap: '8px', marginBottom: '12px' } },
        titleInput, durationInput),
      h('div', { class: 'row', style: { justifyContent: 'flex-end', gap: '8px' } },
        h('button', { class: 'btn', onclick: () => close() }, 'Cancel'),
        auditBtn),
      resultBox);
  }, { wide: true });
}

export async function renderAgent(view, id) {
  let d = await api('GET', `agents/${id}`);
  const meta = d.meta;
  const labelOf = (p) => meta.networks.find((n) => n.platform === p)?.label || p;
  let tab = d.pending.length ? 'approvals' : 'upcoming';
  const head = h('div');
  const tabs = h('div', { class: 'tabs', role: 'tablist' });
  const panel = h('div');

  if (new URLSearchParams(location.search).get('first')) {
    history.replaceState({}, '', `/agents/${id}`);
    // First batch right away, so she sees what it writes.
    setTimeout(async () => {
      panel.prepend(h('div', { class: 'card ag-working' }, h('span', { class: 'spinner' }), 'Planning the first week of posts…'));
      try {
        const r = await api('POST', `agents/${id}/run`);
        toast(r.message);
      } catch (e) { toast(e.message, 'err', 9000); }
      await reload();
    }, 50);
  }

  async function reload() {
    d = await api('GET', `agents/${id}`);
    refreshNotifications();
    draw();
  }

  function draw() {
    const a = d.agent;
    const activeInput = h('input', {
      type: 'checkbox', checked: a.active, onchange: async (e) => {
        await api('PATCH', `agents/${a.id}`, { active: e.target.checked }).catch((err) => toast(err.message, 'err'));
        toast(e.target.checked ? 'Agent is running.' : 'Agent paused: it won’t plan or publish until you turn it back on.');
        reload();
      },
    });
    const run = h('button', { class: 'btn sm', onclick: () => busy(run, 'Planning…', async () => { const r = await api('POST', `agents/${a.id}/run`); toast(r.message); await reload(); }) }, icon('sparkle', 14), 'Plan now');
    clear(head).append(
      h('div', { class: 'page-head ag-head' },
        h('div', { class: 'row', style: { gap: '14px', alignItems: 'center' } },
          h('span', { class: 'ag-avatar lg' }, icon('bot', 26)),
          h('div', {}, h('h1', {}, a.name),
            h('div', { class: 'row ag-nets' }, a.platforms.map((p) => netChip(p, labelOf(p))),
              h('span', { class: `pill ${a.approval === 'auto' ? 'ok' : 'new'}` }, a.approval === 'auto' ? 'Autonomous' : 'Reviews with you')))),
        h('div', { class: 'row', style: { flexWrap: 'wrap', gap: '8px' } },
          h('label', { class: 'row ag-active', title: 'Running / paused' }, h('span', { class: 'switch' }, activeInput, h('span', { class: 'slider' })), h('span', { class: 'small' }, a.active ? 'Running' : 'Paused')),
          run,
          h('button', { class: 'btn sm ghost', onclick: () => repurposeModal(a, reload) }, icon('repeat', 14), 'Repurpose video'),
          h('button', { class: 'btn sm ghost', onclick: () => breakoutSpikeModal(a, reload) }, icon('trend', 14), 'Breakout spike'),
          h('button', { class: 'btn sm ghost', onclick: () => deliberateModal(a, reload) }, icon('sparkle', 14), 'Deliberate committee'),
          h('button', { class: 'btn sm ghost', onclick: () => swarmModal(a, reload) }, icon('flow', 14), 'Swarm mission'),
          h('button', { class: 'btn sm ghost', onclick: () => treeSearchModal(a, reload) }, icon('chart', 14), 'Tree search (ToT)'),
          h('button', { class: 'btn sm ghost', onclick: () => debateModal(a, reload) }, icon('alert', 14), 'Red-team debate'),
          h('button', { class: 'btn sm ghost', onclick: () => dealDeskModal(a, reload) }, icon('briefcase', 14), 'Deal desk agent'),
          h('button', { class: 'btn sm ghost', onclick: () => cascadeModal(a, reload) }, icon('flow', 14), 'Transmedia cascade'),
          h('button', { class: 'btn sm ghost', onclick: () => hookMatrixModal(a, reload) }, icon('sparkle', 14), 'Viral hook matrix'),
          h('button', { class: 'btn sm ghost', onclick: () => factCheckModal(a, reload) }, icon('check', 14), 'Fact-check shield'),
          h('button', { class: 'btn sm ghost', onclick: () => outlierDeconstructModal(a, reload) }, icon('trend', 14), 'Reverse outlier'),
          h('button', { class: 'btn sm ghost', onclick: () => banditTitlesModal(a, reload) }, icon('chart', 14), 'Bandit titles'),
          h('button', { class: 'btn sm ghost', onclick: () => leadTriageModal(a, reload) }, icon('users', 14), 'Lead triage'),
          h('button', { class: 'btn sm ghost', onclick: () => retentionAuditModal(a, reload) }, icon('play', 14), 'Retention audit'),
          h('button', { class: 'btn ghost sm', onclick: async () => { if (await wizard(meta, a)) reload(); } }, icon('pen', 14), 'Edit'),
          h('button', {
            class: 'btn ghost sm', 'aria-label': 'Delete agent', onclick: async () => {
              if (!(await confirmDialog(`Delete ${a.name}? Posts waiting for approval are deleted; approved and published posts stay on your calendar.`))) return;
              await api('DELETE', `agents/${a.id}`);
              navigate('/agents');
            },
          }, icon('trash', 14)))),
      h('div', { class: 'ag-kpis' },
        kpi(a.counts.pending, 'to approve', a.counts.pending ? 'hot' : ''),
        kpi(a.counts.scheduled, 'scheduled'),
        kpi(a.counts.published, 'published'),
        kpi(a.lessons.length, 'lessons learned'),
        kpi(a.next_run_at && a.active ? timeAgoFuture(a.next_run_at) : '—', 'next planning')),
      h('div', { class: 'card ag-intelligence-banner', style: { padding: '10px 14px', margin: '10px 0 14px', background: 'var(--card-subtle, rgba(255,255,255,0.03))', border: '1px solid var(--border)', borderRadius: '8px' } },
        h('div', { class: 'row', style: { gap: '10px', alignItems: 'center' } },
          icon('sparkle', 16),
          h('span', { class: 'small' },
            h('strong', {}, 'Agent Signal Intelligence: '),
            'Ingests live breakout competitor trends, fresh RSS feeds, viewer questions, and your recent scripts to draft high-performing, authentic posts.'))));
    const TABS = [['approvals', 'Approvals', d.pending.length], ['upcoming', 'Upcoming', d.upcoming.length], ['published', 'Published', d.published.length], ['learning', 'Learning', a.lessons.length], ['harness', 'Harness & Evals', null], ['activity', 'Activity', null]];
    clear(tabs).append(TABS.map(([k, label, n]) => h('button', {
      class: `tab${tab === k ? ' active' : ''}`, role: 'tab', 'aria-selected': String(tab === k), onclick: () => { tab = k; draw(); },
    }, label, n ? h('span', { class: 'count' }, n) : null)));
    clear(panel).append({ approvals, upcoming, published, learning, harness, activity }[tab]());
  }

  const kpi = (n, label, cls = '') => h('div', { class: `ag-kpi ${cls}` }, h('strong', {}, n), h('span', {}, label));
  const timeAgoFuture = (iso) => {
    const m = Math.round((new Date(iso) - Date.now()) / 60000);
    return m <= 1 ? 'now' : m < 60 ? `in ${m} min` : m < 1440 ? `in ${Math.round(m / 60)} h` : `in ${Math.round(m / 1440)} d`;
  };

  function approvals() {
    if (!d.pending.length) {
      return emptyState(icon('check', 28), 'Nothing to approve', d.agent.approval === 'auto' ? 'This agent publishes by itself. Failed posts come back here.' : 'New drafts appear here when the agent plans your week.');
    }
    return h('div', { class: 'ag-queue' }, d.pending.map((p) => {
      const lines = (p.body || '').split('\n').reduce((n, l) => n + Math.max(1, Math.ceil(l.length / 90)), 0);
      const body = h('textarea', { rows: Math.min(16, Math.max(3, lines + 1)), 'aria-label': 'Post text' });
      body.value = p.body || '';
      const parts = (p.parts || []).map((x, i) => { const t = h('textarea', { rows: 2, 'aria-label': `Part ${i + 1}` }); t.value = x; return t; });
      const at = h('input', { type: 'datetime-local', value: p.scheduled_for ? toLocalInput(p.scheduled_for) : '' });
      const publishes = meta.networks.find((n) => n.platform === p.platform)?.publishes === 'auto';
      const payload = () => ({ body: body.value, parts: parts.map((t) => t.value).filter((x) => x.trim()), scheduled_for: at.value ? new Date(at.value).toISOString() : null });
      const approve = h('button', {
        class: 'btn primary sm', onclick: () => busy(approve, 'Approving…', async () => {
          const r = await api('POST', `agents/posts/${p.id}/approve`, payload());
          toast(r.publishes === 'auto' ? `Approved. It publishes ${when(r.post.scheduled_for)}.` : `Approved and scheduled for ${when(r.post.scheduled_for)}. You’ll get a reminder to post it.`);
          await reload();
        }),
      }, icon('check', 14), 'Approve');
      const now = publishes ? h('button', {
        class: 'btn sm', onclick: () => busy(now, 'Publishing…', async () => {
          await api('POST', `agents/posts/${p.id}/approve`, payload());
          const r = await api('POST', `agents/posts/${p.id}/publish`);
          toast('Published.');
          if (r.url) window.open(r.url, '_blank', 'noopener');
          await reload();
        }),
      }, icon('external', 14), 'Publish now') : null;
      const reject = h('button', {
        class: 'btn ghost sm', onclick: async () => {
          const reason = await modal('Why not this one?', (close) => {
            const t = h('textarea', { rows: 3, placeholder: 'e.g. too salesy, wrong topic, I’d never say “game-changer”' });
            const quick = ['Too salesy', 'Off-topic for me', 'Not my voice', 'Already said this', 'Too long'].map((q) => h('button', { class: 'wz-chip', type: 'button', onclick: () => { t.value = q; } }, q));
            return h('div', {}, h('p', { class: 'muted small' }, 'The agent learns from your reasons.'), h('div', { class: 'wz-chips' }, quick), t,
              h('div', { class: 'row', style: { justifyContent: 'flex-end', marginTop: '12px' } },
                h('button', { class: 'btn', onclick: () => close(undefined) }, 'Cancel'),
                h('button', { class: 'btn danger-solid', onclick: () => close(t.value.trim()) }, 'Reject')));
          });
          if (reason === undefined) return;
          await api('POST', `agents/posts/${p.id}/reject`, { reason });
          toast('Rejected. The agent will learn from it.');
          reload();
        },
      }, icon('x', 14), 'Reject');
      const why = (p.notes || '').split('\n').filter(Boolean);
      const scoreSlot = h('span');
      api('POST', 'social/score', { platform: p.platform, format: p.format, body: p.body || '', parts: p.parts || [], scheduled_for: p.scheduled_for, pillar: p.pillar, post_id: p.id })
        .then((r) => scoreSlot.replaceWith(scoreBadge(r.score, `${r.label} (${r.basis})`))).catch(() => {});
      return h('div', { class: `card ag-post pf-${p.platform}` },
        h('div', { class: 'ag-post-top' }, netChip(p.platform, labelOf(p.platform)), h('strong', { class: 'clamp-1' }, p.title || ''), scoreSlot,
          h('span', { class: 'muted small ag-when' }, icon('calendar', 13), when(p.scheduled_for))),
        p.publish_error ? h('p', { class: 'error-text small' }, `Last try failed: ${p.publish_error}`) : null,
        body,
        parts.length ? h('div', { class: 'ag-parts' }, parts.map((t, i) => h('div', { class: 'ag-part' }, h('span', { class: 'muted small' }, `${i + 1}`), t))) : null,
        why.length ? h('ul', { class: 'ag-why muted small' }, why.map((w) => h('li', {}, w))) : null,
        h('div', { class: 'row ag-post-actions' },
          h('label', { class: 'row small ag-at' }, 'Time', at),
          h('span', { class: 'grow' }),
          reject, now, approve,
          link(`/posts/${p.id}`, { class: 'btn ghost sm', title: 'Open in the full editor (media, preview)' }, icon('pen', 14))));
    }));
  }

  function upcoming() {
    if (!d.upcoming.length) return emptyState(icon('calendar', 28), 'Nothing scheduled yet', 'Approved posts show here with their publish time.');
    return h('div', { class: 'card' }, h('ul', { class: 'item-list ag-list' }, d.upcoming.map((p) => {
      const auto = meta.networks.find((n) => n.platform === p.platform)?.publishes === 'auto';
      return h('li', {},
        netChip(p.platform, labelOf(p.platform)),
        h('div', { class: 'item-main' }, link(`/posts/${p.id}`, { class: 'clamp-1' }, p.title || p.body || 'Post'), h('span', { class: 'muted small' }, when(p.scheduled_for))),
        h('span', { class: `pill ${auto ? 'ok' : 'late'}`, title: auto ? 'The studio publishes it at this time' : 'You get a “due now” reminder to post it' }, auto ? 'Auto-publish' : 'You post it'));
    })));
  }

  function published() {
    if (!d.published.length) return emptyState(icon('external', 28), 'Nothing published yet', 'Published posts and their numbers show here. Numbers arrive from connected accounts every hour.');
    return h('div', { class: 'card' }, h('div', { class: 'table-wrap' }, h('table', {},
      h('thead', {}, h('tr', {}, h('th', {}, 'Post'), h('th', {}, 'Network'), h('th', { class: 'num' }, 'Views'), h('th', { class: 'num' }, 'Engagements'), h('th', { class: 'num' }, 'Rate'))),
      h('tbody', {}, d.published.map((p) => h('tr', {},
        h('td', {}, link(`/posts/${p.id}`, { class: 'clamp-1' }, p.title || p.body || 'Post'), h('div', { class: 'muted small' }, when(p.posted_at))),
        h('td', {}, netChip(p.platform, labelOf(p.platform))),
        h('td', { class: 'num' }, p.metrics?.impressions != null ? compact(p.metrics.impressions) : '—'),
        h('td', { class: 'num' }, p.engagement ? compact(p.engagement.total) : '—'),
        h('td', { class: 'num' }, p.engagement?.rate != null ? `${p.engagement.rate}%` : '—')))))));
  }

  function learning() {
    const a = d.agent;
    const sig = d.signals;
    const have = sig.measured + sig.edited + sig.rejected;
    const learn = h('button', { class: 'btn primary sm', disabled: have < sig.needed, onclick: () => busy(learn, 'Learning…', async () => { await api('POST', `agents/${a.id}/learn`); toast('Lessons updated.'); await reload(); }) }, icon('sparkle', 14), 'Learn now');
    const max = Math.max(1, ...d.trend.map((t) => t.median_rate || 0));
    return h('div', { class: 'grid grid-2' },
      h('div', { class: 'card' },
        h('div', { class: 'card-header' }, h('h2', {}, 'What it has learned'), learn),
        a.lessons.length ? h('ol', { class: 'ag-lessons' }, a.lessons.map((l, i) => h('li', {},
          h('div', {}, h('strong', {}, l.text), l.evidence ? h('div', { class: 'muted small' }, l.evidence) : null),
          h('button', {
            class: 'btn ghost sm', 'aria-label': 'Remove this lesson', title: 'Remove: the agent stops following it', onclick: async () => {
              await api('PATCH', `agents/${a.id}`, { lessons: a.lessons.filter((_, j) => j !== i) });
              reload();
            },
          }, icon('x', 13))))) : h('p', { class: 'muted small' }, 'No lessons yet.'),
        h('p', { class: 'muted small' }, a.learned_at ? `Last updated ${timeAgo(a.learned_at)}. ` : '',
          'It reviews itself every Sunday evening, using posts with numbers, how you edited its drafts, and why you rejected some.'),
        h('div', { class: 'ag-signals' },
          h('span', {}, h('strong', {}, sig.measured), ' posts with numbers'), h('span', {}, h('strong', {}, sig.edited), ' edited by you'), h('span', {}, h('strong', {}, sig.rejected), ' rejected'),
          have < sig.needed ? h('span', { class: 'muted' }, `needs ${sig.needed - have} more to start learning`) : null)),
      h('div', { class: 'card' },
        h('div', { class: 'card-header' }, h('h2', {}, 'Engagement rate, median per 30 days')),
        d.trend.some((t) => t.posts) ? h('div', { class: 'ag-trend', role: 'img', 'aria-label': `Median engagement rate: ${d.trend.map((t) => `${t.to}: ${t.median_rate ?? 'no data'}`).join(', ')}` },
          d.trend.map((t) => h('div', { class: 'ag-trend-col', 'data-tip': t.posts ? `${t.median_rate}% median over ${t.posts} post${t.posts === 1 ? '' : 's'} (${t.from} – ${t.to})` : `No posts with views ${t.from} – ${t.to}` },
            h('span', { class: 'ag-trend-val' }, t.median_rate != null ? `${t.median_rate}%` : ''),
            h('span', { class: 'ag-trend-bar', style: { height: `${t.median_rate != null ? Math.max(4, (t.median_rate / max) * 100) : 0}%` } }),
            h('span', { class: 'ag-trend-label muted small' }, new Date(t.to).toLocaleDateString([], { month: 'short', day: 'numeric' }))))) :
          h('p', { class: 'muted small' }, 'Shows once its posts have views and engagement (from connected accounts or your imports).')));
  }

  function harness() {
    const wrap = h('div', { class: 'ag-harness-view' });
    const loadHarness = async () => {
      clear(wrap).append(h('p', { class: 'muted small' }, 'Loading harness benchmark metrics…'));
      try {
        const ev = await api('GET', `agents/${a.id}/evals`);
        const tools = [
          { name: 'fetch_breakouts', label: 'Outlier Competitor Spikes', desc: 'Real-time trending YouTube/viral outliers' },
          { name: 'fetch_rss_feeds', label: 'Fresh RSS Signals', desc: 'Niche industry feeds and articles' },
          { name: 'fetch_channel_scripts', label: 'Video Transcripts & Concepts', desc: 'Recently drafted and produced scripts' },
          { name: 'fetch_audience_asks', label: 'Audience FAQ Extractor', desc: 'Viewer inquiries from comments and inbox' },
          { name: 'fetch_active_sponsors', label: 'Sponsorship Matching', desc: 'Brand deliverables & FTC disclosures' },
          { name: 'get_open_schedule_slots', label: 'Cadence & Slots', desc: 'Optimal timing avoiding posting collisions' },
        ];

        const avgScore = ev.average ?? '—';
        const avgCls = ev.average >= 80 ? 'ok' : ev.average >= 60 ? 'picked' : 'WARN';

        const runBench = h('button', {
          class: 'btn primary sm',
          onclick: () => busy(runBench, 'Benchmarking…', async () => {
            const r = await api('POST', `agents/${a.id}/harness/benchmark`);
            toast(r.message);
            await loadHarness();
          }),
        }, icon('sparkle', 14), 'Run benchmark suite');

        const runCycle = h('button', {
          class: 'btn sm ghost',
          onclick: () => busy(runCycle, 'Executing…', async () => {
            const r = await api('POST', `agents/${a.id}/harness/run`);
            toast(r.message);
            await loadHarness();
          }),
        }, icon('play', 14), 'Run autonomous loop');

        clear(wrap).append(
          h('div', { class: 'card ag-harness-head', style: { marginBottom: '16px' } },
            h('div', { class: 'row', style: { justifyContent: 'space-between', alignItems: 'center', flexWrap: 'wrap', gap: '12px' } },
              h('div', {},
                h('h2', { style: { margin: 0 } }, 'Agent Harness & Benchmark Suite'),
                h('p', { class: 'muted small', style: { margin: '4px 0 0' } },
                  'Automated quality regression harness, 5-factor rubric evaluations (Hook, Voice, Retention, Cliche Freedom, Safety), and sandbox testing.')),
              h('div', { class: 'row', style: { gap: '8px' } }, runBench, runCycle)),
            h('div', { class: 'ag-kpis', style: { marginTop: '14px' } },
              h('div', { class: `ag-kpi` },
                h('strong', {}, avgScore, avgScore !== '—' ? h('span', { class: 'small muted' }, '/100') : ''),
                h('span', {}, 'Quality readiness index')),
              h('div', { class: 'ag-kpi' },
                h('strong', {}, ev.reports.length),
                h('span', {}, 'Evaluations recorded')),
              h('div', { class: 'ag-kpi' },
                h('strong', {}, Object.keys(ev.scenarios || {}).length),
                h('span', {}, 'Standard scenarios')),
              h('div', { class: 'ag-kpi' },
                h('strong', {}, tools.length),
                h('span', {}, 'Sandboxed tools')))),

          h('div', { class: 'card', style: { marginBottom: '16px' } },
            h('div', { class: 'card-header' },
              h('h3', { style: { margin: 0, fontSize: '1rem' } }, 'Tool Execution Registry (Model Context Protocol / MCP)'),
              h('div', { class: 'row', style: { gap: '6px' } },
                h('span', { class: 'pill ok small' }, 'MCP v2024-11-05'),
                h('span', { class: 'pill picked small' }, 'JSON-RPC 2.0'))),
            h('p', { class: 'muted small', style: { margin: '4px 0 10px' } },
              'Standardized Anthropic/OpenAI Model Context Protocol endpoint at ', h('code', {}, 'POST /api/mcp'), '. Available to external Claude, OpenAI Swarm, or local agent runners.'),
            h('div', { class: 'row', style: { flexWrap: 'wrap', gap: '8px' } },
              tools.map((t) => h('div', {
                class: 'card',
                style: { flex: '1 1 calc(33.333% - 8px)', minWidth: '220px', padding: '10px 12px', background: 'var(--card-subtle, rgba(255,255,255,0.02))' },
              },
              h('div', { class: 'row', style: { alignItems: 'center', gap: '6px' } },
                icon('check', 13),
                h('strong', { class: 'small' }, t.name)),
              h('div', { class: 'muted small', style: { marginTop: '4px' } }, t.desc))))),

          h('div', { class: 'grid grid-2', style: { gap: '16px', marginBottom: '16px' } },
            h('div', { class: 'card' },
              h('div', { class: 'card-header' },
                h('h3', { style: { margin: 0, fontSize: '1rem' } }, 'Episodic & Procedural Memory Bank'),
                h('span', { class: 'pill ok small' }, 'Few-Shot Memory Active')),
              h('p', { class: 'muted small' },
                'Automatically injects past winning exemplars (>1.5x median ER) and past creator rejections/edits into agent prompts to eliminate repeated mistakes.'),
              h('div', { class: 'row', style: { gap: '8px', marginTop: '10px' } },
                h('button', {
                  class: 'btn sm ghost',
                  onclick: async () => {
                    const mem = await api('GET', `agents/${a.id}/memory`);
                    modal('Agent Memory Bank Inspector', (close) => {
                      return h('div', {},
                        h('h4', {}, `Winning Exemplars (${mem.winning_exemplars.length})`),
                        h('ul', { class: 'item-list' }, mem.winning_exemplars.map((w) => h('li', {},
                          h('div', {}, h('strong', {}, `[${w.platform.toUpperCase()}] ER: ${w.rate}%`), h('p', { class: 'small' }, w.hook))
                        ))),
                        h('h4', { style: { marginTop: '16px' } }, `Anti-Patterns & Rejections (${mem.anti_patterns.length})`),
                        h('ul', { class: 'item-list' }, mem.anti_patterns.map((ap) => h('li', {},
                          h('div', {}, h('strong', { style: { color: 'var(--warn)' } }, ap.reason), ap.text_sample ? h('p', { class: 'small muted' }, ap.text_sample) : null)
                        ))),
                        h('div', { class: 'row', style: { justifyContent: 'flex-end', marginTop: '12px' } },
                          h('button', { class: 'btn', onclick: () => close() }, 'Close')
                        )
                      );
                    }, { wide: true });
                  },
                }, icon('eye', 13), 'Inspect memory bank')
              )
            ),
            h('div', { class: 'card' },
              h('div', { class: 'card-header' },
                h('h3', { style: { margin: 0, fontSize: '1rem' } }, 'Policy-as-Code & Circuit Breakers'),
                h('span', { class: 'pill ok small' }, 'Guardrails Armed')),
              h('ul', { class: 'item-list small', style: { marginTop: '8px' } },
                h('li', {}, h('span', {}, 'Loop Breaker'), h('strong', {}, 'Halt on 2 consecutive duplicate calls')),
                h('li', {}, h('span', {}, 'Max Execution Budget'), h('strong', {}, '10 steps / $0.50 cap')),
                h('li', {}, h('span', {}, 'PII & Secret Filter'), h('strong', { style: { color: 'var(--ok)' } }, 'API key & private email redaction'))))),

          h('div', { class: 'card' },
            h('div', { class: 'card-header' },
              h('h3', { style: { margin: 0, fontSize: '1rem' } }, 'Evaluation Scorecards & Regression History')),
            !ev.reports.length
              ? h('div', { style: { padding: '24px 0', textAlign: 'center' } },
                h('p', { class: 'muted small' }, 'No benchmark reports yet. Run the benchmark suite to evaluate your agent against standardized prompts.'),
                runBench)
              : h('div', { class: 'ag-reports-list', style: { display: 'flex', flexDirection: 'column', gap: '12px', marginTop: '12px' } },
                ev.reports.map((r) => {
                  const sc = (ev.scenarios && ev.scenarios[r.scenario]) || { label: r.scenario };
                  const tells = r.hard_tells_caught || [];
                  const rub = r.rubric || {};
                  return h('div', {
                    class: 'card',
                    style: { padding: '14px', border: '1px solid var(--border)', background: 'var(--card-subtle, rgba(255,255,255,0.02))' },
                  },
                  h('div', { class: 'row', style: { justifyContent: 'space-between', alignItems: 'center', marginBottom: '8px' } },
                    h('div', { class: 'row', style: { gap: '8px', alignItems: 'center' } },
                      h('span', { class: 'pill picked' }, sc.label || r.scenario),
                      h('span', { class: `pill ${r.overall_score >= 80 ? 'ok' : r.overall_score >= 60 ? 'new' : 'WARN'}` },
                        `Score: ${r.overall_score}/100`)),
                    h('span', { class: 'muted small' }, timeAgo(r.created_at))),
                  h('div', { class: 'row', style: { gap: '6px', flexWrap: 'wrap', margin: '8px 0' } },
                    rub.hook_score != null ? h('span', { class: 'pill small' }, `Hook: ${rub.hook_score}`) : null,
                    rub.voice_score != null ? h('span', { class: 'pill small' }, `Voice: ${rub.voice_score}`) : null,
                    rub.retention_score != null ? h('span', { class: 'pill small' }, `Retention: ${rub.retention_score}`) : null,
                    rub.cliche_freedom_score != null ? h('span', { class: 'pill small' }, `Anti-Cliche: ${rub.cliche_freedom_score}`) : null,
                    rub.safety_score != null ? h('span', { class: 'pill small' }, `Safety: ${rub.safety_score}`) : null),
                  tells.length
                    ? h('div', { class: 'row', style: { gap: '6px', alignItems: 'center', margin: '6px 0' } },
                      h('span', { class: 'small', style: { color: 'var(--warn)' } }, 'Flagged tells:'),
                      tells.map((tell) => h('span', { class: 'pill WARN small' }, tell)))
                    : null,
                  r.feedback
                    ? h('p', { class: 'small', style: { margin: '6px 0', color: 'var(--muted)' } }, r.feedback)
                    : null,
                  r.generated_output
                    ? h('details', { style: { marginTop: '8px' } },
                      h('summary', { class: 'small muted', style: { cursor: 'pointer' } }, 'Inspect generated test output'),
                      h('pre', {
                        style: {
                          whiteSpace: 'pre-wrap',
                          fontSize: '12px',
                          background: 'rgba(0,0,0,0.2)',
                          padding: '10px',
                          borderRadius: '6px',
                          marginTop: '6px',
                          fontFamily: 'monospace',
                        },
                      }, r.generated_output))
                    : null);
                }))),

          h('div', { class: 'grid grid-2', style: { gap: '16px', marginTop: '16px' } },
            h('div', { class: 'card' },
              h('div', { class: 'card-header' },
                h('h3', { style: { margin: 0, fontSize: '1rem' } }, 'Automated Prompt Optimizer (DSPy / APO)'),
                h('span', { class: 'pill ok small' }, 'Self-Tuning Engine')),
              h('p', { class: 'muted small' },
                'Meta-prompts candidate system prompt variations against the benchmark harness to autonomously maximize hook strength, voice authenticity, and cliché elimination.'),
              h('div', { class: 'row', style: { gap: '8px', marginTop: '12px' } },
                h('button', {
                  class: 'btn sm primary',
                  onclick: async (e) => {
                    await busy(e.target, 'Optimizing prompt against benchmark…', async () => {
                      try {
                        const r = await api('POST', `agents/${a.id}/prompt/optimize`);
                        toast(r.message);
                        loadHarness();
                      } catch (err) {
                        toast(err.message || 'Prompt optimization failed.', 'err');
                      }
                    });
                  },
                }, icon('sparkle', 13), 'Auto-optimize prompt (APO)'),
                h('button', {
                  class: 'btn sm ghost',
                  onclick: async () => {
                    const vers = await api('GET', `agents/${a.id}/prompt/versions`);
                    modal('Prompt Version History', (close) => {
                      return h('div', {},
                        h('p', { class: 'muted small' }, 'Tracked DSPy prompt evolution checkpoints and benchmark scores.'),
                        !vers.versions.length ? h('p', { class: 'muted small' }, 'No versions recorded yet. Run prompt optimization to generate version checkpoints.') :
                          h('ul', { class: 'item-list' }, vers.versions.map((v) => h('li', {},
                            h('div', {},
                              h('div', { class: 'row', style: { gap: '8px', alignItems: 'center' } },
                                h('strong', {}, `v${v.version}`),
                                h('span', { class: `pill ${v.is_active ? 'ok' : ''} small` }, v.is_active ? 'Active' : 'Archived'),
                                h('span', { class: 'pill small' }, `Score: ${v.score}/100`)),
                              h('p', { class: 'small muted', style: { margin: '4px 0 0' } }, v.changes_summary || 'Prompt revision'))))),
                        h('div', { class: 'row', style: { justifyContent: 'flex-end', marginTop: '12px' } },
                          h('button', { class: 'btn', onclick: () => close() }, 'Close')));
                    }, { wide: true });
                  },
                }, icon('history', 13), 'Version history'))),

            h('div', { class: 'card' },
              h('div', { class: 'card-header' },
                h('h3', { style: { margin: 0, fontSize: '1rem' } }, 'Swarm Missions & Durable Checkpoints'),
                h('button', { class: 'btn sm ghost', onclick: () => swarmModal(a, loadHarness) }, icon('plus', 13), 'New mission')),
              h('p', { class: 'muted small' },
                'Multi-agent campaigns executed by worker agents (Signal Scout → Angle Strategist → Platform Copywriter → Grounding Auditor).'),
              h('div', { style: { marginTop: '10px' } },
                h('button', {
                  class: 'btn sm ghost',
                  onclick: async () => {
                    const m = await api('GET', `agents/${a.id}/missions`);
                    modal('Swarm Mission History', (close) => {
                      return h('div', {},
                        !m.missions.length ? h('p', { class: 'muted small' }, 'No swarm missions planned yet. Launch a mission from the header.') :
                          h('ul', { class: 'item-list' }, m.missions.map((ms) => h('li', {},
                            h('div', {},
                              h('div', { class: 'row', style: { gap: '8px', alignItems: 'center' } },
                                h('strong', {}, ms.title),
                                h('span', { class: `pill ${ms.status === 'completed' ? 'ok' : 'new'} small` }, ms.status)),
                              h('p', { class: 'small muted', style: { margin: '2px 0 0' } }, ms.goal))))),
                        h('div', { class: 'row', style: { justifyContent: 'flex-end', marginTop: '12px' } },
                          h('button', { class: 'btn', onclick: () => close() }, 'Close')));
                    }, { wide: true });
                  },
                }, icon('eye', 13), 'Inspect missions')))));
      } catch (err) {
        clear(wrap).append(h('p', { class: 'muted small', style: { color: 'var(--warn)' } }, `Error loading harness: ${err.message}`));
      }
    };

    loadHarness();
    return wrap;
  }

  function activity() {
    if (!d.events.length) return emptyState(icon('bell', 28), 'No activity yet', null);
    return h('div', { class: 'card' }, h('ul', { class: 'item-list ag-activity' }, d.events.map((e) => h('li', {},
      h('span', { class: `ag-ev ag-ev-${e.type}` }, e.platform ? icon(PLATFORM_ICONS[e.platform], 14) : icon('bot', 14)),
      h('div', { class: 'item-main' }, h('span', {}, e.message), h('span', { class: 'muted small' }, timeAgo(e.created_at)))))));
  }

  view.append(head, tabs, panel);
  draw();
}

function toLocalInput(iso) {
  const dt = new Date(iso);
  const pad = (n) => String(n).padStart(2, '0');
  return `${dt.getFullYear()}-${pad(dt.getMonth() + 1)}-${pad(dt.getDate())}T${pad(dt.getHours())}:${pad(dt.getMinutes())}`;
}
