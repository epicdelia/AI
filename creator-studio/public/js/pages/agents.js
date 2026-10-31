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
    const TABS = [['approvals', 'Approvals', d.pending.length], ['upcoming', 'Upcoming', d.upcoming.length], ['published', 'Published', d.published.length], ['learning', 'Learning', a.lessons.length], ['activity', 'Activity', null]];
    clear(tabs).append(TABS.map(([k, label, n]) => h('button', {
      class: `tab${tab === k ? ' active' : ''}`, role: 'tab', 'aria-selected': String(tab === k), onclick: () => { tab = k; draw(); },
    }, label, n ? h('span', { class: 'count' }, n) : null)));
    clear(panel).append({ approvals, upcoming, published, learning, activity }[tab]());
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
